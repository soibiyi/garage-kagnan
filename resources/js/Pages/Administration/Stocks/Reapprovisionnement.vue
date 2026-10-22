<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    stocks: Array
});

// État de la recherche
const searchQuery = ref('');

// Préparation des articles présélectionnés
const articlesSelectionnes = ref(
    props.stocks.map(item => {
        const totalSorties = item.sieges?.reduce((acc, s) => acc + s.sortie, 0) ?? 0;
        const stockRestant = item.quantite - totalSorties;
        return {
            id: item.id,
            reference: item.reference || '',
            designation: item.designation_piece,
            stock_restant: stockRestant,
            quantite_a_commander: Math.max(1, 10 - stockRestant),
            prix_estime: item.prix_achat || 0,
            selectionne: true
        };
    })
);

// Articles filtrés par la recherche
const articlesFiltres = computed(() => {
    if (!searchQuery.value.trim()) return articlesSelectionnes.value;
    const query = searchQuery.value.toLowerCase();
    return articlesSelectionnes.value.filter(item => 
        item.designation.toLowerCase().includes(query) || 
        item.reference.toLowerCase().includes(query)
    );
});

const form = useForm({
    fournisseur_nom: '',
    fournisseur_contact: '',
    articles: []
});

const toutCocher = ref(true);

const toggleToutCocher = () => {
    articlesFiltres.value.forEach(a => a.selectionne = toutCocher.value);
};

const genererCommande = () => {
    const retenus = articlesSelectionnes.value.filter(a => a.selectionne);
    if (retenus.length === 0) {
        alert('Veuillez sélectionner au moins une pièce à commander.');
        return;
    }

    form.articles = retenus;
    form.post(route('administration.stocks.bon-commande'));
};

const totalEstime = computed(() => {
    return articlesSelectionnes.value
        .filter(a => a.selectionne)
        .reduce((sum, a) => sum + (a.quantite_a_commander * (a.prix_estime || 0)), 0);
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- En-tête -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <div class="flex items-center gap-2">
                        <Link :href="route('administration.stocks.index')" class="text-xs font-bold text-slate-400 hover:text-slate-700">
                            &larr; Retour aux stocks
                        </Link>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 mt-1">Assistant de Réapprovisionnement</h1>
                    <p class="text-sm text-slate-500">Générez un bon de commande fournisseur pour réapprovisionner les pièces en alerte de stock (&le; 5).</p>
                </div>

                <div class="bg-amber-50 border border-amber-200 p-3 rounded-xl flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-500 text-white flex items-center justify-center font-black text-lg">
                        {{ props.stocks.length }}
                    </div>
                    <div>
                        <div class="text-xs font-bold text-amber-900">Références sous le seuil</div>
                        <div class="text-[11px] text-amber-700">Stock restant critique (&le; 5 unités)</div>
                    </div>
                </div>
            </div>

            <!-- Formulaire Fournisseur & Table -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Colonne Informations Fournisseur -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4 h-fit">
                    <h3 class="text-base font-black text-slate-900 border-b pb-3">Informations Fournisseur</h3>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nom du Fournisseur *</label>
                        <input 
                            type="text" 
                            v-model="form.fournisseur_nom" 
                            placeholder="ex: CFAO Motors, SIFAM..." 
                            class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5 font-medium"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Contact / Téléphone / Email</label>
                        <input 
                            type="text" 
                            v-model="form.fournisseur_contact" 
                            placeholder="ex: +225 07 00 00 00 00" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5 font-medium"
                        />
                    </div>

                    <div class="pt-4 border-t space-y-2">
                        <div class="flex justify-between text-xs text-slate-600">
                            <span>Articles à commander :</span>
                            <span class="font-bold text-slate-900">{{ articlesSelectionnes.filter(a => a.selectionne).length }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-black text-slate-900">
                            <span>Total Estimé :</span>
                            <span class="text-emerald-600">{{ totalEstime.toLocaleString() }} FCFA</span>
                        </div>
                    </div>

                    <button 
                        @click="genererCommande" 
                        :disabled="form.processing"
                        class="w-full py-3 bg-[#C8102E] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition flex items-center justify-center gap-2 shadow-lg shadow-rose-600/20"
                    >
                        <i class="fa-solid fa-file-pdf"></i> Générer le Bon de Commande
                    </button>
                </div>

                <!-- Colonne Liste des pièces sous le seuil -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                    <!-- Barre de recherche et sélection -->
                    <div class="p-4 bg-slate-50 border-b flex flex-col sm:flex-row justify-between items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                            <input type="checkbox" v-model="toutCocher" @change="toggleToutCocher" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                            Tout sélectionner / Désélectionner
                        </label>
                        
                        <!-- Barre de recherche -->
                        <div class="relative w-full sm:w-64">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input 
                                type="text" 
                                v-model="searchQuery" 
                                placeholder="Filtrer par réf. ou nom..." 
                                class="w-full bg-white border border-slate-200 rounded-lg text-xs pl-8 pr-3 py-1.5 font-medium focus:outline-none focus:ring-1 focus:ring-slate-900"
                            />
                        </div>
                    </div>

                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50 border-b text-[11px] font-bold text-slate-400 uppercase">
                                    <th class="p-3 w-10 text-center">Coil</th>
                                    <th class="p-3">Référence & Désignation</th>
                                    <th class="p-3 text-center">Stock Restant</th>
                                    <th class="p-3 w-32">Qté à Commander</th>
                                    <th class="p-3 text-right">Prix Unitaire Est.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-xs">
                                <tr v-for="item in articlesFiltres" :key="item.id" class="hover:bg-slate-50" :class="!item.selectionne ? 'opacity-40' : ''">
                                    <td class="p-3 text-center">
                                        <input type="checkbox" v-model="item.selectionne" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                                    </td>
                                    <td class="p-3">
                                        <div class="font-bold text-slate-900">{{ item.designation }}</div>
                                        <div class="font-mono text-[10px] text-slate-400">{{ item.reference || 'Sans réf.' }}</div>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                            {{ item.stock_restant }} un.
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <input 
                                            type="number" 
                                            v-model.number="item.quantite_a_commander" 
                                            min="1" 
                                            class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-1.5 font-bold text-slate-900 text-center" 
                                        />
                                    </td>
                                    <td class="p-3 text-right font-semibold text-slate-700">
                                        <input 
                                            type="number" 
                                            v-model.number="item.prix_estime" 
                                            min="0" 
                                            class="w-28 bg-slate-50 border border-slate-200 rounded-lg text-xs p-1.5 font-semibold text-slate-900 text-right inline-block" 
                                        /> FCFA
                                    </td>
                                </tr>
                                <tr v-if="articlesFiltres.length === 0">
                                    <td colspan="5" class="p-8 text-center text-slate-400">
                                        <i class="fa-solid fa-magnifying-glass text-slate-300 text-3xl mb-2"></i>
                                        <div>Aucune pièce ne correspond à votre recherche.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>