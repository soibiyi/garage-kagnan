<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    dossier: Object,
});

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const imprimer = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Devis - Dossier #${dossier.id}`" />

    <AuthenticatedLayout>
        <!-- En-tête de page (masqué à l'impression) -->
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice text-[#E11D48]"></i>
                        <span>Détail Devis / Facturation - Dossier #{{ dossier.id }}</span>
                    </h2>
                </div>
                <div class="flex items-center space-x-3">
                    <button 
                        @click="imprimer" 
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#E11D48] hover:bg-rose-700 text-white font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-xs"
                    >
                        <i class="fa-solid fa-print text-[11px]"></i>
                        <span>Imprimer le Devis</span>
                    </button>
                    <Link 
                        :href="route('administration.facturation.index')" 
                        class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-gray-900 font-medium"
                    >
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        <span>Retour à la liste</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Si aucun devis n'est lié au dossier -->
                <div v-if="!dossier.devis" class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl p-12 border border-gray-100 text-center">
                    <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun devis disponible</h3>
                    <p class="text-xs text-gray-500 mt-1">Aucun devis n'a encore été généré pour ce dossier.</p>
                </div>

                <!-- Feuille de Devis format A4 -->
                <div v-else class="bg-white shadow-2xl shadow-gray-200/50 sm:rounded-2xl p-8 border border-gray-100 print:shadow-none print:border-none print:p-0 text-gray-800">
                    
                    <!-- En-tête du Devis -->
                    <div class="flex justify-between items-start border-b-2 border-gray-900 pb-6 mb-6">
                        <div>
                            <h1 class="text-2xl font-black tracking-wider text-[#E11D48] italic flex items-center gap-2">
                                <i class="fa-solid fa-car-burst text-lg"></i>
                                <span>GARAGE KAGNAN</span>
                            </h1>
                            <p class="text-xs font-medium text-gray-500 mt-1">Service Entretien & Réparation Automobile</p>
                        </div>
                        <div class="border-2 border-gray-900 p-3 text-right rounded-xl min-w-[240px] bg-gray-50/50">
                            <p class="text-xs font-bold uppercase bg-gray-200/80 px-2 py-1 mb-2 text-center rounded text-gray-900 tracking-wider">Devis</p>
                            <p class="text-xs text-gray-700 mb-1"><span class="font-semibold text-gray-900">N° :</span> D/1/ADMI/{{ dossier.devis.id }}/{{ new Date(dossier.devis.created_at).getFullYear() }}</p>
                            <p class="text-xs text-gray-700 mb-1"><span class="font-semibold text-gray-900">Date :</span> {{ formatDate(dossier.devis.created_at) }}</p>
                            <p class="text-xs text-gray-700"><span class="font-semibold text-gray-900">Statut :</span> <span class="capitalize font-semibold text-[#E11D48]">{{ dossier.devis.statut?.replace('_', ' ') }}</span></p>
                        </div>
                    </div>

                    <!-- Infos Client & Véhicule -->
                    <div class="grid grid-cols-2 gap-4 border border-gray-900 text-xs mb-6 rounded-lg overflow-hidden">
                        <div class="p-3.5 border-r border-gray-900 space-y-1.5">
                            <p class="font-bold underline uppercase bg-gray-100 p-1.5 mb-2 text-gray-900 tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-user text-gray-500 text-[11px]"></i>
                                <span>Clients</span>
                            </p>
                            <p><span class="font-semibold">Nom client :</span> {{ dossier.vehicule?.client?.nom }} {{ dossier.vehicule?.client?.prenom }}</p>
                            <p><span class="font-semibold">Adresse :</span> {{ dossier.vehicule?.client?.adresse || 'N/A' }}</p>
                            <p><span class="font-semibold">Téléphone :</span> {{ dossier.vehicule?.client?.telephone || 'N/A' }}</p>
                            <p><span class="font-semibold">Code Equipe :</span> {{ dossier.mecanicien_id || 'N/A' }}</p>
                        </div>
                        <div class="p-3.5 space-y-1.5">
                            <p class="font-bold underline uppercase bg-gray-100 p-1.5 mb-2 text-gray-900 tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-info text-gray-500 text-[11px]"></i>
                                <span>Divers</span>
                            </p>
                            <p><span class="font-semibold">N° OT :</span> {{ dossier.id }}</p>
                            <p><span class="font-semibold">Immatriculation :</span> {{ dossier.vehicule?.immatriculation }}</p>
                            <p><span class="font-semibold">Marque :</span> {{ dossier.vehicule?.marque }}</p>
                            <p><span class="font-semibold">Type :</span> {{ dossier.vehicule?.modele }}</p>
                            <p><span class="font-semibold">Kilométrages :</span> {{ dossier.kilometrage || 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="border-x border-b border-gray-900 px-3.5 py-2 text-xs mb-6 -mt-6 rounded-b-lg bg-gray-50/30">
                        <span class="font-semibold">N° Chassis :</span> <span class="font-mono">{{ dossier.vehicule?.chassis || 'N/A' }}</span>
                    </div>

                    <!-- Tableau des lignes -->
                    <div class="mb-6">
                        <p class="text-xs font-bold uppercase bg-gray-900 text-white px-3 py-1.5 rounded-t-lg tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-gray-400 text-[11px]"></i>
                            <span>Incident / Prestations & Pièces</span>
                        </p>
                        <table class="min-w-full border-collapse border border-gray-900 text-xs">
                            <thead>
                                <tr class="bg-gray-100 border-b border-gray-900 text-center font-semibold">
                                    <th class="border-r border-gray-900 p-2 w-16">Quantité</th>
                                    <th class="border-r border-gray-900 p-2 text-left">Désignation / Réf</th>
                                    <th class="border-r border-gray-900 p-2 w-20">PU Net</th>
                                    <th class="border-r border-gray-900 p-2 w-16">Remise</th>
                                    <th class="border-r border-gray-900 p-2 w-20">TVA</th>
                                    <th class="p-2 w-24">Montant HT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ligne in dossier.devis.lignes" :key="ligne.id" class="border-b border-gray-300 text-center hover:bg-gray-50/50">
                                    <td class="border-r border-gray-900 p-2 font-medium">{{ ligne.quantite }}</td>
                                    <td class="border-r border-gray-900 p-2 text-left">
                                        <span class="font-medium text-gray-900">{{ ligne.designation }}</span>
                                        <span v-if="ligne.reference_piece" class="block text-[10px] text-gray-500 font-mono mt-0.5">Réf : {{ ligne.reference_piece }}</span>
                                    </td>
                                    <td class="border-r border-gray-900 p-2 text-right">{{ Number(ligne.pu_net).toLocaleString() }} F</td>
                                    <td class="border-r border-gray-900 p-2 text-right">{{ Number(ligne.remise || 0).toLocaleString() }} F</td>
                                    <td class="border-r border-gray-900 p-2 text-center">
                                        <span v-if="ligne.ne_pas_appliquer_tva" class="text-amber-600 font-semibold bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 text-[10px]">Exonéré</span>
                                        <span v-else class="text-gray-600">18%</span>
                                    </td>
                                    <td class="p-2 text-right font-bold text-gray-900">{{ Number(ligne.montant_ht).toLocaleString() }} F</td>
                                </tr>
                                <tr v-if="!dossier.devis.lignes || dossier.devis.lignes.length === 0">
                                    <td colspan="6" class="text-center py-8 text-gray-500">Aucune ligne enregistrée dans ce devis.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totaux -->
                    <div class="flex justify-end mb-6" v-if="dossier.devis.lignes && dossier.devis.lignes.length > 0">
                        <div class="w-80 border border-gray-900 text-xs rounded-lg overflow-hidden shadow-sm">
                            <div class="flex justify-between border-b border-gray-900 px-3 py-1.5 bg-gray-50">
                                <span class="font-semibold text-gray-700">Total HT global</span>
                                <span class="font-medium">{{ dossier.devis.lignes.reduce((acc, l) => acc + Number(l.montant_ht), 0).toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-900 px-3 py-1.5 bg-white">
                                <span class="font-semibold text-gray-700">Total Remises</span>
                                <span class="font-medium">{{ dossier.devis.lignes.reduce((acc, l) => acc + Number(l.remise || 0), 0).toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-900 px-3 py-1.5 bg-white">
                                <span class="font-semibold text-gray-700">TVA Totale</span>
                                <span class="font-medium">{{ dossier.devis.lignes.reduce((acc, l) => acc + (Number(l.montant_ttc) - Number(l.montant_ht)), 0).toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between px-3 py-2 font-black bg-gray-200 text-sm text-gray-900">
                                <span>Total TTC</span>
                                <span class="text-[#E11D48]">{{ dossier.devis.lignes.reduce((acc, l) => acc + Number(l.montant_ttc), 0).toLocaleString() }} F</span>
                            </div>
                        </div>
                    </div>

                    <!-- Conditions -->
                    <div class="border border-gray-900 p-3 text-xs mb-8 rounded-lg bg-rose-50/30 border-rose-200">
                        <p class="font-bold underline text-[#E11D48] mb-1 uppercase tracking-wider">NB :</p>
                        <p class="font-bold text-gray-900 tracking-wide">PAYER UNE AVANCE DE 70% AVANT TRAVAUX</p>
                    </div>

                    <!-- Signatures -->
                    <div class="grid grid-cols-2 gap-8 text-xs text-center font-bold pt-4 mb-10">
                        <div>
                            <p class="mb-14 text-gray-700 uppercase tracking-wider">CLIENT</p>
                            <div class="border-b border-gray-400 w-48 mx-auto"></div>
                        </div>
                        <div>
                            <p class="mb-14 text-gray-700 uppercase tracking-wider">PRESTATAIRE</p>
                            <div class="border-b border-gray-400 w-48 mx-auto"></div>
                        </div>
                    </div>

                    <!-- Pied de page légal -->
                    <div class="mt-8 pt-4 border-t border-gray-300 text-[10px] text-center text-gray-500 space-y-0.5">
                        <p>Siège : Yopougon Zone Industrielle & Terminus 27 NCC : 1113876 J - RCCM : CI- ABJ-2012-B-5126</p>
                        <p>Régime d'imposition : Taxe d'Etat de l'Entreprenant (TEE)</p>
                        <p>Tel : 25 23 01 90 86 / 07 08 38 83 25/ 05 44 10 00 78 E-mail : garagekagnan@gmail.com</p>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    body {
        background: white !important;
    }
    .print\:hidden {
        display: none !important;
    }
}
</style>