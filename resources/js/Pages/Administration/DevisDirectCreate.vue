<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { FAMILLES, FAMILLE_PAR_DEFAUT } from '@/constants/familles.js';
const props = defineProps({
    vehicules: Array,
});

// Détection automatique de la Famille & Sous-famille selon les mots-clés
const detecterFamilleEtSousFamille = (designation) => {
    if (!designation || designation.trim().length === 0) {
        return { famille: FAMILLE_PAR_DEFAUT, sousFamille: '' };
    }

    const texte = designation.toLowerCase();

    for (const [familleNom, sousFamillesList] of Object.entries(FAMILLES)) {
        for (const sousFamille of sousFamillesList) {
            const sousFamilleLower = sousFamille.toLowerCase();
            if (texte.includes(sousFamilleLower) || texte.includes(familleNom.toLowerCase())) {
                return {
                    famille: familleNom,
                    sousFamille: sousFamille
                };
            }
        }
    }

    return {
        famille: FAMILLE_PAR_DEFAUT,
        sousFamille: ''
    };
};

// Variables d'état pour l'autocomplétion des pièces
const activeDropdownIndex = ref(null);
const searchResults = ref([]);

const form = useForm({
    vehicule_id: '',
    nouveau_client_nom: '',
    nouveau_client_prenom: '', 
    nouveau_client_telephone: '',
    nouvelle_marque: '',
    nouveau_modele: '',
    nouvelle_immatriculation: '',
    kilometrage: '',
    remarques: '',
    petite_fourniture_active: true,
    petite_fourniture_montant: '',
    lignes: [
        {
            quantite: 1,
            designation: 'DIAGNOSTIC TECHNIQUE',
            reference_piece: '',
            famille: FAMILLE_PAR_DEFAUT,
            sous_famille: '',
            pu_net: 10000,
            remise: 0,
            ne_pas_appliquer_tva: true,
        }
    ],
});

// Saisie dans le champ désignation avec autocomplétion & détection auto
const surSaisieDesignation = (ligne, index) => {
    rechercherPiecesStock(ligne, index);

    const res = detecterFamilleEtSousFamille(ligne.designation);
    ligne.famille = res.famille;
    ligne.sous_famille = res.sousFamille;
};

