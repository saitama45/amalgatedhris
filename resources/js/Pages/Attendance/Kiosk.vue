<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import Toast from '@/Components/Toast.vue';
import { Human } from '@vladmandic/human';
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
const humanConfig = {
    debug: false,
    userConsole: false,
    modelBasePath: 'https://cdn.jsdelivr.net/npm/@vladmandic/human/models/',
    filter: { enabled: true, equalization: true, flip: false },
    face: {
        enabled: true,
        detector: { return: true, rotation: true, mask: false, minConfidence: 0.4 },
        mesh: { enabled: true },
        iris: { enabled: true },
        description: { enabled: true },
        emotion: { enabled: false },
        antispoof: { enabled: true },
        liveness: { enabled: true },
    },
    body: { enabled: false },
    hand: { enabled: false },
    object: { enabled: false },
    segmentation: { enabled: false },
};

const human = new Human(humanConfig);

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

const resetLiveness = () => {
    livenessStatus.value = 'waiting';
    livenessScore.value = 0;
    identifiedUser.value = null;
    livenessFeedback.value = 'Ready for scan';
    livenessHistory.length = 0;
    stepHeadTurn.value = false;
    stepMouthMove.value = false;
    consecutiveMatches.value = 0;
    lastIdentifiedCode.value = null;
};

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
        await human.load();
        await human.warmup();
        modelsLoaded.value = true;
        startCamera();
    } catch (e) {
        console.error("Critical: Face Recognition unavailable.", e);
        errorMsg.value = "System Error: Face Recognition unavailable.";
    }
});

// Auto Scan Loop
let scanInterval = null;

const startAutoScan = () => {
    if (scanInterval) clearInterval(scanInterval);
    // Scan at 5-10 FPS for responsiveness
    scanInterval = setInterval(async () => {
        if (isAutoScan.value && isCameraActive.value && !isScanning.value && !scanCooldown.value && !isLoading.value && modelsLoaded.value) {
            await performLocalScan();
        }
    }, 150); 
};

// Robust Similarity Helper (Cosine Similarity)
const getSimilarity = (embedding1, embedding2) => {
    if (!embedding1 || !embedding2) return 0;
    // Human similarity is usually under human.match.similarity
    if (human.match && typeof human.match.similarity === 'function') {
        return human.match.similarity(embedding1, embedding2);
    }
    // Fallback: Manual Dot Product (Human embeddings are usually normalized)
    let dot = 0;
    for (let i = 0; i < embedding1.length; i++) {
        dot += embedding1[i] * embedding2[i];
    }
    return dot;
};

