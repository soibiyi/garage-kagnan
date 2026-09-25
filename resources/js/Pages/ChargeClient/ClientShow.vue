<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    client: Object,
});
</script>

<template>
    <Head :title="`Client : ${client.nom} — Garage Kagnan`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center py-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19]">
                        Fiche Client : <span class="text-[#E11D48]">{{ client.nom }} {{ client.prenom }}</span>
                    </h2>
                    <p class="text-sm text-[#8A8D8F] mt-0.5 font-medium">
                        Téléphone : <span class="text-[#0B0F19] font-bold">{{ client.telephone || 'N/A' }}</span>
                    </p>
                </div>
                <Link :href="route('charge_client.clients.index')" class="text-xs font-bold text-[#0B0F19] hover:text-[#E11D48] transition">
                    <i class="fa-solid fa-arrow-left mr-1.5"></i> Retour à la liste
                </Link>
            </div>
        </template>

        <div class="py-12 bg-white min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                
                <h3 class="text-lg font-black text-[#0B0F19] flex items-center gap-2.5">
                    <i class="fa-solid fa-car text-[#E11D48]"></i>
                    <span>Véhicules du client</span>
                </h3>

                <div v-if="client.vehicules && client.vehicules.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="vehicule in client.vehicules" :key="vehicule.id" class="p-6 bg-[#F8FAFC] rounded-3xl border border-gray-200 hover:border-[#E11D48] transition space-y-4 shadow-xs">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="text-base font-black text-[#0B0F19]">{{ vehicule.marque }} {{ vehicule.modele }}</h4>
                                <p class="text-xs text-[#8A8D8F] font-bold uppercase tracking-wider mt-0.5">Immat : {{ vehicule.immatriculation || 'Non immatriculé' }}</p>
                            </div>
                            <Link :href="route('charge_client.vehicules.show', vehicule.id)" class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#0B0F19] hover:bg-[#E11D48] hover:text-white hover:border-[#E11D48] transition shadow-xs">
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-10 text-[#8A8D8F] text-sm bg-[#F8FAFC] rounded-3xl border border-dashed border-gray-200 font-medium">
                    Ce client n'a aucun véhicule enregistré.
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>