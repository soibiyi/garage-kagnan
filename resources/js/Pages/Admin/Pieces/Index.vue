<script setup>
import { ref, computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    stocks: Object,
    filters: Object,
    seuilStockBas: { type: Number, default: 5 },
    resumeStock: { type: Object, default: () => ({ total: 0, stock_bas: 0, stock_normal: 0, non_attribues: 0 }) },
    siegesDisponibles: { type: Array, default: () => ['Terminus 27', 'Zone Industrielle', 'Yamoussoukro'] },
});

// Suppression d'une pièce : réservée aux administrateurs
const estAdmin = computed(() => usePage().props.auth.user?.role === 'admin');

const filtresMemorises = (() => {
    if (typeof window === 'undefined') return null;
    const params = new URLSearchParams(window.location.search);
    if (['search', 'filter', 'siege'].some((key) => params.has(key))) return null;
    const filter = sessionStorage.getItem('admin-pieces-filter') || 'tous';
    const siege = sessionStorage.getItem('admin-pieces-siege') || '';

    return {
        search: sessionStorage.getItem('admin-pieces-search') || '',
        filter: ['tous', 'stock_bas', 'stock_normal'].includes(filter) ? filter : 'tous',
        siege: props.siegesDisponibles.includes(siege) ? siege : '',
    };
})();

const search = ref(props.filters?.search || '');
const filtreStock = ref(props.filters?.filter || 'tous');
const siegeFiltre = ref(props.filters?.siege || '');
if (filtresMemorises) {
    search.value = filtresMemorises.search;
    filtreStock.value = filtresMemorises.filter;
    siegeFiltre.value = filtresMemorises.siege;
}
const rechercheEnCours = ref(false);
let searchTimer = null;
const showModal = ref(false);
const showImportModal = ref(false);
const isEditing = ref(false);
const currentStockId = ref(null);
const step = ref(1); // 1 = infos pièce, 2 = distribution aux sièges

const siegesVides = () => props.siegesDisponibles.map(siege => ({ siege, quantite_attribuee: 0, sortie: 0 }));

// Formulaire complet pour l'Admin (Pièce + Sièges + Prix)
const form = useForm({
    date_entree: '',
    reference: '',
    designation_piece: '',
    famille: '',
    sous_famille: '',
    marque: '',
    modele: '',
    quantite: 0,
    prix_achat: '',
    marge_vente: '',
    prix_kagnan_ht: '',
    sieges: siegesVides(),
});

// Formulaire pour l'import CSV
const importForm = useForm({
    fichier: null,
});
const importPreview = ref(null);
const previewEnCours = ref(false);
const erreurApercu = ref('');

// ---------- Helpers affichage tableau ----------
const totalSorties = (item) => (item.sieges ?? []).reduce((a, s) => a + Number(s.sortie || 0), 0);
const totalAttribue = (item) => (item.sieges ?? []).reduce((a, s) => a + Number(s.quantite_attribuee || 0), 0);
const stockRestant = (item) => Number(item.quantite || 0) - totalSorties(item);
const nonAttribue = (item) => Number(item.quantite || 0) - totalAttribue(item);
const restantSiege = (s) => Number(s.quantite_attribuee || 0) - Number(s.sortie || 0);
const siegesAffiches = (item) => siegeFiltre.value
    ? (item.sieges ?? []).filter((siege) => siege.siege === siegeFiltre.value)
    : item.sieges ?? [];
const stockRestantAffiche = (item) => siegeFiltre.value
    ? restantSiege((item.sieges ?? []).find((siege) => siege.siege === siegeFiltre.value) ?? { quantite_attribuee: 0, sortie: 0 })
    : stockRestant(item);
const sortiesAffichees = (item) => siegeFiltre.value
    ? Number((item.sieges ?? []).find((siege) => siege.siege === siegeFiltre.value)?.sortie || 0)
    : totalSorties(item);

