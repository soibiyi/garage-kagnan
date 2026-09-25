<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios'; // Import d'Axios pour la recherche de pièces

const props = defineProps({
    vehicules: Array, // Gardé au cas où, mais non utilisé vu qu'on est en mode manuel direct
});

// Variables d'état pour l'autocomplétion des pièces
const activeDropdownIndex = ref(null);
const searchResults = ref([]);

const form = useForm({
    vehicule_id: '',
    nouveau_client_nom: '',
    nouveau_client_prenom: '', 
    nouvelle_marque: '',
    nouveau_modele: '',
    remarques: '',
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

// --- LOGIQUE D'AUTOCOMPLÉTION DES PIÈCES ---
const rechercherPiecesStock = (ligne, index) => {
    activeDropdownIndex.value = index;
    const query = ligne.designation;

    if (!query || query.trim().length === 0) {
        searchResults.value = [];
        return;
    }

    // Appel de la route spécifique aux devis directs
    axios.get(route('administration.devis.directs.rechercher-pieces'), { 
        params: { 
            q: query,
            marque: form.nouvelle_marque,
            modele: form.nouveau_modele
        } 
    })
    .then(response => {
        searchResults.value = response.data;
    })
    .catch(error => {
        console.error("Erreur lors de la recherche de pièces :", error);
        searchResults.value = [];
    });
};

const selectionnerPiece = (ligne, piece) => {
    ligne.designation = piece.designation_piece || '';
    ligne.reference_piece = piece.reference || '';
    ligne.pu_net = piece.prix_kagnan_ht || piece.prix_marche_ht || 0;
    activeDropdownIndex.value = null;
    searchResults.value = [];
};

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
    form.post(route('administration.devis.directs.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Création d'un Devis Direct" />

    <AuthenticatedLayout>
        <!-- En-tête de page -->
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <Link :href="route('administration.devis.directs.index')" class="hover:text-slate-900 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-arrow-left"></i><span>Retour aux devis directs</span>
                        </Link>
                    </div>
                    <h2 class="text-xl font-bold tracking-tight text-slate-900 mt-2 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Nouveau Devis Direct (Comptoir)</span>
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <form @submit.prevent="submitDevis" class="space-y-6">
                    
                    <!-- SECTION CLIENT & VÉHICULE (Saisie Manuelle Directe) -->
                    <!-- SECTION CLIENT & VÉHICULE (Saisie Manuelle Directe allégée) -->
<div class="bg-slate-50 p-5 rounded-xl shadow-sm border border-slate-200 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
            <i class="fa-solid fa-car text-[#E11D48]"></i>
            <span>Informations du Client & Véhicule</span>
        </h3>
    </div>

    <!-- Formulaire direct allégé (4 colonnes) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nom du Client</label>
            <input type="text" v-model="form.nouveau_client_nom" placeholder="Ex: Kouassi" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Prénom du Client</label>
            <input type="text" v-model="form.nouveau_client_prenom" placeholder="Ex: Jean" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Marque du Véhicule</label>
            <input type="text" v-model="form.nouvelle_marque" placeholder="Ex: Toyota" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Modèle du Véhicule</label>
            <input type="text" v-model="form.nouveau_modele" placeholder="Ex: Corolla" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
        </div>
    </div>
</div>

                    <!-- TABLEAU DE SAISIE DU DEVIS -->
                    <div class="bg-slate-50 rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <thead class="bg-slate-100 text-slate-500 uppercase tracking-wider text-[10px]">
                                    <tr>
                                        <th class="px-3 py-3 font-semibold w-24 text-center">Qté</th>
                                        <th class="px-3 py-3 font-semibold">Désignation (Autocomplétion)</th>
                                        <th class="px-3 py-3 font-semibold w-32">Réf. Pièce</th>
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
                                            <input type="number" v-model="ligne.quantite" min="0" step="any" class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-xs text-center font-bold text-slate-900 focus:border-[#E11D48]" />
                                        </td>
                                        
                                        <!-- Désignation avec Autocomplétion intégrée -->
                                        <td class="px-3 py-3 overflow-visible">
                                            <div class="relative w-full">
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
                                                
                                                <!-- Dropdown de résultats -->
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
                                            <input type="text" v-model="ligne.reference_piece" placeholder="Réf..." class="w-full bg-white border border-slate-300 rounded-lg text-xs uppercase text-slate-900 focus:border-[#E11D48]" />
                                        </td>
                                        
                                        <!-- PU Net -->
                                        <td class="px-3 py-3">
                                            <input type="number" v-model="ligne.pu_net" step="0.01" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-right text-slate-900 focus:border-[#E11D48]" />
                                        </td>
                                        
                                        <!-- Remise -->
                                        <td class="px-3 py-3">
                                            <input type="number" v-model="ligne.remise" step="0.01" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-right text-slate-900 focus:border-[#E11D48]" />
                                        </td>
                                        
                                        <!-- Montant HT -->
                                        <td class="px-3 py-3 text-right">
                                            <span class="font-mono font-semibold text-slate-700">{{ calculerMontantHt(ligne).toLocaleString() }}</span>
                                        </td>
                                        
                                        <!-- Total TTC -->
                                        <td class="px-3 py-3 text-right">
                                            <span class="font-mono font-bold text-slate-900">{{ calculerTotalTtc(ligne).toLocaleString() }}</span>
                                        </td>
                                        
                                        <!-- Exempt TVA -->
                                        <td class="px-3 py-3 text-center">
                                            <input type="checkbox" v-model="ligne.ne_pas_appliquer_tva" class="rounded bg-white border-slate-300 text-[#E11D48] w-4 h-4 cursor-pointer" />
                                        </td>
                                        
                                        <!-- Supprimer -->
                                        <td class="px-3 py-3 text-center">
                                            <button type="button" @click="supprimerLigne(index)" class="w-7 h-7 bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white rounded-lg flex items-center justify-center transition mx-auto border border-rose-200">
                                                <i class="fa-solid fa-trash-can text-[11px]"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Barre d'ajout de ligne -->
                        <div class="p-4 bg-slate-100/70 border-t border-slate-200 flex justify-between items-center">
                            <span class="text-xs text-slate-500">Ajoutez des lignes de pièces ou de main-d'œuvre selon les besoins.</span>
                            <button type="button" @click="ajouterLigne" class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 flex items-center gap-2 transition shadow-sm">
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
                            <p>Vérifiez scrupuleusement les informations client, les quantités, les prix unitaires et l'application ou non de la TVA avant d'émettre officiellement le devis direct.</p>
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

                            <button type="submit" :disabled="form.processing" class="mt-4 px-6 py-3 bg-[#E11D48] hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-md transition-all disabled:opacity-50 flex items-center gap-2">
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