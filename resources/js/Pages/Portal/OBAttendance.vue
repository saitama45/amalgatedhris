<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    CameraIcon, 
    MapPinIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ArrowTopRightOnSquareIcon,
    UserCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    todayLog: Object,
    employee: Object,
});

const video = ref(null);
const canvas = ref(null);
const isCameraOpen = ref(false);
const stream = ref(null);
const capturedPhoto = ref(null);
const location = ref({ latitude: null, longitude: null });
const locationError = ref(null);
const isProcessing = ref(false);

const form = useForm({
    type: '',
    photo: '',
    latitude: null,
    longitude: null,
});

const canTimeIn = computed(() => !props.todayLog?.time_in);
const canTimeOut = computed(() => props.todayLog?.time_in && !props.todayLog?.time_out);

const startCamera = async () => {
    try {
        isCameraOpen.value = true;
        capturedPhoto.value = null;
        stream.value = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: "user" }, 
            audio: false 
        });
        if (video.value) {
            video.value.srcObject = stream.value;
        }
    } catch (err) {
        console.error("Error accessing camera:", err);
        alert("Could not access camera. Please ensure you have given permission.");
        isCameraOpen.value = false;
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        stream.value = null;
    }
    isCameraOpen.value = false;
};

const capturePhoto = () => {
    const context = canvas.value.getContext('2d');
    canvas.value.width = video.value.videoWidth;
    canvas.value.height = video.value.videoHeight;
    context.drawImage(video.value, 0, 0, canvas.value.width, canvas.value.height);
    capturedPhoto.value = canvas.value.toDataURL('image/jpeg');
    form.photo = capturedPhoto.value;
    stopCamera();
};

const getLocation = () => {
    if (!navigator.geolocation) {
        locationError.value = "Geolocation is not supported by your browser.";
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            location.value.latitude = position.coords.latitude;
            location.value.longitude = position.coords.longitude;
            form.latitude = position.coords.latitude;
            form.longitude = position.coords.longitude;
            locationError.value = null;
        },
        (err) => {
            locationError.value = "Could not get your location. Please enable GPS.";
            console.error(err);
        },
        { enableHighAccuracy: true }
    );
};

const submitAttendance = (type) => {
    if (!capturedPhoto.value) {
        alert("Please take a selfie first.");
        return;
    }
    if (!location.value.latitude) {
        alert("Waiting for GPS location...");
        getLocation();
        return;
    }

    form.type = type;
    isProcessing.value = true;
    
    form.post(route('portal.ob-attendance.store'), {
        preserveScroll: true,
        onSuccess: () => {
            capturedPhoto.value = null;
            form.photo = '';
            isProcessing.value = false;
        },
        onError: () => {
            isProcessing.value = false;
        }
    });
};

onMounted(() => {
    getLocation();
});

onUnmounted(() => {
    stopCamera();
});