// ---------- Helpers formulaire (distribution) ----------
const totalAttribueForm = computed(() =>
    form.sieges.reduce((a, s) => a + Number(s.quantite_attribuee || 0), 0)
);
const resteAAttribuer = computed(() => Number(form.quantite || 0) - totalAttribueForm.value);
const distributionValide = computed(() =>
    resteAAttribuer.value >= 0 &&
    form.sieges.every(s => Number(s.sortie || 0) <= Number(s.quantite_attribuee || 0))
);

const repartirEquitablement = () => {
    const total = Number(form.quantite || 0);
    const n = form.sieges.length;
    const base = Math.floor(total / n);
    let reste = total - base * n;
    form.sieges.forEach((s) => {
        s.quantite_attribuee = base + (reste > 0 ? 1 : 0);
        if (reste > 0) reste--;
    });
};

const goToStep2 = () => {
    if (!form.designation_piece) {
        step.value = 1;
        form.setError('designation_piece', 'La désignation est obligatoire');
        return;
    }
    form.clearErrors();
    step.value = 2;
};

const onSubmit = () => (step.value === 1 ? goToStep2() : submit());

// ---------- Modals ----------
const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.clearErrors();
    form.sieges = siegesVides();
    step.value = 1;
    showModal.value = true;
};

const openEditModal = (item) => {
    isEditing.value = true;
    currentStockId.value = item.id;
    form.clearErrors();
    form.date_entree = item.date_entree || '';
    form.reference = item.reference || '';
    form.designation_piece = item.designation_piece || '';
    form.famille = item.famille || '';
    form.sous_famille = item.sous_famille || '';
    form.marque = item.marque || '';
    form.modele = item.modele || '';
    form.quantite = item.quantite ?? 0;
    form.prix_achat = item.prix_achat || '';
    form.marge_vente = item.marge_vente || '';
    form.prix_kagnan_ht = item.prix_kagnan_ht || '';

    // Charger les attributions et initialiser les sorties à 0 pour la saisie de nouveaux mouvements incrémentaux
    form.sieges = props.siegesDisponibles.map(nomSiege => {
        const existant = item.sieges?.find(s => s.siege === nomSiege);
        return {
            siege: nomSiege,
            quantite_attribuee: existant ? existant.quantite_attribuee : 0,
            sortie: 0, // Remis à 0 pour saisir uniquement la nouvelle quantité additionnelle à sortir
        };
    });

    step.value = 1;
    showModal.value = true;
};

const submit = () => {
    if (!distributionValide.value) return;

    if (isEditing.value) {
        form.put(route('administration.stocks.update', currentStockId.value), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; }
        });
    } else {
        form.post(route('administration.stocks.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                form.sieges = siegesVides();
            }
        });
    }
};

const deletePiece = (id) => {
    if (confirm('Voulez-vous vraiment supprimer cette pièce du stock ?')) {
        router.delete(route('administration.stocks.destroy', id), {
            preserveScroll: true
        });
    }
};

const handleSearch = () => {
    clearTimeout(searchTimer);
    rechercheEnCours.value = true;
    router.get(
        route('admin.pieces.index'),
        {
            search: search.value.trim() || undefined,
            filter: filtreStock.value === 'tous' ? undefined : filtreStock.value,
            siege: siegeFiltre.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => { rechercheEnCours.value = false; },
        }
    );
};

watch([search, filtreStock, siegeFiltre], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(handleSearch, 300);
    if (typeof window !== 'undefined') {
        sessionStorage.setItem('admin-pieces-search', search.value);
        sessionStorage.setItem('admin-pieces-filter', filtreStock.value);
        sessionStorage.setItem('admin-pieces-siege', siegeFiltre.value);
    }
});

onMounted(() => {
    if (!filtresMemorises) return;
    const filtresServeur = props.filters ?? {};
    if (
        filtresMemorises.search !== (filtresServeur.search || '')
        || filtresMemorises.filter !== (filtresServeur.filter || 'tous')
        || filtresMemorises.siege !== (filtresServeur.siege || '')
    ) {
        handleSearch();
    }
});