const performLocalScan = async () => {
    if (!videoRef.value || videoRef.value.paused || videoRef.value.ended || isLoading.value || scanCooldown.value || !modelsLoaded.value) return;

    // 1. Detection
    const result = await human.detect(videoRef.value);
    
    if (!result.face || result.face.length === 0) {
        if (livenessStatus.value !== 'waiting') resetLiveness();
        return;
    }

    const face = result.face[0];
    const box = face.boxRaw; // [x, y, width, height] normalized
    
    // --- SPATIAL FILTERING ---
    const faceCenterX = box[0] + (box[2] / 2);
    const faceCenterY = box[1] + (box[3] / 2);
    const distFromCenter = Math.sqrt(Math.pow(faceCenterX - 0.5, 2) + Math.pow(faceCenterY - 0.5, 2));
    
    if (distFromCenter > 0.18) {
        livenessFeedback.value = "Center your face in the circle";
        if (livenessStatus.value !== 'waiting') resetLiveness();
        return;
    }

    // Quality Check
    if (box[2] < 0.22) {
        livenessFeedback.value = "Please come closer...";
        return;
    }

    // 2. Liveness Data Collection (Using Human's advanced mesh)
    const landmarks = face.meshRaw; // 468 points
    
    // Roll check using iris/eyes
    const leftEye = landmarks[33];
    const rightEye = landmarks[263];
    const roll = Math.abs(rightEye[1] - leftEye[1]) / Math.abs(rightEye[0] - leftEye[0]);
    if (roll > 0.12) {
        livenessFeedback.value = "Keep your head level...";
        return;
    }

    // Parallax & Action Meta-Data
    const noseBridge = landmarks[1];
    const leftEdge = landmarks[234];
    const rightEdge = landmarks[454];
    const topLip = landmarks[13];
    const bottomLip = landmarks[14];

    const eyeDist = Math.abs(rightEye[0] - leftEye[0]);
    const noseOffset = (noseBridge[0] - leftEye[0]) / eyeDist;
    const edgeRatio = Math.abs(leftEye[0] - leftEdge[0]) / Math.abs(rightEdge[0] - rightEye[0]);
    const mouthOpenRatio = Math.abs(bottomLip[1] - topLip[1]) / eyeDist;

    livenessHistory.push({
        noseOffset,
        edgeRatio,
        mouthOpenRatio,
        time: Date.now()
    });
    if (livenessHistory.length > 20) livenessHistory.shift();

    // 3. Identification Phase (with Human Similarity)
    if (livenessStatus.value === 'waiting') {
        let bestMatch = { label: 'unknown', distance: 1.0 };
        
        const currentEmbedding = face.embedding;
        if (currentEmbedding) {
            for (const emp of props.employees) {
                const similarity = getSimilarity(currentEmbedding, emp.descriptor);
                // Convert similarity (1.0 = match) to distance (0.0 = match)
                const distance = 1.0 - similarity;
                
                if (distance < 0.35 && distance < bestMatch.distance) {
                    bestMatch = { label: emp.employee_code, distance };
                }
            }
        }

        if (bestMatch.label !== 'unknown') {
            if (bestMatch.label === lastIdentifiedCode.value) {
                consecutiveMatches.value++;
            } else {
                lastIdentifiedCode.value = bestMatch.label;
                consecutiveMatches.value = 1;
            }

            if (consecutiveMatches.value >= 3) {
                const emp = props.employees.find(e => e.employee_code === bestMatch.label);
                if (emp) {
                    identifiedUser.value = emp.name;
                    form.employee_code = emp.employee_code;
                    livenessStatus.value = 'verifying';
                }
            }
        } else {
            consecutiveMatches.value = 0;
            lastIdentifiedCode.value = null;
        }
    }

    // 4. Verification Phase (Anti-Spoofing)
    if (livenessStatus.value === 'verifying') {
        const offsets = livenessHistory.map(h => h.noseOffset);
        const edgeRatios = livenessHistory.map(h => h.edgeRatio);
        const mouthRatios = livenessHistory.map(h => h.mouthOpenRatio);

        if (!stepHeadTurn.value) {
            livenessFeedback.value = "Slowly turn head left & right";
            const yawVar = Math.max(...offsets) - Math.min(...offsets);
            const edgeVar = Math.max(...edgeRatios) - Math.min(...edgeRatios);
            
            if (yawVar > 0.04 && edgeVar > 0.15) {
                stepHeadTurn.value = true;
                livenessHistory.length = 0; 
            }
        } else if (!stepMouthMove.value) {
            livenessFeedback.value = "Now Smile or Open Mouth";
            const mouthVar = Math.max(...mouthRatios) - Math.min(...mouthRatios);
            if (mouthVar > 0.07) {
                stepMouthMove.value = true;
            }
        } else {
            // Final check: Antispoofing probability (if model is ready)
            if (face.real > 0.3) { // 0.3 is decent threshold for "Real"
                livenessScore.value = 100;
                livenessStatus.value = 'verified';
                livenessFeedback.value = "Access Granted";
                submitAttendance(true);
                
                scanCooldown.value = true;
                setTimeout(() => {
                    resetLiveness();
                    scanCooldown.value = false;
                }, 5000);
            } else if (face.real !== undefined) {
                livenessFeedback.value = "Liveness check failed. Try again.";
                resetLiveness();
            }
        }
    }
};

const form = useForm({
    employee_code: '',
    image: null,
    type: 'time_in' // 'time_in' or 'time_out'
});

const startCamera = async () => {
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({ video: { width: 1280, height: 720 } });
        if (videoRef.value) {
            videoRef.value.srcObject = stream.value;
            isCameraActive.value = true;
            startAutoScan();
        }
    } catch (err) {
        console.error("Camera error:", err);
        errorMsg.value = "Could not access camera. Please allow permissions.";
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        isCameraActive.value = false;
    }
    if (scanInterval) clearInterval(scanInterval);
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
    if (!form.employee_code) {
        if (!isAuto) errorMsg.value = "Please enter Employee ID.";
        return;
    }

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

    // Use form.data() to send a plain object instead of the Inertia Form proxy
    axios.post(route('attendance.kiosk.store'), form.data())
        .then(response => {
            lastLog.value = response.data;
            form.employee_code = ''; 
            resetLiveness();
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
                                <span v-if="livenessStatus === 'verifying'" class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                {{ livenessFeedback }}
                            </div>
                            <div v-if="livenessStatus !== 'waiting'" class="flex flex-col gap-1 mt-2">
                                <div class="flex items-center justify-center gap-1 text-[10px] uppercase tracking-tighter" :class="stepHeadTurn ? 'text-emerald-400' : 'text-slate-500'">
                                    <CheckCircleIcon v-if="stepHeadTurn" class="w-3 h-3" />
                                    <div v-else class="w-2 h-2 rounded-full bg-slate-700"></div>
                                    3D Rotation
                                </div>
                                <div class="flex items-center justify-center gap-1 text-[10px] uppercase tracking-tighter" :class="stepMouthMove ? 'text-emerald-400' : 'text-slate-500'">
                                    <CheckCircleIcon v-if="stepMouthMove" class="w-3 h-3" />
                                    <div v-else class="w-2 h-2 rounded-full bg-slate-700"></div>
                                    Action Check
                                </div>
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
                                placeholder="Scan or Enter ID"
                                autofocus
                                autocomplete="off"
                            >
                        </div>
                        <p class="text-xs text-slate-500 text-right">Press Enter to Submit</p>
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