// --- LOGIQUE D'AUTOCOMPLÉTION DES PIÈCES ---
const rechercherPiecesStock = (ligne, index) => {
    activeDropdownIndex.value = index;
    const query = ligne.designation;

    if (!query || query.trim().length === 0) {
        searchResults.value = [];
        return;
    }

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

    const res = detecterFamilleEtSousFamille(ligne.designation);
    ligne.famille = piece.famille || res.famille;
    ligne.sous_famille = piece.sous_famille || res.sousFamille;

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
        famille: FAMILLE_PAR_DEFAUT,
        sous_famille: '',
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
    // Remise en POURCENTAGE du montant de la ligne
    const remise = Math.min(Math.max(parseFloat(ligne.remise) || 0, 0), 100);
    const total = (qte * pu) * (1 - remise / 100);
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

// ─── Petite fourniture : 3 % auto du TTC, ou montant saisi (alors non décochable) ───
const petiteFournitureAuto = computed(() =>
    Math.round(totalGeneralTtc.value * 1.03) - Math.round(totalGeneralTtc.value)
);
const petiteFournitureManuelle = computed(() =>
    form.petite_fourniture_montant !== '' && form.petite_fourniture_montant !== null && form.petite_fourniture_montant !== undefined
);
const petiteFournitureMontant = computed(() => {
    if (!form.petite_fourniture_active || totalGeneralTtc.value <= 0) return 0;
    return petiteFournitureManuelle.value
        ? Math.round(Number(form.petite_fourniture_montant) || 0)
        : petiteFournitureAuto.value;
});
const totalFinalTtc = computed(() => Math.round(totalGeneralTtc.value) + petiteFournitureMontant.value);

// Dès qu'un montant est saisi, la case est cochée et verrouillée
watch(petiteFournitureManuelle, (manuel) => {
    if (manuel) form.petite_fourniture_active = true;
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
                    <h2 class="text-xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Nouveau Devis Direct (Comptoir)</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Établissez un devis direct avec saisie libre ou choix sur pièces.</p>
                </div>

                <Link 
                    :href="route('administration.devis.directs.index')" 
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Retour aux devis directs</span>
                </Link>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <form @submit.prevent="submitDevis" class="space-y-6">
                    
                    <!-- SECTION CLIENT & VÉHICULE -->
                    <div class="bg-slate-50 p-5 rounded-xl shadow-sm border border-slate-200 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-car text-[#E11D48]"></i>
                                <span>Informations du Client & Véhicule</span>
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nom du Client <span class="text-[#E11D48]">*</span></label>
                                <input type="text" v-model="form.nouveau_client_nom" required placeholder="Ex: Kouassi" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
                                <p v-if="form.errors.nouveau_client_nom" class="mt-1 text-[11px] font-bold text-[#E11D48]">{{ form.errors.nouveau_client_nom }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Prénom du Client <span class="text-[#E11D48]">*</span></label>
                                <input type="text" v-model="form.nouveau_client_prenom" required placeholder="Ex: Jean" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
                                <p v-if="form.errors.nouveau_client_prenom" class="mt-1 text-[11px] font-bold text-[#E11D48]">{{ form.errors.nouveau_client_prenom }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Numéro de téléphone</label>
                                <input type="text" v-model="form.nouveau_client_telephone" placeholder="Ex: 0700000000" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
                                <p v-if="form.errors.nouveau_client_telephone" class="mt-1 text-[11px] font-bold text-[#E11D48]">{{ form.errors.nouveau_client_telephone }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Marque du Véhicule <span class="text-[#E11D48]">*</span></label>
                                <input type="text" v-model="form.nouvelle_marque" required placeholder="Ex: Toyota" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
                                <p v-if="form.errors.nouvelle_marque" class="mt-1 text-[11px] font-bold text-[#E11D48]">{{ form.errors.nouvelle_marque }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Modèle du Véhicule <span class="text-[#E11D48]">*</span></label>
                                <input type="text" v-model="form.nouveau_modele" required placeholder="Ex: Corolla" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
                                <p v-if="form.errors.nouveau_modele" class="mt-1 text-[11px] font-bold text-[#E11D48]">{{ form.errors.nouveau_modele }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Immatriculation</label>
                                <input type="text" v-model="form.nouvelle_immatriculation" placeholder="Ex: 1234 AB 01" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
                                <p v-if="form.errors.nouvelle_immatriculation" class="mt-1 text-[11px] font-bold text-[#E11D48]">{{ form.errors.nouvelle_immatriculation }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Kilométrage</label>
                                <input type="number" min="0" v-model="form.kilometrage" placeholder="Ex: 45000" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-[#E11D48]" />
                                <p v-if="form.errors.kilometrage" class="mt-1 text-[11px] font-bold text-[#E11D48]">{{ form.errors.kilometrage }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- TABLEAU DE SAISIE DU DEVIS -->
                    <div class="bg-slate-50 rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <thead class="bg-slate-100 text-slate-500 uppercase tracking-wider text-[10px]">
                                    <tr>
                                        <th class="px-3 py-3 font-semibold w-20 text-center">Qté</th>
                                        <th class="px-3 py-3 font-semibold">Désignation (Autocomplétion)</th>
                                        <th class="px-3 py-3 font-semibold w-36">Famille / Sous-famille</th>
                                        <th class="px-3 py-3 font-semibold w-28">Réf. Pièce</th>
                                        <th class="px-3 py-3 font-semibold w-24 text-right">PU Net</th>
                                        <th class="px-3 py-3 font-semibold w-20 text-right">Remise (%)</th>
                                        <th class="px-3 py-3 font-semibold w-24 text-right">Total HT</th>
                                        <th class="px-3 py-3 font-semibold w-24 text-right">Total TTC</th>
                                        <th class="px-3 py-3 font-semibold text-center w-16">Exempt TVA</th>
                                        <th class="px-3 py-3 font-semibold text-center w-10">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr v-for="(ligne, index) in form.lignes" :key="index" class="hover:bg-slate-100/60 transition-colors">
                                        <!-- Quantité -->
                                        <td class="px-3 py-3 text-center">
                                            <input type="number" v-model="ligne.quantite" min="0" step="any" class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-xs text-center font-bold text-slate-900 focus:border-[#E11D48]" />
                                        </td>
                                        
                                        <!-- Désignation avec Autocomplétion & Détection auto -->
                                        <td class="px-3 py-3 overflow-visible">
                                            <div class="relative w-full">
                                                <input 
                                                    type="text" 
                                                    v-model="ligne.designation" 
                                                    @input="surSaisieDesignation(ligne, index)"
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

                                        <!-- Famille et Sous-Famille -->
                                        <td class="px-3 py-3 space-y-1">
                                            <select 
                                                v-model="ligne.famille" 
                                                class="w-full bg-white border border-slate-300 rounded-lg text-[11px] font-semibold text-slate-800 focus:border-[#E11D48]"
                                            >
                                                <option :value="FAMILLE_PAR_DEFAUT">{{ FAMILLE_PAR_DEFAUT }}</option>
                                                <option v-for="(sousFamilles, nomFamille) in FAMILLES" :key="nomFamille" :value="nomFamille">
                                                    {{ nomFamille }}
                                                </option>
                                            </select>

                                            <select 
                                                v-if="FAMILLES[ligne.famille]" 
                                                v-model="ligne.sous_famille" 
                                                class="w-full bg-slate-50 border border-slate-200 rounded-md text-[10px] text-slate-600"
                                            >
                                                <option value="">-- Sous-famille --</option>
                                                <option v-for="sf in FAMILLES[ligne.famille]" :key="sf" :value="sf">
                                                    {{ sf }}
                                                </option>
                                            </select>
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
                                            <input type="number" v-model="ligne.remise" min="0" max="100" step="0.01" class="w-full bg-white border border-slate-300 rounded-lg text-xs text-right text-slate-900 focus:border-[#E11D48]" />
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
                            <div class="text-xs text-slate-500 flex items-center gap-2">
                                <span>Total TTC des lignes :</span>
                                <span class="font-mono text-sm font-semibold text-slate-700">{{ totalGeneralTtc.toLocaleString() }} FCFA</span>
                            </div>
                            <div class="text-xs text-slate-600 flex flex-wrap items-center justify-end gap-2">
                                <label class="flex items-center gap-1.5" :class="petiteFournitureManuelle ? 'cursor-not-allowed opacity-80' : 'cursor-pointer'">
                                    <input type="checkbox" v-model="form.petite_fourniture_active" :disabled="petiteFournitureManuelle" class="rounded bg-white border-slate-300 text-[#E11D48] w-4 h-4" />
                                    <span class="font-semibold">Petite fourniture</span>
                                </label>
                                <input
                                    type="number" min="0" step="1"
                                    v-model="form.petite_fourniture_montant"
                                    :disabled="!form.petite_fourniture_active"
                                    :placeholder="'Auto 3 % : ' + petiteFournitureAuto.toLocaleString()"
                                    class="w-44 bg-white border border-slate-300 rounded-lg text-xs text-right text-slate-900 focus:border-[#E11D48] disabled:bg-slate-100"
                                />
                                <span class="font-mono text-sm font-semibold text-slate-700">{{ petiteFournitureMontant.toLocaleString() }} FCFA</span>
                            </div>
                            <p v-if="petiteFournitureManuelle" class="text-[10px] text-slate-400">Montant saisi : la case ne peut plus être décochée. Videz le champ pour revenir au calcul automatique.</p>
                            <div class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                                <span>Total Général TTC :</span>
                                <span class="font-mono text-lg text-[#E11D48]">{{ totalFinalTtc.toLocaleString() }} FCFA</span>
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