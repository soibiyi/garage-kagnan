<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    interventionsAtelier: Object, // résultat paginé Laravel : { data, from, to, total, current_page, last_page, prev_page_url, next_page_url }
    filters: { type: Object, default: () => ({}) },
    statuts: { type: Array, default: () => [] },
    // { normal: { tous: 12, reception: 3, ... }, direct: { tous: 5, ... } }
    compteurs: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

/* ------------------------------------------------------------------ */
/* Libellés + styles des statuts (le texte porte toujours l'information, la couleur la renforce) */
/* ------------------------------------------------------------------ */
const statutLabels = {
    reception: 'Sur le parc',
    atelier: 'En atelier',
    en_cours: 'En réparation',
    attente_accord: 'Attente accord devis',
    accepte: 'Devis accepté',
};
const statutStyles = {
    reception: 'k-badge--steel',
    atelier: 'k-badge--ink',
    en_cours: 'k-badge--signal',
    attente_accord: 'k-badge--amber',
    accepte: 'k-badge--ok',
};
const getStatutLabel = (st) => statutLabels[st] || (st || '').replace(/_/g, ' ');
const getStatutStyle = (st) => statutStyles[st] || 'k-badge--steel';

/* ------------------------------------------------------------------ */
/* Onglets (type de devis) + sous-onglets (statut) + recherche         */
/* ------------------------------------------------------------------ */
const search = ref(props.filters.search || '');
const statut = ref(props.filters.statut || '');
const type = ref(props.filters.type === 'direct' ? 'direct' : 'normal');
const chargementListe = ref(false);
const filtresActifs = computed(() => Boolean(search.value || statut.value));

const onglets = [
    { value: 'normal', label: 'Devis normaux', icone: 'fa-folder-open' },
    { value: 'direct', label: 'Devis direct', icone: 'fa-file-invoice-dollar' },
];

const compteurType = (t) => props.compteurs?.[t]?.tous ?? 0;
const compteurStatut = (st) => props.compteurs?.[type.value]?.[st || 'tous'] ?? 0;

const sousOnglets = computed(() => [
    { value: '', label: 'Tous' },
    ...props.statuts.map((st) => ({ value: st, label: getStatutLabel(st) })),
]);

const applyFilters = () => {
    router.get(
        route('dashboard'),
        {
            type: type.value === 'direct' ? 'direct' : undefined,
            search: search.value.trim() || undefined,
            statut: statut.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (chargementListe.value = true),
            onFinish: () => (chargementListe.value = false),
        }
    );
};

const choisirType = (t) => {
    if (type.value === t) return;
    type.value = t; // le statut choisi est conservé en changeant d'onglet
};
const choisirStatut = (st) => {
    statut.value = st;
};

// Délai pour ne pas interroger le serveur à chaque lettre tapée
let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});
watch([statut, type], () => {
    clearTimeout(searchTimer);
    applyFilters();
});

const reinitialiserFiltres = () => {
    search.value = '';
    statut.value = '';
};

/* Actions : mêmes routes pour les deux types ; adapte ici si le devis direct a ses propres routes */
const routeConsulter = (item) => route('parc.show', item.id);
const routeSupprimer = (item) => route('parc.destroy', item.id);

/* ------------------------------------------------------------------ */
/* Rôle                                                                */
/* ------------------------------------------------------------------ */
const roleInfo = computed(() => {
    const roles = {
        receptionniste: { label: 'Réceptionniste', badge: 'bg-white text-[#0B0F19] border border-[#0B0F19]/25' },
        mecanicien: { label: 'Mécanicien', badge: 'bg-[#8A8D8F]/20 text-[#0B0F19] border border-[#8A8D8F]/40' },
        administratif: { label: 'Administratif', badge: 'bg-[#E11D48]/10 text-[#BE123C] border border-[#E11D48]/30' },
        charge_client: { label: 'Chargé de Suivi Client', badge: 'bg-gray-100 text-gray-800 border border-gray-300' },
    };
    return roles[user.value?.role] || { label: user.value?.role || 'Espace', badge: 'bg-gray-100 text-gray-700 border border-gray-200' };
});

/*
 * Un seul modèle de données par rôle : le template ne répète plus 20 blocs identiques.
 * - principal : l'action la plus fréquente du rôle (carte sombre, le seul point fort de l'écran)
 * - large     : occupe toute la largeur sur desktop
 * - bientot   : fonctionnalité pas encore branchée (pas de faux lien)
 * Les routes sont évaluées par rôle (fonctions) pour ne résoudre que celles du rôle connecté.
 */
