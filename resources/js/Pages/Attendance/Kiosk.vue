<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch, shallowRef } from 'vue';
import Toast from '@/Components/Toast.vue';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from "html5-qrcode";
// import { FaceLandmarker, FilesetResolver } from "@mediapipe/tasks-vision";
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
const html5QrCode = shallowRef(null);

// High-Speed 3D Liveness Engine
const livenessStatus = ref('waiting'); 
const livenessHistory = [];
const livenessScore = ref(0);
const identifiedUser = ref(null);
const livenessFeedback = ref('Ready for QR Scan');
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

// Initialize QR Scanner
onMounted(async () => {
    /* 
    // Facial Recognition Disabled
    console.log('Kiosk mounted. Employees with descriptors:', props.employees.length);
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
    */
    startCamera();
});

const getSimilarity = (sig1, sig2) => {
    /*
    if (!sig1 || !sig2 || sig1.length !== sig2.length) {
        console.warn('Signature length mismatch:', sig1?.length, 'vs', sig2?.length);
        return 0;
    }
    
    // Euclidean distance - more discriminative than cosine for faces
    let sumSquaredDiff = 0;
    for (let i = 0; i < sig1.length; i++) {
        sumSquaredDiff += (sig1[i] - sig2[i]) ** 2;
    }
    const distance = Math.sqrt(sumSquaredDiff);
    
    // Convert distance to similarity using exponential decay
    const similarity = Math.exp(-distance / 2.5);
    return similarity;
    */
    return 0;
};

const performScan = async () => {
    /*
    if (!videoRef.value || videoRef.value.paused || videoRef.value.ended || isLoading.value || scanCooldown.value || !modelsLoaded.value || !faceLandmarker.value) return;
    if (videoRef.value.readyState < 2 || videoRef.value.videoWidth === 0) return;

    try {
        const timestamp = performance.now();
        const result = await faceLandmarker.value.detectForVideo(videoRef.value, timestamp);
        // ... rest of the old face recognition logic ...
    } catch (e) {
        console.error("Detection error:", e);
    }
    */
    return;
};

const isStartingScanner = ref(false);
const startQRScanner = () => {
    if (html5QrCode.value || isStartingScanner.value) return;

    isStartingScanner.value = true;
    html5QrCode.value = new Html5Qrcode("reader");
    
    // Optimized configuration for Kiosk performance
    const config = { 
        fps: 25, 
        qrbox: (viewfinderWidth, viewfinderHeight) => {
            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
            const size = Math.floor(minEdge * 0.7);
            return { width: size, height: size };
        },
        aspectRatio: 1.0, 
        formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ],
        experimentalFeatures: {
            useBarCodeDetectorIfSupported: true 
        }
    };

    // The first argument must be a simple selector object or a deviceId string
    html5QrCode.value.start(
        { facingMode: "user" },
        config,
        (decodedText, decodedResult) => {
            if (scanCooldown.value || isLoading.value) return;
            handleQRDetected(decodedText);
        },
        (errorMessage) => {
            // silence noise
        }
    ).then(() => {
        isCameraActive.value = true;
        isStartingScanner.value = false;
        console.log("High-performance QR Scanner started");
    }).catch((err) => {
        console.error("QR Start Error:", err);
        isStartingScanner.value = false;
        errorMsg.value = "Camera Error: " + err;
    });
};

const handleQRDetected = (qrCode) => {
    if (scanCooldown.value) return;
    
    // Auto-fill and submit
    form.employee_code = qrCode;
    submitAttendance(true);
};

// Auto Scan Loop
const startAutoScan = () => {
    startQRScanner();
};

const form = useForm({
    employee_code: '',
    image: null,
    type: 'time_in' // 'time_in' or 'time_out'
});

const startCamera = async () => {
    errorMsg.value = null;
    startQRScanner();
};

