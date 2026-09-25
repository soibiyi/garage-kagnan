<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    dossier: Object,
});

// Formulaire Inertia pour les lignes de devis
const form = useForm({
    lignes: [
        {
            quantite: 1,
            designation: 'DIAGNOSTIC TECHNIQUE',
            reference_piece: '',
            pu_net: 10000,
            remise: 0,
            ne_pas_appliquer_tva: true,
        }
    ],
});

// États pour la gestion de l'autocomplétion des pièces par ligne
const activeDropdownIndex = ref(null);
const searchResults = ref([]);
const isLoadingPieces = ref(false);

// Rechercher des pièces dans le stock
const rechercherPiecesStock = async (ligne, index) => {
    console.log("La fonction est bien appelée !", ligne.designation); // <-- Ajoutez ceci
    activeDropdownIndex.value = index;
    const query = ligne.designation || '';

    if (query.length < 1) {
        searchResults.value = [];
        return;
    }

    isLoadingPieces.value = true;
    try {
        const marque = props.dossier.vehicule?.marque || '';
        const modele = props.dossier.vehicule?.modele || '';
        
        const response = await axios.get(route('administration.dossiers.rechercher-pieces', props.dossier.id), {
            params: { marque, modele, q: query }
        });
        searchResults.value = response.data;
    } catch (error) {
        console.error("Erreur lors de la recherche des pièces:", error);
        searchResults.value = [];
    } finally {
        isLoadingPieces.value = false;
    }
};

// Sélectionner une pièce depuis les résultats
const selectionnerPiece = (ligne, piece) => {
    ligne.designation = piece.designation_piece || '';
    ligne.reference_piece = piece.reference || '';
    // Utilisation du prix kagnan HT ou prix marché selon votre préférence (ici prix_kagnan_ht ou prix_ttc_kagnan)
    ligne.pu_net = parseFloat(piece.prix_kagnan_ht) || parseFloat(piece.prix_marche_ht) || 0;
    activeDropdownIndex.value = null;
    searchResults.value = [];
};

// Fermer les suggestions si on clique ailleurs
const fermerSuggestions = () => {
    setTimeout(() => {
        activeDropdownIndex.value = null;
    }, 200);
};

// Ajouter une nouvelle ligne vide
const ajouterLigne = () => {
    form.lignes.push({
        quantite: 1,
        designation: '',
        reference_piece: '',
        pu_net: 0,
        remise: 0,
        ne_pas_appliquer_tva: true,
    });
};

// Supprimer une ligne
const supprimerLigne = (index) => {
    if (form.lignes.length > 1) {
        form.lignes.splice(index, 1);
    }
};

// Calcul du montant HT par ligne
const calculerMontantHt = (ligne) => {
    const qte = parseFloat(ligne.quantite) || 0;
    const pu = parseFloat(ligne.pu_net) || 0;
    const remise = parseFloat(ligne.remise) || 0;
    const total = (qte * pu) - remise;
    return isNaN(total) ? 0 : total;
};

// Calcul du Total TTC par ligne (TVA 18% si applicable)
const calculerTotalTtc = (ligne) => {
    const ht = calculerMontantHt(ligne);
    if (ligne.ne_pas_appliquer_tva) {
        return ht;
    }
    return ht * 1.18;
};

// Totaux globaux
const totalGeneralHt = computed(() => {
    return form.lignes.reduce((acc, ligne) => acc + calculerMontantHt(ligne), 0);
});

const totalGeneralTtc = computed(() => {
    return form.lignes.reduce((acc, ligne) => acc + calculerTotalTtc(ligne), 0);
});

