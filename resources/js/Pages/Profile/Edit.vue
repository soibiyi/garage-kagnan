<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');

// Fonction de déconnexion rapide
const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Mon Profil" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold leading-tight" style="color: #1A1A1A;">
                    Mon Compte & Paramètres
                </h2>
                <!-- Bouton de déconnexion visible pour tous -->
                <button @click="logout" 
                        class="text-sm font-semibold px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition shadow-sm bg-white"
                        style="color: #1A1A1A;">
                    Déconnexion
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- MESSAGE POUR LES NON-ADMINISTRATEURS -->
                <div v-if="!isAdmin" class="bg-amber-50 border border-amber-200 text-amber-800 p-6 rounded-xl shadow-sm flex items-start gap-4">
                    <span class="text-2xl">ℹ️</span>
                    <div>
                        <h3 class="font-bold text-base">Gestion centralisée des accès</h3>
                        <p class="text-sm mt-1 text-amber-700">
                            Vos informations personnelles (nom, email, rôle et mot de passe) sont gérées par l'administrateur du garage. 
                            Veuillez vous adresser à lui pour toute modification.
                        </p>
                    </div>
                </div>

                <!-- VUE COMPLÈTE (Réservée à l'Admin) -->
                <template v-if="isAdmin">
                    <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                            class="max-w-xl"
                        />
                    </div>

                    <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdatePasswordForm class="max-w-xl" />
                    </div>

                    <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <DeleteUserForm class="max-w-xl" />
                    </div>
                </template>

                <!-- VUE CONDENSÉE (Pour les employés : affichage simple de leurs infos en lecture seule) -->
                <template v-else>
                    <div class="p-6 bg-white shadow sm:rounded-lg max-w-xl space-y-4 border border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900 border-b pb-3">Informations de votre compte</h3>
                        <div>
                            <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Nom complet</span>
                            <span class="text-base font-semibold text-gray-800">{{ user.name }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Adresse e-mail</span>
                            <span class="text-base font-semibold text-gray-800">{{ user.email }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Rôle assigné</span>
                            <span class="inline-block mt-1 px-3 py-1 text-xs font-bold rounded-full bg-gray-100 text-[#C8102E] border border-gray-300">
                                {{ user.role }}
                            </span>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>