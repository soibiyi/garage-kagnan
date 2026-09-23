<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    dossiers: Array,
});
</script>

<template>
    <Head title="Gestion des Dossiers & Devis" />

    <AuthenticatedLayout>
        <!-- En-tête de page -->
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-folder-open text-[#E11D48]"></i>
                        <span>Liste des Dossiers à Chiffrer</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Consultez les dossiers transmis par l'atelier et établissez les devis clients correspondants.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl overflow-hidden border border-gray-100 p-6">
                    
                    <!-- Message si aucun dossier -->
                    <div v-if="dossiers.length === 0" class="text-center py-16">
                        <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                            <i class="fa-solid fa-folder-closed text-xl"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun dossier disponible</h3>
                        <p class="text-xs text-gray-500 mt-1">Il n'y a pas de dossier en attente de chiffrage pour le moment.</p>
                    </div>

                    <!-- Liste des dossiers -->
                    <div v-else class="space-y-4">
                        <div 
                            v-for="dossier in dossiers" 
                            :key="dossier.id" 
                            class="p-5 bg-gray-50/80 rounded-xl border border-gray-200/80 hover:border-[#E11D48]/50 hover:bg-gray-50 transition-all flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
                        >
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xs font-mono font-semibold px-2.5 py-0.5 bg-white text-[#E11D48] border border-[#E11D48]/20 rounded-md shadow-xs">
                                        Dossier #{{ dossier.id }}
                                    </span>
                                    <span class="text-xs text-gray-500 flex items-center gap-1">
                                        <i class="fa-regular fa-clock text-[10px]"></i>
                                        <span>Créé le {{ new Date(dossier.created_at).toLocaleDateString() }}</span>
                                    </span>
                                </div>

                                <h4 class="font-bold text-gray-900 text-sm uppercase tracking-wide flex items-center gap-2">
                                    <i class="fa-solid fa-car text-gray-400 text-xs"></i>
                                    <span>{{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }}</span>
                                    <span class="text-xs font-mono font-normal text-[#E11D48]">[{{ dossier.vehicule?.immatriculation }}]</span>
                                </h4>

                                <p class="text-xs text-gray-600 flex items-center gap-1.5">
                                    <i class="fa-solid fa-user text-[10px] text-gray-400"></i>
                                    <span>Client :</span>
                                    <span class="font-semibold text-gray-900">
                                        {{ dossier.vehicule?.client?.name || dossier.vehicule?.client?.nom || 'N/A' }}
                                    </span>
                                </p>
                            </div>

                            <!-- Action vers la page de chiffrage -->
                            <Link 
                                :href="route('administration.dossiers.show', dossier.id)" 
                                class="px-4 py-2.5 bg-[#E11D48] hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-md shadow-rose-900/10 transition flex items-center gap-2 shrink-0"
                            >
                                <span>Établir le devis</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </Link>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>