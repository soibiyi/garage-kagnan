<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    dossier: Object,
});

// Formulaire Inertia incluant les lignes dynamiques du devis
const form = useForm({
    desactiver_restriction: false,
    lignes: [
        {
            quantite: 1,
            designation: 'DIAGNOSTIC',
            reference_piece: '',
            pu_net: 10000,
            remise: 0,
            statut_prestation: false,
            ne_pas_appliquer_tva: false,
        }
    ],
    remarques_devis: '',
});

// Ajouter une nouvelle ligne vide
const ajouterLigne = () => {
    form.lignes.push({
        quantite: 1,
        designation: '',
        reference_piece: '',
        pu_net: 0,
        remise: 0,
        statut_prestation: false,
        ne_pas_appliquer_tva: false,
    });
};

// Supprimer une ligne
const supprimerLigne = (index) => {
    if (form.lignes.length > 1) {
        form.lignes.splice(index, 1);
    }
};

// Calcul du montant HT par ligne (Quantité * PU net - Remise ou calcul adapté)
const calculerMontantHt = (ligne) => {
    const qte = parseFloat(ligne.quantite) || 0;
    const pu = parseFloat(ligne.pu_net) || 0;
    const remise = parseFloat(ligne.remise) || 0;
    const total = (qte * pu) - remise;
    return isNaN(total) ? 0 : total;
};

// Calcul du Total TTC par ligne (application TVA 18% par exemple si non exempté)
const calculerTotalTtc = (ligne) => {
    const ht = calculerMontantHt(ligne);
    if (ligne.ne_pas_appliquer_tva) {
        return ht;
    }
    return ht * 1.18; // TVA standard de 18 p. 100 par exemple
};

// Totaux globaux du devis
const totalGeneralHt = computed(() => {
    return form.lignes.reduce((acc, ligne) => acc + calculerMontantHt(ligne), 0);
});

const totalGeneralTtc = computed(() => {
    return form.lignes.reduce((acc, ligne) => acc + calculerTotalTtc(ligne), 0);
});

// Soumission du devis
const submitDevis = () => {
    form.post(route('administration.devis.store', props.dossier.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Actions après succès
        },
    });
};
</script>

