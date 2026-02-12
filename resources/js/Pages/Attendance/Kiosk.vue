<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch, shallowRef } from 'vue';
import Toast from '@/Components/Toast.vue';
import { FaceLandmarker, FilesetResolver } from "@mediapipe/tasks-vision";
import { 
    CameraIcon, 
    ClockIcon, 
    UserIcon, 
    CheckCircleIcon, 
    XCircleIcon,
    ArrowPathIcon,
    QrCodeIcon
} from '@heroicons/vue/24/outline';
const props = defineProps({
    employees: Array,
});

const videoRef = ref(null);
const canvasRef = ref(null);
const stream = ref(null);
const capturedImage = ref(null);
const isCameraActive = ref(false);
const isAutoScan = ref(true); // Default ON
const isScanning = ref(false); // Request in progress
const scanCooldown = ref(false);
const isLoading = ref(false);
const lastLog = ref(null);
const errorMsg = ref(null);
const modelsLoaded = ref(false);
const faceLandmarker = shallowRef(null);

// High-Speed 3D Liveness Engine
const livenessStatus = ref('waiting'); 
const livenessHistory = [];
const livenessScore = ref(0);
const identifiedUser = ref(null);
const livenessFeedback = ref('Ready for scan');
const consecutiveMatches = ref(0);
const lastIdentifiedCode = ref(null);

// Verification Steps
const stepHeadTurn = ref(false);
const stepMouthMove = ref(false);

// Pre-load Success Sound
const successSound = new Audio('/sounds/success.wav');
successSound.load();

const errorSound = new Audio('/sounds/error.wav');
errorSound.load();

const currentTime = ref(new Date());
const timeInterval = setInterval(() => {
    currentTime.value = new Date();
}, 1000);

// Initialize Face AI
onMounted(async () => {
    try {
        const vision = await FilesetResolver.forVisionTasks(
            "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.32/wasm"
        );
        
        faceLandmarker.value = await FaceLandmarker.createFromOptions(vision, {
            baseOptions: {
                modelAssetPath: `/models/face_landmarker.task`,
                delegate: "CPU"
            },
            outputFaceBlendshapes: true,
            runningMode: "VIDEO",
            numFaces: 1
        });

        modelsLoaded.value = true;
        startCamera();
    } catch (e) {
        console.error("Critical: Face Recognition unavailable.", e);
        errorMsg.value = "System Error: Face Recognition unavailable.";
    }
});

const getSimilarity = (sig1, sig2) => {
    if (!sig1 || !sig2 || sig1.length !== sig2.length) return 0;
    // Cosine similarity
    let dot = 0;
    let mag1 = 0;
    let mag2 = 0;
    for (let i = 0; i < sig1.length; i++) {
        dot += sig1[i] * sig2[i];
        mag1 += sig1[i] ** 2;
        mag2 += sig2[i] ** 2;
    }
    return dot / (Math.sqrt(mag1) * Math.sqrt(mag2));
};

