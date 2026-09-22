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
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900">
                        Liste des Dossiers à Chiffrer
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Consultez les dossiers transmis et établissez les devis clients.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-2xl overflow-hidden border border-gray-200 p-6">
                    
                    <!-- Message si aucun dossier -->
                    <div v-if="dossiers.length === 0" class="text-center py-12">
                        <div class="text-4xl mb-3">📂</div>
                        <h3 class="text-base font-bold text-gray-900">Aucun dossier disponible</h3>
                        <p class="text-xs text-gray-500 mt-1">Il n'y a pas de dossier en attente pour le moment.</p>
                    </div>

                    <!-- Liste des dossiers -->
                    <div v-else class="space-y-4">
                        <div v-for="dossier in dossiers" :key="dossier.id" class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 transition flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-bold px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full">
                                        Dossier #{{ dossier.id }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        Créé le {{ new Date(dossier.created_at).toLocaleDateString() }}
                                    </span>
                                </div>
                                <h4 class="font-bold text-gray-900 text-base uppercase">
                                    {{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }} 
                                    <span class="text-xs font-semibold text-gray-500 normal-case">({{ dossier.vehicule?.immatriculation }})</span>
                                </h4>
                                <p class="text-xs text-gray-600 mt-1">
                                    Client : <span class="font-semibold text-gray-900">{{ dossier.vehicule?.client?.name || dossier.vehicule?.client?.nom || 'N/A' }}</span>
                                </p>
                            </div>

                            <!-- Lien vers la page de détail/chiffrage qu'on a faite -->
                            <Link :href="route('administration.dossiers.show', dossier.id)" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                                Faire un devis <span>→</span>
                            </Link>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>