<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import Toast from '@/Components/Toast.vue';
import confetti from 'canvas-confetti';
import { 
    ClockIcon, 
    UserIcon, 
    CheckCircleIcon, 
    XCircleIcon,
    QrCodeIcon,
    ArrowRightOnRectangleIcon,
    ArrowLeftOnRectangleIcon,
    HandThumbUpIcon,
    SparklesIcon,
    CakeIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    employees: Array,
    settings: Object,
});

const isLoading = ref(false);
const lastLog = ref(null);
const errorMsg = ref(null);
const showSuccessCard = ref(false);

// Pre-load Feedback Sounds
const successSound = new Audio('/sounds/success.wav');
const errorSound = new Audio('/sounds/error.wav');

const currentTime = ref(new Date());
const timeInterval = setInterval(() => {
    currentTime.value = new Date();
}, 1000);

const form = useForm({
    employee_code: '',
    type: 'time_in' // 'time_in' or 'time_out'
});

const submitAttendance = () => {
    let inputCode = form.employee_code.trim();
    
    if (!inputCode) return;

    isLoading.value = true;
    errorMsg.value = null;

    axios.post(route('attendance.kiosk.store'), {
        employee_code: inputCode,
        type: form.type
    })
        .then(response => {
            lastLog.value = response.data;
            form.employee_code = ''; 
            
            // Success Feedback
            showSuccessCard.value = true;
            successSound.currentTime = 0;
            successSound.play().catch(e => console.warn("Audio play blocked:", e)); 
            
            // Birthday Celebration
            if (lastLog.value?.is_birthday) {
                const duration = 3 * 1000;
                const end = Date.now() + duration;

                (function frame() {
                    confetti({
                        particleCount: 3,
                        angle: 60,
                        spread: 55,
                        origin: { x: 0 },
                        colors: ['#3B82F6', '#10B981', '#F59E0B']
                    });
                    confetti({
                        particleCount: 3,
                        angle: 120,
                        spread: 55,
                        origin: { x: 1 },
                        colors: ['#3B82F6', '#10B981', '#F59E0B']
                    });

                    if (Date.now() < end) {
                        requestAnimationFrame(frame);
                    }
                }());
            }

            // Auto-reset: Prolong display for birthdays (15s) vs regular (5s)
            const resetDelay = lastLog.value?.is_birthday ? 15000 : 5000;
            setTimeout(() => {
                showSuccessCard.value = false;
                lastLog.value = null;
            }, resetDelay);
        })
        .catch(err => {
            const responseData = err.response?.data;
            errorMsg.value = responseData?.message || "Attendance failed.";
            form.employee_code = '';

            errorSound.currentTime = 0;
            errorSound.play().catch(e => console.warn("Audio play blocked:", e));
            
            setTimeout(() => {
                errorMsg.value = null;
            }, 3000);
        })
        .finally(() => {
            isLoading.value = false;
            // Always keep focus on input
            focusInput();
        });
};

const focusInput = () => {
    const input = document.getElementById('employee_code');
    if(input) input.focus();
};

onMounted(() => {
    focusInput();
    // Re-focus input if user clicks anywhere
    document.addEventListener('click', focusInput);
});

onUnmounted(() => {
    clearInterval(timeInterval);
    document.removeEventListener('click', focusInput);
});

const formattedTime = computed(() => {
    return currentTime.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
});

const formattedDate = computed(() => {
    return currentTime.value.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
});

const greeting = computed(() => {
    const hour = currentTime.value.getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 18) return 'Good Afternoon';
    return 'Good Evening';
});
</script>