const performScan = async () => {
    if (!videoRef.value || videoRef.value.paused || videoRef.value.ended || isLoading.value || scanCooldown.value || !modelsLoaded.value || !faceLandmarker.value) return;

    // Ensure video is ready and has dimensions
    if (videoRef.value.readyState < 2 || videoRef.value.videoWidth === 0) return;

    try {
        // MediaPipe VIDEO mode requires a timestamp
        const timestamp = performance.now();
        const result = await faceLandmarker.value.detectForVideo(videoRef.value, timestamp);
        
        if (!result || !result.faceLandmarks || result.faceLandmarks.length === 0) {
            livenessFeedback.value = "Ready for scan";
            return;
        }

        const landmarks = result.faceLandmarks[0];

        // --- CIRCLE DETECTION: Ensure face is centered ---
        // Circle is 256px (w-64) in the center of the viewport.
        // Landmarks are normalized 0-1.
        // For simplicity, we check if the nose (index 1) is near the center (0.5, 0.5)
        // and if the face size is appropriate.
        const nose = landmarks[1];
        const faceCenterX = nose.x;
        const faceCenterY = nose.y;

        // Tolerance: face must be within the center 30% of the screen to be "inside" the guide
        if (faceCenterX < 0.35 || faceCenterX > 0.65 || faceCenterY < 0.3 || faceCenterY > 0.7) {
            livenessFeedback.value = "Center your face in circle";
            return;
        }
        
        // --- Create Geometric Signature (Identical to Registration) ---
        const centered = landmarks.map(l => ({
            x: l.x - nose.x,
            y: l.y - nose.y,
            z: l.z - nose.z
        }));

        const eyeDist = Math.sqrt(
            Math.pow(landmarks[133].x - landmarks[362].x, 2) + 
            Math.pow(landmarks[133].y - landmarks[362].y, 2)
        );
        
        if (eyeDist < 0.05) {
            livenessFeedback.value = "Move closer to camera";
            return;
        }

        const currentSignature = centered.flatMap(l => [
            l.x / eyeDist,
            l.y / eyeDist,
            l.z / eyeDist
        ]);
        
        isScanning.value = true;
        livenessFeedback.value = "Analyzing face...";

        let bestMatch = { label: 'unknown', similarity: 0 };
        
        // Compare against all registered employees locally
        for (const emp of props.employees) {
            if (!emp.descriptor) continue;
            const similarity = getSimilarity(currentSignature, emp.descriptor);
            
            // Landmark-based signature threshold (0.95+ is usually same person after normalization)
            if (similarity > 0.96 && similarity > bestMatch.similarity) {
                bestMatch = { 
                    label: emp.employee_code, 
                    similarity,
                    name: emp.name
                };
            }
        }

        if (bestMatch.label !== 'unknown') {
            identifiedUser.value = bestMatch.name;
            livenessFeedback.value = `✓ Identified: ${bestMatch.name}`;
            livenessStatus.value = 'verified';
            
            // Auto-submit attendance
            const fullCode = bestMatch.label;
            
            // Capture frame for record
            const context = canvasRef.value.getContext('2d');
            canvasRef.value.width = videoRef.value.videoWidth;
            canvasRef.value.height = videoRef.value.videoHeight;
            context.drawImage(videoRef.value, 0, 0);
            const imageData = canvasRef.value.toDataURL('image/jpeg', 0.6);

            axios.post(route('attendance.kiosk.store'), {
                employee_code: fullCode,
                image: imageData,
                type: form.type
            })
            .then(response => {
                lastLog.value = response.data;
                successSound.play().catch(e => console.warn("Audio play blocked:", e));
                
                scanCooldown.value = true;
                setTimeout(() => {
                    scanCooldown.value = false;
                    livenessFeedback.value = "Ready for scan";
                    identifiedUser.value = null;
                    livenessStatus.value = 'waiting';
                    lastLog.value = null;
                }, 5000);
            })
            .catch(err => {
                console.error("Attendance error:", err);
                errorMsg.value = err.response?.data?.message || "Log failed";
                
                // Play error sound for validation errors (like "Already timed in")
                errorSound.currentTime = 0;
                errorSound.play().catch(e => console.warn("Audio play blocked:", e));

                scanCooldown.value = true;
                setTimeout(() => {
                    errorMsg.value = null;
                    scanCooldown.value = false;
                    livenessFeedback.value = "Ready for scan";
                    identifiedUser.value = null;
                    livenessStatus.value = 'waiting';
                }, 4000);
            })
            .finally(() => {
                isScanning.value = false;
            });
        } else {
            livenessFeedback.value = "Face not recognized";
            isScanning.value = false;
        }
    } catch (e) {
        console.error("Detection error:", e);
        // Don't show error message to user for transient detection errors
        isScanning.value = false;
    }
};

// Auto Scan Loop
let isLoopRunning = false;

