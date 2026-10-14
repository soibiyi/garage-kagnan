<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    stocks: Object,
    filters: Object
});

const search = ref(props.filters.search || '');
const famille = ref(props.filters.famille || '');
const showModal = ref(false);
const currentStock = ref(null);

const form = useForm({
    sieges: [
        { siege: 'Terminus 27', quantite_attribuee: 0, sortie: 0 },
        { siege: 'Zone Industrielle', quantite_attribuee: 0, sortie: 0 },
        { siege: 'Yamoussoukro', quantite_attribuee: 0, sortie: 0 },
    ]
});

const openEditModal = (item) => {
    currentStock.value = item;
    
    const siegesList = ['Terminus 27', 'Zone Industrielle', 'Yamoussoukro'];
    form.sieges = siegesList.map(nomSiege => {
        const existant = item.sieges?.find(s => s.siege === nomSiege);
        return {
            siege: nomSiege,
            quantite_attribuee: existant ? existant.quantite_attribuee : 0,
            sortie: existant ? existant.sortie : 0,
        };
    });

    showModal.value = true;
};

const submit = () => {
    form.put(route('magasinier.pieces.update', currentStock.value.id), {
        onSuccess: () => { showModal.value = false; }
    });
};

const handleSearch = () => {
    router.get(route('magasinier.pieces.index'), { 
        search: search.value,
        famille: famille.value 
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex justify-between items-center bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Espace Magasinier - Catalogue & Gestion des Sorties</h1>
                    <p class="text-sm text-slate-500">Visualisez l'ensemble du stock et gérez les attributions et sorties par siège.</p>
                </div>
            </div>

            <!-- Barre de recherche -->
            <div class="flex flex-col sm:flex-row items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                <input 
                    type="text" 
                    v-model="search" 
                    @keyup.enter="handleSearch" 
                    placeholder="Rechercher par référence, désignation, marque, modèle..." 
                    class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5" 
                />
                <button @click="handleSearch" class="w-full sm:w-auto px-6 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800 transition">
                    Filtrer
                </button>
            </div>

            <!-- Tableau complet du stock -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b text-[11px] font-bold text-slate-400 uppercase">
                                <th class="p-3">Date d'entrée</th>
                                <th class="p-3">Référence</th>
                                <th class="p-3">Désignation</th>
                                <th class="p-3">Famille / Sous-famille</th>
                                <th class="p-3">Marque / Modèle</th>
                                <th class="p-3">Qté Initiale</th>
                                <th class="p-3">Détail Sièges (Attrib. / Sortie)</th>
                                <th class="p-3 text-right">Prix d'achat</th>
                                <th class="p-3 text-right">Marge</th>
                                <th class="p-3 text-right">Total Sorties</th>
                                <th class="p-3 text-right">Stock Global Restant</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-xs text-slate-700">
                            <tr v-for="item in stocks.data" :key="item.id" class="hover:bg-slate-50">
                                <td class="p-3 text-slate-500">{{ item.date_entree || '-' }}</td>
                                <td class="p-3 font-mono font-semibold">{{ item.reference || '-' }}</td>
                                <td class="p-3 font-bold text-slate-900">
                                    {{ item.designation_piece }}
                                    <!-- Alerte stock bas si <= 5 -->
                                    <div v-if="(item.quantite - (item.sieges?.reduce((acc, s) => acc + s.sortie, 0) ?? 0)) <= 5" class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Stock bas (&le; 5)
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="font-semibold text-slate-800">{{ item.famille || '-' }}</div>
                                    <div class="text-[10px] text-slate-400">{{ item.sous_famille || '' }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="font-semibold text-slate-800">{{ item.marque || '-' }}</div>
                                    <div class="text-[10px] text-slate-500">{{ item.modele || '-' }}</div>
                                </td>
                                <td class="p-3 font-medium">{{ item.quantite ?? 0 }}</td>
                                <td class="p-3 text-[11px]">
                                    <div v-for="s in (item.sieges || [])" :key="s.id" class="mb-0.5">
                                        <span class="font-semibold text-slate-900">{{ s.siege }}:</span> 
                                        <span class="text-blue-600 font-semibold">{{ s.quantite_attribuee }}</span> / 
                                        <span class="text-rose-600 font-semibold">{{ s.sortie }}</span>
                                    </div>
                                    <span v-if="!item.sieges || item.sieges.length === 0" class="text-slate-400 italic">Aucune attribution</span>
                                </td>
                                <td class="p-3 text-right font-semibold">{{ item.prix_achat ? item.prix_achat + ' FCFA' : '-' }}</td>
                                <td class="p-3 text-right font-semibold text-blue-600">{{ item.marge_vente ? item.marge_vente + ' FCFA' : '-' }}</td>
                                <td class="p-3 text-right text-rose-600 font-semibold">
                                    {{ item.sieges?.reduce((acc, s) => acc + s.sortie, 0) ?? 0 }}
                                </td>
                                <td class="p-3 text-right font-black" :class="(item.quantite - (item.sieges?.reduce((acc, s) => acc + s.sortie, 0) ?? 0)) <= 5 ? 'text-amber-600' : 'text-slate-900'">
                                    {{ item.quantite - (item.sieges?.reduce((acc, s) => acc + s.sortie, 0) ?? 0) }}
                                </td>
                                <td class="p-3 text-right">
                                    <button @click="openEditModal(item)" class="px-3 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-bold hover:bg-[#C8102E] transition">
                                        Gérer Sièges & Sorties
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="stocks.data.length === 0">
                                <td colspan="12" class="p-8 text-center text-slate-400">Aucune pièce trouvée dans le stock.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination en bas du tableau -->
                <div v-if="stocks.links && stocks.links.length > 3" class="p-4 bg-slate-50 border-t flex justify-center items-center gap-1">
                    <template v-for="(link, index) in stocks.links" :key="index">
                        <Link 
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg border transition"
                            :class="link.active ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 hover:bg-slate-100 border-slate-200'"
                        />
                        <span 
                            v-else 
                            v-html="link.label" 
                            class="px-3 py-1.5 text-xs font-medium text-slate-400 border border-transparent"
                        />
                    </template>
                </div>
            </div>

            <!-- Modal de gestion par siège -->
            <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                    <h3 class="text-base font-black text-slate-900">Répartition par Siège & Enregistrement des Sorties</h3>
                    <p class="text-xs text-slate-500 font-medium">
                        {{ currentStock?.designation_piece }} (Réf: {{ currentStock?.reference || 'N/A' }}) — 
                        <span class="font-bold text-slate-800">Quantité Initiale Lot : {{ currentStock?.quantite ?? 0 }}</span>
                    </p>

                    <div v-if="form.errors.sieges" class="p-3 bg-rose-50 border border-rose-200 text-rose-600 rounded-lg text-xs font-semibold">
                        {{ form.errors.sieges }}
                    </div>
                    
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-3 divide-y">
                            <div v-for="ligne in form.sieges" :key="ligne.siege" class="pt-3 first:pt-0">
                                <h4 class="text-xs font-bold text-slate-900 mb-2">{{ ligne.siege }}</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Quantité Attribuée</label>
                                        <input type="number" v-model.number="ligne.quantite_attribuee" class="w-full bg-slate-50 border rounded-lg text-xs p-2 font-bold text-blue-600" min="0" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Quantité Sortie (Atelier)</label>
                                        <input type="number" v-model.number="ligne.sortie" class="w-full bg-slate-50 border rounded-lg text-xs p-2 font-bold text-rose-600" min="0" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t">
                            <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 text-xs font-bold rounded-xl">Annuler</button>
                            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-[#C8102E] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition">
                                {{ form.processing ? 'Enregistrement...' : 'Valider les modifications' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>