<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    stocks: Object,
    filters: Object
});

const search = ref(props.filters.search || '');
const showModal = ref(false);
const isEditing = ref(false);
const currentStockId = ref(null);

const form = useForm({
    marque: '',
    modele: '',
    designation_piece: '',
    reference: '',
    prix_kagnan_ht: ''
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (stock) => {
    isEditing.value = true;
    currentStockId.value = stock.id;
    form.marque = stock.marque || '';
    form.modele = stock.modele || '';
    form.designation_piece = stock.designation_piece || '';
    form.reference = stock.reference || '';
    form.prix_kagnan_ht = stock.prix_kagnan_ht || '';
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('administration.stocks.update', currentStockId.value), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; }
        });
    } else {
        form.post(route('administration.stocks.store'), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); }
        });
    }
};

const deleteStock = (id) => {
    if (confirm('Voulez-vous vraiment supprimer cette pièce du stock ?')) {
        router.delete(route('administration.stocks.destroy', id), {
            preserveScroll: true
        });
    }
};

const handleSearch = () => {
    router.get(route('administration.stocks.index'), { search: search.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header avec bouton Retour au dashboard -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Gestion des Stocks</h1>
                    <p class="text-sm text-slate-500">Catalogue des pièces détachées et inventaire (25 éléments par page).</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('dashboard')" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-200 transition shadow-sm">
                        <i class="fa-solid fa-arrow-left"></i> Retour au dashboard
                    </Link>
                    <button @click="openCreateModal" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#E11D48] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition shadow-sm">
                        <i class="fa-solid fa-plus"></i> Ajouter une pièce
                    </button>
                </div>
            </div>

            <!-- Recherche élargie (Désignation, Référence, Marque, Modèle) -->
            <div class="flex items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                <div class="relative flex-1">
                    <i class="fa-solid fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" v-model="search" @keyup.enter="handleSearch" placeholder="Rechercher par marque, modèle, référence ou désignation..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:border-[#E11D48]" />
                </div>
                <button @click="handleSearch" class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800 transition">Filtrer</button>
            </div>

            <!-- Tableau -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="p-4">Marque</th>
                                <th class="p-4">Modèle</th>
                                <th class="p-4">Désignation</th>
                                <th class="p-4">Référence</th>
                                <th class="p-4 text-right">Prix Kagnan HT</th>
                                <th class="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            <tr v-for="item in stocks.data" :key="item.id" class="hover:bg-slate-50/50 transition">
                                <td class="p-4 font-semibold text-slate-900">{{ item.marque || '-' }}</td>
                                <td class="p-4 text-slate-600">{{ item.modele || '-' }}</td>
                                <td class="p-4 font-bold text-slate-900">{{ item.designation_piece }}</td>
                                <td class="p-4 font-mono text-slate-600">{{ item.reference || '-' }}</td>
                                <td class="p-4 text-right font-semibold">{{ item.prix_kagnan_ht ? item.prix_kagnan_ht + ' FCFA' : '-' }}</td>
                                <td class="p-4 text-right space-x-2">
                                    <button @click="openEditModal(item)" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition" title="Modifier">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button @click="deleteStock(item.id)" class="p-2 bg-rose-50 hover:bg-rose-100 text-[#E11D48] rounded-lg transition" title="Supprimer">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="stocks.data.length === 0">
                                <td colspan="6" class="p-8 text-center text-slate-400 font-medium">Aucune pièce trouvée dans le stock.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-4 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-xs text-slate-500">Affichage de {{ stocks.from || 0 }} à {{ stocks.to || 0 }} sur {{ stocks.total }} éléments</p>
                    <div class="flex gap-1">
                        <template v-for="(link, index) in stocks.links" :key="index">
                            <component :is="link.url ? Link : 'span'" :href="link.url" v-html="link.label" class="px-3 py-1.5 text-xs rounded-lg border" :class="link.active ? 'bg-[#E11D48] text-white border-[#E11D48]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" />
                        </template>
                    </div>
                </div>
            </div>

            <!-- Modal Ajout/Modification -->
            <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-6 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <h3 class="text-base font-black text-slate-900">{{ isEditing ? 'Modifier la pièce' : 'Ajouter une nouvelle pièce' }}</h3>
                        <button @click="showModal = false" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Marque</label>
                                <input type="text" v-model="form.marque" class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5 focus:border-[#E11D48]" placeholder="Ex: Toyota" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Modèle</label>
                                <input type="text" v-model="form.modele" class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5 focus:border-[#E11D48]" placeholder="Ex: Corolla" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Désignation de la pièce *</label>
                            <input type="text" v-model="form.designation_piece" class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5 focus:border-[#E11D48]" placeholder="Ex: Plaquettes de frein avant" required />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Référence</label>
                                <input type="text" v-model="form.reference" class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5 focus:border-[#E11D48]" placeholder="Ex: REF-12345" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Prix Kagnan HT</label>
                                <input type="text" v-model="form.prix_kagnan_ht" class="w-full bg-slate-50 border border-slate-200 rounded-lg text-xs p-2.5 focus:border-[#E11D48]" placeholder="Ex: 15000" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition">Annuler</button>
                            <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-[#E11D48] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition shadow-sm">
                                {{ isEditing ? 'Mettre à jour' : 'Enregistrer' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>