<template>
    <Head title="Attendance Kiosk" />
    <div class="h-screen bg-[#0F172A] flex flex-col overflow-hidden relative font-sans text-white">
        <Toast />
        
        <!-- Main Layout -->
        <div class="flex-1 flex flex-col lg:flex-row">
            
            <!-- LEFT: Hero Section (Feedback & Identity) -->
            <div class="flex-1 relative flex flex-col items-center justify-center p-8 overflow-hidden border-r border-slate-800/50">
                
                <!-- Abstract Background Blobs -->
                <div class="absolute top-0 -left-20 w-96 h-96 bg-blue-600/10 rounded-full blur-[120px]"></div>
                <div class="absolute bottom-0 -right-20 w-96 h-96 bg-indigo-600/10 rounded-full blur-[120px]"></div>

                <!-- Idle State: Big Clock & Instructions -->
                <Transition name="fade" mode="out-in">
                    <div v-if="!showSuccessCard" class="text-center z-10 space-y-12">
                        <div class="space-y-4">
                            <div class="text-[120px] font-black tracking-tighter leading-none text-transparent bg-clip-text bg-gradient-to-b from-white to-slate-500">
                                {{ formattedTime }}
                            </div>
                            <div class="text-2xl font-bold text-blue-400 uppercase tracking-[0.3em]">
                                {{ formattedDate }}
                            </div>
                        </div>

                        <div class="flex flex-col items-center space-y-6">
                            <div class="w-32 h-32 bg-slate-800/50 rounded-3xl flex items-center justify-center border-2 border-slate-700/50 relative group">
                                <QrCodeIcon class="w-16 h-16 text-slate-500 group-hover:text-blue-400 transition-colors" />
                                <!-- Scanline effect -->
                                <div class="absolute inset-0 bg-gradient-to-b from-transparent via-blue-500/20 to-transparent h-1 w-full top-0 animate-scan"></div>
                            </div>
                            <div class="space-y-2">
                                <h2 class="text-3xl font-black text-white">Ready to Scan</h2>
                                <p class="text-slate-400 font-medium">Please position your ID or QR Code at the scanner.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Success State: Employee Identity -->
                    <div v-else class="z-10 w-full max-w-2xl">
                        <div class="bg-white/5 backdrop-blur-2xl border border-white/10 rounded-[40px] p-12 shadow-2xl relative overflow-hidden">
                            <!-- Success Badge -->
                            <div class="absolute top-8 right-8">
                                <div class="bg-emerald-500 text-white p-3 rounded-2xl shadow-lg shadow-emerald-500/40 animate-bounce">
                                    <CheckCircleIcon class="w-8 h-8" />
                                </div>
                            </div>

                            <div class="flex flex-col items-center text-center space-y-8">
                                <!-- Birthday Badge -->
                                <div v-if="lastLog?.is_birthday" class="animate-pulse">
                                    <div class="bg-gradient-to-r from-amber-400 to-rose-500 text-white px-6 py-2 rounded-full text-xs font-black uppercase tracking-[0.3em] shadow-lg flex items-center gap-2">
                                        <CakeIcon class="w-4 h-4" />
                                        Happy Birthday!
                                        <CakeIcon class="w-4 h-4" />
                                    </div>
                                </div>

                                <!-- Photo -->
                                <div class="relative">
                                    <div class="w-48 h-48 rounded-[2.5rem] overflow-hidden border-4 shadow-2xl transition-all"
                                        :class="lastLog?.is_birthday ? 'border-amber-400 animate-glow' : 'border-emerald-500/50'">
                                        <img v-if="lastLog?.photo" :src="lastLog.photo" class="w-full h-full object-cover">
                                        <div v-else class="w-full h-full bg-slate-800 flex items-center justify-center">
                                            <UserIcon class="w-20 h-20 text-slate-600" />
                                        </div>
                                    </div>
                                    <div class="absolute -bottom-4 left-1/2 -translate-x-1/2 px-6 py-1.5 rounded-full text-xs font-black uppercase tracking-widest shadow-lg text-white"
                                        :class="lastLog?.is_birthday ? 'bg-amber-500' : 'bg-emerald-500'">
                                        {{ lastLog?.message?.includes('In') ? 'Timed In' : 'Timed Out' }}
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <p class="text-emerald-400 font-black uppercase tracking-[0.2em] text-sm" :class="{'text-amber-400': lastLog?.is_birthday}">
                                        {{ lastLog?.is_birthday ? 'Celebrate your day,' : greeting + ',' }}
                                    </p>
                                    <h1 class="text-5xl font-black text-white tracking-tight">{{ lastLog?.employee }}</h1>
                                    <div class="flex items-center justify-center gap-3 mt-4">
                                        <ClockIcon class="w-6 h-6 text-slate-400" />
                                        <span class="text-3xl font-mono font-bold text-slate-200">{{ lastLog?.time }}</span>
                                    </div>
                                </div>

                                <div class="pt-8 w-full border-t border-white/5">
                                    <div v-if="lastLog?.is_birthday" class="space-y-2">
                                        <template v-if="lastLog?.message?.includes('In')">
                                            <p class="text-amber-200 font-bold text-lg">We're so glad you're with us today!</p>
                                            <p class="text-slate-400 text-xs italic">"The only way to do great work is to love what you do." - Thank you for your amazing dedication even on your special day.</p>
                                        </template>
                                        <template v-else>
                                            <p class="text-amber-200 font-bold text-lg">Shift ends, let the party begin!</p>
                                            <p class="text-slate-400 text-xs italic">Go home, relax, and celebrate with your loved ones. You've earned a wonderful evening. Happy Birthday!</p>
                                        </template>
                                    </div>
                                    <p v-else class="text-slate-400 font-medium">Have a productive day ahead!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- RIGHT: Controls Panel -->
            <div class="w-full lg:w-[400px] bg-slate-900/50 backdrop-blur-xl border-l border-white/5 flex flex-col z-20">
                
                <!-- Brand -->
                <div class="p-8 border-b border-white/5 flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-600/30">
                        <SparklesIcon class="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h3 class="font-black text-xl tracking-tight">Amalgated</h3>
                        <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest">Self-Service Kiosk</p>
                    </div>
                </div>

                <div class="p-8 flex-1 space-y-10 flex flex-col justify-center">
                    
                    <!-- Type Selector -->
                    <div class="space-y-4">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Select Transaction</label>
                        <div class="grid grid-cols-1 gap-3">
                            <button 
                                @click="form.type = 'time_in'"
                                class="group p-6 rounded-3xl border-2 transition-all duration-300 flex items-center gap-6"
                                :class="form.type === 'time_in' ? 'bg-emerald-500 border-emerald-400 shadow-xl shadow-emerald-500/20 translate-x-2' : 'bg-slate-800/50 border-slate-700 hover:border-slate-600'"
                            >
                                <div class="p-3 rounded-2xl transition-colors" :class="form.type === 'time_in' ? 'bg-white/20 text-white' : 'bg-slate-700 text-slate-400'">
                                    <ArrowRightOnRectangleIcon class="w-8 h-8" />
                                </div>
                                <div class="text-left">
                                    <div class="text-lg font-black" :class="form.type === 'time_in' ? 'text-white' : 'text-slate-300'">TIME IN</div>
                                    <div class="text-xs font-bold" :class="form.type === 'time_in' ? 'text-emerald-100' : 'text-slate-500'">Shift Start Log</div>
                                </div>
                            </button>

                            <button 
                                @click="form.type = 'time_out'"
                                class="group p-6 rounded-3xl border-2 transition-all duration-300 flex items-center gap-6"
                                :class="form.type === 'time_out' ? 'bg-amber-500 border-amber-400 shadow-xl shadow-amber-500/20 translate-x-2' : 'bg-slate-800/50 border-slate-700 hover:border-slate-600'"
                            >
                                <div class="p-3 rounded-2xl transition-colors" :class="form.type === 'time_out' ? 'bg-white/20 text-white' : 'bg-slate-700 text-slate-400'">
                                    <ArrowLeftOnRectangleIcon class="w-8 h-8" />
                                </div>
                                <div class="text-left">
                                    <div class="text-lg font-black" :class="form.type === 'time_out' ? 'text-white' : 'text-slate-300'">TIME OUT</div>
                                    <div class="text-xs font-bold" :class="form.type === 'time_out' ? 'text-amber-100' : 'text-slate-500'">Shift End Log</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Manual Entry / Scanner Input -->
                    <div class="relative">
                        <!-- Visible Input: Only shown when enabled by Admin in DTR -->
                        <div v-if="settings?.kiosk_manual_input" class="space-y-2">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Manual Input (Debug Mode)</label>
                            <input 
                                id="employee_code"
                                v-model="form.employee_code" 
                                @keyup.enter="submitAttendance"
                                type="text" 
                                class="w-full bg-slate-800 border-2 border-slate-700 rounded-2xl py-4 px-6 text-white placeholder-slate-600 focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono shadow-inner"
                                placeholder="Paste QR Code Here..."
                                autofocus
                                autocomplete="off"
                            >
                        </div>

                        <!-- Hidden Input: Standard Kiosk Operation -->
                        <input 
                            v-else
                            id="employee_code"
                            v-model="form.employee_code" 
                            @keyup.enter="submitAttendance"
                            type="password" 
                            class="opacity-0 absolute pointer-events-none"
                            autofocus
                            autocomplete="off"
                        >
                        
                        <div v-if="errorMsg" class="p-6 bg-rose-500/10 border border-rose-500/20 rounded-3xl flex items-start gap-4 animate-shake">
                            <XCircleIcon class="w-8 h-8 text-rose-500 shrink-0" />
                            <div>
                                <h4 class="font-black text-rose-500 text-sm uppercase">Access Denied</h4>
                                <p class="text-xs text-rose-200 mt-1 font-medium">{{ errorMsg }}</p>
                            </div>
                        </div>

                        <div v-else class="p-6 bg-blue-500/5 border border-blue-500/10 rounded-3xl flex items-center gap-4">
                            <div class="w-3 h-3 bg-blue-500 rounded-full animate-pulse"></div>
                            <span class="text-xs font-black text-blue-400 uppercase tracking-widest">Scanner Ready</span>
                        </div>
                    </div>

                </div>
                
                <!-- Technical Status -->
                <div class="p-8 border-t border-white/5 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">System Online</span>
                    </div>
                    <span class="text-[10px] font-mono text-slate-600">v2.0.4-ELITE</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Animations */
.animate-scan {
    animation: scan 3s ease-in-out infinite;
}

@keyframes scan {
    0%, 100% { top: 0; opacity: 0; }
    50% { top: 100%; opacity: 1; }
}

.animate-glow {
    animation: glow 2s ease-in-out infinite;
}

@keyframes glow {
    0%, 100% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.3); }
    50% { box-shadow: 0 0 40px rgba(245, 158, 11, 0.6); }
}

.animate-shake {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
    40%, 60% { transform: translate3d(4px, 0, 0); }
}

.fade-enter-active, .fade-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-enter-from, .fade-leave-to {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}
</style>
