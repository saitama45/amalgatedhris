<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col lg:flex-row bg-slate-50">
        <!-- Left Panel - Branding & Vision (Wider 60%) -->
        <div class="hidden lg:flex lg:w-7/12 bg-slate-900 relative overflow-hidden flex-col justify-between p-16">
            
            <!-- Modern Gradient Background -->
            <div class="absolute inset-0">
                <div class="absolute inset-0 bg-[#0B1120]"></div>
                <!-- Organic Mesh Gradients -->
                <div class="absolute top-0 right-0 -mr-20 -mt-20 w-[600px] h-[600px] bg-indigo-900/30 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-[500px] h-[500px] bg-teal-900/20 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full h-full bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-slate-800/20 via-slate-900/0 to-slate-900/0"></div>
            </div>

            <!-- Header / Logo Area -->
            <div class="relative z-10">
                <Link href="/" class="flex items-center gap-3 group">
                    <div class="bg-white/10 p-2 rounded-lg backdrop-blur-sm group-hover:bg-white/20 transition-all duration-300">
                        <ApplicationLogo class="h-8 w-8 text-white" />
                    </div>
                    <span class="text-xl font-bold text-white tracking-wide">Amalgated <span class="text-teal-400">HRIS</span></span>
                </Link>
            </div>

            <!-- Main Content Area -->
            <div class="relative z-10 max-w-2xl">
                <h1 class="text-5xl font-bold text-white leading-tight mb-6">
                    Empowering your <br/>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-teal-400 to-indigo-400">most valuable asset.</span>
                </h1>
                
                <p class="text-lg text-slate-400 leading-relaxed font-light mb-8 max-w-xl">
                    Experience the next generation of workforce management. From recruitment to retirement, we streamline every step of the employee lifecycle.
                </p>

                <!-- Feature Grid (Modern Pill Style) -->
                <div class="flex flex-wrap gap-3">
                    <div class="px-4 py-2 bg-white/5 backdrop-blur-md border border-white/10 rounded-full text-sm text-slate-300">
                        ✨ People Analytics
                    </div>
                    <div class="px-4 py-2 bg-white/5 backdrop-blur-md border border-white/10 rounded-full text-sm text-slate-300">
                        🚀 Talent Management
                    </div>
                    <div class="px-4 py-2 bg-white/5 backdrop-blur-md border border-white/10 rounded-full text-sm text-slate-300">
                        🛡️ Compliance Ready
                    </div>
                </div>
            </div>

            <!-- Footer / Copyright -->
            <div class="relative z-10 text-xs text-slate-500 font-mono">
                AMALGATED SYSTEM v4.0.2 // SECURE CONNECTION
            </div>
        </div>

        <!-- Right Panel - Authentication Form (40%) -->
        <div class="flex-1 flex flex-col justify-center items-center p-6 sm:p-12 relative overflow-y-auto">
             <!-- Mobile Header -->
             <div class="lg:hidden w-full max-w-md mb-8 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationLogo class="h-8 w-8 text-indigo-900" />
                    <span class="font-bold text-slate-900">Amalgated</span>
                </Link>
            </div>

            <div class="w-full max-w-[400px]">
                <slot />
            </div>
            
             <div class="mt-8 text-center lg:hidden">
                <p class="text-xs text-slate-400">
                    &copy; 2026 Amalgated | HRIS
                </p>
            </div>
        </div>
    </div>
</template>