<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { FAMILLES, FAMILLE_PAR_DEFAUT } from '@/constants/familles.js';

const props = defineProps({
    dossier: Object,
    paiements: Array,
    resume: Object,
});

// Seules les lignes acceptées par le client sont facturées
const lignesFacturees = computed(() =>
    (props.dossier?.devis?.lignes || []).filter(l => l.is_accepted)
);

// Organiser et grouper les lignes facturées par famille avec calcul du sous-total HT par famille
const lignesGroupesParFamille = computed(() => {
    if (!lignesFacturees.value || lignesFacturees.value.length === 0) return [];

    const groupes = {};

    lignesFacturees.value.forEach(ligne => {
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
            const sousTotalHt = groupes[familleNom].reduce((acc, l) => acc + Number(l.montant_ht || 0), 0);
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
});

const somme = (champ) =>
    lignesFacturees.value.reduce((acc, l) => acc + Number(l[champ] || 0), 0);

const totalHt = computed(() => somme('montant_ht'));
const totalRemises = computed(() => somme('remise'));
const totalTtcBrut = computed(() => somme('montant_ttc'));
const totalTva = computed(() => totalTtcBrut.value - totalHt.value);

// ─────────────────────────────────────────────
// TOTAUX : le serveur (resume) est la seule source de vérité.
// Cela évite tout écart d'arrondi entre le front et le contrôleur,
// qui empêcherait la facture de passer automatiquement à « soldée ».
// ─────────────────────────────────────────────
const totalTtcFinal = computed(() => Number(props.resume?.total_ttc || 0));
const montantPaye = computed(() => Number(props.resume?.montant_paye || 0));
const reste = computed(() => Number(props.resume?.reste || 0));
const soldee = computed(() => Boolean(props.resume?.soldee));

// Petite fourniture (3 %) = écart entre le total final et le TTC brut des lignes
const petiteFourniture = computed(() =>
    Math.max(Math.round(totalTtcFinal.value - totalTtcBrut.value), 0)
);

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const aujourdhui = new Date().toISOString().slice(0, 10);
const modesPaiement = ['Especes', 'Carte Bancaire', 'Virement', 'Orange Money', 'MTN Money', 'Moov Money'];

const form = useForm({
    montant: '',
    mode_paiement: 'Especes',
    notes: '',
    date_paiement: aujourdhui,
});

const fmt = (n) => Number(n || 0).toLocaleString('fr-FR');

const pctVersement = (montant) =>
    totalTtcFinal.value > 0
        ? Math.min(100, Math.round((Number(montant) * 100) / totalTtcFinal.value))
        : 0;

const solderReste = () => {
    form.montant = reste.value;
};

const submitEncaissement = () => {
    form.post(route('administration.factures.encaisser', props.dossier.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('montant', 'notes'),
    });
};

const imprimer = () => {
    window.print();
};

const retour = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(route('administration.factures.index'));
    }
};

// ─────────────────────────────────────────────
// PASSAGE AUTOMATIQUE À « SOLDÉE »
// Détecté aussi bien après notre propre versement
// qu'après un versement saisi par quelqu'un d'autre (actualisation auto).
// ─────────────────────────────────────────────
const vientDEtreSoldee = ref(false);

watch(
    () => props.resume?.soldee,
    (nouveau, ancien) => {
        if (nouveau && !ancien) {
            vientDEtreSoldee.value = true;
            form.reset('montant', 'notes');
        }
    }
);

// ─────────────────────────────────────────────
// ACTUALISATION AUTOMATIQUE (versements + totaux)
// S'arrête une fois la facture soldée.
// ─────────────────────────────────────────────
const REFRESH_INTERVAL = 5000; // 5 secondes
let refreshTimer = null;
let rechargementEnCours = false;

const actualiser = () => {
    if (document.hidden || rechargementEnCours || form.processing || soldee.value) return;

    rechargementEnCours = true;
    router.reload({
        only: ['dossier', 'paiements', 'resume'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            rechargementEnCours = false;
        },
    });
};

const onVisibilityChange = () => {
    if (!document.hidden) actualiser();
};

