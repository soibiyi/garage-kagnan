<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    stocks: Object,
    filters: Object
});

const SIEGES = ['Terminus 27', 'Zone Industrielle', 'Yamoussoukro'];
const SEUIL_STOCK_BAS = 5;

const search = ref(props.filters.search || '');
const showModal = ref(false);
const currentStock = ref(null);

// Formulaire pour la gestion des attributions, sorties et du motif par siège
const form = useForm({
    sieges: SIEGES.map(siege => ({ siege, quantite_attribuee: 0, sortie: 0 })),
    motif: '',
});

// ---------- Helpers affichage tableau ----------
const totalSorties = (item) => (item.sieges ?? []).reduce((a, s) => a + Number(s.sortie || 0), 0);
const totalAttribue = (item) => (item.sieges ?? []).reduce((a, s) => a + Number(s.quantite_attribuee || 0), 0);
const stockRestant = (item) => Number(item.quantite || 0) - totalSorties(item);
const nonAttribue = (item) => Number(item.quantite || 0) - totalAttribue(item);
const restantSiege = (s) => Number(s.quantite_attribuee || 0) - Number(s.sortie || 0);

// ---------- Helpers formulaire (distribution) ----------
const quantiteLot = computed(() => Number(currentStock.value?.quantite || 0));
const totalAttribueForm = computed(() =>
    form.sieges.reduce((a, s) => a + Number(s.quantite_attribuee || 0), 0)
);
const resteAAttribuer = computed(() => quantiteLot.value - totalAttribueForm.value);
const distributionValide = computed(() =>
    resteAAttribuer.value >= 0 &&
    form.sieges.every(s => Number(s.sortie || 0) <= Number(s.quantite_attribuee || 0))
);

const repartirEquitablement = () => {
    const total = quantiteLot.value;
    const n = form.sieges.length;
    const base = Math.floor(total / n);
    let reste = total - base * n;
    form.sieges.forEach((s) => {
        s.quantite_attribuee = base + (reste > 0 ? 1 : 0);
        if (reste > 0) reste--;
        if (Number(s.sortie || 0) > s.quantite_attribuee) s.sortie = s.quantite_attribuee;
    });
};

// ---------- Modal ----------
const openEditModal = (item) => {
    currentStock.value = item;
    form.clearErrors();
    form.motif = '';

    form.sieges = SIEGES.map(nomSiege => {
        const existant = item.sieges?.find(s => s.siege === nomSiege);
        return {
            siege: nomSiege,
            quantite_attribuee: existant ? existant.quantite_attribuee : 0,
            sortie: 0, // Remis à 0 pour saisir uniquement la nouvelle sortie du moment
        };
    });

    showModal.value = true;
};

const submit = () => {
    if (!distributionValide.value) return;

    form.put(route('magasinier.pieces.update', currentStock.value.id), {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; }
    });
};

const handleSearch = () => {
    router.get(route('magasinier.pieces.index'), {
        search: search.value
    }, { preserveState: true, replace: true });
};

