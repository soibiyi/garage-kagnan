<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
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
    <GuestLayout>
        <Head title="Connexion — Garage Kagnan" />

        <div class="bg-gray-50/80 p-8 rounded-3xl border border-gray-200/80 shadow-sm space-y-6">
            
            <!-- EN-TÊTE AVEC NOM DE L'ENTREPRISE -->
            <div class="text-center space-y-3">
                <div class="inline-flex w-14 h-14 rounded-2xl bg-red-600/10 border border-red-600/30 items-center justify-center text-red-600 shadow-inner">
                    <i class="fa-solid fa-wrench text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight uppercase">
                        <span class="text-gray-900">GARAGE</span> <span class="text-red-600">KAGNAN</span>
                    </h1>
                    <p class="text-xs text-gray-500 font-medium tracking-wider uppercase mt-1">Portail de Gestion & Administration</p>
                </div>
            </div>

            <!-- MESSAGE DE STATUT EVENTUEL -->
            <div v-if="status" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-medium text-emerald-700 text-center">
                {{ status }}
            </div>

            <!-- FORMULAIRE DE CONNEXION -->
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel for="email" value="Adresse E-mail" class="text-xs font-bold uppercase tracking-wider text-gray-600" />

                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full rounded-xl border-gray-300 focus:border-red-600 focus:ring-red-600 shadow-sm text-sm bg-white"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nom@garagekagnan.com"
                    />

                    <InputError class="mt-1.5 text-xs" :message="form.errors.email" />
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <InputLabel for="password" value="Mot de passe" class="text-xs font-bold uppercase tracking-wider text-gray-600" />
                        
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs text-red-600 hover:text-red-700 font-semibold transition"
                        >
                            Mot de passe oublié ?
                        </Link>
                    </div>

                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full rounded-xl border-gray-300 focus:border-red-600 focus:ring-red-600 shadow-sm text-sm bg-white"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                    />

                    <InputError class="mt-1.5 text-xs" :message="form.errors.password" />
                </div>

                <div class="block">
                    <label class="flex items-center">
                        <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
                        <span class="ms-2 text-xs font-medium text-gray-600">Se souvenir de moi</span>
                    </label>
                </div>

                <div>
                    <button
                        type="submit"
                        class="w-full py-3 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-sm shadow-lg shadow-red-600/25 transition duration-200 flex items-center justify-center gap-2"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                        :disabled="form.processing"
                    >
                        <i class="fa-solid fa-right-to-bracket text-xs"></i>
                        <span>Se connecter au système</span>
                    </button>
                </div>
            </form>

            <!-- LIEN RETOUR ACCUEIL -->
            <div class="text-center pt-3 border-t border-gray-200/80">
                <Link href="/" class="text-xs font-medium text-gray-500 hover:text-gray-800 transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Retour à la page d'accueil</span>
                </Link>
            </div>

        </div>
    </GuestLayout>
</template>