onMounted(() => {
    refreshTimer = setInterval(actualiser, REFRESH_INTERVAL);
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('focus', actualiser);
});

onUnmounted(() => {
    clearInterval(refreshTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('focus', actualiser);
});
</script>

<template>
    <Head :title="`Facture - Dossier #${dossier.numero_ot || dossier.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Facture / Encaissement - OT : {{ dossier.numero_ot || dossier.id }}</span>
                    </h2>
                </div>
                <div class="flex items-center space-x-3">
                    <button @click="imprimer" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-xs">
                        <i class="fa-solid fa-print text-[11px]"></i>
                        <span>Imprimer</span>
                    </button>
                    <button type="button" @click="retour" class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        <span>Retour</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900 print:py-0 print:bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 print:max-w-none print:px-0 print:mx-0">

                <!-- BANNIÈRE : facture vient d'être soldée -->
                <transition
                    enter-active-class="transition duration-300"
                    enter-from-class="opacity-0 -translate-y-2"
                    leave-active-class="transition duration-200"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="vientDEtreSoldee"
                        class="print:hidden mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-xl"
                    >
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i>
                            Cette facture est entièrement soldée. Elle apparaît maintenant dans l'onglet « Soldées ».
                        </span>
                        <span class="flex items-center gap-2">
                            <button type="button" @click="imprimer" class="px-3 py-1.5 bg-white border border-emerald-200 hover:bg-emerald-100 rounded-lg transition">
                                Imprimer
                            </button>
                            <Link
                                :href="route('administration.factures.index', { onglet: 'soldes' })"
                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition"
                            >
                                Voir les factures soldées
                            </Link>
                            <button type="button" @click="vientDEtreSoldee = false" class="text-emerald-600 hover:text-emerald-800">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </span>
                    </div>
                </transition>

                <div v-if="!dossier.devis" class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl p-12 border border-gray-100 text-center print:hidden">
                    <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun devis disponible</h3>
                    <p class="text-xs text-gray-500 mt-1">Aucun devis n'a été généré pour ce dossier.</p>
                </div>

                <div v-else class="invoice-sheet bg-white shadow-2xl shadow-gray-200/50 sm:rounded-2xl p-8 border border-gray-100 print:shadow-none print:border-none print:p-2 text-gray-800">

                    <!-- En-tête -->
                    <div class="flex justify-between items-start border-b-2 border-gray-900 pb-3 mb-3">
                        <div>
                            <img src="/images/logo-kagnan.png" alt="Garage Kagnan" class="h-16 w-auto object-contain" />
                            <p class="text-xs font-medium text-gray-500 mt-0.5">Service Entretien & Réparation Automobile</p>
                        </div>
                        <div class="border-2 border-gray-900 p-2 text-right rounded-xl min-w-[220px] bg-gray-50/50">
                            <p class="text-xs font-bold uppercase bg-emerald-100 px-2 py-0.5 mb-1 text-center rounded text-emerald-800 tracking-wider">Facture</p>
                            <p class="text-xs text-gray-700 mb-0.5"><span class="font-semibold text-gray-900">N° :</span> {{ resume.numero }}</p>
                            <p class="text-xs text-gray-700 mb-0.5"><span class="font-semibold text-gray-900">Date :</span> {{ formatDate(dossier.devis.created_at) }}</p>
                            <p class="text-xs text-gray-700"><span class="font-semibold text-gray-900">Devis :</span> <span class="font-semibold text-emerald-600">Accepté</span></p>
                        </div>
                    </div>

                    <!-- Infos Client & Véhicule -->
                    <div class="grid grid-cols-2 gap-4 border border-gray-900 text-xs mb-3 rounded-lg overflow-hidden">
                        <div class="p-2.5 border-r border-gray-900 space-y-1">
                            <p class="font-bold underline uppercase bg-gray-100 p-1 mb-1 text-gray-900 tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-user text-gray-500 text-[11px]"></i>
                                <span>Client</span>
                            </p>
                            <p><span class="font-semibold">Nom client :</span> {{ dossier.vehicule?.client?.nom }} {{ dossier.vehicule?.client?.prenom }}</p>
                            <p><span class="font-semibold">Adresse :</span> {{ dossier.vehicule?.client?.adresse || 'N/A' }}</p>
                            <p><span class="font-semibold">Téléphone :</span> {{ dossier.vehicule?.client?.telephone || 'N/A' }}</p>
                            <p><span class="font-semibold">Code Equipe :</span> {{ dossier.mecanicien_id || 'N/A' }}</p>
                        </div>
                        <div class="p-2.5 space-y-1">
                            <p class="font-bold underline uppercase bg-gray-100 p-1 mb-1 text-gray-900 tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-info text-gray-500 text-[11px]"></i>
                                <span>Divers</span>
                            </p>
                            <p><span class="font-semibold">N° OT :</span> {{ dossier.numero_ot || dossier.id }}</p>
                            <p><span class="font-semibold">Immatriculation :</span> {{ dossier.vehicule?.immatriculation }}</p>
                            <p><span class="font-semibold">Marque :</span> {{ dossier.vehicule?.marque }}</p>
                            <p><span class="font-semibold">Type :</span> {{ dossier.vehicule?.modele }}</p>
                            <p><span class="font-semibold">Kilométrages :</span> {{ dossier.kilometrage || 'N/A' }}</p>
                        </div>
                    </div>

                    <div class="border-x border-b border-gray-900 px-3 py-1 text-xs mb-3 -mt-3 rounded-b-lg bg-gray-50/30">
                        <span class="font-semibold">N° Chassis :</span> <span class="font-mono">{{ dossier.vehicule?.vin || 'N/A' }}</span>
                    </div>

                    <!-- Tableau par famille avec sous-totaux -->
                    <div class="mb-3">
                        <div class="bg-gray-900 text-white px-3 py-1 rounded-t-lg">
                            <p class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-gray-400 text-[11px]"></i>
                                <span>Prestations & Pièces facturées par famille</span>
                            </p>
                        </div>

                        <table class="min-w-full border-collapse border border-gray-900 text-xs">
                            <thead>
                                <tr class="bg-gray-100 border-b border-gray-900 text-center font-semibold">
                                    <th class="border-r border-gray-900 p-1 w-16">Quantité</th>
                                    <th class="border-r border-gray-900 p-1 text-left">Désignation / Réf</th>
                                    <th class="border-r border-gray-900 p-1 w-20">PU Net</th>
                                    <th class="border-r border-gray-900 p-1 w-16">Remise</th>
                                    <th class="border-r border-gray-900 p-1 w-20">TVA</th>
                                    <th class="p-1 w-24">Montant HT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="lignesFacturees.length === 0">
                                    <td colspan="6" class="p-3 text-center text-gray-500 italic">Aucune ligne acceptée par le client.</td>
                                </tr>
                                <template v-else v-for="groupe in lignesGroupesParFamille" :key="groupe.famille">
                                    <!-- EN-TÊTE DE LA FAMILLE -->
                                    <tr class="bg-gray-200/80 border-y border-gray-900 font-bold">
                                        <td colspan="6" class="px-3 py-1 text-gray-900 uppercase tracking-wider text-[11px]">
                                            <i class="fa-solid fa-layer-group text-slate-600 mr-1.5"></i>
                                            <span>{{ groupe.famille }}</span>
                                        </td>
                                    </tr>

                                    <!-- LIGNES DE LA FAMILLE -->
                                    <tr v-for="ligne in groupe.lignes" :key="ligne.id" class="border-b border-gray-300 text-center hover:bg-gray-50/50">
                                        <td class="border-r border-gray-900 p-1 font-medium">{{ ligne.quantite }}</td>
                                        <td class="border-r border-gray-900 p-1 text-left">
                                            <span class="font-medium text-gray-900">{{ ligne.designation }}</span>
                                            <span v-if="ligne.sous_famille" class="text-[10px] text-gray-500 font-semibold ml-1">({{ ligne.sous_famille }})</span>
                                            <span v-if="ligne.reference_piece" class="block text-[10px] text-gray-500 font-mono mt-0.5">Réf : {{ ligne.reference_piece }}</span>
                                        </td>
                                        <td class="border-r border-gray-900 p-1 text-right">{{ Number(ligne.pu_net).toLocaleString() }} F</td>
                                        <td class="border-r border-gray-900 p-1 text-right">{{ Number(ligne.remise || 0).toLocaleString() }} F</td>
                                        <td class="border-r border-gray-900 p-1 text-center">
                                            <span v-if="ligne.ne_pas_appliquer_tva" class="text-amber-600 font-semibold bg-amber-50 px-1 py-0.5 rounded border border-amber-200 text-[10px]">Exonéré</span>
                                            <span v-else class="text-gray-600">18%</span>
                                        </td>
                                        <td class="p-1 text-right font-bold text-gray-900">{{ Number(ligne.montant_ht).toLocaleString() }} F</td>
                                    </tr>

                                    <!-- SOUS-TOTAL DE LA FAMILLE -->
                                    <tr class="bg-gray-50 border-b-2 border-gray-900 font-bold text-xs">
                                        <td colspan="5" class="px-3 py-1 text-right italic text-gray-700">
                                            Sous-total HT {{ groupe.famille }} :
                                        </td>
                                        <td class="p-1 text-right text-gray-900 border-t border-gray-400">
                                            {{ groupe.sousTotalHt.toLocaleString() }} F
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totaux -->
                    <div class="flex justify-end mb-3" v-if="lignesFacturees.length > 0">
                        <div class="w-72 border border-gray-900 text-xs rounded-lg overflow-hidden shadow-sm">
                            <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
                                <span class="font-semibold text-gray-700">Total HT</span>
                                <span class="font-medium">{{ totalHt.toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
                                <span class="font-semibold text-gray-700">Total Remises</span>
                                <span class="font-medium">{{ totalRemises.toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
                                <span class="font-semibold text-gray-700">TVA Totale</span>
                                <span class="font-medium">{{ totalTva.toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
                                <span class="font-semibold text-gray-700">Petite fourniture </span>
                                <span class="font-medium">{{ petiteFourniture.toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between px-3 py-1.5 font-black bg-gray-200 text-sm text-gray-900">
                                <span>Total TTC à Payer</span>
                                <span class="text-[#E11D48]">{{ totalTtcFinal.toLocaleString() }} F</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-900 px-3 py-1 bg-white">
                                <span class="font-semibold text-gray-700">Déjà payé</span>
                                <span class="font-medium text-emerald-700">{{ fmt(montantPaye) }} F</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-900 px-3 py-1.5 font-black bg-gray-50 text-sm">
                                <span>Reste à payer</span>
                                <span :class="soldee ? 'text-emerald-700' : 'text-[#E11D48]'">{{ fmt(reste) }} F</span>
                            </div>
                        </div>
                    </div>

                    <!-- SUIVI DES PAIEMENTS -->
                    <div class="print:hidden mb-3 border border-gray-900 rounded-lg overflow-hidden text-xs">
                        <div class="flex justify-between items-center bg-gray-900 text-white px-3 py-1">
                            <p class="font-bold uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-money-bill-wave text-gray-400 text-[11px]"></i>
                                <span>Suivi des paiements</span>
                            </p>
                            <span v-if="soldee" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-bold text-[10px] uppercase">Facture soldée</span>
                            <span v-else class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded font-bold text-[10px] uppercase">En cours de paiement</span>
                        </div>

                        <!-- Barre de progression -->
                        <div class="px-3 py-2 border-b border-gray-300">
                            <div class="flex justify-between mb-1 font-semibold text-gray-700">
                                <span>{{ fmt(montantPaye) }} F payés sur {{ fmt(totalTtcFinal) }} F</span>
                                <span :class="soldee ? 'text-emerald-700' : 'text-[#E11D48]'">{{ pctVersement(montantPaye) }} %</span>
                            </div>
                            <div class="h-2.5 w-full bg-gray-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="soldee ? 'bg-emerald-500' : 'bg-[#E11D48]'"
                                    :style="{ width: pctVersement(montantPaye) + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Historique -->
                        <table class="min-w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-100 border-b border-gray-900 text-center font-semibold">
                                    <th class="border-r border-gray-300 p-1 w-10">N°</th>
                                    <th class="border-r border-gray-300 p-1 w-24">Date</th>
                                    <th class="border-r border-gray-300 p-1 text-right">Montant</th>
                                    <th class="border-r border-gray-300 p-1 w-14">%</th>
                                    <th class="border-r border-gray-300 p-1">Mode</th>
                                    <th class="p-1">Référence / Encaissé par</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!paiements || paiements.length === 0">
                                    <td colspan="6" class="p-3 text-center text-gray-500 italic">Aucun versement enregistré.</td>
                                </tr>
                                <tr v-for="(paiement, index) in paiements" :key="paiement.id" class="border-b border-gray-200 text-center">
                                    <td class="border-r border-gray-300 p-1">{{ index + 1 }}</td>
                                    <td class="border-r border-gray-300 p-1">{{ formatDate(paiement.date_paiement) }}</td>
                                    <td class="border-r border-gray-300 p-1 text-right font-bold text-gray-900">{{ fmt(paiement.montant) }} F</td>
                                    <td class="border-r border-gray-300 p-1">{{ pctVersement(paiement.montant) }} %</td>
                                    <td class="border-r border-gray-300 p-1">{{ paiement.mode_paiement }}</td>
                                    <td class="p-1 text-left">
                                        <span v-if="paiement.notes" class="font-mono">{{ paiement.notes }}</span>
                                        <span v-if="paiement.enregistre_par_nom" class="block text-[10px] text-gray-500">{{ paiement.enregistre_par_nom }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Formulaire d'encaissement (disparaît automatiquement quand la facture est soldée) -->
                        <form v-if="reste > 0" @submit.prevent="submitEncaissement" class="print:hidden bg-gray-50 border-t border-gray-900 p-3">
                            <p class="font-bold uppercase tracking-wider text-gray-900 mb-2">Enregistrer un versement</p>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block font-semibold text-gray-700 mb-1">Montant (F)</label>
                                    <input v-model="form.montant" type="number" min="1" :max="reste" step="1" required
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:border-[#E11D48]" />
                                    <button type="button" @click="solderReste" class="mt-1 text-[10px] text-[#E11D48] font-bold hover:underline">
                                        Solder le reste ({{ fmt(reste) }} F)
                                    </button>
                                </div>
                                <div>
                                    <label class="block font-semibold text-gray-700 mb-1">Date</label>
                                    <input v-model="form.date_paiement" type="date" :max="aujourdhui" required
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:border-[#E11D48]" />
                                </div>
                                <div>
                                    <label class="block font-semibold text-gray-700 mb-1">Mode de paiement</label>
                                    <select v-model="form.mode_paiement"
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:border-[#E11D48]">
                                        <option v-for="mode in modesPaiement" :key="mode" :value="mode">{{ mode }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-gray-700 mb-1">Référence / note (optionnel)</label>
                                    <input v-model="form.notes" type="text" maxlength="255"
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:border-[#E11D48]" />
                                </div>
                            </div>
                            <p v-if="form.errors.montant" class="mt-2 text-[11px] font-semibold text-rose-600">{{ form.errors.montant }}</p>
                            <p v-if="form.errors.date_paiement" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.date_paiement }}</p>
                            <div class="mt-3 flex justify-end">
                                <button type="submit" :disabled="form.processing"
                                    class="px-4 py-2 bg-[#E11D48] hover:bg-rose-700 disabled:opacity-60 text-white font-bold uppercase tracking-wider rounded-lg shadow transition text-xs">
                                    Enregistrer le versement
                                </button>
                            </div>
                        </form>
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

                    <!-- Pied de page -->
                    <div class="mt-2 pt-2 border-t border-gray-300 text-[9px] text-center text-gray-500 space-y-0.5">
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

    .min-h-screen {
        min-height: auto !important;
        padding: 0 !important;
        background: white !important;
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