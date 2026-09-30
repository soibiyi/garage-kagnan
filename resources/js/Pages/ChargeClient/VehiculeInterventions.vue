<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VehiculeEntete from '@/Components/VehiculeEntete.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FAMILLES, FAMILLE_PAR_DEFAUT } from '@/constants/familles.js';

const props = defineProps({
    vehicule: Object,
});

/* ------------------------------------------------------------------ */
/* Utilitaires de formatage                                            */
/* ------------------------------------------------------------------ */
const formatDateLongue = (date) =>
    date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const formatDateCourte = (valeur) => {
    if (!valeur) return 'date non renseignée';
    const date = new Date(valeur);
    return !isNaN(date) ? formatDateLongue(date) : 'date non renseignée';
};

const nombre = (n) => Number(n || 0);
const fmt = (n) => Math.round(nombre(n)).toLocaleString('fr-FR');

/* ------------------------------------------------------------------ */
/* Historique des interventions                                       */
/* ------------------------------------------------------------------ */
const interventions = computed(() =>
    [...(props.vehicule.interventions || [])].sort((a, b) => {
        const da = new Date(a.date_reception || 0);
        const db = new Date(b.date_reception || 0);
        return db - da || b.id - a.id;
    })
);

const ouverts = ref(interventions.value.length ? [interventions.value[0].id] : []);

const estOuvert = (id) => ouverts.value.includes(id);
const basculer = (id) => {
    ouverts.value = estOuvert(id) ? ouverts.value.filter((x) => x !== id) : [...ouverts.value, id];
};

const LIBELLES_STATUT = {
    atelier: 'En atelier',
    attente_accord: "En attente d'accord client",
    accepte: 'Devis accepté',
};

const libelleStatut = (statut) => {
    if (!statut) return 'En cours';
    const texte = LIBELLES_STATUT[statut] || statut.replace(/_/g, ' ');
    return texte.charAt(0).toUpperCase() + texte.slice(1);
};

const devisValide = (devis) => Boolean(devis && devis.statut && devis.statut !== 'en_attente');
const estAcceptee = (ligne) => ligne.is_accepted === true || Number(ligne.is_accepted) === 1;

const statutLigne = (devis, ligne) => {
    if (!devisValide(devis)) return 'attente';
    return estAcceptee(ligne) ? 'accepte' : 'refuse';
};

/* ------------------------------------------------------------------ */
/* Calculs financiers par intervention                                */
/* ------------------------------------------------------------------ */
const grouperLignesParFamille = (lignes) => {
    if (!lignes || lignes.length === 0) return [];

    const groupes = {};
    lignes.forEach(ligne => {
        const familleNom = ligne.famille || FAMILLE_PAR_DEFAUT;
        if (!groupes[familleNom]) {
            groupes[familleNom] = [];
        }
        groupes[familleNom].push(ligne);
    });

    const ordreFamilles = [...Object.keys(FAMILLES), FAMILLE_PAR_DEFAUT];
    const resultat = [];

    const ajouterGroupe = (familleNom) => {
        if (groupes[familleNom] && groupes[familleNom].length > 0) {
            const sousTotalHt = groupes[familleNom].reduce((acc, l) => acc + nombre(l.montant_ht), 0);
            resultat.push({
                famille: familleNom,
                lignes: groupes[familleNom],
                sousTotalHt: sousTotalHt
            });
        }
    };

    ordreFamilles.forEach(ajouterGroupe);

    Object.keys(groupes).forEach(familleNom => {
        if (!ordreFamilles.includes(familleNom)) {
            ajouterGroupe(familleNom);
        }
    });

    return resultat;
};