const startAutoScan = () => {
    if (isLoopRunning) return;
    isLoopRunning = true;
    
    const loop = async () => {
        if (!isAutoScan.value || !isCameraActive.value || isScanning.value || scanCooldown.value || isLoading.value || !modelsLoaded.value) {
            requestAnimationFrame(loop);
            return;
        }
        
        await performScan();
        requestAnimationFrame(loop);
    };
    
    requestAnimationFrame(loop);
};

const form = useForm({
    employee_code: '',
    image: null,
    type: 'time_in' // 'time_in' or 'time_out'
});

const startCamera = async () => {
    errorMsg.value = null;
    
    // Check for Secure Context (HTTPS/Localhost)
    if (!window.isSecureContext && window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
        errorMsg.value = "Camera access requires HTTPS. Please ensure your site has an SSL certificate.";
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        errorMsg.value = "Your browser does not support camera access or it is blocked.";
        return;
    }

    try {
        // Try HD first
        try {
            stream.value = await navigator.mediaDevices.getUserMedia({ 
                video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: "user" } 
            });
        } catch (e) {
            // Fallback to any video
            console.warn("HD camera failed, trying default...", e);
            stream.value = await navigator.mediaDevices.getUserMedia({ video: true });
        }

        if (videoRef.value) {
            videoRef.value.srcObject = stream.value;
            isCameraActive.value = true;
            
            // Wait for video to be ready before scanning
            videoRef.value.onloadedmetadata = () => {
                videoRef.value.play();
                startAutoScan();
            };
        }
    } catch (err) {
        console.error("Camera error:", err);
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            errorMsg.value = "Camera permission denied. Please allow camera access in your browser settings.";
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
            errorMsg.value = "Camera is already in use by another application.";
        } else {
            errorMsg.value = "Could not access camera. Error: " + err.message;
        }
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        isCameraActive.value = false;
    }
    isLoopRunning = false;
};

const capture = () => {
    if (!videoRef.value || !canvasRef.value) return;
    
    const context = canvasRef.value.getContext('2d');
    canvasRef.value.width = videoRef.value.videoWidth;
    canvasRef.value.height = videoRef.value.videoHeight;
    context.drawImage(videoRef.value, 0, 0);
    
    const data = canvasRef.value.toDataURL('image/jpeg', 0.7); // Compressed
    form.image = data;
    capturedImage.value = data;
};

const resetCapture = () => {
    capturedImage.value = null;
    form.image = null;
};

const submitAttendance = (isAuto = false) => {
    let inputCode = form.employee_code.trim();
    
    if (!inputCode) {
        if (!isAuto) errorMsg.value = "Please enter Employee ID.";
        return;
    }

    // Always match by last 4 digits
    const lastDigits = inputCode.slice(-4);
    const match = props.employees.find(emp => emp.employee_code.slice(-4) === lastDigits);
    
    if (!match) {
        errorMsg.value = "No employee found with ID ending in " + lastDigits;
        return;
    }
    
    const fullEmployeeCode = match.employee_code;

    if (isAuto) {
         isScanning.value = true;
    }

    // Capture for record
    if (!form.image && isCameraActive.value) {
         if (videoRef.value && canvasRef.value) {
            const context = canvasRef.value.getContext('2d');
            canvasRef.value.width = videoRef.value.videoWidth;
            canvasRef.value.height = videoRef.value.videoHeight;
            context.drawImage(videoRef.value, 0, 0);
            form.image = canvasRef.value.toDataURL('image/jpeg', 0.6);
         }
    }

    isLoading.value = true;
    errorMsg.value = null;

    // Send with full employee code
    axios.post(route('attendance.kiosk.store'), {
        employee_code: fullEmployeeCode,
        image: form.image,
        type: form.type
    })
        .then(response => {
            lastLog.value = response.data;
            form.employee_code = ''; 
            if (!isAuto) resetCapture();
            else form.image = null; 

            scanCooldown.value = true;
            successSound.currentTime = 0;
            successSound.play().catch(e => console.warn("Audio play blocked:", e)); 
            
            setTimeout(() => {
                scanCooldown.value = false;
            }, 5000);
        })
        .catch(err => {
            // Extract specific validation message if available
            const responseData = err.response?.data;
            errorMsg.value = responseData?.message || 
                            (responseData?.errors ? Object.values(responseData.errors).flat()[0] : "Attendance failed.");
            
            scanCooldown.value = true;
            errorSound.currentTime = 0;
            errorSound.play().catch(e => console.warn("Audio play blocked:", e));
            
            setTimeout(() => scanCooldown.value = false, 3000);
        })
        .finally(() => {
            isLoading.value = false;
            isScanning.value = false;
            const input = document.getElementById('employee_code');
            if(input) input.focus();
        });
};