const vuesParRole = {
    receptionniste: () => ({
        icone: 'fa-car-tunnel',
        titre: 'Réception véhicule & accueil client',
        description: "Enregistrez l'arrivée d'un véhicule et initiez le circuit de prise en charge.",
        groupes: [
            {
                actions: [
                    { label: 'Nouvelle réception', description: "Fiche d'entrée, kilométrage et état initial.", icone: 'fa-circle-plus', href: route('reception.create'), principal: true },
                    { label: 'Véhicules sur le parc', description: "Consulter l'état des véhicules en cours d'accueil.", icone: 'fa-square-parking', href: route('parc.index') },
                ],
            },
        ],
    }),
    mecanicien: () => ({
        icone: 'fa-wrench',
        titre: 'Atelier & diagnostics',
        description: 'Consultez les véhicules assignés, réalisez les essais et diagnostics techniques.',
        groupes: [
            {
                actions: [
                    { label: 'Mes interventions assignées', description: "Liste des travaux en cours dans votre bay d'atelier.", icone: 'fa-screwdriver-wrench', href: route('mecanicien.index'), principal: true },
                    { label: "Rapports d'essai", description: 'Saisir les observations suite aux essais routiers.', icone: 'fa-car-burst', bientot: true },
                ],
            },
        ],
    }),
    administratif: () => ({
        icone: 'fa-file-invoice',
        titre: 'Devis & facturation',
        description: "Gérez l'édition des devis, validez les coûts et éditez les factures clients.",
        groupes: [
            {
                titre: 'Devis',
                actions: [
                    { label: 'En attente de devis', description: "Chiffrages pièces et main-d'œuvre à transformer.", icone: 'fa-file-pen', href: route('administration.dossiers.index'), principal: true, large: true },
                    { label: 'Devis en attente de validation', description: 'Saisir les choix du client et enregistrer.', icone: 'fa-receipt', href: route('administration.facturation.index') },
                    { label: 'Devis validés', description: "Dossiers dont l'accord client a été enregistré.", icone: 'fa-clipboard-check', href: route('administration.devis.acceptes') },
                    { label: 'Devis direct', description: 'Création et gestion des devis directs (comptoir).', icone: 'fa-file-invoice-dollar', href: route('administration.devis.directs.index') },
                    { label: 'Historique global des devis', description: "Vue d'ensemble de tous les services (acceptés / refusés).", icone: 'fa-clock-rotate-left', href: route('administration.devis.historique') },
                ],
            },
            {
                titre: 'Encaissement & pilotage',
                actions: [
                    { label: 'Facture & encaissement', description: 'Facturation finale et suivi des règlements.', icone: 'fa-cash-register', href: route('administration.factures.index') },
                    { label: "Historique du chiffre d'affaires", description: "Évolution et statistiques globales du chiffre d'affaires.", icone: 'fa-chart-line', href: route('administration.chiffre-affaires') },
                    { label: 'Gestion des stocks de pièces', description: 'Consultez, ajoutez, modifiez ou supprimez les pièces et composants du stock.', icone: 'fa-boxes-stacked', href: route('administration.stocks.index'), large: true },
                ],
            },
        ],
    }),
    charge_client: () => ({
        icone: 'fa-headset',
        titre: 'Suivi client & relances',
        description: "Annuaire des clients, véhicules, alertes d'assurance / SICTA et devis refusés.",
        groupes: [
            {
                actions: [
                    { label: 'Annuaire des clients & véhicules', description: 'Fiches clients, historique, assurances et lignes refusées.', icone: 'fa-users', href: route('charge_client.clients.index'), principal: true, large: true },
                    { label: 'En attente de devis', description: "Chiffrages pièces et main-d'œuvre à transformer.", icone: 'fa-file-pen', href: route('administration.dossiers.index') },
                    { label: 'Devis en attente de validation', description: 'Modifier les devis, saisir les choix du client et enregistrer.', icone: 'fa-receipt', href: route('administration.facturation.index') },
                    { label: 'Devis acceptés', description: "Modifier les lignes et les choix du client tant qu'aucun paiement n'est enregistré.", icone: 'fa-clipboard-check', href: route('administration.devis.acceptes') },
                    { label: 'Devis direct', description: 'Création et gestion des devis directs (comptoir).', icone: 'fa-file-invoice-dollar', href: route('administration.devis.directs.index') },
                ],
            },
        ],
    }),
};
const vue = computed(() => vuesParRole[user.value?.role]?.() ?? null);

/* ------------------------------------------------------------------ */
/* Écran de chargement : une seule fois par session                    */
/* Initialisé de façon synchrone pour éviter un flash du dashboard     */
/* ------------------------------------------------------------------ */
const isLoading = ref(typeof window !== 'undefined' && !sessionStorage.getItem('dashboard_loaded'));

/* ------------------------------------------------------------------ */
/* Suppression d'un dossier                                            */
/* ------------------------------------------------------------------ */
const dossierASupprimer = ref(null);
const suppressionEnCours = ref(false);
const erreurSuppression = ref('');
const boutonAnnuler = ref(null);

const demanderSuppression = (item) => {
    erreurSuppression.value = '';
    dossierASupprimer.value = item;
    nextTick(() => boutonAnnuler.value?.focus());
};

const annulerSuppression = () => {
    if (suppressionEnCours.value) return;
    dossierASupprimer.value = null;
};