<template>
    <Head :title="`Établissement Devis - Dossier N° ${dossier.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <div class="flex items-center gap-3">
                        <Link :href="route('administration.dossiers.index')" class="text-sm text-gray-500 hover:text-gray-900 transition">
                            ← Retour aux dossiers
                        </Link>
                        <span class="text-gray-300">/</span>
                        <span class="text-xs font-bold px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full">
                            Dossier #{{ dossier.id }}
                        </span>
                    </div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 mt-1">
                        Établissement du Devis & Chiffrage
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- INFORMATIONS RAPIDES ET RESTRICTION -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Véhicule & Client</h4>
                        <p class="text-sm font-bold text-gray-900 uppercase mt-0.5">
                            {{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }} 
                            <span class="text-indigo-600">({{ dossier.vehicule?.immatriculation }})</span>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Client : <span class="font-semibold text-gray-700">{{ dossier.vehicule?.client?.name || dossier.vehicule?.client?.nom || 'N/A' }}</span></p>
                    </div>

                    <!-- Option Restriction -->
                    <div class="bg-red-50 border border-red-200 p-3 rounded-xl flex items-center gap-3">
                        <input 
                            type="checkbox" 
                            id="restriction" 
                            v-model="form.desactiver_restriction"
                            class="rounded border-red-300 text-red-600 focus:ring-red-500 w-4 h-4"
                        >
                        <label for="restriction" class="text-xs text-red-700 font-semibold cursor-pointer">
                            Cocher pour DESACTIVER la restriction ?<br>
                            <span class="text-[10px] font-normal text-red-500">Vous assumerez les responsabilités</span>
                        </label>
                    </div>
                </div>

                <!-- TABLEAU DE SAISIE DU DEVIS -->
                <form @submit.prevent="submitDevis" class="space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th class="px-3 py-3 font-semibold w-20">Quantité</th>
                                    <th class="px-3 py-3 font-semibold">Désignation</th>
                                    <th class="px-3 py-3 font-semibold w-36">Référence pièce</th>
                                    <th class="px-3 py-3 font-semibold w-12 text-center">Pièce</th>
                                    <th class="px-3 py-3 font-semibold w-28">PU net</th>
                                    <th class="px-3 py-3 font-semibold w-24">Remise</th>
                                    <th class="px-3 py-3 font-semibold w-28">Montant HT</th>
                                    <th class="px-3 py-3 font-semibold w-28">Total TTC</th>
                                    <th class="px-3 py-3 font-semibold text-center w-20">Statut</th>
                                    <th class="px-3 py-3 font-semibold text-center w-20">Exempt TVA</th>
                                    <th class="px-3 py-3 font-semibold text-center w-12">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(ligne, index) in form.lignes" :key="index" class="hover:bg-gray-50/50">
                                    <td class="px-3 py-3">
                                        <input 
                                            type="number" 
                                            v-model="ligne.quantite" 
                                            min="0" 
                                            step="any"
                                            class="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center font-bold"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input 
                                            type="text" 
                                            v-model="ligne.designation" 
                                            placeholder="Désignation..."
                                            class="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 uppercase"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input 
                                            type="text" 
                                            v-model="ligne.reference_piece" 
                                            placeholder="Réf..."
                                            class="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 uppercase"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <button type="button" class="w-8 h-8 bg-sky-500 hover:bg-sky-600 text-white rounded-lg flex items-center justify-center shadow-sm mx-auto transition" title="Associer pièce">
                                            +
                                        </button>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input 
                                            type="number" 
                                            v-model="ligne.pu_net" 
                                            step="0.01"
                                            class="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 text-right"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input 
                                            type="number" 
                                            v-model="ligne.remise" 
                                            step="0.01"
                                            class="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 text-right"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input 
                                            type="text" 
                                            :value="calculerMontantHt(ligne).toLocaleString()" 
                                            readonly
                                            class="w-full rounded-lg border-gray-200 bg-gray-100 text-xs text-right font-bold text-gray-700"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input 
                                            type="text" 
                                            :value="calculerTotalTtc(ligne).toLocaleString()" 
                                            readonly
                                            class="w-full rounded-lg border-gray-200 bg-gray-100 text-xs text-right font-bold text-gray-900"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <input 
                                            type="checkbox" 
                                            v-model="ligne.statut_prestation" 
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <input 
                                            type="checkbox" 
                                            v-model="ligne.ne_pas_appliquer_tva" 
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <button 
                                            type="button" 
                                            @click="supprimerLigne(index)"
                                            class="w-8 h-8 bg-rose-600 hover:bg-rose-700 text-white rounded-lg flex items-center justify-center shadow-sm mx-auto transition"
                                            title="Supprimer la ligne"
                                        >
                                            -
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Bouton Ajout de ligne (+) -->
                        <div class="mt-4 flex justify-end">
                            <button 
                                type="button" 
                                @click="ajouterLigne"
                                class="w-10 h-10 bg-sky-500 hover:bg-sky-600 text-white rounded-xl font-bold flex items-center justify-center shadow-sm transition"
                                title="Ajouter une ligne"
                            >
                                +
                            </button>
                        </div>
                    </div>

                    <!-- TOTAUX ET VALIDATION -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row justify-between items-center gap-6">
                        <div class="w-full md:w-1/2 space-y-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase">Remarques / Notes du devis</label>
                            <textarea 
                                v-model="form.remarques_devis" 
                                rows="2" 
                                placeholder="Précisions sur le devis..."
                                class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <div class="w-full md:w-auto flex flex-col items-end gap-2 text-right">
                            <div class="text-sm text-gray-600">
                                Total Général HT : <span class="font-bold text-gray-900">{{ totalGeneralHt.toLocaleString() }} FCFA</span>
                            </div>
                            <div class="text-base font-extrabold text-blue-600">
                                Total Général TTC : <span>{{ totalGeneralTtc.toLocaleString() }} FCFA</span>
                            </div>

                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="mt-3 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition disabled:opacity-50"
                            >
                                Enregistrer et émettre le devis ✓
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>