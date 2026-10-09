<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import { FAMILLES, FAMILLE_PAR_DEFAUT } from '@/constants/familles.js';

const props = defineProps({
    dossier: Object,
    // Modification d'un devis déjà accepté (aucun paiement) : lignes modifiables + cases à cocher
    modeEdition: { type: Boolean, default: false },
});

// Détection automatique de la Famille & Sous-famille selon les mots-clés (comme dans le formulaire de devis)
const detecterFamilleEtSousFamille = (designation) => {
    if (!designation || designation.trim().length === 0) {
        return { famille: FAMILLE_PAR_DEFAUT, sousFamille: '' };
    }

    const texte = designation.toLowerCase();

    for (const [familleNom, sousFamillesList] of Object.entries(FAMILLES)) {
        for (const sousFamille of sousFamillesList) {
            if (texte.includes(sousFamille.toLowerCase()) || texte.includes(familleNom.toLowerCase())) {
                return { famille: familleNom, sousFamille };
            }
        }
    }

    return { famille: FAMILLE_PAR_DEFAUT, sousFamille: '' };
};

// Lignes affichées : copie modifiable en mode édition, lignes du devis sinon
const lignesLocales = ref((props.dossier?.devis?.lignes || []).map((l) => ({ ...l })));
const lignesDevis = computed(() =>
    props.modeEdition ? lignesLocales.value : (props.dossier?.devis?.lignes || [])
);

// Formulaire pour la sélection des lignes acceptées
const form = useForm({
    lignes_acceptees: props.dossier?.devis?.lignes
        ? props.dossier.devis.lignes.filter(l => l.is_accepted).map(l => l.id)
        : []
});

// Organiser et grouper les lignes par famille selon l'ordre prédéfini dans familles.js
const lignesGroupesParFamille = computed(() => {
    if (!lignesDevis.value.length) return [];

    const groupes = {};
    
    lignesDevis.value.forEach(ligne => {
        const familleNom = ligne.famille || FAMILLE_PAR_DEFAUT;
        if (!groupes[familleNom]) {
            groupes[familleNom] = [];
        }
        groupes[familleNom].push(ligne);
    });

    // Ordre d'affichage basé sur les clés de FAMILLES, puis FAMILLE_PAR_DEFAUT à la fin
    const ordreFamilles = [...Object.keys(FAMILLES), FAMILLE_PAR_DEFAUT];

    const resultat = [];
    ordreFamilles.forEach(familleNom => {
        if (groupes[familleNom] && groupes[familleNom].length > 0) {
            resultat.push({
                famille: familleNom,
                lignes: groupes[familleNom]
            });
        }
    });

    // Récupérer les familles non listées s'il y en a
    Object.keys(groupes).forEach(familleNom => {
        if (!ordreFamilles.includes(familleNom) && groupes[familleNom].length > 0) {
            resultat.push({
                famille: familleNom,
                lignes: groupes[familleNom]
            });
        }
    });

    return resultat;
});

// Remise = pourcentage par ligne → montant en F = qté × PU × remise / 100
const montantRemise = (l) => Number(l.quantite || 0) * Number(l.pu_net || 0) * Number(l.remise || 0) / 100;

// Totaux d'un jeu de lignes, avec la petite fourniture définie sur le devis
// (auto = 3 % du TTC, ou montant saisi à la création du devis)
const calculerTotaux = (lignes) => {
    const devis = props.dossier?.devis || {};
    const ht = lignes.reduce((a, l) => a + Number(l.montant_ht || 0), 0);
    const ttcBrut = lignes.reduce((a, l) => a + Number(l.montant_ttc || 0), 0);
    const remises = Math.round(lignes.reduce((a, l) => a + montantRemise(l), 0));
    const actif = devis.petite_fourniture_active === null || devis.petite_fourniture_active === undefined
        ? true : !!devis.petite_fourniture_active;
    const manuel = devis.petite_fourniture_montant !== null && devis.petite_fourniture_montant !== undefined;

    let pf = 0;
    if (ttcBrut > 0 && actif) {
        pf = manuel
            ? Math.round(ttcBrut + Number(devis.petite_fourniture_montant)) - Math.round(ttcBrut)
            : Math.round(ttcBrut * 1.03) - Math.round(ttcBrut);
    }

    return { ht, remises, tva: ttcBrut - ht, pf, manuel, ttc: Math.round(ttcBrut) + pf };
};