// Exporter vers Excel (CSV) pour le magasinier
const exporterExcel = () => {
    window.location.href = route('administration.stocks.export');
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- En-tête avec boutons parfaitement alignés -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Espace Magasinier - Catalogue & Gestion des Mouvements</h1>
                    <p class="text-sm text-slate-500">Consultez l'ensemble du catalogue, le stock restant par siège, et enregistrez la répartition par siège et les sorties atelier.</p>
                </div>
                <!-- Bloc d'actions alignées uniformément -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <Link :href="route('magasinier.stocks.historique')" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-violet-600 text-white text-xs font-bold rounded-xl hover:bg-violet-700 transition shadow-sm">
                        <i class="fa-solid fa-clock-rotate-left"></i> Historique
                    </Link>
                    <Link :href="route('administration.stocks.reapprovisionnement')" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-amber-500 text-white text-xs font-bold rounded-xl hover:bg-amber-600 transition shadow-sm">
                        <i class="fa-solid fa-cart-shopping"></i> Réappro.
                    </Link>
                    <button @click="exporterExcel" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition shadow-sm">
                        <i class="fa-solid fa-file-excel"></i> Exporter
                    </button>
                </div>
            </div>

            <!-- Barre de recherche -->
            <div class="flex flex-col sm:flex-row items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                <input
                    type="text"
                    v-model="search"
                    @keyup.enter="handleSearch"
                    placeholder="Rechercher par référence, désignation, famille, marque, modèle..."
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
                                <th class="p-3">Date</th>
                                <th class="p-3">Référence</th>
                                <th class="p-3">Désignation</th>
                                <th class="p-3">Famille / Sous-famille</th>
                                <th class="p-3">Marque / Modèle</th>
                                <th class="p-3 text-right">Prix Kagnan HT</th>
                                <th class="p-3">Qté Initiale</th>
                                <th class="p-3">Sièges (Attrib. / Sortie → Restant)</th>
                                <th class="p-3 text-right">Prix d'achat</th>
                                <th class="p-3 text-right">Marge</th>
                                <th class="p-3 text-right">Total Sorties</th>
                                <th class="p-3 text-right">Stock Restant</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-xs text-slate-700">
                            <tr v-for="item in stocks.data" :key="item.id" class="hover:bg-slate-50">
                                <td class="p-3 text-slate-500">{{ item.date_entree || '-' }}</td>
                                <td class="p-3 font-mono font-semibold">{{ item.reference || '-' }}</td>
                                <td class="p-3 font-bold text-slate-900">
                                    {{ item.designation_piece }}
                                    <div v-if="stockRestant(item) <= SEUIL_STOCK_BAS" class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Stock bas (&le; {{ SEUIL_STOCK_BAS }})
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
                                <td class="p-3 text-right font-bold text-slate-900">{{ item.prix_kagnan_ht ? item.prix_kagnan_ht + ' FCFA' : '-' }}</td>
                                <td class="p-3 font-medium">{{ item.quantite ?? 0 }}</td>

                                <td class="p-3 text-[11px] min-w-[230px]">
                                    <div v-for="s in (item.sieges || [])" :key="s.id"
                                         class="mb-1 flex items-center justify-between gap-3 bg-slate-50 rounded-lg px-2 py-1">
                                        <span class="font-semibold text-slate-900">{{ s.siege }}</span>
                                        <span class="flex items-center gap-1.5">
                                            <span class="text-blue-600 font-semibold" title="Attribué">{{ s.quantite_attribuee }}</span>
                                            <span class="text-slate-300">/</span>
                                            <span class="text-rose-600 font-semibold" title="Sorties">{{ s.sortie }}</span>
                                            <span class="text-slate-300">&rarr;</span>
                                            <span class="px-1.5 py-0.5 rounded font-black"
                                                  :class="restantSiege(s) <= SEUIL_STOCK_BAS ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                                                  title="Restant">{{ restantSiege(s) }}</span>
                                        </span>
                                    </div>
                                    <div v-if="nonAttribue(item) > 0" class="text-[10px] font-bold text-violet-600 mt-1">
                                        <i class="fa-solid fa-box-open mr-1"></i>{{ nonAttribue(item) }} non attribué(s)
                                    </div>
                                    <span v-if="!item.sieges || item.sieges.length === 0" class="text-slate-400 italic">Aucune attribution</span>
                                </td>

                                <td class="p-3 text-right font-semibold">{{ item.prix_achat ? item.prix_achat + ' FCFA' : '-' }}</td>
                                <td class="p-3 text-right font-semibold text-blue-600">{{ item.marge_vente ? item.marge_vente + ' FCFA' : '-' }}</td>
                                <td class="p-3 text-right text-rose-600 font-semibold">
                                    {{ totalSorties(item) }}
                                </td>
                                <td class="p-3 text-right font-black" :class="stockRestant(item) <= SEUIL_STOCK_BAS ? 'text-amber-600' : 'text-slate-900'">
                                    {{ stockRestant(item) }}
                                </td>
                                <td class="p-3 text-right">
                                    <button @click="openEditModal(item)" class="px-3 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-bold hover:bg-[#C8102E] transition">
                                        Gérer Sièges & Sorties
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="stocks.data.length === 0">
                                <td colspan="13" class="p-8 text-center text-slate-400">Aucune pièce trouvée dans le stock.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

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

            <!-- Modal de gestion par siège & motif -->
            <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                    <h3 class="text-base font-black text-slate-900">Répartition par Siège & Enregistrement des Sorties</h3>
                    <p class="text-xs text-slate-500 font-medium">
                        {{ currentStock?.designation_piece }} (Réf: {{ currentStock?.reference || 'N/A' }})
                    </p>

                    <div v-if="form.errors.sieges || form.errors.motif" class="p-3 bg-rose-50 border border-rose-200 text-rose-600 rounded-lg text-xs font-semibold">
                        {{ form.errors.sieges || form.errors.motif }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-3">
                        <div class="p-3 rounded-xl border flex items-center justify-between"
                             :class="resteAAttribuer < 0 ? 'bg-rose-50 border-rose-200' : resteAAttribuer === 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-violet-50 border-violet-200'">
                            <p class="text-[11px] text-slate-600">
                                Quantité initiale du lot : <strong class="text-slate-900">{{ quantiteLot }}</strong><br />
                                Déjà attribué : <strong class="text-slate-900">{{ totalAttribueForm }}</strong>
                            </p>
                            <div class="text-right">
                                <p class="text-[10px] uppercase font-bold text-slate-400">Reste à attribuer</p>
                                <p class="text-lg font-black" :class="resteAAttribuer < 0 ? 'text-rose-600' : 'text-slate-900'">{{ resteAAttribuer }}</p>
                            </div>
                        </div>

                        <button type="button" @click="repartirEquitablement"
                                class="w-full py-2 text-xs font-bold bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                            <i class="fa-solid fa-scale-balanced mr-1"></i> Répartir équitablement
                        </button>

                        <div v-for="ligne in form.sieges" :key="ligne.siege" class="bg-slate-50 p-3 rounded-xl border space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-extrabold text-slate-900">{{ ligne.siege }}</h4>
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded"
                                      :class="restantSiege(ligne) <= SEUIL_STOCK_BAS ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'">
                                    Restant : {{ restantSiege(ligne) }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Quantité Attribuée</label>
                                    <input type="number" v-model.number="ligne.quantite_attribuee" class="w-full bg-white border rounded-lg text-xs p-2 font-bold text-blue-600" min="0" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Quantité Sortie (Atelier)</label>
                                    <input type="number" v-model.number="ligne.sortie" class="w-full bg-white border rounded-lg text-xs p-2 font-bold text-rose-600" min="0" :max="ligne.quantite_attribuee" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Motif de sortie / Intervention *</label>
                            <input type="text" v-model="form.motif" placeholder="ex: Réparation véhicule client, maintenance..." class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5 font-medium" required />
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t">
                            <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 text-xs font-bold rounded-xl">Annuler</button>
                            <button type="submit" :disabled="form.processing || !distributionValide"
                                    class="px-4 py-2 bg-[#C8102E] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition disabled:opacity-50">
                                {{ form.processing ? 'Enregistrement...' : 'Valider les modifications' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>