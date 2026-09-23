<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    dossiers: Array,
});
</script>

<template>
    <Head title="Facturation & Règlements" />

    <AuthenticatedLayout>
        <!-- En-tête de page -->
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Facturation & Règlements</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Gérez les dossiers en attente de facturation et suivez les encaissements clients.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl overflow-hidden border border-gray-100 p-6">
                    
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-clock text-gray-400 text-xs"></i>
                            <span>Dossiers en attente de facturation / encaissement</span>
                        </h3>
                    </div>

                    <!-- Message si aucun dossier -->
                    <div v-if="dossiers.length === 0" class="text-center py-16">
                        <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                            <i class="fa-solid fa-folder-closed text-xl"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun dossier à facturer</h3>
                        <p class="text-xs text-gray-500 mt-1">Il n'y a pas de dossier en attente pour le moment.</p>
                    </div>

                    <!-- Tableau des dossiers -->
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50/70 text-gray-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">N° Dossier / Véhicule</th>
                                    <th class="px-6 py-3 font-semibold">Client</th>
                                    <th class="px-6 py-3 font-semibold">Statut</th>
                                    <th class="px-6 py-3 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/60">
                                <tr v-for="dossier in dossiers" :key="dossier.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-gray-900 flex items-center gap-2 uppercase">
                                            <i class="fa-solid fa-car text-gray-400 text-[11px]"></i>
                                            <span>{{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }}</span>
                                        </div>
                                        <span class="font-mono text-[11px] text-[#E11D48] mt-0.5 block">
                                            Immat: {{ dossier.vehicule?.immatriculation }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">
                                        {{ dossier.vehicule?.client?.nom || '' }} {{ dossier.vehicule?.client?.prenom || dossier.vehicule?.client?.name || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 inline-flex text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200">
                                            {{ dossier.statut }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <Link 
                                            :href="route('administration.facturation.show', dossier.id)" 
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#E11D48] hover:bg-rose-700 text-white font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-[11px]"
                                        >
                                            <span>Traiter</span>
                                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                        </Link>
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