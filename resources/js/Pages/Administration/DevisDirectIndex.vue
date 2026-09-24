<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    devisDirects: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <Head title="Gestion des Devis Directs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Gestion des Devis Directs</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Consultez et créez vos devis rapides en atelier.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Bouton pour créer un nouveau devis direct -->
                    <Link 
                        :href="route('administration.devis.directs.create')" 
                        class="px-4 py-2 bg-[#E11D48] hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-rose-600/25 transition flex items-center gap-2"
                    >
                        <i class="fa-solid fa-plus"></i>
                        <span>+ Nouveau Devis Direct</span>
                    </Link>

                    <!-- Bouton de retour -->
                    <Link 
                        :href="route('dashboard')" 
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition"
                    >
                        ← Retour
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Tableau des devis directs -->
                <div class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl overflow-hidden border border-gray-100">
                    <div v-if="devisDirects.length === 0" class="p-12 text-center">
                        <div class="w-16 h-16 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-[#E11D48] mx-auto mb-4">
                            <i class="fa-solid fa-folder-open text-2xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">Aucun devis direct enregistré</h3>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                            Commencez par créer votre premier devis direct en cliquant sur le bouton en haut à droite.
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-3">Client / Véhicule</th>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3">Créé par</th>
                                    <th class="px-6 py-3">Statut</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="dossier in devisDirects" :key="dossier.id" class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-gray-900">
                                            {{ dossier.vehicule?.client?.nom || 'Client inconnu' }} {{ dossier.vehicule?.client?.prenoms || '' }}
                                        </div>
                                        <div class="text-gray-500 text-[11px]">
                                            {{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }} ({{ dossier.vehicule?.immatriculation }})
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        {{ new Date(dossier.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        {{ dossier.receptionniste?.name || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200">
                                            {{ dossier.statut }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                        <Link 
                                            :href="route('administration.facturation.show', dossier.id)" 
                                            class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition"
                                        >
                                            Voir le détail
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