// Calculs basés sur les lignes acceptées (ou toutes si devis non encore validé)
const calculerTotaux = (devis) => {
    if (!devis || !devis.lignes) return { ht: 0, remises: 0, tva: 0, ttcBrut: 0, petiteFourniture: 0, ttcFinal: 0 };
    
    // Si devis validé, on ne compte que les lignes acceptées pour le total à payer
    const lignesCibles = devisValide(devis) 
        ? devis.lignes.filter(estAcceptee)
        : devis.lignes;

    const ht = lignesCibles.reduce((acc, l) => acc + nombre(l.montant_ht), 0);
    const remises = lignesCibles.reduce((acc, l) => acc + nombre(l.remise), 0);
    const ttcBrut = lignesCibles.reduce((acc, l) => acc + nombre(l.montant_ttc), 0);
    const tva = ttcBrut - ht;
    const petiteFourniture = Math.round(ttcBrut * 0.03);
    const ttcFinal = ttcBrut + petiteFourniture;

    return { ht, remises, tva, ttcBrut, petiteFourniture, ttcFinal };
};

/* --- Suivi des paiements --- */
const extrairePaiements = (intervention) => intervention?.facture?.paiements || [];

const totalPaye = (intervention) => {
    const paiements = extrairePaiements(intervention);
    return paiements.reduce((somme, p) => somme + nombre(p.montant), 0);
};

const soldeRestant = (intervention) => {
    const totaux = calculerTotaux(intervention.devis);
    return Math.max(0, totaux.ttcFinal - totalPaye(intervention));
};

const pourcentagePaiement = (intervention) => {
    const totaux = calculerTotaux(intervention.devis);
    if (totaux.ttcFinal <= 0) return 0;
    return Math.min(100, Math.round((totalPaye(intervention) * 100) / totaux.ttcFinal));
};

const imprimerPage = () => window.print();
</script>

