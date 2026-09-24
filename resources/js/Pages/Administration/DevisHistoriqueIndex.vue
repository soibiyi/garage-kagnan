<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    dossiers: Array,
});
</script>

<template>
    <Head title="Historique Global des Devis" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-[#E11D48]"></i>
                        <span>Historique Global des Devis</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Retrouvez l'intégralité des services proposés, acceptés et refusés par les clients.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <div v-if="dossiers.length === 0" class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl p-12 text-center border border-gray-100">
                    <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                        <i class="fa-solid fa-folder-closed text-xl"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun historique disponible</h3>
                    <p class="text-xs text-gray-500 mt-1">Il n'y a pas encore de devis enregistré.</p>
                </div>

                <!-- Boucle sur chaque dossier / devis -->
                <div v-for="dossier in dossiers" :key="dossier.id" class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl overflow-hidden border border-gray-100 p-6 space-y-4">
                    
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-gray-100 gap-2">
                        <div>
                            <span class="text-[11px] font-mono text-[#E11D48] font-bold uppercase">Immat: {{ dossier.vehicule?.immatriculation }}</span>
                            <h3 class="text-sm font-extrabold text-gray-900 uppercase">
                                {{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }} - Client : {{ dossier.vehicule?.client?.nom || '' }} {{ dossier.vehicule?.client?.prenom || dossier.vehicule?.client?.name || 'N/A' }}
                            </h3>
                        </div>
                        <span class="px-2.5 py-1 text-[11px] font-semibold rounded-md uppercase" :class="dossier.devis?.statut === 'accepte' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'">
                            Devis : {{ dossier.devis?.statut || 'N/A' }}
                        </span>
                    </div>

                    <!-- Tableau récapitulatif des lignes du devis -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50/70 text-gray-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="px-4 py-2 font-semibold">Désignation</th>
                                    <th class="px-4 py-2 font-semibold text-center">Qté</th>
                                    <th class="px-4 py-2 font-semibold text-right">P.U. Net</th>
                                    <th class="px-4 py-2 font-semibold text-right">Total HT</th>
                                    <th class="px-4 py-2 font-semibold text-center">Choix Client</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/60">
                                <tr v-for="ligne in dossier.devis?.lignes" :key="ligne.id">
                                    <td class="px-4 py-3 text-gray-800 font-medium">{{ ligne.designation }}</td>
                                    <td class="px-4 py-3 text-center text-gray-600">{{ ligne.quantite }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ Number(ligne.pu_net).toLocaleString() }} F</td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900">{{ Number(ligne.montant_ht).toLocaleString() }} F</td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="ligne.is_accepted" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            <i class="fa-solid fa-check"></i> Accepté
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">
                                            <i class="fa-solid fa-xmark"></i> Refusé
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>