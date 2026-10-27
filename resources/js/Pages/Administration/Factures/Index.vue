<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Le contrôleur utilise ->get() : "dossiers" est un simple tableau (pas de .data)
const props = defineProps({
    dossiers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// URL de base de la page (ex: /admin/factures). Adaptez si vos routes diffèrent.
const base = window.location.pathname.replace(/\/$/, '');

const search = ref(props.filters?.search || '');

const rechercher = () => {
    router.get(base, { search: search.value }, { preserveState: true, replace: true });
};

const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(Number(n || 0)) + ' FCFA';

const nomClient = (d) => {
    const c = d.vehicule?.client;
    return c ? `${c.nom ?? ''} ${c.prenom ?? ''}`.trim() || 'N/A' : 'N/A';
};

const statut = (d) => {
    const r = d.resume_paiement;
    if (r?.soldee) return { label: 'Soldée', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    if ((r?.montant_paye ?? 0) > 0) return { label: 'Partielle', cls: 'bg-amber-50 text-amber-700 border-amber-200' };
    return { label: 'Impayée', cls: 'bg-rose-50 text-rose-700 border-rose-200' };
};

const supprimer = (d) => {
    if (confirm('Supprimer ce devis direct ?')) {
        router.delete(`${base}/${d.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="p-6 space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-slate-900">Factures et encaissements</h1>
                <a :href="`${base}/export`" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-bold">
                    Exporter le tableau de créances
                </a>
            </div>

            <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-bold">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.errors?.error" class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs font-bold">
                {{ $page.props.errors.error }}
            </div>

            <form @submit.prevent="rechercher" class="flex gap-2">
                <input v-model="search" type="text" placeholder="N° OT ou nom du client"
                    class="w-full max-w-sm rounded-lg border-slate-300 text-sm" />
                <button type="submit" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-800 text-xs font-bold">Rechercher</button>
            </form>

            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b text-[11px] font-bold text-slate-500">
                            <th class="p-3">N° facture</th>
                            <th class="p-3">N° OT</th>
                            <th class="p-3">Client</th>
                            <th class="p-3">Immatriculation</th>
                            <th class="p-3 text-right">Total TTC</th>
                            <th class="p-3 text-right">Payé</th>
                            <th class="p-3 text-right">Reste</th>
                            <th class="p-3">Statut</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-xs text-slate-700">
                        <tr v-for="d in dossiers" :key="d.id" class="hover:bg-slate-50">
                            <td class="p-3 font-mono">{{ d.resume_paiement?.numero || '-' }}</td>
                            <td class="p-3 font-mono">{{ d.numero_ot || '-' }}</td>
                            <td class="p-3 font-semibold">{{ nomClient(d) }}</td>
                            <td class="p-3">{{ d.vehicule?.immatriculation || '-' }}</td>
                            <td class="p-3 text-right font-bold">{{ fcfa(d.resume_paiement?.total_ttc) }}</td>
                            <td class="p-3 text-right">
                                {{ fcfa(d.resume_paiement?.montant_paye) }}
                                <div class="mt-1 h-1.5 w-24 ml-auto rounded bg-slate-100 overflow-hidden">
                                    <div class="h-full bg-emerald-500" :style="{ width: (d.resume_paiement?.pourcentage || 0) + '%' }"></div>
                                </div>
                            </td>
                            <td class="p-3 text-right font-bold">{{ fcfa(d.resume_paiement?.reste) }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded border text-[10px] font-bold" :class="statut(d).cls">
                                    {{ statut(d).label }}
                                </span>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <Link :href="`${base}/${d.id}`" class="text-blue-600 font-bold mr-3">Ouvrir</Link>
                                <button v-if="!d.a_paiement && !d.numero_ot" @click="supprimer(d)" class="text-rose-600 font-bold">
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                        <tr v-if="dossiers.length === 0">
                            <td colspan="9" class="p-8 text-center text-slate-400">
                                Aucun dossier avec un devis accepté.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>