onBeforeUnmount(() => clearTimeout(searchTimer));

// Exporter vers Excel (CSV)
const exporterExcel = () => {
    window.location.href = route('administration.stocks.export');
};

const openImportModal = () => {
    importForm.reset();
    importForm.clearErrors();
    importPreview.value = null;
    erreurApercu.value = '';
    showImportModal.value = true;
};

const handleFileChange = (e) => {
    importForm.fichier = e.target.files?.[0] ?? null;
    importPreview.value = null;
    erreurApercu.value = '';
    importForm.clearErrors();
};

const analyserImport = async () => {
    if (!importForm.fichier || previewEnCours.value) return;

    previewEnCours.value = true;
    erreurApercu.value = '';
    importPreview.value = null;

    const donnees = new FormData();
    donnees.append('fichier', importForm.fichier);

    try {
        const { data } = await axios.post(route('administration.stocks.import.preview'), donnees);
        importPreview.value = data;
    } catch (error) {
        erreurApercu.value = error.response?.data?.errors?.fichier?.[0]
            || error.response?.data?.message
            || 'Impossible d’analyser le fichier. Vérifiez votre connexion et réessayez.';
    } finally {
        previewEnCours.value = false;
    }
};

const submitImport = () => {
    if (!importForm.fichier || !importPreview.value?.can_import || importForm.processing) return;

    importForm.post(route('administration.stocks.import'), {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            importPreview.value = null;
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Gestion des pièces" />
        <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header avec actions unifiées et parfaitement alignées -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Gestion Globale des Pièces & Stocks (Admin)</h1>
                    <p class="text-sm text-slate-500">Catalogue, prix Kagnan HT, attributions par siège, import/export et alertes de stock.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <Link :href="route('dashboard')" class="inline-flex items-center justify-center px-3.5 py-2.5 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-200 transition shadow-sm">
                        Dashboard
                    </Link>
                    <Link :href="route('administration.stocks.historique')" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 bg-violet-600 text-white text-xs font-bold rounded-xl hover:bg-violet-700 transition shadow-sm">
                        <i class="fa-solid fa-clock-rotate-left"></i> Historique
                    </Link>
                    <button @click="exporterExcel" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition shadow-sm">
                        <i class="fa-solid fa-file-excel"></i> Exporter
                    </button>
                    <button @click="openImportModal" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition shadow-sm">
                        <i class="fa-solid fa-file-import"></i> Importer
                    </button>
                    <button @click="openCreateModal" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 bg-[#E11D48] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition shadow-sm">
                        <i class="fa-solid fa-plus"></i> Ajouter
                    </button>
                </div>
            </div>

            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Résumé du stock">
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Références</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ resumeStock.total }}</p>
                </article>
                <button
                    type="button"
                    class="rounded-xl border p-4 text-left shadow-sm transition"
                    :class="filtreStock === 'stock_bas' ? 'border-amber-400 bg-amber-100' : 'border-amber-200 bg-amber-50 hover:bg-amber-100'"
                    @click="filtreStock = 'stock_bas'; siegeFiltre = ''"
                >
                    <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800">Stock bas global (≤ {{ seuilStockBas }})</p>
                    <p class="mt-1 text-2xl font-black text-amber-900">{{ resumeStock.stock_bas }}</p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border p-4 text-left shadow-sm transition"
                    :class="filtreStock === 'stock_normal' ? 'border-emerald-400 bg-emerald-100' : 'border-emerald-200 bg-emerald-50 hover:bg-emerald-100'"
                    @click="filtreStock = 'stock_normal'; siegeFiltre = ''"
                >
                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-800">Stock normal global</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900">{{ resumeStock.stock_normal }}</p>
                </button>
                <article class="rounded-xl border border-violet-200 bg-violet-50 p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-violet-800">Unités à attribuer</p>
                    <p class="mt-1 text-2xl font-black text-violet-900">{{ resumeStock.non_attribues }}</p>
                </article>
            </section>

            <!-- Barre de recherche -->
            <div class="space-y-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <label for="recherche-pieces" class="sr-only">Rechercher une pièce</label>
                    <div class="relative min-w-0 flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <input
                        id="recherche-pieces"
                        type="search"
                        v-model="search"
                        @keyup.enter="handleSearch"
                        placeholder="Rechercher par référence, désignation, famille, marque..."
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-10 text-xs focus:border-slate-400 focus:ring-slate-300"
                    />
                    <button
                        v-if="search"
                        type="button"
                        @click="search = ''"
                        class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-700"
                        aria-label="Effacer la recherche"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                    </div>
                    <span v-if="rechercheEnCours" class="shrink-0 text-xs font-semibold text-slate-500" role="status" aria-live="polite">
                        <i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i>Recherche…
                    </span>
                    <button v-else @click="handleSearch" class="shrink-0 rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-slate-800">
                        Rechercher
                    </button>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrer les pièces par niveau de stock">
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-bold transition"
                            :class="filtreStock === 'tous' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                            :aria-pressed="filtreStock === 'tous'"
                            @click="filtreStock = 'tous'"
                        >
                            Toutes les pièces
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-bold transition"
                            :class="filtreStock === 'stock_bas' ? 'border-amber-600 bg-amber-600 text-white' : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'"
                            :aria-pressed="filtreStock === 'stock_bas'"
                            @click="filtreStock = 'stock_bas'"
                        >
                            <i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>
                            <template v-if="siegeFiltre">Stock bas au siège (≤ {{ seuilStockBas }})</template>
                            <template v-else>Stock bas (≤ {{ seuilStockBas }})</template>
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs font-bold transition"
                            :class="filtreStock === 'stock_normal' ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'"
                            :aria-pressed="filtreStock === 'stock_normal'"
                            @click="filtreStock = 'stock_normal'"
                        >
                            <i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>
                            <template v-if="siegeFiltre">Stock normal au siège (&gt; {{ seuilStockBas }})</template>
                            <template v-else>Stock normal (&gt; {{ seuilStockBas }})</template>
                        </button>
                    </div>
                    <label class="sr-only" for="filtre-siege">Filtrer par siège</label>
                    <select
                        id="filtre-siege"
                        v-model="siegeFiltre"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 focus:border-slate-400 focus:ring-slate-300"
                    >
                        <option value="">Tous les sièges</option>
                        <option v-for="siege in siegesDisponibles" :key="siege" :value="siege">{{ siege }}</option>
                    </select>
                    </div>
                    <span class="text-xs font-semibold text-slate-500">
                        {{ stocks.total }} pièce{{ stocks.total === 1 ? '' : 's' }} trouvée{{ stocks.total === 1 ? '' : 's' }}
                    </span>
                </div>
            </div>

            <!-- Tableau unique et complet -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="hidden overflow-x-auto lg:block">
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
                                <th class="p-3 text-right">Sorties <span v-if="siegeFiltre">({{ siegeFiltre }})</span><span v-else>totales</span></th>
                                <th class="p-3 text-right">Stock restant <span v-if="siegeFiltre">({{ siegeFiltre }})</span><span v-else>global</span></th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-xs text-slate-700">
                            <tr v-for="item in stocks.data" :key="item.id" class="hover:bg-slate-50">
                                <td class="p-3 text-slate-500">{{ item.date_entree || '-' }}</td>
                                <td class="p-3 font-mono font-semibold">{{ item.reference || '-' }}</td>
                                <td class="p-3 font-bold text-slate-900">
                                    {{ item.designation_piece }}
                                    <div v-if="stockRestantAffiche(item) <= seuilStockBas" class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Stock bas (&le; {{ seuilStockBas }})
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
                                    <div v-for="s in siegesAffiches(item)" :key="s.id"
                                         class="mb-1 flex items-center justify-between gap-3 bg-slate-50 rounded-lg px-2 py-1">
                                        <span class="font-semibold text-slate-900">{{ s.siege }}</span>
                                        <span class="flex items-center gap-1.5">
                                            <span class="text-blue-600 font-semibold" title="Attribué">{{ s.quantite_attribuee }}</span>
                                            <span class="text-slate-300">/</span>
                                            <span class="text-rose-600 font-semibold" title="Sorties">{{ s.sortie }}</span>
                                            <span class="text-slate-300">&rarr;</span>
                                            <span class="px-1.5 py-0.5 rounded font-black"
                                                  :class="restantSiege(s) <= seuilStockBas ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                                                  title="Restant">{{ restantSiege(s) }}</span>
                                        </span>
                                    </div>
                                    <div v-if="nonAttribue(item) > 0" class="text-[10px] font-bold text-violet-600 mt-1">
                                        <i class="fa-solid fa-box-open mr-1"></i>{{ nonAttribue(item) }} non attribué(s)
                                    </div>
                                    <span v-if="!siegesAffiches(item).length" class="text-slate-400 italic">-</span>
                                </td>

                                <td class="p-3 text-right font-semibold">{{ item.prix_achat ? item.prix_achat + ' FCFA' : '-' }}</td>
                                <td class="p-3 text-right font-semibold text-blue-600">{{ item.marge_vente ? item.marge_vente + ' FCFA' : '-' }}</td>
                                <td class="p-3 text-right text-rose-600 font-semibold">
                                    {{ sortiesAffichees(item) }}
                                </td>
                                <td class="p-3 text-right font-black" :class="stockRestantAffiche(item) <= seuilStockBas ? 'text-amber-600' : 'text-slate-900'">
                                    {{ stockRestantAffiche(item) }}
                                </td>
                                <td class="p-3 text-right space-x-1">
                                    <button @click="openEditModal(item)" class="p-1.5 bg-slate-100 rounded-lg hover:bg-slate-200" title="Modifier"><i class="fa-solid fa-pen"></i></button>
                                    <button v-if="estAdmin" @click="deletePiece(item.id)" class="p-1.5 bg-rose-50 text-rose-600 rounded-lg hover:bg-rose-100" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr v-if="stocks.data.length === 0">
                                <td colspan="13" class="p-8 text-center text-slate-400">Aucune pièce trouvée.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 p-3 lg:hidden">
                    <article v-for="item in stocks.data" :key="`mobile-${item.id}`" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-sm font-black text-slate-900">{{ item.designation_piece || 'Pièce sans désignation' }}</h2>
                                <p class="mt-1 truncate font-mono text-[11px] text-slate-500">{{ item.reference || 'Sans référence' }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold"
                                :class="stockRestantAffiche(item) <= seuilStockBas ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                            >
                                {{ stockRestantAffiche(item) <= seuilStockBas ? 'Stock bas' : 'Stock normal' }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Stock restant</p>
                                <p class="mt-1 text-xl font-black" :class="stockRestantAffiche(item) <= seuilStockBas ? 'text-amber-700' : 'text-slate-900'">
                                    {{ stockRestantAffiche(item) }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Lot initial</p>
                                <p class="mt-1 text-xl font-black text-slate-900">{{ item.quantite ?? 0 }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Famille</p>
                                <p class="mt-0.5 font-semibold text-slate-800">{{ item.famille || '—' }}<span v-if="item.sous_famille"> · {{ item.sous_famille }}</span></p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Marque / modèle</p>
                                <p class="mt-0.5 font-semibold text-slate-800">{{ [item.marque, item.modele].filter(Boolean).join(' ') || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Prix Kagnan HT</p>
                                <p class="mt-0.5 font-semibold text-slate-800">{{ item.prix_kagnan_ht ? `${item.prix_kagnan_ht} FCFA` : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Sorties</p>
                                <p class="mt-0.5 font-semibold text-rose-700">{{ sortiesAffichees(item) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-slate-100 pt-3">
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-slate-500">Stock par siège</p>
                            <div v-if="siegesAffiches(item).length" class="space-y-1.5">
                                <div v-for="siege in siegesAffiches(item)" :key="`mobile-${item.id}-${siege.id}`" class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs">
                                    <span class="font-semibold text-slate-800">{{ siege.siege }}</span>
                                    <span class="font-black" :class="restantSiege(siege) <= seuilStockBas ? 'text-amber-700' : 'text-emerald-700'">
                                        {{ restantSiege(siege) }} restant
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-xs italic text-slate-400">Aucune quantité attribuée aux sièges.</p>
                            <p v-if="nonAttribue(item) > 0" class="mt-2 text-xs font-bold text-violet-700">
                                {{ nonAttribue(item) }} pièce(s) non attribuée(s)
                            </p>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                            <span class="text-[11px] text-slate-500">Entrée : {{ item.date_entree || '—' }}</span>
                            <div class="flex gap-2">
                                <button type="button" @click="openEditModal(item)" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200" :aria-label="`Modifier ${item.designation_piece}`">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>Modifier
                                </button>
                                <button v-if="estAdmin" type="button" @click="deletePiece(item.id)" class="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100" :aria-label="`Supprimer ${item.designation_piece}`">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>Supprimer
                                </button>
                            </div>
                        </div>
                    </article>
                    <div v-if="stocks.data.length === 0" class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
                        Aucune pièce trouvée.
                    </div>
                </div>

                <!-- Pagination -->
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

            <!-- Modal d'Ajout / Modification en 2 étapes -->
            <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                    <h3 class="text-base font-black text-slate-900">{{ isEditing ? 'Modifier la pièce' : 'Ajouter une pièce' }}</h3>

                    <div v-if="form.errors.sieges" class="p-3 bg-rose-50 border border-rose-200 text-rose-600 rounded-lg text-xs font-semibold">
                        {{ form.errors.sieges }}
                    </div>

                    <form @submit.prevent="onSubmit" class="space-y-3">

                        <!-- Indicateur d'étapes -->
                        <div class="flex items-center gap-2 text-[11px] font-bold">
                            <button type="button" @click="step = 1"
                                    class="flex-1 py-2 rounded-lg border transition"
                                    :class="step === 1 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-500 border-slate-200'">
                                1. Pièce
                            </button>
                            <button type="button" @click="goToStep2"
                                    class="flex-1 py-2 rounded-lg border transition"
                                    :class="step === 2 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-500 border-slate-200'">
                                2. Distribution aux sièges
                            </button>
                        </div>

                        <!-- ÉTAPE 1 : infos pièce -->
                        <template v-if="step === 1">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Date d'entrée</label>
                                    <input type="date" v-model="form.date_entree" class="w-full bg-slate-50 border rounded-lg text-xs p-2" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Référence</label>
                                    <input type="text" v-model="form.reference" class="w-full bg-slate-50 border rounded-lg text-xs p-2" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">Désignation *</label>
                                <input type="text" v-model="form.designation_piece" class="w-full bg-slate-50 border rounded-lg text-xs p-2" required />
                                <p v-if="form.errors.designation_piece" class="text-xs text-rose-600 mt-1">{{ form.errors.designation_piece }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Famille</label>
                                    <input type="text" v-model="form.famille" class="w-full bg-slate-50 border rounded-lg text-xs p-2" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Sous-famille</label>
                                    <input type="text" v-model="form.sous_famille" class="w-full bg-slate-50 border rounded-lg text-xs p-2" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Marque</label>
                                    <input type="text" v-model="form.marque" class="w-full bg-slate-50 border rounded-lg text-xs p-2" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Modèle</label>
                                    <input type="text" v-model="form.modele" class="w-full bg-slate-50 border rounded-lg text-xs p-2" />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Prix Kagnan HT</label>
                                    <input type="text" v-model="form.prix_kagnan_ht" class="w-full bg-slate-50 border rounded-lg text-xs p-2 font-semibold text-slate-900" placeholder="Ex: 15000" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Prix d'achat</label>
                                    <input type="number" v-model="form.prix_achat" class="w-full bg-slate-50 border rounded-lg text-xs p-2" step="0.01" min="0" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Marge vente</label>
                                    <input type="number" v-model="form.marge_vente" class="w-full bg-slate-50 border rounded-lg text-xs p-2" step="0.01" min="0" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">Quantité Initiale (Lot reçu)</label>
                                <input type="number" v-model.number="form.quantite" class="w-full bg-slate-50 border rounded-lg text-xs p-2 font-bold text-slate-900" min="0" />
                            </div>
                        </template>

                        <!-- ÉTAPE 2 : distribution aux sièges -->
                        <template v-else>
                            <div class="p-3 rounded-xl border flex items-center justify-between"
                                 :class="resteAAttribuer < 0 ? 'bg-rose-50 border-rose-200' : resteAAttribuer === 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-violet-50 border-violet-200'">
                                <div>
                                    <p class="text-[11px] font-bold text-slate-700">{{ form.designation_piece }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        Lot reçu : <strong>{{ form.quantite || 0 }}</strong> &middot;
                                        Attribué : <strong>{{ totalAttribueForm }}</strong>
                                    </p>
                                </div>
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
                                    <span class="text-xs font-extrabold text-slate-800">{{ ligne.siege }}</span>
                                    <span class="text-[11px] font-bold px-2 py-0.5 rounded"
                                          :class="restantSiege(ligne) <= seuilStockBas ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'">
                                        Restant : {{ restantSiege(ligne) }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500">Qté attribuée</label>
                                        <input type="number" v-model.number="ligne.quantite_attribuee" class="w-full bg-white border rounded-lg text-xs p-1.5 font-bold text-blue-600" min="0" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500">Nouvelles sorties</label>
                                        <input type="number" v-model.number="ligne.sortie" class="w-full bg-white border rounded-lg text-xs p-1.5 font-bold text-rose-600" min="0" />
                                    </div>
                                </div>
                            </div>

                            <p v-if="resteAAttribuer < 0" class="text-xs text-rose-600 font-semibold">
                                Tu as attribué {{ Math.abs(resteAAttribuer) }} pièce(s) de plus que le lot reçu.
                            </p>
                        </template>

                        <!-- Boutons -->
                        <div class="flex justify-between gap-2 pt-3 border-t">
                            <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 text-xs font-bold rounded-xl">Annuler</button>
                            <div class="flex gap-2">
                                <button v-if="step === 2" type="button" @click="step = 1" class="px-4 py-2 bg-slate-100 text-xs font-bold rounded-xl">Retour</button>
                                <button v-if="step === 1" type="submit" class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition">
                                    Suivant : distribuer <i class="fa-solid fa-arrow-right ml-1"></i>
                                </button>
                                <button v-else type="submit" :disabled="form.processing || !distributionValide"
                                        class="px-4 py-2 bg-[#E11D48] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition disabled:opacity-50">
                                    {{ form.processing ? 'Enregistrement...' : 'Enregistrer' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal d'Importation Excel / CSV -->
            <div v-if="showImportModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="max-h-[90vh] w-full max-w-2xl space-y-5 overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <h3 class="text-base font-black text-slate-900">Importer un fichier Excel ou CSV</h3>
                        <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <form @submit.prevent="submitImport" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-2">Sélectionner un fichier Excel (.xlsx, .xls) ou CSV (.csv)</label>
                            <input
                                type="file"
                                @change="handleFileChange"
                                accept=".xlsx,.xls,.csv,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                required
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2 focus:border-blue-600 text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            />
                            <p v-if="importForm.errors.fichier" class="text-xs text-rose-600 mt-1.5 font-medium">
                                {{ importForm.errors.fichier }}
                            </p>
                        </div>

                        <div v-if="erreurApercu" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700" role="alert">
                            {{ erreurApercu }}
                        </div>

                        <button
                            type="button"
                            @click="analyserImport"
                            :disabled="!importForm.fichier || previewEnCours || importForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <i class="fa-solid" :class="previewEnCours ? 'fa-spinner fa-spin' : 'fa-magnifying-glass-chart'" aria-hidden="true"></i>
                            {{ previewEnCours ? 'Analyse en cours…' : 'Analyser le fichier' }}
                        </button>

                        <section v-if="importPreview" class="space-y-3 rounded-xl border p-4" :class="importPreview.can_import ? 'border-emerald-200 bg-emerald-50/60' : 'border-amber-200 bg-amber-50/70'" aria-live="polite">
                            <div>
                                <h4 class="text-sm font-black text-slate-900">
                                    {{ importPreview.can_import ? 'Aperçu prêt' : 'Vérifiez le fichier avant de continuer' }}
                                </h4>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ importPreview.crees }} création{{ importPreview.crees === 1 ? '' : 's' }} ·
                                    {{ importPreview.mis_a_jour }} mise{{ importPreview.mis_a_jour === 1 ? '' : 's' }} à jour ·
                                    {{ importPreview.ignores }} ligne{{ importPreview.ignores === 1 ? '' : 's' }} ignorée{{ importPreview.ignores === 1 ? '' : 's' }}
                                </p>
                            </div>

                            <ul v-if="importPreview.issues.length" class="max-h-28 space-y-1 overflow-y-auto text-xs text-amber-900" aria-label="Erreurs à corriger">
                                <li v-for="(issue, index) in importPreview.issues" :key="index">
                                    <i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ issue }}
                                </li>
                                <li v-if="importPreview.issue_count > importPreview.issues.length" class="font-bold">
                                    Et {{ importPreview.issue_count - importPreview.issues.length }} autre(s) erreur(s).
                                </li>
                            </ul>

                            <div v-if="importPreview.rows.length" class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100 text-[10px] uppercase text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2">Ligne</th>
                                            <th class="px-3 py-2">Désignation</th>
                                            <th class="px-3 py-2">Référence</th>
                                            <th class="px-3 py-2">Action prévue</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="row in importPreview.rows" :key="row.ligne">
                                            <td class="px-3 py-2 text-slate-500">{{ row.ligne }}</td>
                                            <td class="px-3 py-2 font-semibold text-slate-800">{{ row.designation }}</td>
                                            <td class="px-3 py-2 font-mono text-slate-600">{{ row.reference || '—' }}</td>
                                            <td class="px-3 py-2 font-bold" :class="row.action === 'Création' ? 'text-emerald-700' : row.action === 'Mise à jour' ? 'text-blue-700' : 'text-amber-700'">
                                                {{ row.action }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-if="importPreview.can_import" class="text-[11px] font-semibold text-emerald-800">
                                L’import peut être lancé. Seules les 12 premières lignes sont affichées.
                            </p>
                            <p v-else class="text-[11px] font-semibold text-amber-800">
                                L’import est bloqué tant que les erreurs listées ne sont pas corrigées.
                            </p>
                        </section>

                        <div class="p-3 bg-blue-50/50 border border-blue-100 rounded-xl text-[11px] text-blue-800 space-y-1">
                            <p class="font-bold"><i class="fa-solid fa-circle-info mr-1"></i> Structure attendue (1re ligne = en-têtes, colonnes dans cet ordre) :</p>
                            <p class="text-slate-600">
                                <strong>Date Entree</strong>, <strong>Reference</strong>, <strong>Designation Piece</strong>, <strong>Famille</strong>, <strong>Sous Famille</strong>, <strong>Marque</strong>, <strong>Modele</strong>, <strong>Prix Kagnan HT</strong>, <strong>Quantite Initiale</strong>, <strong>Prix Achat</strong>, <strong>Marge Vente</strong>, puis les 6 colonnes des sièges (Attribuee / Sortie).
                            </p>
                            <p class="text-slate-500">Astuce : le fichier produit par le bouton « Exporter » sert de modèle.</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showImportModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition">Annuler</button>
                            <button type="submit" :disabled="importForm.processing || !importPreview?.can_import" class="px-5 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <i class="fa-solid fa-upload mr-1.5"></i>{{ importForm.processing ? 'Import en cours…' : 'Importer' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>