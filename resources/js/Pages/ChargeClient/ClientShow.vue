<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    client: Object,
});

// Fonction pour vérifier l'expiration et la règle des 10 jours
const checkExpirationDetail = (dateStr) => {
    if (!dateStr) return { text: 'Non renseigné', class: 'bg-gray-100 text-gray-800' };
    const date = new Date(dateStr);
    const today = new Date();
    
    const tenDaysLimit = new Date();
    tenDaysLimit.setDate(today.getDate() + 10);

    if (date < today) {
        return { text: `Expiré le ${dateStr}`, class: 'bg-[#E11D48]/10 text-[#E11D48] border border-[#E11D48]/30 font-bold' };
    } else if (date <= tenDaysLimit) {
        return { text: `Expire bientôt (${dateStr})`, class: 'bg-amber-50 text-amber-700 border border-amber-200 font-bold' };
    }
    return { text: `Valide jusqu'au ${dateStr}`, class: 'bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold' };
};
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

                        <!-- Statut Assurance & SICTA par véhicule -->
                        <div class="space-y-2 pt-2 border-t border-gray-200/60 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-semibold">Assurance :</span>
                                <span :class="['px-2.5 py-1 rounded-lg', checkExpirationDetail(vehicule.expiration_assurance).class]">
                                    {{ checkExpirationDetail(vehicule.expiration_assurance).text }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-semibold">Visite Technique (SICTA) :</span>
                                <span :class="['px-2.5 py-1 rounded-lg', checkExpirationDetail(vehicule.expiration_sicta).class]">
                                    {{ checkExpirationDetail(vehicule.expiration_sicta).text }}
                                </span>
                            </div>
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