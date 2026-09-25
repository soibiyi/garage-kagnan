<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    clients: Array,
});
</script>

<template>
    <Head title="Liste des Clients — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center py-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19]">
                        Annuaire des Clients
                    </h2>
                    <p class="text-sm text-[#8A8D8F] mt-0.5 font-medium">
                        Sélectionnez un client pour voir ses véhicules et l'historique.
                    </p>
                </div>
                <Link :href="route('dashboard')" class="text-xs font-bold text-[#0B0F19] hover:text-[#E11D48] transition">
                    <i class="fa-solid fa-arrow-left mr-1.5"></i> Retour au tableau de bord
                </Link>
            </div>
        </template>

        <div class="py-12 bg-white min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <div v-if="clients && clients.length > 0" class="overflow-x-auto rounded-3xl border border-gray-200 shadow-xs bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-[#F8FAFC] text-left text-xs font-extrabold text-[#8A8D8F] uppercase tracking-wider">
                                <th class="px-6 py-4">Client</th>
                                <th class="px-6 py-4">Téléphone</th>
                                <th class="px-6 py-4">Véhicules enregistrés</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-for="client in clients" :key="client.id" class="hover:bg-gray-50/60 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-extrabold text-[#0B0F19]">{{ client.nom }} {{ client.prenom }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">
                                    {{ client.telephone || 'Non renseigné' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold text-[#0B0F19]">
                                    <span class="px-2.5 py-1 rounded-lg bg-gray-100 text-gray-800 text-xs">
                                        {{ client.vehicules?.length || 0 }} véhicule(s)
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <Link :href="route('charge_client.clients.show', client.id)" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0B0F19] text-white text-xs font-extrabold hover:bg-[#E11D48] transition shadow-xs">
                                        <span>Détails & Véhicules</span>
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-12 text-[#8A8D8F] text-sm bg-[#F8FAFC] rounded-3xl border border-dashed border-gray-200 font-medium">
                    Aucun client trouvé dans la base de données.
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>