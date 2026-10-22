<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    mouvements: Object,
    filters: Object
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Redirection dynamique du bouton retour selon le rôle de l'utilisateur
const routeRetour = computed(() => {
    if (user.value?.role === 'admin') {
        return route('admin.pieces.index');
    } else if (user.value?.role === 'magasinier') {
        return route('magasinier.pieces.index');
    } else {
        return route('administration.stocks.index');
    }
});

const texteRetour = computed(() => {
    if (user.value?.role === 'admin') return '← Retour au stock Admin';
    if (user.value?.role === 'magasinier') return '← Retour à l’espace Magasinier';
    return '← Retour à la gestion des stocks';
});

const ongletActif = ref(props.filters.type || 'entree');

const changerOnglet = (type) => {
    ongletActif.value = type;
    router.get(route('administration.stocks.historique'), {
        type: type,
        siege: props.filters.siege
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <Link :href="routeRetour" class="text-xs font-bold text-slate-400 hover:text-slate-700">
                        {{ texteRetour }}
                    </Link>
                    <h1 class="text-2xl font-black text-slate-900 mt-1">Historique des Mouvements de Stock</h1>
                    <p class="text-sm text-slate-500">Traçabilité complète des entrées et des sorties (immuables) par siège.</p>
                </div>

                <div class="flex bg-slate-100 p-1 rounded-xl">
                    <button 
                        @click="changerOnglet('entree')"
                        class="px-5 py-2 text-xs font-bold rounded-lg transition"
                        :class="ongletActif === 'entree' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                    >
                        <i class="fa-solid fa-arrow-down text-emerald-600 mr-1"></i> Entrées
                    </button>
                    <button 
                        @click="changerOnglet('sortie')"
                        class="px-5 py-2 text-xs font-bold rounded-lg transition"
                        :class="ongletActif === 'sortie' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                    >
                        <i class="fa-solid fa-arrow-up text-rose-600 mr-1"></i> Sorties
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b text-[11px] font-bold text-slate-400 uppercase">
                                <th class="p-3">Date & Heure</th>
                                <th class="p-3">Référence & Pièce</th>
                                <th class="p-3 text-center">Quantité</th>
                                <th class="p-3">Siège Concerné</th>
                                <th class="p-3">Motif / Source</th>
                                <th class="p-3">Auteur</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-xs text-slate-700">
                            <tr v-for="mvt in mouvements.data" :key="mvt.id" class="hover:bg-slate-50">
                                <td class="p-3 text-slate-500 font-medium">
                                    {{ new Date(mvt.created_at).toLocaleString('fr-FR') }}
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-slate-900">{{ mvt.stock?.designation_piece || 'Pièce supprimée' }}</div>
                                    <div class="font-mono text-[10px] text-slate-400">Réf: {{ mvt.stock?.reference || 'N/A' }}</div>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full font-black text-xs"
                                          :class="mvt.type === 'entree' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                                        {{ mvt.type === 'entree' ? '+' : '-' }}{{ mvt.quantite }}
                                    </span>
                                </td>
                                <td class="p-3 font-semibold text-slate-800">
                                    {{ mvt.siege || 'Stock Central / Général' }}
                                </td>
                                <td class="p-3 font-medium text-slate-600">
                                    {{ mvt.motif || '-' }}
                                </td>
                                <td class="p-3 text-slate-600">
                                    {{ mvt.user?.name || 'Système / Rétroactif' }}
                                </td>
                            </tr>
                            <tr v-if="mouvements.data.length === 0">
                                <td colspan="6" class="p-8 text-center text-slate-400">Aucun mouvement enregistré pour cet onglet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="mouvements.links && mouvements.links.length > 3" class="p-4 bg-slate-50 border-t flex justify-center items-center gap-1">
                    <template v-for="(link, index) in mouvements.links" :key="index">
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
        </div>
    </AuthenticatedLayout>
</template>