onUnmounted(() => {
    stopCamera();
    clearInterval(timeInterval);
});


const formattedTime = computed(() => {
    return currentTime.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
});

const formattedDate = computed(() => {
    return currentTime.value.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
});
</script>

<template>
    <Head title="Attendance Kiosk" />
    <div class="h-screen bg-slate-900 flex flex-col md:flex-row overflow-hidden relative">
        <Toast />
        
        <!-- Left: Camera Feed -->
        <div class="flex-1 relative bg-black flex items-center justify-center overflow-hidden">
                <video 
                    ref="videoRef" 
                    autoplay 
                    playsinline 
                    muted 
                    class="absolute inset-0 w-full h-full object-cover"
                    :class="{'opacity-50': capturedImage}"
                ></video>
                
                <!-- Face Overlay Guide -->
                <div v-if="isCameraActive && !capturedImage" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div 
                        class="w-64 h-64 border-4 rounded-full transition-all duration-300 shadow-[0_0_100px_rgba(59,130,246,0.3)]"
                        :class="livenessStatus === 'verified' ? 'border-emerald-500 scale-110' : (livenessStatus === 'verifying' ? 'border-amber-400 animate-pulse' : 'border-blue-500/50')"
                    ></div>
                    
                    <div class="absolute flex flex-col items-center mt-96">
                        <div class="text-blue-200 font-mono text-sm bg-black/70 px-6 py-2 rounded-xl backdrop-blur-md border border-white/10 shadow-2xl text-center">
                            <div v-if="identifiedUser" class="text-emerald-400 font-bold mb-1">USER: {{ identifiedUser }}</div>
                            <div class="flex items-center justify-center gap-2 mb-1">
                                <span v-if="isScanning" class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                {{ livenessFeedback }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Captured Image Overlay -->
                <img v-if="capturedImage" :src="capturedImage" class="absolute inset-0 w-full h-full object-cover z-10">

                <canvas ref="canvasRef" class="hidden"></canvas>

                <!-- Camera Controls -->
                <div class="absolute bottom-6 left-0 right-0 flex justify-center z-20 gap-4 items-center">
                    <button 
                        @click="isAutoScan = !isAutoScan"
                        class="px-4 py-2 rounded-full font-bold text-xs backdrop-blur-md transition-all flex items-center border"
                        :class="isAutoScan ? 'bg-emerald-500/20 border-emerald-500 text-emerald-400' : 'bg-white/10 border-white/20 text-slate-400 hover:text-white'"
                    >
                        <div class="w-2 h-2 rounded-full mr-2" :class="isAutoScan ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'"></div>
                        {{ isAutoScan ? 'Auto Scan ON' : 'Auto Scan OFF' }}
                    </button>

                    <!-- Manual capture hidden per request
                    <button @click="resetCapture" v-if="capturedImage" class="bg-white/20 hover:bg-white/30 backdrop-blur-md text-white p-4 rounded-full transition-all">
                        <ArrowPathIcon class="w-8 h-8" />
                    </button>
                    <button @click="capture" v-if="!capturedImage" class="bg-white hover:bg-slate-200 text-slate-900 p-4 rounded-full shadow-lg transition-all transform hover:scale-105 active:scale-95">
                        <CameraIcon class="w-8 h-8" />
                    </button>
                    -->
                </div>
            </div>

            <!-- Right: Controls -->
            <div class="w-full md:w-96 bg-slate-800 text-white flex flex-col relative z-30 shadow-2xl border-l border-slate-700">
                
                <!-- Clock -->
                <div class="p-8 text-center border-b border-slate-700 bg-slate-800/50 backdrop-blur">
                    <div class="text-4xl font-mono font-bold tracking-wider text-blue-400">{{ formattedTime }}</div>
                    <div class="text-slate-400 text-sm mt-1 uppercase tracking-widest">{{ formattedDate }}</div>
                </div>

                <!-- Form -->
                <div class="p-8 flex-1 flex flex-col justify-center space-y-6">
                    
                    <!-- Mode Toggle -->
                    <div class="grid grid-cols-2 gap-2 bg-slate-700 p-1 rounded-xl">
                        <button 
                            @click="form.type = 'time_in'"
                            class="py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2"
                            :class="form.type === 'time_in' ? 'bg-emerald-500 text-white shadow-lg' : 'text-slate-400 hover:text-white'"
                        >
                            <span class="w-2 h-2 rounded-full bg-white animate-pulse" v-if="form.type === 'time_in'"></span>
                            TIME IN
                        </button>
                        <button 
                            @click="form.type = 'time_out'"
                            class="py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2"
                            :class="form.type === 'time_out' ? 'bg-amber-500 text-white shadow-lg' : 'text-slate-400 hover:text-white'"
                        >
                            <span class="w-2 h-2 rounded-full bg-white animate-pulse" v-if="form.type === 'time_out'"></span>
                            TIME OUT
                        </button>
                    </div>

                    <!-- Input -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-400 uppercase ml-1">Employee ID / Scan QR</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <QrCodeIcon class="h-5 w-5 text-slate-500" />
                            </div>
                            <input 
                                id="employee_code"
                                v-model="form.employee_code" 
                                @keyup.enter="submitAttendance"
                                type="text" 
                                class="w-full bg-slate-900 border border-slate-600 rounded-xl py-4 pl-12 pr-4 text-lg font-mono text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-inner"
                                placeholder="Last 4 digits or Full ID"
                                autofocus
                                autocomplete="off"
                            >
                        </div>
                        <p class="text-xs text-slate-500 text-right">Enter at least last 4 digits • Press Enter</p>
                    </div>

                    <button 
                        @click="submitAttendance" 
                        :disabled="isLoading || !form.employee_code"
                        class="w-full py-4 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow-lg shadow-blue-600/20 transition-all transform hover:-translate-y-0.5 active:translate-y-0 text-lg flex items-center justify-center gap-2"
                    >
                        <span v-if="isLoading" class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></span>
                        <span>{{ isLoading ? 'Verifying...' : 'VERIFY & LOG' }}</span>
                    </button>

                    <!-- Feedback -->
                    <div v-if="errorMsg" class="p-4 bg-rose-500/10 border border-rose-500/20 rounded-xl flex items-start gap-3 animate-in fade-in slide-in-from-bottom-2">
                        <XCircleIcon class="w-6 h-6 text-rose-500 shrink-0" />
                        <span class="text-sm text-rose-200">{{ errorMsg }}</span>
                    </div>

                    <div v-if="lastLog && !errorMsg" class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl animate-in fade-in slide-in-from-bottom-2">
                        <div class="flex items-center gap-3 mb-2">
                            <CheckCircleIcon class="w-6 h-6 text-emerald-500" />
                            <span class="text-lg font-bold text-emerald-400">{{ lastLog.message }}</span>
                        </div>
                        <div class="pl-9">
                            <div class="text-white font-bold text-xl">{{ lastLog.employee }}</div>
                            <div class="text-slate-400 text-sm font-mono mt-1">{{ lastLog.time }}</div>
                        </div>
                    </div>

                </div>
                
                <!-- Footer -->
                <div class="p-4 border-t border-slate-700 text-center text-xs text-slate-500">
                    <p>Face Recognition Active • Secure Connection</p>
                </div>
            </div>
        </div>
    </template>

<style scoped>
/* High contrast focus for scanner visibility */
input:focus {
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3);
}
</style>