<template>
    <Head title="Interventions — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <VehiculeEntete
                :vehicule="vehicule"
                :href="route('charge_client.vehicules.show', vehicule.id)"
                libelle-retour="Retour au véhicule"
                section="Interventions"
            />
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12 print:py-0 print:bg-white">
            <div class="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8 print:max-w-none print:px-0">

                <section class="space-y-4" aria-labelledby="titre-historique">
                    <div class="flex justify-between items-center print:hidden">
                        <h3 id="titre-historique" class="flex items-center gap-2.5 text-lg font-black text-[#0B0F19]">
                            <i class="fa-solid fa-clock-rotate-left text-[#E11D48]"></i>
                            <span>Historique des interventions</span>
                            <span
                                v-if="interventions.length"
                                class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-black tabular-nums text-[#0B0F19]"
                            >
                                {{ interventions.length }}
                            </span>
                        </h3>

                        <button 
                            @click="imprimerPage" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gray-800 hover:bg-gray-900 text-white font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-xs"
                        >
                            <i class="fa-solid fa-print text-[11px]"></i>
                            <span>Imprimer</span>
                        </button>
                    </div>

                    <div v-if="interventions.length" class="space-y-6">
                        <article
                            v-for="(intervention, index) in interventions"
                            :key="intervention.id"
                            class="row-in overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm print:border-none print:shadow-none"
                            :style="{ animationDelay: Math.min(index, 6) * 60 + 'ms' }"
                        >
                            <!-- En-tête cliquable (masqué à l'impression) -->
                            <button
                                type="button"
                                :aria-expanded="estOuvert(intervention.id)"
                                :aria-controls="`intervention-${intervention.id}`"
                                @click="basculer(intervention.id)"
                                class="flex w-full items-center gap-4 px-4 py-4 text-left transition-colors duration-200 hover:bg-[#F8FAFC] focus-visible:bg-[#F8FAFC] focus-visible:outline-none sm:px-6 print:hidden"
                            >
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#0B0F19] text-white">
                                    <i class="fa-solid fa-screwdriver-wrench text-sm"></i>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-extrabold text-[#0B0F19]">
                                        Ordre de travail {{ intervention.numero_ot || '#' + intervention.id }}
                                    </p>
                                    <p class="mt-0.5 text-xs font-medium text-[#8A8D8F]">
                                        Réceptionné le {{ formatDateCourte(intervention.date_reception) }}
                                    </p>
                                </div>

                                <span
                                    class="hidden rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset sm:inline-flex"
                                    :class="intervention.statut === 'accepte' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-gray-100 text-gray-700 ring-gray-200'"
                                >
                                    {{ libelleStatut(intervention.statut) }}
                                </span>

                                <div v-if="intervention.devis" class="hidden text-right md:block">
                                    <p class="text-sm font-black tabular-nums text-[#0B0F19]">
                                        {{ fmt(calculerTotaux(intervention.devis).ttcFinal) }} FCFA
                                    </p>
                                    <p class="text-[11px] font-medium text-[#8A8D8F]">
                                        {{ devisValide(intervention.devis) ? 'Total accepté' : 'Total proposé' }}
                                    </p>
                                </div>

                                <i
                                    class="fa-solid fa-chevron-down text-xs text-[#8A8D8F] transition-transform duration-300"
                                    :class="{ 'rotate-180': estOuvert(intervention.id) }"
                                ></i>
                            </button>

                            <!-- Contenu repliable (Toujours affiché à l'impression) -->
                            <div
                                :id="`intervention-${intervention.id}`"
                                class="grid transition-[grid-template-rows] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] print:block"
                                :class="estOuvert(intervention.id) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            >
                                <div class="min-h-0 overflow-hidden print:overflow-visible">
                                    <div class="border-t border-gray-100 p-4 sm:p-6 print:border-none print:p-0">

                                        <!-- FICHE FACTURE / DEVIS TYPE SHOW.VUE -->
                                        <div v-if="intervention.devis" class="invoice-sheet bg-white sm:rounded-2xl p-4 sm:p-6 border border-gray-200 text-gray-800 shadow-xs print:shadow-none print:border-none print:p-0">
                                            
                                            <!-- En-tête Facture -->
                                            <div class="flex justify-between items-start border-b-2 border-gray-900 pb-3 mb-3">
                                                <div>
                                                    <img src="/images/logo-kagnan.png" alt="Garage Kagnan" class="h-14 w-auto object-contain" />
                                                    <p class="text-[11px] font-medium text-gray-500 mt-0.5">Service Entretien & Réparation Automobile</p>
                                                </div>
                                                <div class="border-2 border-gray-900 p-2 text-right rounded-xl min-w-[200px] bg-gray-50/50">
                                                    <p class="text-[11px] font-bold uppercase px-2 py-0.5 mb-1 text-center rounded text-gray-900 tracking-wider"
                                                       :class="devisValide(intervention.devis) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                                        {{ devisValide(intervention.devis) ? 'Facture / Devis Validé' : 'Devis Proposé' }}
                                                    </p>
                                                    <p class="text-[11px] text-gray-700 mb-0.5"><span class="font-semibold text-gray-900">N° :</span> D/1/ADMI/{{ intervention.devis.id }}/{{ new Date(intervention.devis.created_at).getFullYear() }}</p>
                                                    <p class="text-[11px] text-gray-700 mb-0.5"><span class="font-semibold text-gray-900">Date :</span> {{ formatDateCourte(intervention.devis.created_at) }}</p>
                                                    <p class="text-[11px] text-gray-700"><span class="font-semibold text-gray-900">Statut :</span> <span class="capitalize font-semibold text-[#E11D48]">{{ intervention.devis.statut?.replace('_', ' ') }}</span></p>
                                                </div>
                                            </div>

                                            <!-- Infos Client & Véhicule -->
                                            <div class="grid grid-cols-2 gap-4 border border-gray-900 text-xs mb-3 rounded-lg overflow-hidden">
                                                <div class="p-2.5 border-r border-gray-900 space-y-1">
                                                    <p class="font-bold underline uppercase bg-gray-100 p-1 mb-1 text-gray-900 tracking-wider flex items-center gap-1.5">
                                                        <i class="fa-solid fa-user text-gray-500 text-[11px]"></i>
                                                        <span>Client</span>
                                                    </p>
                                                    <p><span class="font-semibold">Nom client :</span> {{ vehicule.client?.nom }} {{ vehicule.client?.prenom || vehicule.client?.name }}</p>
                                                    <p><span class="font-semibold">Adresse :</span> {{ vehicule.client?.adresse || 'N/A' }}</p>
                                                    <p><span class="font-semibold">Téléphone :</span> {{ vehicule.client?.telephone || 'N/A' }}</p>
                                                    <p><span class="font-semibold">Code Equipe :</span> {{ intervention.mecanicien_id || 'N/A' }}</p>
                                                </div>
                                                <div class="p-2.5 space-y-1">
                                                    <p class="font-bold underline uppercase bg-gray-100 p-1 mb-1 text-gray-900 tracking-wider flex items-center gap-1.5">
                                                        <i class="fa-solid fa-circle-info text-gray-500 text-[11px]"></i>
                                                        <span>Divers</span>
                                                    </p>
                                                    <p><span class="font-semibold">N° OT :</span> {{ intervention.numero_ot || intervention.id }}</p>
                                                    <p><span class="font-semibold">Immatriculation :</span> {{ vehicule.immatriculation }}</p>
                                                    <p><span class="font-semibold">Marque :</span> {{ vehicule.marque }}</p>
                                                    <p><span class="font-semibold">Type :</span> {{ vehicule.modele }}</p>
                                                    <p><span class="font-semibold">Kilométrage :</span> {{ intervention.kilometrage || 'N/A' }} km</p>
                                                </div>
                                            </div>

                                            <div class="border-x border-b border-gray-900 px-3 py-1 text-xs mb-3 -mt-3 rounded-b-lg bg-gray-50/30">
                                                <span class="font-semibold">N° Chassis :</span> <span class="font-mono">{{ vehicule.vin || 'N/A' }}</span>
                                            </div>

                                            <!-- Tableau REGROUPÉ PAR FAMILLE des prestations -->
                                            <div class="mb-3">
                                                <div class="bg-gray-900 text-white px-3 py-1 rounded-t-lg">
                                                    <p class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                                                        <i class="fa-solid fa-list-check text-gray-400 text-[11px]"></i>
                                                        <span>Prestations & Pièces regroupées par famille</span>
                                                    </p>
                                                </div>

                                                <table class="min-w-full border-collapse border border-gray-900 text-xs">
                                                    <thead>
                                                        <tr class="bg-gray-100 border-b border-gray-900 text-center font-semibold">
                                                            <th class="border-r border-gray-900 p-1 w-12">Qté</th>
                                                            <th class="border-r border-gray-900 p-1 text-left">Désignation / Réf</th>
                                                            <th class="border-r border-gray-900 p-1 w-20">PU Net</th>
                                                            <th class="border-r border-gray-900 p-1 w-16">Remise</th>
                                                            <th class="border-r border-gray-900 p-1 w-16">TVA</th>
                                                            <th class="border-r border-gray-900 p-1 w-24">Montant HT</th>
                                                            <th class="p-1 w-28">Statut Client</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-if="!intervention.devis.lignes || intervention.devis.lignes.length === 0">
                                                            <td colspan="7" class="p-3 text-center text-gray-500 italic">Aucune ligne enregistrée pour ce devis.</td>
                                                        </tr>
                                                        <template v-else v-for="groupe in grouperLignesParFamille(intervention.devis.lignes)" :key="groupe.famille">
                                                            <!-- En-tête de Famille -->
                                                            <tr class="bg-gray-200/90 border-y border-gray-900 font-bold">
                                                                <td colspan="7" class="px-3 py-1 text-gray-900 uppercase tracking-wider text-[11px]">
                                                                    <i class="fa-solid fa-layer-group text-slate-600 mr-1.5"></i>
                                                                    <span>{{ groupe.famille }}</span>
                                                                </td>
                                                            </tr>

                                                            <!-- Lignes de la famille -->
                                                            <tr 
                                                                v-for="ligne in groupe.lignes" 
                                                                :key="ligne.id" 
                                                                :class="{'bg-rose-50/50 opacity-70': devisValide(intervention.devis) && !estAcceptee(ligne)}"
                                                                class="border-b border-gray-300 text-center hover:bg-gray-50/50"
                                                            >
                                                                <td class="border-r border-gray-900 p-1 font-medium">{{ ligne.quantite }}</td>
                                                                <td class="border-r border-gray-900 p-1 text-left">
                                                                    <span class="font-medium text-gray-900" :class="{'line-through text-gray-500': devisValide(intervention.devis) && !estAcceptee(ligne)}">
                                                                        {{ ligne.designation }}
                                                                    </span>
                                                                    <span v-if="ligne.sous_famille" class="text-[10px] text-gray-500 font-semibold ml-1">({{ ligne.sous_famille }})</span>
                                                                    <span v-if="ligne.reference_piece" class="block text-[10px] text-gray-500 font-mono">Réf : {{ ligne.reference_piece }}</span>
                                                                    <span v-if="devisValide(intervention.devis) && !estAcceptee(ligne)" class="text-[10px] italic text-rose-600 ml-1 font-bold">(Refusé par le client)</span>
                                                                </td>
                                                                <td class="border-r border-gray-900 p-1 text-right">{{ fmt(ligne.pu_net) }} F</td>
                                                                <td class="border-r border-gray-900 p-1 text-right">{{ fmt(ligne.remise) }} F</td>
                                                                <td class="border-r border-gray-900 p-1 text-center">
                                                                    <span v-if="ligne.ne_pas_appliquer_tva" class="text-amber-600 font-semibold bg-amber-50 px-1 py-0.5 rounded border border-amber-200 text-[10px]">Exonéré</span>
                                                                    <span v-else class="text-gray-600">18%</span>
                                                                </td>
                                                                <td class="border-r border-gray-900 p-1 text-right font-bold text-gray-900">{{ fmt(ligne.montant_ht) }} F</td>
                                                                <td class="p-1 text-center">
                                                                    <span v-if="!devisValide(intervention.devis)" class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded font-bold text-[10px]">En attente</span>
                                                                    <span v-else-if="estAcceptee(ligne)" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-bold text-[10px]">
                                                                        <i class="fa-solid fa-check"></i> Accepté
                                                                    </span>
                                                                    <span v-else class="px-2 py-0.5 bg-rose-100 text-rose-800 rounded font-bold text-[10px]">
                                                                        <i class="fa-solid fa-xmark"></i> Refusé
                                                                    </span>
                                                                </td>
                                                            </tr>

                                                            <!-- Sous-total de la famille -->
                                                            <tr class="bg-gray-50 border-b-2 border-gray-900 font-bold text-xs">
                                                                <td colspan="5" class="px-3 py-1 text-right italic text-gray-700">
                                                                    Sous-total HT {{ groupe.famille }} :
                                                                </td>
                                                                <td colspan="2" class="p-1 text-left text-gray-900 border-t border-gray-400 pl-4">
                                                                    {{ fmt(groupe.sousTotalHt) }} F
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Récapitulatif des Totaux -->
                                            <div class="flex justify-end mb-3" v-if="intervention.devis.lignes && intervention.devis.lignes.length > 0">
                                                <div class="w-80 border border-gray-900 text-xs rounded-lg overflow-hidden shadow-xs">
                                                    <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
                                                        <span class="font-semibold text-gray-700">Total HT {{ devisValide(intervention.devis) ? '(Accepté)' : '' }}</span>
                                                        <span class="font-medium">{{ fmt(calculerTotaux(intervention.devis).ht) }} F</span>
                                                    </div>
                                                    <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
                                                        <span class="font-semibold text-gray-700">Total Remises</span>
                                                        <span class="font-medium">{{ fmt(calculerTotaux(intervention.devis).remises) }} F</span>
                                                    </div>
                                                    <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
                                                        <span class="font-semibold text-gray-700">TVA Totale</span>
                                                        <span class="font-medium">{{ fmt(calculerTotaux(intervention.devis).tva) }} F</span>
                                                    </div>
                                                    <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
                                                        <span class="font-semibold text-gray-700">Petite fourniture (3%)</span>
                                                        <span class="font-medium">{{ fmt(calculerTotaux(intervention.devis).petiteFourniture) }} F</span>
                                                    </div>
                                                    <div class="flex justify-between px-3 py-1.5 font-black bg-gray-200 text-sm text-gray-900">
                                                        <span>Total TTC à Payer</span>
                                                        <span class="text-[#E11D48]">{{ fmt(calculerTotaux(intervention.devis).ttcFinal) }} F</span>
                                                    </div>
                                                    <div class="flex justify-between border-t border-gray-900 px-3 py-1 bg-white" v-if="devisValide(intervention.devis)">
                                                        <span class="font-semibold text-gray-700">Déjà réglé</span>
                                                        <span class="font-medium text-emerald-700">{{ fmt(totalPaye(intervention)) }} F</span>
                                                    </div>
                                                    <div class="flex justify-between border-t border-gray-900 px-3 py-1.5 font-black bg-gray-50 text-sm" v-if="devisValide(intervention.devis)">
                                                        <span>Reste à payer</span>
                                                        <span :class="soldeRestant(intervention) === 0 ? 'text-emerald-700' : 'text-[#E11D48]'">{{ fmt(soldeRestant(intervention)) }} F</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- SUIVI DES PAIEMENTS (Lecture Seule) -->
                                            <div v-if="devisValide(intervention.devis)" class="mb-3 border border-gray-900 rounded-lg overflow-hidden text-xs">
                                                <div class="flex justify-between items-center bg-gray-900 text-white px-3 py-1">
                                                    <p class="font-bold uppercase tracking-wider flex items-center gap-2">
                                                        <i class="fa-solid fa-money-bill-wave text-gray-400 text-[11px]"></i>
                                                        <span>Suivi des paiements</span>
                                                    </p>
                                                    <span v-if="soldeRestant(intervention) === 0" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-bold text-[10px] uppercase">Facture soldée</span>
                                                    <span v-else class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded font-bold text-[10px] uppercase">En cours de règlement</span>
                                                </div>

                                                <!-- Barre de progression -->
                                                <div class="px-3 py-2 border-b border-gray-300 bg-gray-50/50">
                                                    <div class="flex justify-between mb-1 font-semibold text-gray-700">
                                                        <span>{{ fmt(totalPaye(intervention)) }} F réglés sur {{ fmt(calculerTotaux(intervention.devis).ttcFinal) }} F</span>
                                                        <span :class="soldeRestant(intervention) === 0 ? 'text-emerald-700' : 'text-[#E11D48]'">{{ pourcentagePaiement(intervention) }} %</span>
                                                    </div>
                                                    <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                                        <div
                                                            class="h-full transition-all duration-300"
                                                            :class="soldeRestant(intervention) === 0 ? 'bg-emerald-500' : 'bg-[#E11D48]'"
                                                            :style="{ width: pourcentagePaiement(intervention) + '%' }"
                                                        ></div>
                                                    </div>
                                                </div>

                                                <!-- Tableau des versements enregistrés -->
                                                <table class="min-w-full border-collapse">
                                                    <thead>
                                                        <tr class="bg-gray-100 border-b border-gray-300 text-center font-semibold text-[11px]">
                                                            <th class="border-r border-gray-300 p-1 w-10">N°</th>
                                                            <th class="border-r border-gray-300 p-1 w-24">Date</th>
                                                            <th class="border-r border-gray-300 p-1 text-right">Montant</th>
                                                            <th class="border-r border-gray-300 p-1">Mode</th>
                                                            <th class="p-1">Note / Référence</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-if="extrairePaiements(intervention).length === 0">
                                                            <td colspan="5" class="p-2.5 text-center text-gray-500 italic">Aucun règlement enregistré.</td>
                                                        </tr>
                                                        <tr v-for="(p, pIdx) in extrairePaiements(intervention)" :key="p.id" class="border-b border-gray-200 text-center">
                                                            <td class="border-r border-gray-300 p-1">{{ pIdx + 1 }}</td>
                                                            <td class="border-r border-gray-300 p-1">{{ formatDateCourte(p.date_paiement || p.created_at) }}</td>
                                                            <td class="border-r border-gray-300 p-1 text-right font-bold text-emerald-700">+{{ fmt(p.montant) }} F</td>
                                                            <td class="border-r border-gray-300 p-1 capitalize">{{ p.mode_paiement || 'Espèces' }}</td>
                                                            <td class="p-1 text-left font-mono text-[11px] text-gray-600">{{ p.notes || '—' }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Conditions -->
                                            <div class="border border-gray-900 p-2 text-xs mb-3 rounded-lg bg-rose-50/30 border-rose-200">
                                                <p class="font-bold underline text-[#E11D48] mb-0.5 uppercase tracking-wider">NB :</p>
                                                <p class="font-bold text-gray-900 tracking-wide">PAYER UNE AVANCE DE 70% AVANT TRAVAUX</p>
                                            </div>

                                            <!-- Signatures -->
                                            <div class="grid grid-cols-2 gap-8 text-xs text-center font-bold pt-1 mb-3">
                                                <div>
                                                    <p class="mb-8 text-gray-700 uppercase tracking-wider">CLIENT</p>
                                                    <div class="border-b border-gray-400 w-44 mx-auto"></div>
                                                </div>
                                                <div>
                                                    <p class="mb-8 text-gray-700 uppercase tracking-wider">PRESTATAIRE</p>
                                                    <div class="border-b border-gray-400 w-44 mx-auto"></div>
                                                </div>
                                            </div>

                                            <!-- Pied de page légal -->
                                            <div class="mt-2 pt-2 border-t border-gray-300 text-[9px] text-center text-gray-500 space-y-0.5">
                                                <p>Siège : Yopougon Zone Industrielle & Terminus 27 NCC : 1113876 J - RCCM : CI- ABJ-2012-B-5126</p>
                                                <p>Régime d'imposition : Taxe d'Etat de l'Entreprenant (TEE)</p>
                                                <p>Tel : 25 23 01 90 86 / 07 08 38 83 25/ 05 44 10 00 78 E-mail : garagekagnan@gmail.com</p>
                                            </div>

                                        </div>

                                        <!-- Message si aucun devis -->
                                        <p v-else class="flex items-center gap-2 rounded-xl bg-[#F8FAFC] px-4 py-3 text-xs font-medium text-[#8A8D8F]">
                                            <i class="fa-solid fa-file-circle-xmark"></i>
                                            Aucun devis rattaché à cette intervention.
                                        </p>

                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- État vide -->
                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-14 text-center"
                    >
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                        <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucune intervention</h4>
                        <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                            Les interventions et devis de ce véhicule apparaîtront ici dès sa première réception à l'atelier.
                        </p>
                    </div>
                </section>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .row-in {
        animation: row-in 420ms cubic-bezier(0.16, 1, 0.3, 1) both;
    }
}

@keyframes row-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    body {
        background: white !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    nav, header, aside, footer, .print\:hidden {
        display: none !important;
    }

    .invoice-sheet {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>