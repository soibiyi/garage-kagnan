<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    dossiers: Array,
    filters: Object,
});

const page = usePage();

// ─────────────────────────────────────────────
// RECHERCHE
// ─────────────────────────────────────────────
const search = ref(props.filters?.search || '');

watch(search, (value) => {
    router.get(
        route('administration.factures.index'),
        { search: value },
        { preserveState: true, replace: true }
    );
});

// ─────────────────────────────────────────────
// HELPERS PAIEMENT
// Le contrôleur fournit `resume_paiement` pour chaque dossier
// (total_ttc, montant_paye, reste, pourcentage, soldee)
// ─────────────────────────────────────────────
const resteAPayer = (dossier) => Number(dossier.resume_paiement?.reste ?? 0);

const estSolde = (dossier) => Boolean(dossier.resume_paiement?.soldee);

// ─────────────────────────────────────────────
// FILTRE SOLDÉ / NON SOLDÉ
// ─────────────────────────────────────────────
// Ouvre directement « Soldées » si l'URL contient ?onglet=soldes
const ongletInitial = new URLSearchParams((page.url || '').split('?')[1] || '').get('onglet');
const onglet = ref(ongletInitial === 'soldes' ? 'soldes' : 'non_soldes'); // 'non_soldes' | 'soldes'

const dossiersNonSoldes = computed(() => props.dossiers.filter((d) => !estSolde(d)));
const dossiersSoldes = computed(() => props.dossiers.filter((d) => estSolde(d)));

const dossiersAffiches = computed(() =>
    onglet.value === 'soldes' ? dossiersSoldes.value : dossiersNonSoldes.value
);

// ─────────────────────────────────────────────
// ACTUALISATION AUTOMATIQUE
// ─────────────────────────────────────────────
const REFRESH_INTERVAL = 4000; // 4 secondes
let refreshTimer = null;
let rechargementEnCours = false;

const actualiser = () => {
    // Pas de rechargement si l'onglet est caché ou si un rechargement est déjà en cours
    if (document.hidden || rechargementEnCours) return;

    rechargementEnCours = true;
    router.reload({
        only: ['dossiers'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            rechargementEnCours = false;
        },
    });
};

const onVisibilityChange = () => {
    if (!document.hidden) actualiser();
};

onMounted(() => {
    refreshTimer = setInterval(actualiser, REFRESH_INTERVAL);
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('focus', actualiser);
});

