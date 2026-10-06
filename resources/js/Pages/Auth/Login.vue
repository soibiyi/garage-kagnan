<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion — Garage Kagnan" />

    <div class="min-h-screen bg-[#0B0F19] text-white flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 relative overflow-hidden font-sans selection:bg-[#E11D48] selection:text-white">
        
        <!-- EFFETS DE FOND -->
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-[#E11D48]/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-[#E11D48]/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- LOGO DU GARAGE -->
        <div class="mb-8 z-10 text-center space-y-3">
            <Link href="/" class="inline-block transition duration-300 hover:scale-105">
                <div class="rounded-2xl bg-white px-4 py-2 shadow-xl shadow-black/40">
                    <img
                        src="/images/logo-kagnan.png"
                        alt="Garage Kagnan"
                        class="h-12 w-auto object-contain sm:h-14"
                    />
                </div>
            </Link>
            <p class="text-xs font-bold uppercase tracking-widest text-gray-400">
                Portail de Gestion &amp; Administration
            </p>
        </div>

        <!-- CARTE DE CONNEXION -->
        <div class="w-full max-w-md bg-gradient-to-b from-gray-800/80 to-gray-900/90 border border-white/10 rounded-3xl p-8 shadow-2xl shadow-black/60 backdrop-blur-xl z-10 relative space-y-6">
            
            <div class="text-center space-y-1">
                <h1 class="text-2xl font-black text-white tracking-tight">Bienvenue</h1>
                <p class="text-xs font-medium text-gray-400">Saisissez vos identifiants pour accéder au système.</p>
            </div>

            <!-- MESSAGE DE STATUT -->
            <div v-if="status" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs font-bold text-emerald-400 text-center">
                {{ status }}
            </div>

            <!-- FORMULAIRE DE CONNEXION -->
            <form @submit.prevent="submit" class="space-y-4">
                
                <!-- EMAIL -->
                <div>
                    <label for="email" class="block text-xs font-extrabold uppercase tracking-wider text-gray-300 mb-1.5">
                        Adresse E-mail
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                            <i class="fa-solid fa-envelope text-xs"></i>
                        </div>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="nom@garagekagnan.com"
                            class="w-full pl-10 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-sm font-medium text-white placeholder-gray-500 focus:outline-none focus:border-[#E11D48] focus:ring-1 focus:ring-[#E11D48] transition"
                        />
                    </div>
                    <InputError class="mt-1.5 text-xs text-[#E11D48] font-bold" :message="form.errors.email" />
                </div>

                <!-- MOT DE PASSE -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-extrabold uppercase tracking-wider text-gray-300">
                            Mot de passe
                        </label>
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs text-[#E11D48] hover:text-[#BE123C] font-semibold transition"
                        >
                            Mot de passe oublié ?
                        </Link>
                    </div>

                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-500">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                        <input
                            id="password"
                            type="password"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••••••"
                            class="w-full pl-10 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-sm font-medium text-white placeholder-gray-500 focus:outline-none focus:border-[#E11D48] focus:ring-1 focus:ring-[#E11D48] transition"
                        />
                    </div>
                    <InputError class="mt-1.5 text-xs text-[#E11D48] font-bold" :message="form.errors.password" />
                </div>

                <!-- SE SOUVENIR DE MOI -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.remember"
                            class="rounded border-gray-700 bg-white/5 text-[#E11D48] focus:ring-[#E11D48] focus:ring-offset-gray-900"
                        />
                        <span class="text-xs font-semibold text-gray-400">Se souvenir de moi</span>
                    </label>
                </div>

                <!-- BOUTON SUBMIT -->
                <div class="pt-2">
                    <button
                        type="submit"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                        :disabled="form.processing"
                        class="w-full py-3.5 px-4 rounded-xl bg-[#E11D48] hover:bg-[#BE123C] text-white font-extrabold uppercase tracking-wider text-xs transition duration-200 flex items-center justify-center gap-2 shadow-lg shadow-[#E11D48]/30 cursor-pointer"
                    >
                        <i v-if="form.processing" class="fa-solid fa-circle-notch animate-spin text-xs"></i>
                        <i v-else class="fa-solid fa-right-to-bracket text-xs"></i>
                        <span>Se connecter au système</span>
                    </button>
                </div>

            </form>

            <!-- RETOUR ACCUEIL -->
            <div class="text-center pt-4 border-t border-white/10">
                <Link href="/" class="text-xs font-semibold text-gray-400 hover:text-white transition inline-flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Retour à la page d'accueil</span>
                </Link>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="mt-8 text-center text-[11px] font-medium text-gray-500 z-10">
            © 2026 Garage Kagnan — Tous droits réservés.
        </div>

    </div>
</template>