// Soumission
const submitDevis = () => {
    form.post(route('administration.devis.store', props.dossier.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Établissement Devis - Intervention N° ${dossier.id}`" />

    <AuthenticatedLayout>
        <!-- En-tête de page -->
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <Link :href="route('administration.dossiers.index')" class="hover:text-slate-900 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-arrow-left"><span>Retour aux dossiers</span></i>
                        </Link>
                        <span>/</span>
                        <span class="px-2.5 py-1 bg-slate-100 text-[#E11D48] border border-[#E11D48]/20 rounded-md font-mono font-semibold">
                            OT #{{ dossier.numero_ot || dossier.id }}
                        </span>
                    </div>
                    <h2 class="text-xl font-bold tracking-tight text-slate-900 mt-2 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Établissement du Devis & Chiffrage</span>
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- RAPPORT DU MÉCANICIEN -->
                <div v-if="dossier.rapport_mecanicien" class="bg-amber-50/60 border border-amber-500/20 p-5 rounded-xl shadow-sm relative overflow-hidden">
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-amber-500"></div>
                    <h4 class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fa-solid fa-wrench"></i>
                        <span>Constat Technique (Rapport Mécanicien)</span>
                    </h4>
                    <p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed">
                        {{ dossier.rapport_mecanicien }}
                    </p>
                </div>

                <!-- INFORMATIONS VÉHICULE & CLIENT -->
                <div class="bg-slate-50 p-5 rounded-xl shadow-sm border border-slate-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-[#E11D48] shrink-0 shadow-sm">
                            <i class="fa-solid fa-car text-lg"></i>
                        </div>
                        <div>
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Véhicule & Propriétaire</span>
                            <h3 class="text-sm font-bold text-slate-900 uppercase mt-0.5">
                                {{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }} 
                                <span class="text-[#E11D48] font-mono ml-1">[{{ dossier.vehicule?.immatriculation }}]</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span><strong class="text-slate-700">Client :</strong> {{ dossier.vehicule?.client?.name || dossier.vehicule?.client?.nom || 'N/A' }}</span>
                                <span class="text-slate-300">|</span>
                                <span><strong class="text-slate-700">Kilométrage :</strong> {{ dossier.kilometrage }} km</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- TABLEAU DE SAISIE DU DEVIS -->
                <form @submit.prevent="submitDevis" class="space-y-6">
                    <div class="bg-slate-50 rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <thead class="bg-slate-100 text-slate-500 uppercase tracking-wider text-[10px]">
                                    <tr>
                                        <th class="px-3 py-3 font-semibold w-24 text-center">Qté</th>
                                        <th class="px-3 py-3 font-semibold">Désignation</th>
                                        <th class="px-3 py-3 font-semibold w-32">Réf. Pièce</th>
                                        <th class="px-3 py-3 font-semibold w-12 text-center">Stock</th>
                                        <th class="px-3 py-3 font-semibold w-28 text-right">PU Net</th>
                                        <th class="px-3 py-3 font-semibold w-24 text-right">Remise</th>
                                        <th class="px-3 py-3 font-semibold w-28 text-right">Total HT</th>
                                        <th class="px-3 py-3 font-semibold w-28 text-right">Total TTC</th>
                                        <th class="px-3 py-3 font-semibold text-center w-20">Exempt TVA</th>
                                        <th class="px-3 py-3 font-semibold text-center w-12">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr v-for="(ligne, index) in form.lignes" :key="index" class="hover:bg-slate-100/60 transition-colors">
                                        <!-- Quantité -->
                                        <td class="px-3 py-3 text-center">
                                            <input 
                                                type="number" 
                                                v-model="ligne.quantite" 
                                                min="0" 
                                                step="any"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-xs text-center font-bold text-slate-900 focus:border-[#E11D48] focus:ring-1 focus:ring-[#E11D48]"
                                            />
                                        </td>
                                        <!-- Désignation avec autocomplétion -->
                                        <!-- Désignation avec autocomplétion -->
<td class="px-3 py-3 overflow-visible"> <!-- Retirez 'relative' et ajoutez 'overflow-visible' si besoin -->
    <div class="relative w-full"> <!-- Conteneur dédié en relative -->
        <input 
            type="text" 
            v-model="ligne.designation" 
            @input="rechercherPiecesStock(ligne, index)"
            @focus="rechercherPiecesStock(ligne, index)"
            @blur="fermerSuggestions"
            placeholder="Libellé de la prestation ou pièce..."
            autocomplete="off"
            class="w-full bg-white border border-slate-300 rounded-lg text-xs uppercase text-slate-900 focus:border-[#E11D48] focus:ring-1 focus:ring-[#E11D48]"
        />
        
        <!-- Liste déroulante des suggestions de stock -->
        <div v-if="activeDropdownIndex === index && searchResults.length > 0" class="absolute left-0 right-0 z-[999] mt-1 bg-white border border-slate-200 rounded-lg shadow-2xl max-h-48 overflow-y-auto">
            <div 
                v-for="piece in searchResults" 
                :key="piece.id"
                @mousedown.prevent="selectionnerPiece(ligne, piece)"
                class="px-3 py-2 hover:bg-slate-100 cursor-pointer text-xs border-b border-slate-100 last:border-none flex justify-between items-center"
            >
                <div>
                    <span class="font-bold text-slate-800 uppercase">{{ piece.designation_piece }}</span>
                    <span class="text-[10px] text-slate-400 block" v-if="piece.reference">Réf: {{ piece.reference }}</span>
                </div>
                <span class="font-mono text-[#E11D48] font-semibold">{{ piece.prix_kagnan_ht || piece.prix_marche_ht || 0 }} F</span>
            </div>
        </div>
    </div>
</td>
                                        <!-- Référence -->
                                        <td class="px-3 py-3">
                                            <input 
                                                type="text" 
                                                v-model="ligne.reference_piece" 
                                                placeholder="Réf..."
                                                class="w-full bg-white border border-slate-300 rounded-lg text-xs uppercase text-slate-900 focus:border-[#E11D48] focus:ring-1 focus:ring-[#E11D48]"
                                            />
                                        </td>
                                        <!-- Bouton associer pièce -->
                                        <td class="px-3 py-3 text-center">
                                            <button type="button" @click="rechercherPiecesStock(ligne, index)" class="w-7 h-7 bg-white hover:bg-slate-200 text-slate-500 hover:text-slate-900 rounded-lg flex items-center justify-center border border-slate-300 transition mx-auto shadow-sm" title="Rechercher une pièce dans le stock">
                                                <i class="fa-solid fa-magnifying-glass text-[11px]"></i>
                                            </button>
                                        </td>
                                        <!-- PU Net -->
                                        <td class="px-3 py-3">
                                            <input 
                                                type="number" 
                                                v-model="ligne.pu_net" 
                                                step="0.01"
                                                class="w-full bg-white border border-slate-300 rounded-lg text-xs text-right text-slate-900 focus:border-[#E11D48] focus:ring-1 focus:ring-[#E11D48]"
                                            />
                                        </td>
                                        <!-- Remise -->
                                        <td class="px-3 py-3">
                                            <input 
                                                type="number" 
                                                v-model="ligne.remise" 
                                                step="0.01"
                                                class="w-full bg-white border border-slate-300 rounded-lg text-xs text-right text-slate-900 focus:border-[#E11D48] focus:ring-1 focus:ring-[#E11D48]"
                                            />
                                        </td>
                                        <!-- Montant HT (Calculé) -->
                                        <td class="px-3 py-3 text-right">
                                            <span class="font-mono font-semibold text-slate-700">
                                                {{ calculerMontantHt(ligne).toLocaleString() }}
                                            </span>
                                        </td>
                                        <!-- Total TTC (Calculé) -->
                                        <td class="px-3 py-3 text-right">
                                            <span class="font-mono font-bold text-slate-900">
                                                {{ calculerTotalTtc(ligne).toLocaleString() }}
                                            </span>
                                        </td>
                                        <!-- Exempt TVA -->
                                        <td class="px-3 py-3 text-center">
                                            <input 
                                                type="checkbox" 
                                                v-model="ligne.ne_pas_appliquer_tva" 
                                                class="rounded bg-white border-slate-300 text-[#E11D48] focus:ring-[#E11D48] w-4 h-4 cursor-pointer"
                                            />
                                        </td>
                                        <!-- Supprimer -->
                                        <td class="px-3 py-3 text-center">
                                            <button 
                                                type="button" 
                                                @click="supprimerLigne(index)"
                                                class="w-7 h-7 bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white rounded-lg flex items-center justify-center transition mx-auto border border-rose-200"
                                                title="Supprimer la ligne"
                                            >
                                                <i class="fa-solid fa-trash-can text-[11px]"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Barre d'ajout de ligne -->
                        <div class="p-4 bg-slate-100/70 border-t border-slate-200 flex justify-between items-center">
                            <span class="text-xs text-slate-500">Ajoutez des lignes de pièces ou de main-d'œuvre selon les besoins de l'intervention.</span>
                            <button 
                                type="button" 
                                @click="ajouterLigne"
                                class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 flex items-center gap-2 transition shadow-sm"
                            >
                                <i class="fa-solid fa-plus text-[#E11D48]"></i>
                                <span>Ajouter une ligne</span>
                            </button>
                        </div>
                    </div>

                    <!-- TOTAUX ET VALIDATION FINALE -->
                    <div class="bg-slate-50 p-6 rounded-xl shadow-sm border border-slate-200 flex flex-col md:flex-row justify-between items-center gap-6">
                        <div class="w-full md:w-1/2 text-xs text-slate-500 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-[#E11D48] shrink-0 shadow-sm">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <p>Vérifiez scrupuleusement les quantités, les prix unitaires et l'application ou non de la TVA avant d'émettre officiellement le devis client.</p>
                        </div>

                        <div class="w-full md:w-auto flex flex-col items-end gap-2 text-right">
                            <div class="text-xs text-slate-500 flex items-center gap-2">
                                <span>Total Général HT :</span>
                                <span class="font-mono text-sm font-semibold text-slate-700">{{ totalGeneralHt.toLocaleString() }} FCFA</span>
                            </div>
                            <div class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                                <span>Total Général TTC :</span>
                                <span class="font-mono text-lg text-[#E11D48]">{{ totalGeneralTtc.toLocaleString() }} FCFA</span>
                            </div>

                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="mt-4 px-6 py-3 bg-[#E11D48] hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-md transition-all disabled:opacity-50 flex items-center gap-2"
                            >
                                <i class="fa-solid fa-check"></i>
                                <span>Enregistrer et émettre le devis</span>
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>