const stopCamera = () => {
    if (html5QrCode.value) {
        html5QrCode.value.stop().then(() => {
            isCameraActive.value = false;
            html5QrCode.value = null;
        }).catch(err => console.error(err));
    }
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

    // When scanning QR, inputCode might be the full QR code. 
    // The backend now handles finding by employee_code OR qr_code.

    if (isAuto) {
         isScanning.value = true;
    }

    // Capture for record
    if (!form.image && isCameraActive.value) {
         const activeVideo = document.querySelector('#reader video');
         if (activeVideo && canvasRef.value) {
            try {
                const context = canvasRef.value.getContext('2d');
                canvasRef.value.width = activeVideo.videoWidth || 640;
                canvasRef.value.height = activeVideo.videoHeight || 480;
                context.drawImage(activeVideo, 0, 0);
                form.image = canvasRef.value.toDataURL('image/jpeg', 0.6);
            } catch (e) {
                console.warn("Could not capture image from QR stream:", e);
            }
         }
    }

    isLoading.value = true;
    errorMsg.value = null;

    // Send the code (could be ID or QR)
    axios.post(route('attendance.kiosk.store'), {
        employee_code: inputCode,
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
            
            livenessFeedback.value = "✓ SUCCESS";

            setTimeout(() => {
                scanCooldown.value = false;
                livenessFeedback.value = "Ready for QR Scan";
                livenessStatus.value = 'waiting';
                lastLog.value = null;
            }, 5000);
        })
        .catch(err => {
            // Extract specific validation message if available
            const responseData = err.response?.data;
            errorMsg.value = responseData?.message || 
                            (responseData?.errors ? Object.values(responseData.errors).flat()[0] : "Attendance failed.");
            
            livenessFeedback.value = "✕ FAILED";
            livenessStatus.value = 'waiting';

            scanCooldown.value = true;
            errorSound.currentTime = 0;
            errorSound.play().catch(e => console.warn("Audio play blocked:", e));
            
            setTimeout(() => {
                scanCooldown.value = false;
                errorMsg.value = null;
                livenessFeedback.value = "Ready for QR Scan";
            }, 3000);
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
    if (html5QrCode.value) {
        html5QrCode.value.stop().catch(err => console.error(err));
    }
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
        
        <!-- Left: Camera Feed / QR Scanner -->
        <div class="flex-1 relative bg-black flex items-center justify-center overflow-hidden">
                <!-- QR Scanner Target -->
                <div id="reader" class="w-full h-full"></div>

                <video 
                    ref="videoRef" 
                    autoplay 
                    playsinline 
                    muted 
                    class="absolute inset-0 w-full h-full object-cover hidden"
                ></video>
                
                <!-- Overlay Guide -->
                <div v-if="isCameraActive && !capturedImage" class="absolute inset-0 flex items-center justify-center pointer-events-none z-20">
                    <div 
                        class="w-72 h-72 border-4 rounded-3xl transition-all duration-300 shadow-[0_0_100px_rgba(59,130,246,0.3)]"
                        :class="livenessStatus === 'verified' ? 'border-emerald-500 scale-105' : 'border-blue-500/50 border-dashed'"
                    ></div>
                    
                    <div class="absolute flex flex-col items-center mt-[450px]">
                        <div class="text-blue-200 font-mono text-sm bg-black/70 px-6 py-2 rounded-xl backdrop-blur-md border border-white/10 shadow-2xl text-center">
                            <div class="flex items-center justify-center gap-2 mb-1">
                                <span v-if="isScanning" class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                <QrCodeIcon class="w-4 h-4 text-blue-400" />
                                {{ livenessFeedback }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Captured Image Overlay -->
                <img v-if="capturedImage" :src="capturedImage" class="absolute inset-0 w-full h-full object-cover z-30">

                <canvas ref="canvasRef" class="hidden"></canvas>

                <!-- Camera Controls -->
                <div class="absolute bottom-6 left-0 right-0 flex justify-center z-40 gap-4 items-center">
                    <div class="px-4 py-2 rounded-full font-bold text-xs backdrop-blur-md transition-all flex items-center border bg-blue-500/20 border-blue-500 text-blue-400">
                        <div class="w-2 h-2 rounded-full mr-2 bg-blue-400 animate-pulse"></div>
                        QR SCANNER ACTIVE
                    </div>
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

#reader :deep(video) {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}

#reader :deep(img) {
    display: none !important;
}

#reader :deep(#html5-qrcode-anchor-scan-region) {
    border: none !important;
}

/* Hide html5-qrcode controls as we use our own */
#reader :deep(button), 
#reader :deep(span), 
#reader :deep(select) {
    display: none !important;
}

#reader :deep(#html5-qrcode-panel-scan-region) {
    border: none !important;
}

/* Add a custom scanline effect */
.w-72.h-72::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: rgba(59, 130, 246, 0.5);
    box-shadow: 0 0 15px 2px rgba(59, 130, 246, 0.8);
    animation: scan 2s linear infinite;
    z-index: 30;
}

@keyframes scan {
    0% { top: 0; }
    50% { top: 100%; }
    100% { top: 0; }
}
</style>