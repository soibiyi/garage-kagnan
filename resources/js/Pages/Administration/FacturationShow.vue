<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    dossier: Object,
});

// Déclaration explicite et obligatoire de la fonction formatDate
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
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Détail Devis / Facturation - Dossier #{{ dossier.id }}
                </h2>
                <div class="flex items-center space-x-4">
                    <button @click="imprimer" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition flex items-center space-x-2">
                        <span>🖨️ Imprimer le Devis</span>
                    </button>
                    <Link :href="route('administration.facturation.index')" class="text-sm text-gray-600 hover:text-gray-900">
                        ← Retour à la liste
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Si aucun devis n'est lié au dossier -->
                <div v-if="!dossier.devis" class="bg-white shadow-sm sm:rounded-lg p-8 border border-gray-200 text-center text-gray-500">
                    Aucun devis n'a encore été généré pour ce dossier.
                </div>

                <!-- Feuille de Devis format A4 -->
                <div v-else class="bg-white shadow-md sm:rounded-lg p-8 border border-gray-300 print:shadow-none print:border-none print:p-0 text-gray-800">
                    
                    <!-- En-tête -->
                    <div class="flex justify-between items-start border-b-2 border-gray-800 pb-4 mb-6">
                        <div>
                            <h1 class="text-2xl font-black tracking-wider text-red-700 italic">GARAGE KAGNAN</h1>
                            <p class="text-xs text-gray-500 mt-1">Service Entretien & Réparation Automobile</p>
                        </div>
                        <div class="border-2 border-gray-800 p-3 text-right rounded min-w-[240px]">
                            <p class="text-sm font-bold uppercase bg-gray-200 px-2 py-0.5 mb-1 text-center">Devis</p>
                            <p class="text-xs"><span class="font-semibold">N° :</span> D/1/ADMI/{{ dossier.devis.id }}/{{ new Date(dossier.devis.created_at).getFullYear() }}</p>
                            <p class="text-xs"><span class="font-semibold">Date :</span> {{ formatDate(dossier.devis.created_at) }}</p>
                            <p class="text-xs"><span class="font-semibold">Statut :</span> <span class="capitalize font-medium">{{ dossier.devis.statut?.replace('_', ' ') }}</span></p>
                        </div>
                    </div>

                    <!-- Infos Client & Véhicule -->
                    <div class="grid grid-cols-2 gap-4 border border-gray-800 text-xs mb-6">
                        <div class="p-3 border-r border-gray-800 space-y-1">
                            <p class="font-bold underline uppercase bg-gray-100 p-1 mb-1">Clients</p>
                            <p><span class="font-semibold">Nom client :</span> {{ dossier.vehicule?.client?.nom }} {{ dossier.vehicule?.client?.prenom }}</p>
                            <p><span class="font-semibold">Adresse :</span> {{ dossier.vehicule?.client?.adresse || 'N/A' }}</p>
                            <p><span class="font-semibold">Téléphone :</span> {{ dossier.vehicule?.client?.telephone || 'N/A' }}</p>
                            <p><span class="font-semibold">Code Equipe :</span> {{ dossier.mecanicien_id || 'N/A' }}</p>
                        </div>
                        <div class="p-3 space-y-1">
                            <p class="font-bold underline uppercase bg-gray-100 p-1 mb-1">Divers</p>
                            <p><span class="font-semibold">N° OT :</span> {{ dossier.id }}</p>
                            <p><span class="font-semibold">Immatriculation :</span> {{ dossier.vehicule?.immatriculation }}</p>
                            <p><span class="font-semibold">Marque :</span> {{ dossier.vehicule?.marque }}</p>
                            <p><span class="font-semibold">Type :</span> {{ dossier.vehicule?.modele }}</p>
                            <p><span class="font-semibold">Kilométrages :</span> {{ dossier.kilometrage || 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="border-x border-b border-gray-800 px-3 py-1.5 text-xs mb-6 -mt-6">
                        <span class="font-semibold">N° Chassis :</span> {{ dossier.vehicule?.chassis || 'N/A' }}
                    </div>

                    <!-- Tableau des lignes -->
                    <div class="mb-6">
                        <p class="text-xs font-bold uppercase bg-gray-800 text-white px-2 py-1">Incident / Prestations & Pièces</p>
                        <table class="min-w-full border-collapse border border-gray-800 text-xs">
                            <thead>
                                <tr class="bg-gray-100 border-b border-gray-800 text-center">
                                    <th class="border-r border-gray-800 p-1.5 w-16">Quantité</th>
                                    <th class="border-r border-gray-800 p-1.5 text-left">Désignation / Réf</th>
                                    <th class="border-r border-gray-800 p-1.5 w-20">PU Net</th>
                                    <th class="border-r border-gray-800 p-1.5 w-16">Remise</th>
                                    <th class="border-r border-gray-800 p-1.5 w-20">TVA</th>
                                    <th class="p-1.5 w-24">Montant HT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ligne in dossier.devis.lignes" :key="ligne.id" class="border-b border-gray-300 text-center">
                                    <td class="border-r border-gray-800 p-1.5">{{ ligne.quantite }}</td>
                                    <td class="border-r border-gray-800 p-1.5 text-left">
                                        {{ ligne.designation }}
                                        <span v-if="ligne.reference_piece" class="block text-[10px] text-gray-500">Réf : {{ ligne.reference_piece }}</span>
                                    </td>
                                    <td class="border-r border-gray-800 p-1.5 text-right">{{ Number(ligne.pu_net).toLocaleString() }} F</td>
                                    <td class="border-r border-gray-800 p-1.5 text-right">{{ Number(ligne.remise || 0).toLocaleString() }} F</td>
                                    <td class="border-r border-gray-800 p-1.5 text-center">
                                        <span v-if="ligne.ne_pas_appliquer_tva" class="text-orange-600 font-semibold">Exonéré</span>
                                        <span v-else>18%</span>
                                    </td>
                                    <td class="p-1.5 text-right font-medium">{{ Number(ligne.montant_ht).toLocaleString() }} F</td>
                                </tr>
                                <tr v-if="!dossier.devis.lignes || dossier.devis.lignes.length === 0">
                                    <td colspan="6" class="text-center py-4 text-gray-500">Aucune ligne enregistrée dans ce devis.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totaux -->
                    <div class="flex justify-end mb-6" v-if="dossier.devis.lignes && dossier.devis.lignes.length > 0">
                        <div class="w-80 border border-gray-800 text-xs">
                            <div class="flex justify-between border-b border-gray-800 px-2 py-1 bg-gray-50">
                                <span class="font-semibold">Total HT global</span>
                                <span>{{ dossier.devis.lignes.reduce((acc, l) => acc + Number(l.montant_ht), 0).toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-800 px-2 py-1">
                                <span class="font-semibold">Total Remises</span>
                                <span>{{ dossier.devis.lignes.reduce((acc, l) => acc + Number(l.remise || 0), 0).toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-800 px-2 py-1">
                                <span>TVA Totale</span>
                                <span>{{ dossier.devis.lignes.reduce((acc, l) => acc + (Number(l.montant_ttc) - Number(l.montant_ht)), 0).toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between px-2 py-1.5 font-black bg-gray-200 text-sm">
                                <span>Total TTC</span>
                                <span>{{ dossier.devis.lignes.reduce((acc, l) => acc + Number(l.montant_ttc), 0).toLocaleString() }} F</span>
                            </div>
                        </div>
                    </div>

                    <!-- Conditions -->
                    <div class="border border-gray-800 p-3 text-xs mb-6">
                        <p class="font-bold underline text-red-600 mb-1">NB :</p>
                        <p class="font-semibold text-gray-700">PAYER UNE AVANCE DE 70% AVANT TRAVAUX</p>
                    </div>

                    <div class="grid grid-cols-2 gap-8 text-xs text-center font-bold pt-4">
                        <div>
                            <p class="mb-12">CLIENT</p>
                            <div class="border-b border-gray-400 w-48 mx-auto"></div>
                        </div>
                        <div>
                            <p class="mb-12">PRESTATAIRE</p>
                            <div class="border-b border-gray-400 w-48 mx-auto"></div>
                        </div>
                    </div>

                    <div class="mt-12 pt-4 border-t border-gray-400 text-[10px] text-center text-gray-600 space-y-0.5">
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