const totauxCoches = computed(() =>
    calculerTotaux(lignesDevis.value.filter(l => form.lignes_acceptees.includes(l.id)))
);
const totauxAcceptes = computed(() =>
    calculerTotaux((props.dossier?.devis?.lignes || []).filter(l => l.is_accepted))
);

// --- Mode édition : ajout / suppression de lignes ---
let idTemporaire = 0;
const nouvelleLigne = ref({ designation: '', reference_piece: '', quantite: 1, pu_net: 0, remise: 0, ne_pas_appliquer_tva: false, famille: null, sous_famille: null });
const erreurAjout = ref('');

// Saisie automatique : recherche dans le stock selon la désignation, la marque et le modèle du véhicule
const resultatsPieces = ref([]);
const suggestionsVisibles = ref(false);
const chargementPieces = ref(false);
let minuteurRecherche = null;

const rechercherPiecesStock = async () => {
    const requete = (nouvelleLigne.value.designation || '').trim();
    suggestionsVisibles.value = true;

    if (!requete) {
        resultatsPieces.value = [];
        return;
    }

    chargementPieces.value = true;
    try {
        const response = await axios.get(route('administration.dossiers.rechercher-pieces', props.dossier.id), {
            params: {
                marque: props.dossier.vehicule?.marque || '',
                modele: props.dossier.vehicule?.modele || '',
                q: requete,
            },
        });
        resultatsPieces.value = response.data;
    } catch (error) {
        console.error('Erreur lors de la recherche des pièces :', error);
        resultatsPieces.value = [];
    } finally {
        chargementPieces.value = false;
    }
};

const surSaisieDesignation = () => {
    // Saisie manuelle : on oublie la famille issue d'une pièce choisie précédemment
    nouvelleLigne.value.famille = null;
    nouvelleLigne.value.sous_famille = null;
    clearTimeout(minuteurRecherche);
    minuteurRecherche = setTimeout(rechercherPiecesStock, 250);
};

const selectionnerPiece = (piece) => {
    const n = nouvelleLigne.value;
    n.designation = piece.designation_piece || '';
    n.reference_piece = piece.reference || '';
    n.pu_net = parseFloat(piece.prix_kagnan_ht) || parseFloat(piece.prix_marche_ht) || 0;
    n.famille = piece.famille || null;
    n.sous_famille = piece.sous_famille || null;

    suggestionsVisibles.value = false;
    resultatsPieces.value = [];
};

const fermerSuggestions = () => {
    setTimeout(() => { suggestionsVisibles.value = false; }, 200);
};

const ajouterLigne = () => {
    const n = nouvelleLigne.value;
    const quantite = Number(n.quantite);
    const puNet = Number(n.pu_net);
    const remise = Math.min(Math.max(Number(n.remise) || 0, 0), 100);

    if (!n.designation || !n.designation.trim()) {
        erreurAjout.value = 'La désignation est obligatoire.';
        return;
    }
    if (!(quantite >= 0) || !(puNet >= 0)) {
        erreurAjout.value = 'La quantité et le prix unitaire doivent être des nombres positifs.';
        return;
    }
    erreurAjout.value = '';

    const detection = detecterFamilleEtSousFamille(n.designation);
    const ht = quantite * puNet * (1 - remise / 100);
    const ttc = n.ne_pas_appliquer_tva ? ht : ht * 1.18;
    const id = --idTemporaire; // identifiant temporaire (négatif) tant que la ligne n'est pas enregistrée

    lignesLocales.value.push({
        id,
        designation: n.designation.trim(),
        reference_piece: n.reference_piece || '',
        famille: n.famille || detection.famille,
        sous_famille: n.famille ? (n.sous_famille || '') : detection.sousFamille,
        quantite,
        pu_net: puNet,
        remise,
        ne_pas_appliquer_tva: !!n.ne_pas_appliquer_tva,
        montant_ht: ht,
        montant_ttc: ttc,
    });

    // Une ligne ajoutée est acceptée d'office (on peut la décocher ensuite)
    form.lignes_acceptees = [...form.lignes_acceptees, id];

    nouvelleLigne.value = { designation: '', reference_piece: '', quantite: 1, pu_net: 0, remise: 0, ne_pas_appliquer_tva: false, famille: null, sous_famille: null };
    resultatsPieces.value = [];
    suggestionsVisibles.value = false;
};