const formatTime = (time) => {
    if (!time) return '--:--';
    return new Date(time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="My OB Attendance" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Official Business Attendance</h2>
                    <p class="text-sm text-slate-500 mt-1">Submit your attendance for off-site assignments.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Camera Section -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                        <div class="p-4 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                            <h3 class="font-bold text-slate-700 flex items-center gap-2">
                                <CameraIcon class="w-5 h-5 text-indigo-500" />
                                Selfie Verification
                            </h3>
                            <span v-if="location.latitude" class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold flex items-center gap-1">
                                <MapPinIcon class="w-3 h-3" /> GPS ACTIVE
                            </span>
                            <span v-else class="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold animate-pulse">
                                ACQUIRING GPS...
                            </span>
                        </div>

                        <div class="relative aspect-video bg-slate-900 flex items-center justify-center overflow-hidden">
                            <video v-if="isCameraOpen" ref="video" autoplay playsinline class="w-full h-full object-cover"></video>
                            <img v-else-if="capturedPhoto" :src="capturedPhoto" class="w-full h-full object-cover" />
                            <div v-else class="flex flex-col items-center text-slate-500 gap-3">
                                <UserCircleIcon class="w-20 h-20 opacity-20" />
                                <p class="text-sm">Camera is inactive</p>
                            </div>

                            <!-- Processing Overlay -->
                            <div v-if="isProcessing" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-10">
                                <div class="flex flex-col items-center gap-3 text-white">
                                    <svg class="animate-spin h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="font-bold tracking-widest text-xs uppercase">Processing...</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 flex gap-3">
                            <button 
                                v-if="!isCameraOpen && !capturedPhoto" 
                                @click="startCamera" 
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-2"
                            >
                                <CameraIcon class="w-5 h-5" /> Open Camera
                            </button>
                            <template v-else-if="isCameraOpen">
                                <button 
                                    @click="capturePhoto" 
                                    class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-emerald-200"
                                >
                                    Capture Photo
                                </button>
                                <button 
                                    @click="stopCamera" 
                                    class="px-4 bg-white border border-slate-200 text-slate-600 font-bold py-3 rounded-xl hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                            </template>
                            <button 
                                v-else-if="capturedPhoto" 
                                @click="startCamera" 
                                class="flex-1 bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-slate-200"
                            >
                                Retake Photo
                            </button>
                        </div>
                    </div>

                    <!-- Action Section -->
                    <div class="flex flex-col gap-6">
                        <!-- Status Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-4">Today's Status</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <div :class="['p-2 rounded-lg', todayLog?.time_in ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-200 text-slate-400']">
                                            <ClockIcon class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase">Time In</p>
                                            <p class="text-lg font-mono font-bold text-slate-700">{{ formatTime(todayLog?.time_in) }}</p>
                                        </div>
                                    </div>
                                    <a v-if="todayLog?.in_location_url" :href="todayLog.in_location_url" target="_blank" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="View Map">
                                        <MapPinIcon class="w-5 h-5" />
                                    </a>
                                </div>

                                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <div :class="['p-2 rounded-lg', todayLog?.time_out ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-200 text-slate-400']">
                                            <ClockIcon class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase">Time Out</p>
                                            <p class="text-lg font-mono font-bold text-slate-700">{{ formatTime(todayLog?.time_out) }}</p>
                                        </div>
                                    </div>
                                    <a v-if="todayLog?.out_location_url" :href="todayLog.out_location_url" target="_blank" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="View Map">
                                        <MapPinIcon class="w-5 h-5" />
                                    </a>
                                </div>
                            </div>

                            <div v-if="locationError" class="mt-4 p-3 rounded-xl bg-rose-50 border border-rose-100 flex items-start gap-3">
                                <ExclamationTriangleIcon class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" />
                                <p class="text-xs text-rose-700 font-medium">{{ locationError }}</p>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex flex-col gap-3">
                            <button 
                                :disabled="!canTimeIn || isProcessing"
                                @click="submitAttendance('in')"
                                :class="[
                                    'py-4 rounded-2xl font-bold text-lg transition-all shadow-lg flex items-center justify-center gap-3',
                                    canTimeIn ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-200' : 'bg-slate-100 text-slate-400 shadow-none cursor-not-allowed'
                                ]"
                            >
                                <CheckCircleIcon v-if="!canTimeIn" class="w-6 h-6" />
                                {{ !canTimeIn ? 'Already Timed In' : 'Record OB Time In' }}
                            </button>

                            <button 
                                :disabled="!canTimeOut || isProcessing"
                                @click="submitAttendance('out')"
                                :class="[
                                    'py-4 rounded-2xl font-bold text-lg transition-all shadow-lg flex items-center justify-center gap-3',
                                    canTimeOut ? 'bg-rose-600 hover:bg-rose-700 text-white shadow-rose-200' : 'bg-slate-100 text-slate-400 shadow-none cursor-not-allowed'
                                ]"
                            >
                                <CheckCircleIcon v-if="todayLog?.time_out" class="w-6 h-6" />
                                {{ todayLog?.time_out ? 'Already Timed Out' : 'Record OB Time Out' }}
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <Link :href="route('portal.attendance')" class="text-sm font-bold text-indigo-600 hover:text-indigo-700 flex items-center justify-center gap-1">
                                View Attendance History <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- GPS Warning -->
                <div class="mt-8 bg-amber-50 rounded-2xl p-6 border border-amber-100 flex gap-4">
                    <div class="p-2 bg-white rounded-xl shadow-sm h-fit">
                        <MapPinIcon class="w-6 h-6 text-amber-500" />
                    </div>
                    <div>
                        <h4 class="font-bold text-amber-900">GPS Location & Selfie Required</h4>
                        <p class="text-sm text-amber-800/80 mt-1 leading-relaxed">
                            Official Business attendance requires your precise GPS location and a verification selfie. 
                            Ensure you are in an area with good GPS signal. These records will be validated before payroll processing.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <canvas ref="canvas" class="hidden"></canvas>
    </AppLayout>
</template>