const confirmerSuppression = () => {
    if (!dossierASupprimer.value || suppressionEnCours.value) return;

    suppressionEnCours.value = true;
    erreurSuppression.value = '';
    router.delete(routeSupprimer(dossierASupprimer.value), {
        preserveScroll: true,
        onSuccess: () => {
            dossierASupprimer.value = null;
        },
        onError: () => {
            erreurSuppression.value = "La suppression a échoué. Réessayez, ou contactez l'administrateur si le problème persiste.";
        },
        onFinish: () => {
            suppressionEnCours.value = false;
        },
    });
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && dossierASupprimer.value) annulerSuppression();
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    if (isLoading.value) {
        setTimeout(() => {
            isLoading.value = false;
            sessionStorage.setItem('dashboard_loaded', 'true');
        }, 1500);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    clearTimeout(searchTimer);
});

// Déconnexion avec nettoyage de session
const logout = () => {
    sessionStorage.removeItem('dashboard_loaded');
    sessionStorage.removeItem('admin_loaded');
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Tableau de bord — Garage Kagnan" />

    <!-- ÉCRAN DE CHARGEMENT : le moment fort de l'écran, une fois par session -->
    <Transition name="fade">
        <div v-if="isLoading" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-white" role="status" aria-live="polite">
            <div class="w-full max-w-sm px-6 text-center">
                <h1 class="text-2xl font-extrabold tracking-tight text-[var(--k-ink)] k-rise">
                    Garage Kagnan
                </h1>
                <p class="mt-1 text-sm font-semibold text-[var(--k-signal)] k-rise" style="animation-delay: 80ms">
                    {{ roleInfo.label }}
                </p>
                <p class="mt-5 text-xs font-medium text-[var(--k-muted)] k-rise" style="animation-delay: 160ms">
                    Chargement de votre espace de travail…
                </p>
                <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-[var(--k-line)]">
                    <div class="k-loader h-full rounded-full bg-[var(--k-signal)]"></div>
                </div>
            </div>
        </div>
    </Transition>

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 py-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-[var(--k-ink)]">Espace de Travail</h2>
                    <p class="mt-0.5 text-sm font-medium text-[var(--k-muted)]">
                        Connecté en tant que <span class="font-bold text-[var(--k-ink)]">{{ user?.name }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span :class="['rounded-lg px-3.5 py-1.5 text-xs font-bold', roleInfo.badge]">
                        {{ roleInfo.label }}
                    </span>
                    <button type="button" @click="logout" class="k-btn k-btn--ghost">
                        <i class="fa-solid fa-right-from-bracket text-xs" aria-hidden="true"></i>
                        <span>Déconnexion</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-[var(--k-paper)] py-6 sm:py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:space-y-8 sm:px-6 lg:px-8">

                <!-- ====== PANNEAU D'ACTIONS DU RÔLE (un seul gabarit, piloté par les données) ====== -->
                <section v-if="vue" class="k-panel">
                    <header class="flex items-start gap-4 border-b border-[var(--k-line)] pb-5 sm:items-center sm:gap-5 sm:pb-6">
                        <span class="k-chip">
                            <i :class="['fa-solid', vue.icone, 'text-xl']" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-extrabold tracking-tight text-[var(--k-ink)] sm:text-xl">{{ vue.titre }}</h3>
                            <p class="mt-0.5 text-sm text-[var(--k-muted)]">{{ vue.description }}</p>
                        </div>
                    </header>

                    <div class="mt-6 space-y-7">
                        <div v-for="(groupe, gi) in vue.groupes" :key="gi">
                            <h4 v-if="groupe.titre" class="mb-3 text-sm font-bold text-[var(--k-ink)]">{{ groupe.titre }}</h4>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <component
                                    :is="action.href ? Link : 'div'"
                                    v-for="action in groupe.actions"
                                    :key="action.label"
                                    :href="action.href"
                                    :aria-disabled="action.bientot ? 'true' : undefined"
                                    :class="[
                                        'k-action',
                                        action.principal && 'k-action--primary',
                                        action.bientot && 'k-action--soon',
                                        action.large && 'md:col-span-2',
                                    ]"
                                >
                                    <span class="k-action__icon">
                                        <i :class="['fa-solid', action.icone]" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="k-action__title">{{ action.label }}</span>
                                        <span class="k-action__desc">{{ action.description }}</span>
                                    </span>
                                    <span v-if="action.bientot" class="k-soon">Bientôt</span>
                                    <i v-else class="fa-solid fa-arrow-right k-action__arrow" aria-hidden="true"></i>
                                </component>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Rôle sans espace dédié -->
                <section v-else class="k-panel text-center">
                    <span class="k-chip mx-auto">
                        <i class="fa-solid fa-user-lock text-xl" aria-hidden="true"></i>
                    </span>
                    <h3 class="mt-4 text-lg font-extrabold text-[var(--k-ink)]">Aucun espace n'est encore associé à votre rôle</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-[var(--k-muted)]">
                        Contactez l'administrateur pour qu'il vous attribue un rôle (réceptionniste, mécanicien, administratif ou chargé de suivi client).
                    </p>
                </section>

                <!-- ====== DOSSIERS EN COURS / ATELIER (réceptionniste) ====== -->
                <section v-if="user?.role === 'receptionniste'" class="k-panel">
                    <h4 class="flex items-center gap-2.5 text-lg font-extrabold tracking-tight text-[var(--k-ink)]">
                        <i class="fa-solid fa-clipboard-list text-base text-[var(--k-signal)]" aria-hidden="true"></i>
                        <span>Dossiers en cours / Atelier</span>
                    </h4>

                    <!-- Onglets principaux : type de devis -->
                    <div class="k-tabs mt-5" role="tablist" aria-label="Type de devis">
                        <button
                            v-for="o in onglets"
                            :key="o.value"
                            type="button"
                            role="tab"
                            :aria-selected="type === o.value"
                            :class="['k-tab', type === o.value && 'k-tab--active']"
                            @click="choisirType(o.value)"
                        >
                            <i :class="['fa-solid', o.icone, 'text-sm']" aria-hidden="true"></i>
                            <span>{{ o.label }}</span>
                            <span class="k-count">{{ compteurType(o.value) }}</span>
                        </button>
                    </div>

                    <!-- Recherche -->
                    <div class="relative mt-5 w-full">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-[var(--k-steel)]">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            autocomplete="off"
                            aria-label="Rechercher un dossier"
                            placeholder="N° OT, client, immatriculation, marque, modèle…"
                            class="k-field w-full pl-11 pr-11"
                        />
                        <button
                            v-if="search"
                            type="button"
                            @click="search = ''"
                            aria-label="Effacer la recherche"
                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-[var(--k-muted)] transition hover:text-[var(--k-signal)]"
                        >
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Sous-onglets : statuts -->
                    <div class="k-subtabs mt-4" role="tablist" aria-label="Filtrer par statut">
                        <button
                            v-for="s in sousOnglets"
                            :key="s.value || 'tous'"
                            type="button"
                            role="tab"
                            :aria-selected="statut === s.value"
                            :class="['k-subtab', statut === s.value && 'k-subtab--active']"
                            @click="choisirStatut(s.value)"
                        >
                            <span>{{ s.label }}</span>
                            <span class="k-count k-count--sm">{{ compteurStatut(s.value) }}</span>
                        </button>
                    </div>

                    <div class="relative mt-5">
                        <div v-if="chargementListe" class="k-bar" aria-hidden="true"></div>

                        <div v-if="interventionsAtelier?.data?.length > 0" :class="['k-list', chargementListe && 'k-list--busy']">

                            <!-- Mobile : une fiche par dossier -->
                            <ul class="space-y-3 md:hidden">
                                <li v-for="item in interventionsAtelier.data" :key="item.id" class="k-ticket">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-extrabold text-[var(--k-ink)]">{{ item.numero_ot || 'N/A' }}</p>
                                            <p class="text-xs text-[var(--k-muted)]">{{ item.date_reception }}</p>
                                        </div>
                                        <span :class="['k-badge', getStatutStyle(item.statut)]">{{ getStatutLabel(item.statut) }}</span>
                                    </div>

                                    <p class="mt-3 text-sm font-bold text-[var(--k-ink)]">
                                        {{ item.vehicule?.client?.nom }} {{ item.vehicule?.client?.prenom }}
                                    </p>
                                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                                        <span>{{ item.vehicule?.marque }} {{ item.vehicule?.modele }}</span>
                                        <span class="k-plate">{{ item.vehicule?.immatriculation }}</span>
                                    </p>
                                    <p class="mt-1 text-xs text-[var(--k-muted)]">
                                        <i class="fa-solid fa-user-gear mr-1" aria-hidden="true"></i>
                                        <span :class="!item.mecanicien?.name && 'italic'">{{ item.mecanicien?.name || 'Non assigné' }}</span>
                                    </p>

                                    <div class="mt-4 flex gap-2 border-t border-[var(--k-line)] pt-3">
                                        <Link :href="routeConsulter(item)" class="k-btn k-btn--dark flex-1 justify-center">
                                            <span>Consulter</span>
                                            <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                                        </Link>
                                        <button type="button" @click="demanderSuppression(item)" class="k-btn k-btn--ghost k-btn--danger" aria-label="Supprimer ce dossier">
                                            <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </li>
                            </ul>

                            <!-- Tablette / desktop : tableau -->
                            <div class="hidden overflow-x-auto rounded-[var(--k-radius)] border border-[var(--k-line)] md:block">
                                <table class="min-w-full divide-y divide-[var(--k-line)]">
                                    <thead>
                                        <tr class="bg-[var(--k-paper)] text-left text-xs font-semibold text-[var(--k-muted)]">
                                            <th scope="col" class="px-5 py-3.5">OT / Date</th>
                                            <th scope="col" class="px-5 py-3.5">Client</th>
                                            <th scope="col" class="px-5 py-3.5">Véhicule</th>
                                            <th scope="col" class="px-5 py-3.5">Mécanicien</th>
                                            <th scope="col" class="px-5 py-3.5">Statut</th>
                                            <th scope="col" class="px-5 py-3.5 text-right"><span class="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[var(--k-line)] bg-white text-sm">
                                        <tr v-for="item in interventionsAtelier.data" :key="item.id" class="k-row">
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <span class="font-extrabold text-[var(--k-ink)]">{{ item.numero_ot || 'N/A' }}</span>
                                                <div class="text-xs text-[var(--k-muted)]">{{ item.date_reception }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 font-bold text-[var(--k-ink)]">
                                                {{ item.vehicule?.client?.nom }} {{ item.vehicule?.client?.prenom }}
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-gray-600">
                                                <span class="mr-2">{{ item.vehicule?.marque }} {{ item.vehicule?.modele }}</span>
                                                <span class="k-plate">{{ item.vehicule?.immatriculation }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-[var(--k-ink)]">
                                                <span v-if="item.mecanicien?.name">{{ item.mecanicien.name }}</span>
                                                <span v-else class="font-medium italic text-[var(--k-muted)]">Non assigné</span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <span :class="['k-badge', getStatutStyle(item.statut)]">{{ getStatutLabel(item.statut) }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <div class="flex items-center justify-end gap-1">
                                                    <Link :href="routeConsulter(item)" class="k-btn k-btn--text">
                                                        <span>Consulter</span>
                                                        <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                                                    </Link>
                                                    <button type="button" @click="demanderSuppression(item)" class="k-btn k-btn--text k-btn--danger" aria-label="Supprimer ce dossier" title="Supprimer ce dossier">
                                                        <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- État vide : une invitation à agir -->
                        <div v-else class="rounded-[var(--k-radius)] border border-dashed border-[var(--k-steel)]/50 bg-[var(--k-paper)] px-6 py-12 text-center">
                            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-[var(--k-steel)] ring-1 ring-[var(--k-line)]">
                                <i :class="['fa-solid', filtresActifs ? 'fa-magnifying-glass' : 'fa-folder-open', 'text-lg']" aria-hidden="true"></i>
                            </span>
                            <p class="mt-4 text-sm font-bold text-[var(--k-ink)]">
                                {{ filtresActifs ? 'Aucun dossier ne correspond à votre recherche.' : (type === 'direct' ? 'Aucun devis direct pour le moment.' : 'Aucun dossier pour le moment.') }}
                            </p>
                            <p class="mt-1 text-xs text-[var(--k-muted)]">
                                {{ filtresActifs ? 'Vérifiez l\'orthographe ou essayez un autre statut.' : (type === 'direct' ? 'Les devis directs créés au comptoir apparaîtront ici.' : 'Les véhicules enregistrés à la réception apparaîtront ici.') }}
                            </p>
                            <button v-if="filtresActifs" type="button" @click="reinitialiserFiltres" class="k-btn k-btn--dark mt-5">
                                <i class="fa-solid fa-rotate-left text-xs" aria-hidden="true"></i>
                                <span>Effacer les filtres</span>
                            </button>
                            <Link v-else-if="type === 'normal'" :href="route('reception.create')" class="k-btn k-btn--dark mt-5">
                                <i class="fa-solid fa-circle-plus text-xs" aria-hidden="true"></i>
                                <span>Nouvelle réception</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="interventionsAtelier?.total > 0" class="mt-5 flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <p class="text-xs font-medium text-[var(--k-muted)]">
                            Affichage de {{ interventionsAtelier.from }} à {{ interventionsAtelier.to }} sur {{ interventionsAtelier.total }} dossier(s)
                        </p>
                        <nav class="flex items-center gap-2" aria-label="Pagination">
                            <Link
                                v-if="interventionsAtelier.prev_page_url"
                                :href="interventionsAtelier.prev_page_url"
                                preserve-scroll
                                class="k-btn k-btn--ghost"
                            >
                                <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                                <span>Précédent</span>
                            </Link>
                            <span class="px-2 text-xs font-bold text-[var(--k-ink)]">
                                Page {{ interventionsAtelier.current_page }} / {{ interventionsAtelier.last_page }}
                            </span>
                            <Link
                                v-if="interventionsAtelier.next_page_url"
                                :href="interventionsAtelier.next_page_url"
                                preserve-scroll
                                class="k-btn k-btn--dark"
                            >
                                <span>Suivant</span>
                                <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                            </Link>
                        </nav>
                    </div>
                </section>
            </div>
        </div>

        <!-- MODALE : feuille en bas sur mobile, boîte centrée dès sm -->
        <Transition name="sheet">
            <div
                v-if="dossierASupprimer"
                class="fixed inset-0 z-50 flex items-end justify-center bg-[var(--k-ink)]/60 sm:items-center sm:px-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="titre-suppression"
                @click.self="annulerSuppression"
            >
                <div class="k-sheet w-full max-w-md space-y-4 rounded-t-[var(--k-radius-lg)] bg-white p-6 sm:rounded-[var(--k-radius-lg)]">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--k-signal)]/10 text-[var(--k-signal)]">
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h3 id="titre-suppression" class="text-lg font-extrabold tracking-tight text-[var(--k-ink)]">Supprimer ce dossier ?</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Dossier <span class="font-extrabold text-[var(--k-ink)]">{{ dossierASupprimer?.numero_ot || 'N/A' }}</span> —
                                {{ dossierASupprimer?.vehicule?.marque }} {{ dossierASupprimer?.vehicule?.modele }}
                                <span class="k-plate ml-1">{{ dossierASupprimer?.vehicule?.immatriculation }}</span>
                            </p>
                        </div>
                    </div>

                    <p class="rounded-xl border border-[var(--k-signal)]/20 bg-[var(--k-signal)]/5 p-3 text-xs font-semibold leading-relaxed text-[#9F1239]">
                        Cette action est définitive : la fiche de réception, les photos, le devis et la facture (avec ses paiements) de ce dossier seront supprimés.
                        Le client et ses autres véhicules ne sont pas touchés.
                    </p>

                    <p v-if="erreurSuppression" class="text-xs font-bold text-[var(--k-signal)]" role="alert">{{ erreurSuppression }}</p>

                    <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end sm:gap-3">
                        <button ref="boutonAnnuler" type="button" @click="annulerSuppression" :disabled="suppressionEnCours" class="k-btn k-btn--ghost justify-center">
                            Annuler
                        </button>
                        <button type="button" @click="confirmerSuppression" :disabled="suppressionEnCours" class="k-btn k-btn--signal justify-center">
                            <i v-if="suppressionEnCours" class="fa-solid fa-circle-notch fa-spin text-xs" aria-hidden="true"></i>
                            <span>{{ suppressionEnCours ? 'Suppression…' : 'Oui, supprimer' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<!-- Tokens : palette Garage Kagnan (4 couleurs + 2 nuances sémantiques), rayons, ombres, courbe -->
<style>
:root {
    --k-ink: #0b0f19;
    --k-steel: #8a8d8f;
    --k-muted: #5f6368; /* gris métallique assombri pour le texte : contraste AA sur blanc */
    --k-signal: #e11d48;
    --k-paper: #f8fafc;
    --k-line: #e5e7eb;
    --k-amber: #b45309;

    --k-radius-sm: 10px;
    --k-radius: 14px;
    --k-radius-lg: 22px;

    --k-shadow-1: 0 1px 2px rgba(11, 15, 25, 0.05);
    --k-shadow-2: 0 8px 20px -8px rgba(11, 15, 25, 0.18);
    --k-shadow-3: 0 -12px 40px -8px rgba(11, 15, 25, 0.3);

    --k-ease: cubic-bezier(0.16, 1, 0.3, 1);
}
</style>

<style scoped>
/* ---------- Panneaux ---------- */
.k-panel {
    background: #fff;
    border: 1px solid var(--k-line);
    border-radius: var(--k-radius-lg);
    box-shadow: var(--k-shadow-1);
    padding: 1.25rem;
}
@media (min-width: 640px) {
    .k-panel { padding: 2rem; }
}

.k-chip {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 3.25rem;
    height: 3.25rem;
    border-radius: var(--k-radius);
    background: rgba(225, 29, 72, 0.08);
    color: var(--k-signal);
}

/* ---------- Actions rapides ---------- */
.k-action {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1rem;
    min-height: 4.75rem;
    padding: 1rem 1.15rem;
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--k-line);
    border-radius: var(--k-radius);
    transition: transform 0.25s var(--k-ease), box-shadow 0.25s var(--k-ease), border-color 0.2s ease;
}
.k-action::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 3px;
    background: var(--k-signal);
    transform: scaleY(0);
    transition: transform 0.3s var(--k-ease);
}
.k-action:hover {
    transform: translateY(-2px);
    border-color: #cfd3d9;
    box-shadow: var(--k-shadow-2);
}
.k-action:hover::before { transform: scaleY(1); }
.k-action:active { transform: translateY(0) scale(0.99); }
.k-action:focus-visible { outline: 2px solid var(--k-signal); outline-offset: 2px; }

.k-action__icon {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: var(--k-radius-sm);
    background: var(--k-paper);
    color: var(--k-ink);
    transition: background-color 0.25s ease, color 0.25s ease;
}
.k-action:hover .k-action__icon { background: rgba(225, 29, 72, 0.1); color: var(--k-signal); }

.k-action__title { display: block; font-weight: 800; color: var(--k-ink); line-height: 1.25; }
.k-action__desc { display: block; margin-top: 0.15rem; font-size: 0.75rem; line-height: 1.4; color: var(--k-muted); }

.k-action__arrow {
    flex-shrink: 0;
    font-size: 0.75rem;
    color: var(--k-steel);
    transition: transform 0.3s var(--k-ease), color 0.2s ease;
}
.k-action:hover .k-action__arrow { transform: translateX(4px); color: var(--k-signal); }

/* Action principale : carte claire, accent rouge (plus d'aplat noir) */
.k-action--primary {
    background: #fff;
    border-color: rgba(225, 29, 72, 0.35);
    box-shadow: 0 8px 22px -12px rgba(225, 29, 72, 0.45);
}
.k-action--primary::before { transform: scaleY(1); }
.k-action--primary:hover { border-color: var(--k-signal); }
.k-action--primary .k-action__icon,
.k-action--primary:hover .k-action__icon { background: var(--k-signal); color: #fff; }
.k-action--primary .k-action__arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: rgba(225, 29, 72, 0.08);
    color: var(--k-signal);
    transition: background-color 0.25s ease, color 0.25s ease, transform 0.3s var(--k-ease);
}
.k-action--primary:hover .k-action__arrow { background: var(--k-signal); color: #fff; }

/* Fonctionnalité à venir : pas de faux lien */
.k-action--soon { background: var(--k-paper); cursor: not-allowed; }
.k-action--soon:hover { transform: none; box-shadow: none; border-color: var(--k-line); }
.k-action--soon:hover::before { transform: scaleY(0); }
.k-action--soon:hover .k-action__icon { background: var(--k-paper); color: var(--k-ink); }
.k-action--soon .k-action__icon { background: #fff; color: var(--k-steel); }
.k-action--soon .k-action__title { color: var(--k-muted); }
.k-soon {
    flex-shrink: 0;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    border: 1px solid var(--k-line);
    background: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--k-muted);
}

/* ---------- Champs ---------- */
.k-field {
    min-height: 2.9rem;
    padding-top: 0.7rem;
    padding-bottom: 0.7rem;
    font-size: 0.875rem;
    color: var(--k-ink);
    background: var(--k-paper);
    border: 1px solid var(--k-line);
    border-radius: var(--k-radius);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}
.k-field::placeholder { color: var(--k-muted); opacity: 0.8; }
.k-field:hover { border-color: #cfd3d9; }
.k-field:focus {
    outline: none;
    background: #fff;
    border-color: var(--k-signal);
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.15);
}

/* ---------- Boutons ---------- */
.k-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 2.75rem; /* zone tactile >= 44px */
    padding: 0 1rem;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: var(--k-radius-sm);
    border: 1px solid transparent;
    cursor: pointer;
    transition: transform 0.15s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, opacity 0.2s ease;
}
.k-btn:active:not(:disabled) { transform: scale(0.97); }
.k-btn:focus-visible { outline: 2px solid var(--k-signal); outline-offset: 2px; }
.k-btn:disabled { opacity: 0.55; cursor: not-allowed; }
/* ex-« dark » : passe au rouge signal, plus de noir plein */
.k-btn--dark { background: var(--k-signal); color: #fff; }
.k-btn--dark:hover { background: #be123c; }
.k-btn--ghost { background: #fff; color: #374151; border-color: var(--k-line); }
.k-btn--ghost:hover:not(:disabled) { border-color: var(--k-signal); color: var(--k-signal); }
.k-btn--signal { background: var(--k-signal); color: #fff; }
.k-btn--signal:hover:not(:disabled) { background: #be123c; }
.k-btn--text { min-height: 2.25rem; padding: 0 0.7rem; color: var(--k-ink); background: transparent; }
.k-btn--text:hover { background: var(--k-paper); color: var(--k-signal); }
.k-btn--danger { color: var(--k-muted); }
.k-btn--danger:hover:not(:disabled) { color: var(--k-signal); border-color: var(--k-signal); }
.k-btn--text.k-btn--danger:hover { border-color: transparent; background: rgba(225, 29, 72, 0.08); }

/* ---------- Statuts ---------- */
.k-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.28rem 0.7rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}
.k-badge::before {
    content: '';
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 50%;
    background: currentColor;
}
.k-badge--steel { background: rgba(138, 141, 143, 0.16); color: #4b4e51; }
.k-badge--ink { background: rgba(11, 15, 25, 0.08); color: var(--k-ink); }
.k-badge--signal { background: rgba(225, 29, 72, 0.1); color: #be123c; }
.k-badge--amber { background: rgba(180, 83, 9, 0.1); color: var(--k-amber); }
.k-badge--ok { background: rgba(21, 128, 61, 0.1); color: #15803d; }

/* Plaque d'immatriculation */
.k-plate {
    display: inline-block;
    padding: 0.05rem 0.45rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--k-ink);
    background: #fff;
    border: 1.5px solid var(--k-ink);
    border-radius: 6px;
}

/* ---------- Onglets (type de devis) ---------- */
.k-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 1px solid var(--k-line);
}
.k-tab {
    position: relative;
    display: inline-flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    min-height: 3rem;
    padding: 0 1rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--k-muted);
    background: transparent;
    border: 0;
    cursor: pointer;
    transition: color 0.2s ease, background-color 0.2s ease;
}
@media (min-width: 640px) {
    .k-tab { flex: none; padding: 0 1.5rem; }
}
.k-tab::after {
    content: '';
    position: absolute;
    inset: auto 0 -1px 0;
    height: 2px;
    background: var(--k-signal);
    transform: scaleX(0);
    transition: transform 0.3s var(--k-ease);
}
.k-tab:hover { color: var(--k-ink); background: var(--k-paper); }
.k-tab--active { color: var(--k-ink); }
.k-tab--active::after { transform: scaleX(1); }
.k-tab:focus-visible { outline: 2px solid var(--k-signal); outline-offset: -2px; }

/* ---------- Sous-onglets (statuts) ---------- */
.k-subtabs {
    display: flex;
    gap: 0.5rem;
    padding-bottom: 0.25rem;
    overflow-x: auto;
    scrollbar-width: none;
}
.k-subtabs::-webkit-scrollbar { display: none; }
.k-subtab {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.5rem;
    min-height: 2.5rem;
    padding: 0 0.9rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #374151;
    background: #fff;
    border: 1px solid var(--k-line);
    border-radius: 999px;
    cursor: pointer;
    transition: transform 0.15s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
.k-subtab:hover { border-color: var(--k-signal); color: var(--k-signal); }
.k-subtab:active { transform: scale(0.97); }
.k-subtab:focus-visible { outline: 2px solid var(--k-signal); outline-offset: 2px; }
.k-subtab--active { background: var(--k-signal); border-color: var(--k-signal); color: #fff; }
.k-subtab--active:hover { color: #fff; }

/* ---------- Compteurs ---------- */
.k-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    height: 1.4rem;
    padding: 0 0.45rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--k-ink);
    background: rgba(138, 141, 143, 0.16);
    border-radius: 999px;
    font-variant-numeric: tabular-nums;
}
.k-tab--active .k-count { background: rgba(225, 29, 72, 0.1); color: #be123c; }
.k-count--sm { min-width: 1.3rem; height: 1.2rem; font-size: 0.68rem; }
.k-subtab--active .k-count { background: rgba(255, 255, 255, 0.25); color: #fff; }

/* ---------- Liste des dossiers ---------- */
.k-ticket {
    padding: 1rem;
    background: #fff;
    border: 1px solid var(--k-line);
    border-radius: var(--k-radius);
    box-shadow: var(--k-shadow-1);
}
.k-row { transition: background-color 0.2s ease; }
.k-row:hover { background: var(--k-paper); }

.k-list { transition: opacity 0.2s ease; }
.k-list--busy { opacity: 0.45; pointer-events: none; }

.k-bar {
    position: absolute;
    inset: -0.5rem 0 auto 0;
    height: 2px;
    overflow: hidden;
    border-radius: 2px;
    background: var(--k-line);
}
.k-bar::after {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 40%;
    background: var(--k-signal);
    animation: k-slide 1s var(--k-ease) infinite;
}
@keyframes k-slide {
    from { transform: translateX(-100%); }
    to { transform: translateX(250%); }
}

/* ---------- Écran de chargement ---------- */
.fade-leave-active { transition: opacity 0.4s ease; }
.fade-leave-to { opacity: 0; }

.k-loader { width: 0; animation: k-load 1.4s var(--k-ease) forwards; }
@keyframes k-load { to { width: 100%; } }

.k-rise { opacity: 0; animation: k-rise 0.5s var(--k-ease) forwards; }
@keyframes k-rise {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ---------- Modale ---------- */
.k-sheet { box-shadow: var(--k-shadow-3); }
.sheet-enter-active,
.sheet-leave-active { transition: opacity 0.25s ease; }
.sheet-enter-active .k-sheet,
.sheet-leave-active .k-sheet { transition: transform 0.35s var(--k-ease); }
.sheet-enter-from,
.sheet-leave-to { opacity: 0; }
.sheet-enter-from .k-sheet,
.sheet-leave-to .k-sheet { transform: translateY(100%); } /* mobile : la feuille monte du bas */
@media (min-width: 640px) {
    .sheet-enter-from .k-sheet,
    .sheet-leave-to .k-sheet { transform: translateY(12px) scale(0.97); }
}

/* ---------- Accessibilité : mouvement réduit ---------- */
@media (prefers-reduced-motion: reduce) {
    .k-action, .k-action::before, .k-action__arrow, .k-btn, .k-list,
    .k-tab, .k-tab::after, .k-subtab,
    .sheet-enter-active, .sheet-leave-active,
    .sheet-enter-active .k-sheet, .sheet-leave-active .k-sheet { transition: none; }
    .k-action:hover { transform: none; }
    .k-loader { animation: none; width: 100%; }
    .k-rise { animation: none; opacity: 1; }
    .k-bar::after { animation: none; width: 100%; }
}
</style>