onUnmounted(() => {
    clearInterval(refreshTimer);
    clearTimeout(notifTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('focus', actualiser);
});

// ─────────────────────────────────────────────
// NOTIFICATION QUAND UNE FACTURE VIENT D'ÊTRE SOLDÉE
// ─────────────────────────────────────────────
const notification = ref(null);
let notifTimer = null;

watch(
    () => props.dossiers,
    (nouveaux, anciens) => {
        if (!anciens) return;

        const idsNonSoldesAvant = new Set(
            anciens.filter((d) => !estSolde(d)).map((d) => d.id)
        );
        const nouvellementSoldes = nouveaux.filter(
            (d) => estSolde(d) && idsNonSoldesAvant.has(d.id)
        );

        if (nouvellementSoldes.length > 0) {
            const d = nouvellementSoldes[0];
            const ot = d.numero_ot || d.vehicule?.immatriculation || '';
            notification.value =
                nouvellementSoldes.length === 1
                    ? `Le dossier ${ot} vient d'être soldé et a été déplacé dans « Soldées ».`
                    : `${nouvellementSoldes.length} dossiers viennent d'être soldés et ont été déplacés dans « Soldées ».`;

            clearTimeout(notifTimer);
            notifTimer = setTimeout(() => (notification.value = null), 6000);
        }
    }
);

// ─────────────────────────────────────────────
// NAVIGATION (retour dynamique)
// ─────────────────────────────────────────────
const isMecanicien = computed(() => {
    const user = page.props.auth.user;
    return user?.role === 'mecanicien' || user?.roles?.some((r) => r.name === 'mecanicien');
});

const backUrl = computed(() => {
    if (isMecanicien.value) {
        return route('mecanicien.index');
    }
    return route('dashboard');
});

const backText = computed(() => {
    return isMecanicien.value
        ? '← Retour à mon atelier'
        : '← Retour au tableau de bord';
});
</script>

<template>
    <Head title="Facturation & Encaissements" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Facturation & Encaissements</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Liste des dossiers dont le devis a été accepté par le client, prêts pour facturation et encaissement.
                    </p>
                </div>

                <!-- Bouton de retour dynamique -->
                <Link
                    :href="backUrl"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition"
                >
                    {{ backText }}
                </Link>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- NOTIFICATION : facture soldée -->
                <transition
                    enter-active-class="transition duration-300"
                    enter-from-class="opacity-0 -translate-y-2"
                    leave-active-class="transition duration-200"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="notification"
                        class="flex items-center justify-between gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-xl"
                    >
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i>
                            {{ notification }}
                        </span>
                        <button type="button" @click="notification = null" class="text-emerald-600 hover:text-emerald-800">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </transition>

                <!-- BARRE DE RECHERCHE + BOUTONS DE FILTRE -->
                <div class="flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4">
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input
                            type="text"
                            v-model="search"
                            placeholder="Rechercher par nom ou numéro d'OT..."
                            class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-900 focus:outline-none focus:border-[#E11D48] transition shadow-xs"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="onglet = 'non_soldes'"
                            :class="[
                                'inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl border transition',
                                onglet === 'non_soldes'
                                    ? 'bg-[#E11D48] text-white border-[#E11D48] shadow-sm'
                                    : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
                            ]"
                        >
                            <i class="fa-solid fa-hourglass-half text-[11px]"></i>
                            <span>Non soldées</span>
                            <span
                                :class="[
                                    'px-1.5 py-0.5 rounded-md text-[10px] font-black',
                                    onglet === 'non_soldes' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'
                                ]"
                            >{{ dossiersNonSoldes.length }}</span>
                        </button>

                        <button
                            type="button"
                            @click="onglet = 'soldes'"
                            :class="[
                                'inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl border transition',
                                onglet === 'soldes'
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                                    : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
                            ]"
                        >
                            <i class="fa-solid fa-circle-check text-[11px]"></i>
                            <span>Soldées</span>
                            <span
                                :class="[
                                    'px-1.5 py-0.5 rounded-md text-[10px] font-black',
                                    onglet === 'soldes' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'
                                ]"
                            >{{ dossiersSoldes.length }}</span>
                        </button>
                    </div>
                </div>

                <div class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl overflow-hidden border border-gray-100 p-6">

                    <div v-if="dossiersAffiches.length === 0" class="text-center py-16">
                        <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                            <i class="fa-solid fa-folder-closed text-xl"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                            {{ onglet === 'soldes' ? 'Aucune facture soldée' : 'Aucun dossier à facturer' }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">Il n'y a pas de dossier correspondant à votre recherche.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50/70 text-gray-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">N° Dossier / Véhicule</th>
                                    <th class="px-6 py-3 font-semibold">Client</th>
                                    <th class="px-6 py-3 font-semibold">Statut Devis</th>
                                    <th class="px-6 py-3 font-semibold text-right">Reste à payer</th>
                                    <th class="px-6 py-3 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/60">
                                <tr v-for="dossier in dossiersAffiches" :key="dossier.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-gray-900 flex items-center gap-2 uppercase">
                                            <i class="fa-solid fa-car text-gray-400 text-[11px]"></i>
                                            <span>{{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }}</span>
                                        </div>
                                        <span class="font-mono text-[11px] text-[#E11D48] mt-0.5 block">
                                            Immat: {{ dossier.vehicule?.immatriculation }} | OT: {{ dossier.numero_ot || 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">
                                        {{ dossier.vehicule?.client?.nom || '' }} {{ dossier.vehicule?.client?.prenom || dossier.vehicule?.client?.name || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 inline-flex text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Accepté
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <span
                                            v-if="estSolde(dossier)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200"
                                        >
                                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                                            Soldé
                                        </span>
                                        <span v-else class="font-bold text-[#E11D48]">
                                            {{ resteAPayer(dossier).toLocaleString() }} F
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <Link
                                            :href="route('administration.factures.show', dossier.id)"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#E11D48] hover:bg-rose-700 text-white font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-[11px]"
                                        >
                                            <span>{{ estSolde(dossier) ? 'Voir la facture' : 'Facturer / Encaisser' }}</span>
                                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                        </Link>
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