const supprimerLigne = (ligne) => {
    if (lignesLocales.value.length <= 1) return; // un devis garde au moins une ligne
    lignesLocales.value = lignesLocales.value.filter((l) => l.id !== ligne.id);
    form.lignes_acceptees = form.lignes_acceptees.filter((id) => id !== ligne.id);
};

const submitValidation = () => {
    // Devis déjà accepté : on enregistre les lignes ET les cases cochées en une seule fois
    if (props.modeEdition) {
        const devis = props.dossier.devis;
        const pfActive = devis.petite_fourniture_active === null || devis.petite_fourniture_active === undefined
            ? true : !!devis.petite_fourniture_active;
        const pfMontant = devis.petite_fourniture_montant === null || devis.petite_fourniture_montant === undefined
            ? null : Math.round(Number(devis.petite_fourniture_montant));

        form.transform(() => ({
            lignes: lignesLocales.value.map((l) => ({
                quantite: l.quantite,
                designation: l.designation,
                reference_piece: l.reference_piece || null,
                famille: l.famille || null,
                sous_famille: l.sous_famille || null,
                pu_net: l.pu_net,
                remise: l.remise ?? 0,
                ne_pas_appliquer_tva: !!l.ne_pas_appliquer_tva,
                is_accepted: form.lignes_acceptees.includes(l.id),
            })),
            petite_fourniture_active: pfActive,
            petite_fourniture_montant: pfMontant,
        })).put(route('administration.devis.update', props.dossier.id), {
            preserveScroll: true,
        });
        return;
    }

    form.post(route('administration.facturation.valider-devis', props.dossier.id), {
        preserveScroll: true,
    });
};

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

const retour = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(route('administration.facturation.index'));
    }
};
</script>

