<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    vehicule: Object,
});

// Fonction utilitaire pour vérifier si une date est proche ou dépassée
const checkExpiration = (dateStr) => {
    if (!dateStr) return { text: 'Non renseigné', class: 'bg-gray-100 text-gray-800' };
    const date = new Date(dateStr);
    const today = new Date();
    if (date < today) {
        return { text: `Expiré le ${dateStr}`, class: 'bg-[#E11D48]/10 text-[#E11D48] border border-[#E11D48]/30 font-bold' };
    }
    return { text: `Valide jusqu'au ${dateStr}`, class: 'bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold' };
};

// Fonction pour calculer automatiquement le total du devis si la colonne en base renvoie 0 ou est absente
const calculerTotalDevis = (devis) => {
    if (!devis.lignes || devis.lignes.length === 0) {
        return devis.montant_total || devis.total || devis.montant || 0;
    }
    return devis.lignes.reduce((sum, ligne) => {
        const qte = ligne.quantite || 1;
        const prix = ligne.prix_unitaire || ligne.montant_ttc || 0;
        return sum + (qte * prix);
    }, 0);
};
</script>

<template>
    <Head :title="`Détail Véhicule — Garage Kagnan`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center py-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19]">
                        {{ vehicule.marque }} {{ vehicule.modele }} <span class="text-sm font-medium text-[#8A8D8F]">({{ vehicule.immatriculation || 'Sans immat' }})</span>
                    </h2>
                    <p class="text-sm text-[#8A8D8F] mt-0.5 font-medium">
                        Propriétaire : <span class="text-[#0B0F19] font-bold">{{ vehicule.client?.nom }} {{ vehicule.client?.prenom }}</span>
                    </p>
                </div>
                <Link :href="route('charge_client.clients.show', vehicule.client_id)" class="text-xs font-bold text-[#0B0F19] hover:text-[#E11D48] transition">
                    <i class="fa-solid fa-arrow-left mr-1.5"></i> Retour au client
                </Link>
            </div>
        </template>

        <div class="py-12 bg-white min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

                <!-- 1. ALERTES ADMINISTRATIVES (Assurance & SICTA) -->
                <div class="bg-[#F8FAFC] p-8 rounded-3xl border border-gray-200 space-y-6 shadow-xs">
                    <h3 class="text-lg font-black text-[#0B0F19] flex items-center gap-2.5">
                        <i class="fa-solid fa-shield-halved text-[#E11D48]"></i>
                        <span>Validité Administrative (Assurance & SICTA)</span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 flex justify-between items-center">
                            <div>
                                <h4 class="text-xs font-bold text-[#8A8D8F] uppercase tracking-wider">Assurance</h4>
                                <p class="text-sm font-black text-[#0B0F19] mt-1">Police d'assurance</p>
                            </div>
                            <span :class="['px-3 py-1 text-xs rounded-xl', checkExpiration(vehicule.expiration_assurance).class]">
                                {{ checkExpiration(vehicule.expiration_assurance).text }}
                            </span>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-gray-200 flex justify-between items-center">
                            <div>
                                <h4 class="text-xs font-bold text-[#8A8D8F] uppercase tracking-wider">Visite Technique (SICTA)</h4>
                                <p class="text-sm font-black text-[#0B0F19] mt-1">Contrôle technique</p>
                            </div>
                            <span :class="['px-3 py-1 text-xs rounded-xl', checkExpiration(vehicule.expiration_sicta).class]">
                                {{ checkExpiration(vehicule.expiration_sicta).text }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 2. HISTORIQUE DES RÉPARATIONS & DEVIS -->
                <div class="space-y-6">
                    <h3 class="text-lg font-black text-[#0B0F19] flex items-center gap-2.5">
                        <i class="fa-solid fa-clock-rotate-left text-[#E11D48]"></i>
                        <span>Historique des Interventions & Devis</span>
                    </h3>

                    <div v-if="vehicule.interventions && vehicule.interventions.length > 0" class="space-y-6">
                        <div v-for="intervention in vehicule.interventions" :key="intervention.id" class="bg-white p-8 rounded-3xl border border-gray-200 space-y-6 shadow-xs">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-gray-100">
                                <div>
                                    <span class="text-xs font-black text-[#E11D48] uppercase tracking-wider">Ordre de Travail (OT) : {{ intervention.numero_ot || ('#' + intervention.id) }}</span>
                                    <h4 class="text-base font-extrabold text-[#0B0F19] mt-0.5">
                                        Réceptionné le {{ intervention.date_reception ? new Date(intervention.date_reception).toLocaleDateString() : 'Non renseignée' }}
                                    </h4>
                                </div>
                                <span class="px-3 py-1 text-xs font-black rounded-lg bg-gray-100 text-gray-800 uppercase">
                                    Statut : {{ intervention.statut || 'En cours' }}
                                </span>
                            </div>

                            <!-- Affichage du Devis unique lié à l'intervention -->
                            <div v-if="intervention.devis" class="space-y-4">
                                <h5 class="text-xs font-extrabold text-[#8A8D8F] uppercase tracking-wider">Détail du Devis :</h5>
                                
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center bg-gray-50 px-4 py-2 rounded-xl text-xs">
                                        <span class="font-black text-[#0B0F19]">Devis #{{ intervention.devis.id }}</span>
                                        <span class="font-bold text-gray-600">Total : {{ calculerTotalDevis(intervention.devis) }} FCFA</span>
                                    </div>

                                    <div v-if="intervention.devis.lignes && intervention.devis.lignes.length > 0" class="overflow-x-auto rounded-2xl border border-gray-200">
                                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                                            <thead>
                                                <tr class="bg-[#F8FAFC] text-left font-extrabold text-[#8A8D8F] uppercase">
                                                    <th class="px-4 py-3">Désignation</th>
                                                    <th class="px-4 py-3">Qté</th>
                                                    <th class="px-4 py-3">P.U TTC</th>
                                                    <th class="px-4 py-3">Statut Ligne (Client)</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <tr v-for="ligne in intervention.devis.lignes" :key="ligne.id">
                                                    <td class="px-4 py-3 font-bold text-[#0B0F19]">{{ ligne.designation }}</td>
                                                    <td class="px-4 py-3 text-gray-600">{{ ligne.quantite }}</td>
                                                    <td class="px-4 py-3 text-gray-600">{{ ligne.prix_unitaire || ligne.montant_ttc || 0 }} FCFA</td>
                                                    <td class="px-4 py-3">
                                                        <span v-if="ligne.statut === 'refuse' || ligne.is_accepted === 0 || ligne.statut === 'refusée'" class="px-2.5 py-1 rounded-md bg-[#E11D48]/10 text-[#E11D48] font-bold">
                                                            <i class="fa-solid fa-xmark mr-1"></i> Refusé par le client
                                                        </span>
                                                        <span v-else-if="ligne.statut === 'accepte' || ligne.is_accepted === 1 || ligne.statut === 'acceptée'" class="px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 font-bold">
                                                            <i class="fa-solid fa-check mr-1"></i> Accepté
                                                        </span>
                                                        <span v-else class="px-2.5 py-1 rounded-md bg-gray-100 text-gray-600">
                                                            {{ ligne.statut || 'En attente' }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div v-else class="text-xs text-gray-400 italic px-2">Aucune ligne enregistrée pour ce devis.</div>
                                </div>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic">Aucun devis rattaché à cette intervention.</div>
                        </div>
                    </div>

                    <div v-else class="text-center py-10 text-[#8A8D8F] text-sm bg-[#F8FAFC] rounded-3xl border border-dashed border-gray-200 font-medium">
                        Aucun historique d'intervention pour ce véhicule.
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>