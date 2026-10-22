<script setup>
import { computed } from 'vue';

const props = defineProps({
    commande: Object
});

const imprimer = () => {
    window.print();
};

const totalHT = computed(() => {
    return props.commande.articles.reduce((acc, item) => acc + (item.quantite_a_commander * (item.prix_estime || 0)), 0);
});
</script>

<template>
    <div class="min-h-screen bg-slate-100 p-4 sm:p-8 flex flex-col items-center">
        <!-- Barre d'actions (masquée lors de l'impression) -->
        <div class="max-w-3xl w-full flex justify-between items-center mb-6 print:hidden">
            <button 
                @click="window.history.back()" 
                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm"
            >
                <i class="fa-solid fa-arrow-left"></i> Retour
            </button>
            <button @click="imprimer" class="px-6 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition flex items-center gap-2 shadow-lg">
                <i class="fa-solid fa-print"></i> Imprimer / Enregistrer en PDF
            </button>
        </div>

        <!-- Document Papier A4 -->
        <div class="bg-white max-w-3xl w-full p-8 sm:p-12 rounded-2xl shadow-xl print:shadow-none print:p-0 border border-slate-200 print:border-none text-slate-800">
            <!-- En-tête -->
            <div class="flex justify-between items-start border-b-2 border-slate-900 pb-6 mb-6">
                <div>
                    <h1 class="text-2xl font-black uppercase tracking-wider text-slate-900">GARAGE KAGNAN</h1>
                    <p class="text-xs text-slate-500 font-medium">Gestion du Stock & Pièces Détachées</p>
                    <p class="text-xs text-slate-500">Côte d'Ivoire</p>
                </div>
                <div class="text-right">
                    <span class="inline-block px-3 py-1 bg-slate-900 text-white text-xs font-black rounded mb-1">
                        BON DE COMMANDE
                    </span>
                    <div class="text-xs font-mono font-bold text-slate-700">{{ commande.numero }}</div>
                    <div class="text-xs text-slate-500">Date : {{ commande.date }}</div>
                </div>
            </div>

            <!-- Destinataire (Fournisseur) -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Fournisseur Destinataire</div>
                <div class="text-sm font-black text-slate-900">{{ commande.fournisseur_nom }}</div>
                <div v-if="commande.fournisseur_contact" class="text-xs text-slate-600 font-medium mt-0.5">
                    Contact : {{ commande.fournisseur_contact }}
                </div>
            </div>

            <!-- Tableau des Articles -->
            <table class="w-full text-left border-collapse mb-8">
                <thead>
                    <tr class="border-b-2 border-slate-300 text-[11px] font-black uppercase text-slate-500">
                        <th class="py-2">Réf.</th>
                        <th class="py-2">Désignation</th>
                        <th class="py-2 text-center">Quantité</th>
                        <th class="py-2 text-right">Prix Unitaire Est.</th>
                        <th class="py-2 text-right">Total Est.</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-xs">
                    <tr v-for="(item, index) in commande.articles" :key="index">
                        <td class="py-3 font-mono font-bold text-slate-500">{{ item.reference || '-' }}</td>
                        <td class="py-3 font-bold text-slate-900">{{ item.designation }}</td>
                        <td class="py-3 text-center font-black text-slate-900">{{ item.quantite_a_commander }}</td>
                        <td class="py-3 text-right font-medium text-slate-600">
                            {{ (item.prix_estime || 0).toLocaleString() }} FCFA
                        </td>
                        <td class="py-3 text-right font-bold text-slate-900">
                            {{ (item.quantite_a_commander * (item.prix_estime || 0)).toLocaleString() }} FCFA
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Total -->
            <div class="flex justify-end border-t-2 border-slate-900 pt-4 mb-12">
                <div class="w-64 space-y-2">
                    <div class="flex justify-between text-sm font-black text-slate-900">
                        <span>TOTAL ESTIMÉ HT :</span>
                        <span class="text-rose-600">{{ totalHT.toLocaleString() }} FCFA</span>
                    </div>
                </div>
            </div>

            <!-- Signatures -->
            <div class="grid grid-cols-2 gap-8 text-center text-xs font-bold pt-12 border-t border-dashed border-slate-300">
                <div>
                    <div class="mb-12 text-slate-400">Le Responsable du Stock</div>
                    <div class="border-b border-slate-400 w-32 mx-auto"></div>
                </div>
                <div>
                    <div class="mb-12 text-slate-400">Cachet & Signature Fournisseur</div>
                    <div class="border-b border-slate-400 w-32 mx-auto"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body {
        background-color: white !important;
    }
}
</style>