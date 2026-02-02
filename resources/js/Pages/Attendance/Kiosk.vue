<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import * as faceapi from 'face-api.js';
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
const faceMatcher = ref(null);

// Pre-load Success Sound
const successSound = new Audio('/sounds/success.wav');
successSound.load();

const errorSound = new Audio('/sounds/error.wav');
errorSound.load();

const currentTime = ref(new Date());
const timeInterval = setInterval(() => {
    currentTime.value = new Date();
}, 1000);

// Initialize Face Matcher
onMounted(async () => {
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/models')
        ]);
        modelsLoaded.value = true;
        
        if (props.employees && props.employees.length > 0) {
            const labeledDescriptors = props.employees.map(emp => {
                const descriptor = new Float32Array(emp.descriptor);
                return new faceapi.LabeledFaceDescriptors(emp.employee_code, [descriptor]);
            });
            faceMatcher.value = new faceapi.FaceMatcher(labeledDescriptors, 0.6); // 0.6 distance threshold
            console.log("Face Matcher initialized with " + props.employees.length + " profiles.");
        }
        
        startCamera();
    } catch (e) {
        console.error("Failed to load models or initialize matcher", e);
        errorMsg.value = "System Error: Face Recognition unavailable.";
    }
});

// Auto Scan Loop
let scanInterval = null;

const startAutoScan = () => {
    if (scanInterval) clearInterval(scanInterval);
    // Scan frequently for "glimpse" effect (e.g., every 500ms or 1s)
    scanInterval = setInterval(async () => {
        if (isAutoScan.value && isCameraActive.value && !isScanning.value && !scanCooldown.value && !isLoading.value && modelsLoaded.value) {
            await performLocalScan();
        }
    }, 1000); 
};

const performLocalScan = async () => {
    if (!videoRef.value || videoRef.value.paused || videoRef.value.ended) return;

    // Detect Face
    const detection = await faceapi.detectSingleFace(videoRef.value, new faceapi.TinyFaceDetectorOptions())
        .withFaceLandmarks()
        .withFaceDescriptor();

    if (detection) {
        if (faceMatcher.value) {
            const bestMatch = faceMatcher.value.findBestMatch(detection.descriptor);
            
            if (bestMatch.label !== 'unknown') {
                // We found a match!
                console.log("Match found:", bestMatch.toString());
                form.employee_code = bestMatch.label;
                submitAttendance(true); // true = auto submit
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
        stream.value = await navigator.mediaDevices.getUserMedia({ video: {} });
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
    
    const data = canvasRef.value.toDataURL('image/jpeg');
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
         // Visual feedback for auto scan
         isScanning.value = true;
    }

    // Capture for record (optional, if we want to save the image of the log)
    // For auto-scan, we might skip saving the image to save bandwidth, or capture invisible canvas
    if (!form.image && isCameraActive.value) {
        // Quick capture off-screen
         if (videoRef.value && canvasRef.value) {
            const context = canvasRef.value.getContext('2d');
            canvasRef.value.width = videoRef.value.videoWidth;
            canvasRef.value.height = videoRef.value.videoHeight;
            context.drawImage(videoRef.value, 0, 0);
            form.image = canvasRef.value.toDataURL('image/jpeg');
         }
    }

    isLoading.value = true;
    errorMsg.value = null;

    axios.post(route('attendance.kiosk.store'), form)
        .then(response => {
            lastLog.value = response.data;
            form.employee_code = ''; // Reset ID
            if (!isAuto) resetCapture();
            else form.image = null; // Reset image for next auto scan

            // Cooldown to prevent double log
            scanCooldown.value = true;
            successSound.currentTime = 0;
            successSound.play().catch(e => console.warn("Audio play blocked:", e)); 
            
            setTimeout(() => {
                scanCooldown.value = false;
                // lastLog.value = null; // Keep showing last log for a bit?
            }, 5000);
        })
        .catch(err => {
            // If auto-scan, maybe don't show error if it's just "Already timed in" to avoid spam?
            // But user needs to know.
            errorMsg.value = err.response?.data?.message || "Attendance failed.";
            scanCooldown.value = true; // Cooldown on error too?
            
            // Play Error Sound
            errorSound.currentTime = 0;
            errorSound.play().catch(e => console.warn("Audio play blocked:", e));
            
            setTimeout(() => scanCooldown.value = false, 3000);
        })
        .finally(() => {
            isLoading.value = false;
            isScanning.value = false;
            // Refocus input if manual
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
    <AppLayout :fluid="true">
        <div class="h-[calc(100vh-4rem)] bg-slate-900 flex flex-col md:flex-row overflow-hidden">
            
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
                    <div class="w-64 h-64 border-4 border-blue-500/50 rounded-full animate-pulse shadow-[0_0_100px_rgba(59,130,246,0.3)]"></div>
                    <div class="absolute text-blue-200 font-mono text-sm mt-80 bg-black/50 px-4 py-1 rounded-full backdrop-blur-sm">
                        Position face within frame
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

                    <button @click="resetCapture" v-if="capturedImage" class="bg-white/20 hover:bg-white/30 backdrop-blur-md text-white p-4 rounded-full transition-all">
                        <ArrowPathIcon class="w-8 h-8" />
                    </button>
                    <button @click="capture" v-if="!capturedImage" class="bg-white hover:bg-slate-200 text-slate-900 p-4 rounded-full shadow-lg transition-all transform hover:scale-105 active:scale-95">
                        <CameraIcon class="w-8 h-8" />
                    </button>
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
    </AppLayout>
</template>

<style scoped>
/* High contrast focus for scanner visibility */
input:focus {
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3);
}
</style>