<template>
    <Head :title="`Devis / Facture - Dossier #${dossier.numero_ot || dossier.id}`" />

    <AuthenticatedLayout>
        <!-- En-tête de page (masqué à l'impression) -->
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice text-[#E11D48]"></i>
                        <span>{{ modeEdition ? 'Modification du Devis' : 'Validation Devis / Facturation' }} - OT : {{ dossier.numero_ot || dossier.id }}</span>
                    </h2>
                </div>
                <div class="flex items-center space-x-3">
                    <button 
                        @click="imprimer" 
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-xs"
                    >
                        <i class="fa-solid fa-print text-[11px]"></i>
                        <span>Imprimer</span>
                    </button>
                    <button 
                        type="button"
                        @click="retour"
                        class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-gray-900 font-medium"
                    >
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        <span>Retour</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900 print:py-0 print:bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 print:max-w-none print:px-0 print:mx-0">
                
                <div v-if="!dossier.devis" class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl p-12 border border-gray-100 text-center print:hidden">
                    <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun devis disponible</h3>
                    <p class="text-xs text-gray-500 mt-1">Aucun devis n'a encore été généré pour ce dossier.</p>
                </div>

                <div v-else>
                    
                    <!-- MODE 1 : DEVIS EN ATTENTE (Saisie du choix client) -->
                    <form v-if="dossier.devis.statut === 'en_attente' || modeEdition" @submit.prevent="submitValidation">
                        <div class="invoice-sheet bg-white shadow-2xl shadow-gray-200/50 sm:rounded-2xl p-8 border border-gray-100 print:shadow-none print:border-none print:p-2 text-gray-800">
                            
                            <!-- En-tête -->
                            <div class="flex justify-between items-start border-b-2 border-gray-900 pb-3 mb-3">
                                <div>
                                    <img src="/images/logo-kagnan.png" alt="Garage Kagnan" class="h-16 w-auto object-contain" />
                                    <p class="text-xs font-medium text-gray-500 mt-0.5">Service Entretien & Réparation Automobile</p>
                                </div>
                                <div class="border-2 border-gray-900 p-2 text-right rounded-xl min-w-[220px] bg-gray-50/50">
                                    <p class="text-xs font-bold uppercase bg-gray-200/80 px-2 py-0.5 mb-1 text-center rounded text-gray-900 tracking-wider">Devis / Facture Provisoire</p>
                                    <p class="text-xs text-gray-700 mb-0.5"><span class="font-semibold text-gray-900">N° :</span> D/1/ADMI/{{ dossier.devis.id }}/{{ new Date(dossier.devis.created_at).getFullYear() }}</p>
                                    <p class="text-xs text-gray-700 mb-0.5"><span class="font-semibold text-gray-900">Date :</span> {{ formatDate(dossier.devis.created_at) }}</p>
                                    <p class="text-xs text-gray-700"><span class="font-semibold text-gray-900">Statut :</span> <span class="capitalize font-semibold text-[#E11D48]">{{ dossier.devis.statut?.replace('_', ' ') }}</span></p>
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

                            <!-- Tableau REGROUPÉ PAR FAMILLE -->
                            <div class="mb-3">
                                <div class="bg-gray-900 text-white px-3 py-1 rounded-t-lg">
                                    <p class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                                        <i class="fa-solid fa-list-check text-gray-400 text-[11px]"></i>
                                        <span>Prestations & Pièces proposées par famille</span>
                                    </p>
                                </div>

                                <table class="min-w-full border-collapse border border-gray-900 text-xs">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-900 text-center font-semibold">
                                            <th class="border-r border-gray-900 p-1 w-10 print:hidden">Acc.</th>
                                            <th class="border-r border-gray-900 p-1 w-16">Quantité</th>
                                            <th class="border-r border-gray-900 p-1 text-left">Désignation / Réf</th>
                                            <th class="border-r border-gray-900 p-1 w-20">PU Net</th>
                                            <th class="border-r border-gray-900 p-1 w-16">Remise</th>
                                            <th class="border-r border-gray-900 p-1 w-20">TVA</th>
                                            <th class="p-1 w-24">Montant HT</th>
                                            <th v-if="modeEdition" class="border-l border-gray-900 p-1 w-10 print:hidden">Suppr.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="groupe in lignesGroupesParFamille" :key="groupe.famille">
                                            <!-- EN-TÊTE DE LA FAMILLE -->
                                            <tr class="bg-gray-200/80 border-y border-gray-900 font-bold">
                                                <td :colspan="modeEdition ? 8 : 7" class="px-3 py-1 text-gray-900 uppercase tracking-wider text-[11px]">
                                                    <i class="fa-solid fa-layer-group text-slate-600 mr-1.5"></i>
                                                    <span>{{ groupe.famille }}</span>
                                                </td>
                                            </tr>

                                            <!-- LIGNES DE LA FAMILLE -->
                                            <tr v-for="ligne in groupe.lignes" :key="ligne.id" :class="{'bg-gray-50 opacity-60 print:opacity-100': !form.lignes_acceptees.includes(ligne.id)}" class="border-b border-gray-300 text-center hover:bg-gray-50/50">
                                                <td class="border-r border-gray-900 p-1 print:hidden">
                                                    <input 
                                                        type="checkbox" 
                                                        :value="ligne.id" 
                                                        v-model="form.lignes_acceptees"
                                                        class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]"
                                                    />
                                                </td>
                                                <td class="border-r border-gray-900 p-1 font-medium">{{ ligne.quantite }}</td>
                                                <td class="border-r border-gray-900 p-1 text-left">
                                                    <span class="font-medium text-gray-900">{{ ligne.designation }}</span>
                                                    <span v-if="ligne.sous_famille" class="text-[10px] text-gray-500 font-semibold ml-1">({{ ligne.sous_famille }})</span>
                                                    <span v-if="ligne.reference_piece" class="block text-[10px] text-gray-500 font-mono">Réf : {{ ligne.reference_piece }}</span>
                                                    <span v-if="!form.lignes_acceptees.includes(ligne.id)" class="hidden print:inline-block text-[10px] italic text-red-600 ml-1">(Refusé par le client)</span>
                                                </td>
                                                <td class="border-r border-gray-900 p-1 text-right">{{ Number(ligne.pu_net).toLocaleString() }} F</td>
                                                <td class="border-r border-gray-900 p-1 text-right">{{ Number(ligne.remise || 0) }} %</td>
                                                <td class="border-r border-gray-900 p-1 text-center">
                                                    <span v-if="ligne.ne_pas_appliquer_tva" class="text-amber-600 font-semibold bg-amber-50 px-1 py-0.5 rounded border border-amber-200 text-[10px]">Exonéré</span>
                                                    <span v-else class="text-gray-600">18%</span>
                                                </td>
                                                <td class="p-1 text-right font-bold text-gray-900">{{ Number(ligne.montant_ht).toLocaleString() }} F</td>
                                                <td v-if="modeEdition" class="border-l border-gray-900 p-1 print:hidden">
                                                    <button
                                                        type="button"
                                                        @click="supprimerLigne(ligne)"
                                                        :disabled="lignesLocales.length <= 1"
                                                        class="w-6 h-6 bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white rounded flex items-center justify-center mx-auto border border-rose-200 transition disabled:opacity-40 disabled:cursor-not-allowed"
                                                        title="Supprimer la ligne"
                                                    >
                                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Ajout d'une ligne (modification d'un devis accepté) -->
                            <div v-if="modeEdition" class="mb-3 print:hidden border border-dashed border-gray-300 rounded-lg p-3 bg-white">
                                <p class="text-xs font-bold uppercase tracking-wider text-gray-700 mb-2 flex items-center gap-2">
                                    <i class="fa-solid fa-plus text-[#E11D48] text-[11px]"></i>
                                    <span>Ajouter une ligne</span>
                                </p>
                                <div class="grid grid-cols-2 md:grid-cols-12 gap-2 text-xs">
                                    <div class="col-span-2 md:col-span-5 relative">
                                        <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">Désignation * <span class="font-normal text-gray-400">(recherche dans le stock)</span></label>
                                        <input
                                            type="text"
                                            v-model="nouvelleLigne.designation"
                                            @input="surSaisieDesignation"
                                            @focus="rechercherPiecesStock"
                                            @blur="fermerSuggestions"
                                            @keydown.enter.prevent="ajouterLigne"
                                            placeholder="Ex: Plaquettes de frein"
                                            autocomplete="off"
                                            class="w-full rounded-lg border-gray-300 text-xs uppercase p-2 focus:border-[#E11D48] focus:ring-[#E11D48]"
                                        />

                                        <!-- Suggestions de pièces du stock (prix selon la marque / le modèle du véhicule) -->
                                        <div v-if="suggestionsVisibles && resultatsPieces.length > 0" class="absolute left-0 right-0 z-[999] mt-1 bg-white border border-gray-200 rounded-lg shadow-2xl max-h-48 overflow-y-auto">
                                            <div
                                                v-for="piece in resultatsPieces"
                                                :key="piece.id"
                                                @mousedown.prevent="selectionnerPiece(piece)"
                                                class="px-3 py-2 hover:bg-gray-100 cursor-pointer text-xs border-b border-gray-100 last:border-none flex justify-between items-center gap-3"
                                            >
                                                <div>
                                                    <span class="font-bold text-gray-800 uppercase">{{ piece.designation_piece }}</span>
                                                    <span v-if="piece.reference" class="text-[10px] text-gray-400 block">Réf: {{ piece.reference }}</span>
                                                </div>
                                                <span class="font-mono text-[#E11D48] font-semibold whitespace-nowrap">{{ piece.prix_kagnan_ht || piece.prix_marche_ht || 0 }} F</span>
                                            </div>
                                        </div>
                                        <p v-else-if="suggestionsVisibles && chargementPieces" class="absolute left-0 mt-1 text-[10px] text-gray-400">Recherche...</p>
                                    </div>
                                    <div class="col-span-2 md:col-span-3">
                                        <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">Réf. pièce</label>
                                        <input type="text" v-model="nouvelleLigne.reference_piece" @keydown.enter.prevent="ajouterLigne" class="w-full rounded-lg border-gray-300 text-xs p-2 focus:border-[#E11D48] focus:ring-[#E11D48]" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">Quantité</label>
                                        <input type="number" min="0" step="any" v-model="nouvelleLigne.quantite" @keydown.enter.prevent="ajouterLigne" class="w-full rounded-lg border-gray-300 text-xs p-2 focus:border-[#E11D48] focus:ring-[#E11D48]" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">PU Net</label>
                                        <input type="number" min="0" step="any" v-model="nouvelleLigne.pu_net" @keydown.enter.prevent="ajouterLigne" class="w-full rounded-lg border-gray-300 text-xs p-2 focus:border-[#E11D48] focus:ring-[#E11D48]" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-semibold text-gray-500 mb-0.5">Remise (%)</label>
                                        <input type="number" min="0" max="100" step="any" v-model="nouvelleLigne.remise" @keydown.enter.prevent="ajouterLigne" class="w-full rounded-lg border-gray-300 text-xs p-2 focus:border-[#E11D48] focus:ring-[#E11D48]" />
                                    </div>
                                    <label class="col-span-2 md:col-span-3 flex items-end gap-1.5 pb-2 text-gray-600 font-medium">
                                        <input type="checkbox" v-model="nouvelleLigne.ne_pas_appliquer_tva" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" />
                                        <span>Exonéré de TVA</span>
                                    </label>
                                    <div class="col-span-2 md:col-span-2 flex items-end">
                                        <button type="button" @click="ajouterLigne" class="w-full px-3 py-2 bg-gray-800 hover:bg-gray-900 text-white font-bold uppercase tracking-wider rounded-lg transition text-[11px]">
                                            Ajouter
                                        </button>
                                    </div>
                                </div>
                                <p v-if="erreurAjout" class="mt-2 text-[11px] font-bold text-[#E11D48]">{{ erreurAjout }}</p>
                            </div>

                            <!-- Actions -->
                            <div class="mb-4 flex justify-between items-center print:hidden bg-gray-50 p-3 rounded-lg border border-gray-200">
                                <span class="text-xs text-gray-600 font-medium">
                                    {{ modeEdition
                                        ? 'Ajoutez ou supprimez des lignes, cochez celles acceptées par le client puis enregistrez : tout est enregistré en une seule fois.'
                                        : 'Cochez les lignes acceptées par le client puis enregistrez pour valider la facture définitive.' }}
                                </span>
                                <button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="px-4 py-2 bg-[#E11D48] hover:bg-rose-700 text-white font-bold uppercase tracking-wider rounded-lg shadow transition text-xs"
                                >
                                    {{ modeEdition ? 'Enregistrer les modifications' : 'Enregistrer le choix du client' }}
                                </button>
                            </div>
                            <div v-if="modeEdition && Object.keys(form.errors).length" class="mb-4 print:hidden bg-rose-50 border border-rose-200 text-[#E11D48] text-xs font-bold rounded-lg p-3">
                                {{ Object.values(form.errors)[0] }}
                            </div>

                            <!-- Totaux -->
                            <div class="flex justify-end mb-3" v-if="lignesDevis.length > 0">
                                <div class="w-72 border border-gray-900 text-xs rounded-lg overflow-hidden shadow-sm">
                                    <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
                                        <span class="font-semibold text-gray-700">Total HT (Accepté)</span>
                                        <span class="font-medium">
                                            {{ lignesDevis.filter(l => form.lignes_acceptees.includes(l.id)).reduce((acc, l) => acc + Number(l.montant_ht), 0).toLocaleString() }} F
                                        </span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
                                        <span class="font-semibold text-gray-700">Total Remises</span>
                                        <span class="font-medium">
                                            {{ totauxCoches.remises.toLocaleString() }} F
                                        </span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
                                        <span class="font-semibold text-gray-700">TVA Totale</span>
                                        <span class="font-medium">
                                            {{ lignesDevis.filter(l => form.lignes_acceptees.includes(l.id)).reduce((acc, l) => acc + (Number(l.montant_ttc) - Number(l.montant_ht)), 0).toLocaleString() }} F
                                        </span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
                                        <span class="font-semibold text-gray-700">Petite fourniture <span v-if="totauxCoches.manuel" class="text-[10px] font-normal text-gray-500">(saisie)</span><span v-else class="text-[10px] font-normal text-gray-500">(3 %)</span></span>
                                        <span class="font-medium">{{ totauxCoches.pf.toLocaleString() }} F</span>
                                    </div>
                                    <div class="flex justify-between px-3 py-1.5 font-black bg-gray-200 text-sm text-gray-900">
                                        <span>Total TTC à Payer</span>
                                        <span class="text-[#E11D48]">
                                            {{ totauxCoches.ttc.toLocaleString() }} F
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Conditions -->
                            <div class="border border-gray-900 p-2 text-xs mb-3 rounded-lg bg-rose-50/30 border-rose-200">
                                <p class="font-bold underline text-[#E11D48] mb-0.5 uppercase tracking-wider">NB :</p>
                                <p class="font-bold text-gray-900 tracking-wide">PAYER UNE AVANCE DE 70% AVANT TRAVAUX</p>
                            </div>

                            <!-- Mises en garde -->
                            <div class="text-[10px] leading-snug text-gray-700 mb-3 space-y-1">
                                <p><span class="font-bold text-[#E11D48]">(*)</span> Ce devis est valable <span class="font-semibold">15 jours</span> à compter de sa réception. Passé ce délai sans réaction de votre part, il ne sera plus valable et les prix des pièces pourront être révisés.</p>
                                <p><span class="font-bold text-[#E11D48]">(**)</span> Si, <span class="font-semibold">3 jours</span> après la réception de ce devis, nous n'avons reçu aucune réponse de votre part et que votre véhicule se trouve toujours dans notre garage sous notre responsabilité, des frais de gardiennage de <span class="font-semibold">3 000 F CFA par jour</span> vous seront facturés.</p>
                            </div>

                            <!-- Pied de page légal -->
                            <div class="mt-2 pt-2 border-t border-gray-300 text-[9px] text-center text-gray-500 space-y-0.5">
                                <p>Siège : Yopougon Zone Industrielle & Terminus 27 NCC : 1113876 J - RCCM : CI- ABJ-2012-B-5126</p>
                                <p>Régime d'imposition : Taxe d'Etat de l'Entreprenant (TEE)</p>
                                <p>Tel : 25 23 01 90 86 / 07 08 38 83 25/ 05 44 10 00 78 E-mail : garagekagnan@gmail.com</p>
                            </div>

                        </div>
                    </form>

                    <!-- MODE 2 : DEVIS DÉJÀ VALIDÉ -->
                    <div v-else class="invoice-sheet bg-white shadow-2xl shadow-gray-200/50 sm:rounded-2xl p-8 border border-gray-100 print:shadow-none print:border-none print:p-2 text-gray-800">
                        
                        <!-- En-tête -->
                        <div class="flex justify-between items-start border-b-2 border-gray-900 pb-3 mb-3">
                            <div>
                                <img src="/images/logo-kagnan.png" alt="Garage Kagnan" class="h-16 w-auto object-contain" />
                                <p class="text-xs font-medium text-gray-500 mt-0.5">Service Entretien & Réparation Automobile</p>
                            </div>
                            <div class="border-2 border-gray-900 p-2 text-right rounded-xl min-w-[220px] bg-gray-50/50">
                                <p class="text-xs font-bold uppercase bg-emerald-100 px-2 py-0.5 mb-1 text-center rounded text-emerald-800 tracking-wider">Devis Validé</p>
                                <p class="text-xs text-gray-700 mb-0.5"><span class="font-semibold text-gray-900">N° :</span> D/1/ADMI/{{ dossier.devis.id }}/{{ new Date(dossier.devis.created_at).getFullYear() }}</p>
                                <p class="text-xs text-gray-700 mb-0.5"><span class="font-semibold text-gray-900">Date :</span> {{ formatDate(dossier.devis.created_at) }}</p>
                                <p class="text-xs text-gray-700"><span class="font-semibold text-gray-900">Statut :</span> <span class="capitalize font-semibold text-emerald-600">Accepté</span></p>
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

                        <!-- Tableau REGROUPÉ PAR FAMILLE (Consultation) -->
                        <div class="mb-3">
                            <div class="bg-gray-900 text-white px-3 py-1 rounded-t-lg">
                                <p class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-list-check text-gray-400 text-[11px]"></i>
                                    <span>Prestations & Pièces validées par famille</span>
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
                                        <th class="border-r border-gray-900 p-1 w-24">Montant HT</th>
                                        <th class="p-1 w-24">Choix Client</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="groupe in lignesGroupesParFamille" :key="groupe.famille">
                                        <!-- EN-TÊTE DE LA FAMILLE -->
                                        <tr class="bg-gray-200/80 border-y border-gray-900 font-bold">
                                            <td colspan="7" class="px-3 py-1 text-gray-900 uppercase tracking-wider text-[11px]">
                                                <i class="fa-solid fa-layer-group text-slate-600 mr-1.5"></i>
                                                <span>{{ groupe.famille }}</span>
                                            </td>
                                        </tr>

                                        <!-- LIGNES DE LA FAMILLE -->
                                        <tr v-for="ligne in groupe.lignes" :key="ligne.id" :class="{'bg-gray-50 opacity-50 print:opacity-100': !ligne.is_accepted}" class="border-b border-gray-300 text-center hover:bg-gray-50/50">
                                            <td class="border-r border-gray-900 p-1 font-medium">{{ ligne.quantite }}</td>
                                            <td class="border-r border-gray-900 p-1 text-left">
                                                <span class="font-medium text-gray-900">{{ ligne.designation }}</span>
                                                <span v-if="ligne.sous_famille" class="text-[10px] text-gray-500 font-semibold ml-1">({{ ligne.sous_famille }})</span>
                                                <span v-if="ligne.reference_piece" class="block text-[10px] text-gray-500 font-mono">Réf : {{ ligne.reference_piece }}</span>
                                            </td>
                                            <td class="border-r border-gray-900 p-1 text-right">{{ Number(ligne.pu_net).toLocaleString() }} F</td>
                                            <td class="border-r border-gray-900 p-1 text-right">{{ Number(ligne.remise || 0) }} %</td>
                                            <td class="border-r border-gray-900 p-1 text-center">
                                                <span v-if="ligne.ne_pas_appliquer_tva" class="text-amber-600 font-semibold bg-amber-50 px-1 py-0.5 rounded border border-amber-200 text-[10px]">Exonéré</span>
                                                <span v-else class="text-gray-600">18%</span>
                                            </td>
                                            <td class="border-r border-gray-900 p-1 text-right font-bold text-gray-900">{{ Number(ligne.montant_ht).toLocaleString() }} F</td>
                                            <td class="p-1 text-center">
                                                <span v-if="ligne.is_accepted" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-bold text-[10px]">
                                                    Accepté
                                                </span>
                                                <span v-else class="px-2 py-0.5 bg-rose-100 text-rose-800 rounded font-bold text-[10px]">
                                                    Refusé
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totaux -->
                        <div class="flex justify-end mb-3" v-if="dossier.devis.lignes && dossier.devis.lignes.length > 0">
                            <div class="w-72 border border-gray-900 text-xs rounded-lg overflow-hidden shadow-sm">
                                <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
                                    <span class="font-semibold text-gray-700">Total HT (Accepté)</span>
                                    <span class="font-medium">
                                        {{ dossier.devis.lignes.filter(l => l.is_accepted).reduce((acc, l) => acc + Number(l.montant_ht), 0).toLocaleString() }} F
                                    </span>
                                </div>
                                <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
                                    <span class="font-semibold text-gray-700">Total Remises</span>
                                    <span class="font-medium">
                                        {{ totauxAcceptes.remises.toLocaleString() }} F
                                    </span>
                                </div>
                                <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
                                    <span class="font-semibold text-gray-700">TVA Totale</span>
                                    <span class="font-medium">
                                        {{ dossier.devis.lignes.filter(l => l.is_accepted).reduce((acc, l) => acc + (Number(l.montant_ttc) - Number(l.montant_ht)), 0).toLocaleString() }} F
                                    </span>
                                </div>
                                <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
                                    <span class="font-semibold text-gray-700">Petite fourniture <span v-if="totauxAcceptes.manuel" class="text-[10px] font-normal text-gray-500">(saisie)</span><span v-else class="text-[10px] font-normal text-gray-500">(3 %)</span></span>
                                    <span class="font-medium">{{ totauxAcceptes.pf.toLocaleString() }} F</span>
                                </div>
                                <div class="flex justify-between px-3 py-1.5 font-black bg-gray-200 text-sm text-gray-900">
                                    <span>Total TTC à Payer</span>
                                    <span class="text-[#E11D48]">
                                        {{ totauxAcceptes.ttc.toLocaleString() }} F
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Conditions -->
                        <div class="border border-gray-900 p-2 text-xs mb-3 rounded-lg bg-rose-50/30 border-rose-200">
                            <p class="font-bold underline text-[#E11D48] mb-0.5 uppercase tracking-wider">NB :</p>
                            <p class="font-bold text-gray-900 tracking-wide">PAYER UNE AVANCE DE 70% AVANT TRAVAUX</p>
                        </div>

                        <!-- Mises en garde -->
                        <div class="text-[10px] leading-snug text-gray-700 mb-3 space-y-1">
                            <p><span class="font-bold text-[#E11D48]">(*)</span> Ce devis est valable <span class="font-semibold">15 jours</span> à compter de sa réception. Passé ce délai sans réaction de votre part, il ne sera plus valable et les prix des pièces pourront être révisés.</p>
                            <p><span class="font-bold text-[#E11D48]">(**)</span> Si, <span class="font-semibold">3 jours</span> après la réception de ce devis, nous n'avons reçu aucune réponse de votre part et que votre véhicule se trouve toujours dans notre garage sous notre responsabilité, des frais de gardiennage de <span class="font-semibold">3 000 F CFA par jour</span> vous seront facturés.</p>
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