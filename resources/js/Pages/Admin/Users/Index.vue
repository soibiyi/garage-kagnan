<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';
import {
    faGear,
    faSackDollar,
    faCar,
    faUsers,
    faPeopleGroup,
    faFileLines,
    faWrench,
    faUserShield,
    faUserGear,
    faScrewdriverWrench,
    faClipboardUser,
    faHeadset,
    faUserGroup,
    faChevronDown,
    faArrowRight,
    faPlus,
    faPen,
    faTrashCan,
    faRightFromBracket,
    faEnvelope,
    faLocationDot,
    faCalendarDays,
    faReceipt,
    faClipboardCheck,
    faFileInvoiceDollar,
    faClockRotateLeft,
    faTruckFast,
    faIdCardClip,
    faMagnifyingGlass,
    faXmark,
    faTriangleExclamation,
    faCircleNotch,
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    users: Array,
    stats: Object,
    siegeFiltre: { type: String, default: null },
});

const page = usePage();
const meId = computed(() => page.props.auth?.user?.id);

// ─── Écran de chargement (une seule fois par session) ───────────────
const isLoading = ref(false);
const ready = ref(false); // déclenche l'entrée du contenu + le compteur du CA

const prefersReducedMotion = () =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    const hasLoaded = sessionStorage.getItem('admin_loaded');

    if (!hasLoaded) {
        isLoading.value = true;
        setTimeout(() => {
            isLoading.value = false;
            ready.value = true;
            sessionStorage.setItem('admin_loaded', 'true');
        }, 1500);
    } else {
        ready.value = true;
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    cancelAnimationFrame(rafId);
});

// ─── Chiffre d'affaires par mois ────────────────────────────────────
const moisSelectionne = ref(props.stats?.chiffre_affaires_mensuel?.[0]?.cle ?? '');

const chiffreAffairesCible = computed(() => {
    const mois = props.stats?.chiffre_affaires_mensuel?.find(m => m.cle === moisSelectionne.value);
    return Number(mois?.total ?? 0);
});

const moisLabel = computed(
    () => props.stats?.chiffre_affaires_mensuel?.find(m => m.cle === moisSelectionne.value)?.label ?? ''
);

// Le montant « roule » vers sa nouvelle valeur quand le mois change
const caAnime = ref(0);
let rafId = 0;

const animerVers = (cible) => {
    cancelAnimationFrame(rafId);
    if (prefersReducedMotion()) {
        caAnime.value = cible;
        return;
    }
    const depart = caAnime.value;
    const duree = 700;
    const t0 = performance.now();
    const pas = (now) => {
        const p = Math.min((now - t0) / duree, 1);
        const eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p); // easeOutExpo
        caAnime.value = depart + (cible - depart) * eased;
        if (p < 1) rafId = requestAnimationFrame(pas);
    };
    rafId = requestAnimationFrame(pas);
};

watch(
    [chiffreAffairesCible, ready],
    ([cible, pret]) => {
        if (pret) animerVers(cible);
    },
    { immediate: true }
);

const chiffreAffairesAffiche = computed(() =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(caAnime.value))
);

// ─── Accès rapides ──────────────────────────────────────────────────
const accesDevis = [
    { label: 'Devis en attente de validation', description: 'Saisir les choix du client et enregistrer.', icone: faReceipt, href: route('administration.facturation.index') },
    { label: 'Devis validés', description: "Dossiers dont l'accord client a été enregistré.", icone: faClipboardCheck, href: route('administration.devis.acceptes') },
    { label: 'Devis direct', description: 'Création et gestion des devis directs (comptoir).', icone: faFileInvoiceDollar, href: route('administration.devis.directs.index') },
    { label: 'Historique des devis', description: "Vue d'ensemble de tous les services (acceptés / refusés).", icone: faClockRotateLeft, href: route('administration.devis.historique') },
];

const accesSuivi = [
    { label: 'Suivi des interactions', description: 'Échanges entre les chargés client et les clients.', icone: faFileLines, href: route('admin.users.interactionindex') },
    { label: 'Historique des missions', description: 'Missions terminées et chauffeurs responsables.', icone: faTruckFast, href: route('admin.missions.history') },
    { label: 'Historique chauffeurs', description: "Comparer l'activité et consulter les missions de chaque chauffeur.", icone: faIdCardClip, href: route('admin.chauffeurs.history') },
];

// ─── Rôles ──────────────────────────────────────────────────────────
const ROLES_CONFIG = {
    admin: { label: 'Administrateurs', icone: faUserShield, tint: 'bg-gray-900 text-white', avatar: 'bg-[#1A1A1A] text-white' },
    receptionniste: { label: 'Réceptionnistes', icone: faClipboardUser, tint: 'bg-blue-50 text-blue-700', avatar: 'bg-blue-50 text-blue-700' },
    mecanicien: { label: 'Mécaniciens', icone: faScrewdriverWrench, tint: 'bg-amber-50 text-amber-800', avatar: 'bg-amber-50 text-amber-800' },
    administratif: { label: 'Administratifs', icone: faUserGear, tint: 'bg-purple-50 text-purple-700', avatar: 'bg-purple-50 text-purple-700' },
    charge_client: { label: 'Chargés de suivi client', icone: faHeadset, tint: 'bg-rose-50 text-rose-700', avatar: 'bg-rose-50 text-rose-700' },
};

// Accordéon principal
const isEmployeesMenuOpen = ref(true);
const toggleEmployeesMenu = () => {
    isEmployeesMenuOpen.value = !isEmployeesMenuOpen.value;
};

// Sous-accordéons par rôle
const activeRoles = ref({
    admin: true,
    receptionniste: false,
    mecanicien: false,
    administratif: false,
    charge_client: false,
    autre: false,
});
const toggleRole = (roleKey) => {
    activeRoles.value[roleKey] = !activeRoles.value[roleKey];
};

// Recherche + filtre par rôle
const recherche = ref('');
const roleFiltre = ref('tous');

const normaliser = (v = '') =>
    String(v).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

const usersRecherches = computed(() => {
    const q = normaliser(recherche.value.trim());
    if (!props.users) return [];
    if (!q) return props.users;
    return props.users.filter(u => normaliser(u.name).includes(q) || normaliser(u.email).includes(q));
});

const groupedUsers = computed(() => {
    const groups = {};
    Object.keys(ROLES_CONFIG).forEach(role => {
        groups[role] = [];
    });

    usersRecherches.value.forEach(user => {
        if (groups[user.role]) {
            groups[user.role].push(user);
        } else {
            if (!groups['autre']) groups['autre'] = [];
            groups['autre'].push(user);
        }
    });

    return groups;
});

// Groupes réellement affichés (selon le filtre de rôle)
const groupesVisibles = computed(() =>
    Object.entries(groupedUsers.value).filter(
        ([key, list]) => list.length > 0 && (roleFiltre.value === 'tous' || roleFiltre.value === key)
    )
);

const chipsRoles = computed(() =>
    Object.entries(groupedUsers.value)
        .filter(([, list]) => list.length > 0)
        .map(([key, list]) => ({ key, label: ROLES_CONFIG[key]?.label || 'Autres rôles', count: list.length }))
);

// Si le rôle filtré n'a plus de résultat (recherche), on revient à « Tous »
watch(chipsRoles, (chips) => {
    if (roleFiltre.value !== 'tous' && !chips.some(c => c.key === roleFiltre.value)) {
        roleFiltre.value = 'tous';
    }
});

const nombreRoles = computed(() => new Set((props.users || []).map(u => u.role)).size);

const rechercheActive = computed(() => recherche.value.trim() !== '' || roleFiltre.value !== 'tous');
const isRoleOpen = (key) => rechercheActive.value || !!activeRoles.value[key];

const reinitialiserFiltres = () => {
    recherche.value = '';
    roleFiltre.value = 'tous';
};

const formatRole = (role) => ROLES_CONFIG[role]?.label || role;

const initials = (name = '') =>
    name.split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase() || '?';

const formatDate = (date) =>
    date ? new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

// ─── Suppression avec confirmation (feuille modale) ─────────────────
const userASupprimer = ref(null);
const suppressionEnCours = ref(false);
const boutonAnnuler = ref(null);

const demanderSuppression = (user) => {
    userASupprimer.value = user;
    nextTick(() => boutonAnnuler.value?.focus());
};

const fermerSuppression = () => {
    if (suppressionEnCours.value) return;
    userASupprimer.value = null;
};

const confirmerSuppression = () => {
    if (!userASupprimer.value) return;
    router.delete(route('admin.users.destroy', userASupprimer.value.id), {
        preserveScroll: true,
        onStart: () => { suppressionEnCours.value = true; },
        onFinish: () => {
            suppressionEnCours.value = false;
            userASupprimer.value = null;
        },
    });
};

function onKeydown(e) {
    if (e.key === 'Escape') fermerSuppression();
}

const logout = () => {
    sessionStorage.removeItem('admin_loaded');
    router.post(route('logout'));
};
</script>

<template>
  <Head title="Gestion des utilisateurs" />

  <!-- ÉCRAN DE CHARGEMENT -->
  <Transition name="kg-fade">
    <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-white px-6">
      <div class="w-full max-w-xs text-center">
        <span class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#C8102E] text-white shadow-lg shadow-[#C8102E]/25">
          <font-awesome-icon :icon="faGear" class="kg-gear text-2xl" />
        </span>
        <h1 class="text-xl font-extrabold tracking-tight text-[#1A1A1A]">
          Garage Kagnan <span class="text-[#C8102E]">Administration</span>
        </h1>
        <p class="mt-1.5 text-xs font-medium text-[#6B6E70]">Chargement de l'espace d'administration…</p>

        <div class="mt-6 h-1 w-full overflow-hidden rounded-full bg-gray-100">
          <div class="kg-loader h-full rounded-full bg-[#C8102E]"></div>
        </div>
      </div>
    </div>
  </Transition>

  <div class="min-h-screen bg-[#F5F5F6]">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:space-y-8 sm:px-6 sm:py-10 lg:px-8">

      <!-- ═══ EN-TÊTE ═══ -->
      <header
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        :class="ready ? 'kg-rise' : 'opacity-0'"
        style="--d: 0ms"
      >
        <div class="flex items-center gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#C8102E] text-white">
            <font-awesome-icon :icon="faGear" class="text-xl" />
          </span>
          <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-[#1A1A1A] sm:text-3xl">Tableau de bord</h1>
            <p class="mt-0.5 text-sm text-[#6B6E70]">
              Vue d'ensemble de la gestion et des activités
              <span v-if="siegeFiltre" class="font-semibold text-[#C8102E]">, siège {{ siegeFiltre }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div class="min-w-0 flex-1 sm:flex-none">
            <SiegeSwitcher :current="siegeFiltre" />
          </div>
          <button
            type="button"
            @click="logout"
            aria-label="Se déconnecter"
            class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-3 text-sm font-semibold text-[#1A1A1A] shadow-sm transition duration-200 hover:border-gray-300 hover:bg-gray-50 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:px-4"
          >
            <font-awesome-icon :icon="faRightFromBracket" class="text-sm" />
            <span class="hidden sm:inline">Déconnexion</span>
          </button>
        </div>
      </header>

      <!-- ═══ STATISTIQUES ═══ -->
      <section
        class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-12"
        :class="ready ? 'kg-rise' : 'opacity-0'"
        style="--d: 90ms"
        aria-label="Statistiques"
      >
        <!-- Chiffre d'affaires : le seul bloc "fort" de l'écran -->
        <div class="relative col-span-2 flex flex-col justify-between overflow-hidden rounded-2xl bg-[#1A1A1A] p-5 text-white shadow-lg shadow-black/10 sm:p-7 lg:col-span-6">
          <span class="absolute left-0 top-0 h-1 w-16 bg-[#C8102E]"></span>

          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white">
                <font-awesome-icon :icon="faSackDollar" />
              </span>
              <div>
                <p class="text-sm font-semibold text-white">Chiffre d'affaires</p>
                <p v-if="moisLabel" class="text-xs text-white/55">{{ moisLabel }}</p>
              </div>
            </div>

            <div class="relative">
              <select
                v-model="moisSelectionne"
                aria-label="Mois du chiffre d'affaires"
                class="min-h-[44px] max-w-[11rem] cursor-pointer appearance-none rounded-xl border border-white/15 bg-white/10 py-2 pl-3 pr-9 text-xs font-semibold text-white transition duration-200 hover:bg-white/15 focus:border-[#C8102E] focus:outline-none focus:ring-2 focus:ring-[#C8102E]/50"
              >
                <option v-for="m in stats?.chiffre_affaires_mensuel" :key="m.cle" :value="m.cle" class="bg-white text-[#1A1A1A]">
                  {{ m.label }}
                </option>
              </select>
              <font-awesome-icon :icon="faChevronDown" class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[10px] text-white/60" />
            </div>
          </div>

          <div class="mt-8 flex flex-wrap items-end justify-between gap-x-4 gap-y-4 sm:mt-10">
            <p class="min-w-0 text-[clamp(2rem,6vw,3.25rem)] font-black leading-none tabular-nums tracking-tight" aria-live="polite">
              {{ chiffreAffairesAffiche }}
              <span class="ml-1 text-base font-bold text-white/55 sm:text-lg">FCFA</span>
            </p>
            <Link
              :href="route('admin.chiffre-affaires')"
              class="group inline-flex min-h-[44px] shrink-0 items-center gap-2 rounded-xl bg-[#C8102E] px-4 text-sm font-semibold text-white transition duration-200 hover:bg-[#a90d26] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
            >
              <span>Voir le détail</span>
              <font-awesome-icon :icon="faArrowRight" class="text-xs transition-transform duration-300 group-hover:translate-x-1" />
            </Link>
          </div>
        </div>

        <!-- Voitures enregistrées (cliquable) -->
        <Link
          :href="route('admin.vehicules.status')"
          class="group flex flex-col justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-[#C8102E] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:p-6 lg:col-span-3"
        >
          <div class="flex items-center justify-between">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#C8102E]/10 text-[#C8102E] transition duration-300 group-hover:bg-[#C8102E] group-hover:text-white">
              <font-awesome-icon :icon="faCar" />
            </span>
            <font-awesome-icon :icon="faArrowRight" class="text-xs text-gray-300 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
          </div>
          <div class="mt-6">
            <p class="text-3xl font-black tabular-nums tracking-tight text-[#1A1A1A] sm:text-4xl">{{ stats?.nombre_voitures || '0' }}</p>
            <p class="mt-1 text-sm font-medium text-[#6B6E70]">Voitures enregistrées</p>
          </div>
        </Link>

        <!-- Clients totaux (information, non cliquable : plus à plat) -->
        <Link
          :href="route('admin.clients.gestion')"
          class="group flex flex-col justify-between rounded-2xl border border-gray-200 bg-white p-4 sm:p-6 lg:col-span-3"
        >
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-[#1A1A1A]">
            <font-awesome-icon :icon="faUsers" />
          </span>
          <div class="mt-6">
            <p class="text-3xl font-black tabular-nums tracking-tight text-[#1A1A1A] sm:text-4xl">{{ stats?.nombre_clients || '0' }}</p>
            <p class="mt-1 text-sm font-medium text-[#6B6E70]">Clients totaux</p>
          </div>
        </Link>
      </section>

      <!-- ═══ NAVIGATION RAPIDE ═══ -->
      <nav
        class="grid grid-cols-1 gap-4 sm:gap-5 lg:grid-cols-2"
        :class="ready ? 'kg-rise' : 'opacity-0'"
        style="--d: 180ms"
        aria-label="Accès rapide"
      >
        <!-- Colonne gauche : circuit des devis -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-100 px-4 py-4 sm:px-5">
            <h2 class="text-base font-bold text-[#1A1A1A]">Devis</h2>
            <p class="mt-0.5 text-xs text-[#6B6E70]">De la validation client à l'archivage.</p>
          </div>
          <ul class="divide-y divide-gray-100">
            <li v-for="acces in accesDevis" :key="acces.label">
              <Link
                :href="acces.href"
                class="group flex min-h-[68px] items-center gap-4 px-4 py-3.5 transition duration-200 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#C8102E]/40 sm:px-5"
              >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-[#C8102E] transition duration-300 group-hover:bg-[#C8102E] group-hover:text-white">
                  <font-awesome-icon :icon="acces.icone" />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-bold text-[#1A1A1A]">{{ acces.label }}</span>
                  <span class="mt-0.5 block text-xs leading-snug text-[#6B6E70]">{{ acces.description }}</span>
                </span>
                <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-300 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
              </Link>
            </li>
          </ul>
        </div>

        <!-- Colonne droite : pièces + suivi -->
        <div class="flex flex-col gap-4 sm:gap-5">
          <Link
            :href="route('admin.pieces.index')"
            class="group relative flex items-center gap-4 overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-[#C8102E] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:p-5"
          >
            <span class="absolute inset-y-0 left-0 w-1 bg-[#C8102E]"></span>
            <span class="ml-1 flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#C8102E]/10 text-xl text-[#C8102E] transition duration-300 group-hover:bg-[#C8102E] group-hover:text-white">
              <font-awesome-icon :icon="faWrench" />
            </span>
            <div class="min-w-0 flex-1">
              <h2 class="text-base font-bold text-[#1A1A1A]">Gestion de pièces</h2>
              <p class="mt-0.5 text-sm leading-snug text-[#6B6E70]">Entrées, prix d'achat, marges, sorties et stock en temps réel par siège.</p>
            </div>
            <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-300 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
          </Link>

          <div class="flex-1 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-4 sm:px-5">
              <h2 class="text-base font-bold text-[#1A1A1A]">Suivi de l'activité</h2>
              <p class="mt-0.5 text-xs text-[#6B6E70]">Équipes, missions et chauffeurs.</p>
            </div>
            <ul class="divide-y divide-gray-100">
              <li v-for="acces in accesSuivi" :key="acces.label">
                <Link
                  :href="acces.href"
                  class="group flex min-h-[68px] items-center gap-4 px-4 py-3.5 transition duration-200 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#C8102E]/40 sm:px-5"
                >
                  <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-[#C8102E] transition duration-300 group-hover:bg-[#C8102E] group-hover:text-white">
                    <font-awesome-icon :icon="acces.icone" />
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-[#1A1A1A]">{{ acces.label }}</span>
                    <span class="mt-0.5 block text-xs leading-snug text-[#6B6E70]">{{ acces.description }}</span>
                  </span>
                  <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-300 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
                </Link>
              </li>
            </ul>
          </div>
        </div>
      </nav>

      <!-- ═══ LISTE DES EMPLOYÉS ═══ -->
      <section
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
        :class="ready ? 'kg-rise' : 'opacity-0'"
        style="--d: 270ms"
      >
        <!-- En-tête du menu -->
        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:p-6">
          <button
            type="button"
            @click="toggleEmployeesMenu"
            :aria-expanded="isEmployeesMenuOpen"
            aria-controls="employees-panel"
            class="flex min-h-[44px] flex-1 items-center gap-4 rounded-xl text-left transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40"
          >
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#1A1A1A] text-white">
              <font-awesome-icon :icon="faPeopleGroup" class="text-lg" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-lg font-extrabold tracking-tight text-[#1A1A1A] sm:text-xl">Liste des employés</span>
              <span class="block text-xs text-[#6B6E70]">
                {{ users?.length || 0 }} {{ (users?.length || 0) > 1 ? 'collaborateurs' : 'collaborateur' }}
                <template v-if="nombreRoles > 0">répartis sur {{ nombreRoles }} {{ nombreRoles > 1 ? 'rôles' : 'rôle' }}</template>
              </span>
            </span>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
              <font-awesome-icon
                :icon="faChevronDown"
                class="kg-ease text-sm transition-transform duration-300"
                :class="{ 'rotate-180': isEmployeesMenuOpen }"
              />
            </span>
          </button>

          <Link
            :href="route('admin.users.create')"
            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-[#C8102E] px-4 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-[#a90d26] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 focus-visible:ring-offset-2"
          >
            <font-awesome-icon :icon="faPlus" class="text-xs" />
            <span>Ajouter un employé</span>
          </Link>
        </div>

        <!-- Panneau principal (hauteur animée) -->
        <div
          id="employees-panel"
          class="kg-collapse"
          :class="{ 'kg-open': isEmployeesMenuOpen }"
          :inert="!isEmployeesMenuOpen"
        >
          <div class="kg-collapse-inner">
            <div class="border-t border-gray-100 bg-gray-50/60">

              <!-- Barre d'outils : recherche + filtre par rôle -->
              <div v-if="users && users.length > 0" class="space-y-3 border-b border-gray-100 bg-white p-4 sm:p-6">
                <div class="relative">
                  <font-awesome-icon :icon="faMagnifyingGlass" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-gray-400" />
                  <input
                    v-model="recherche"
                    type="search"
                    inputmode="search"
                    autocomplete="off"
                    placeholder="Rechercher un collaborateur par nom ou e-mail"
                    aria-label="Rechercher un collaborateur"
                    class="min-h-[44px] w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-11 text-sm text-[#1A1A1A] placeholder:text-gray-400 transition duration-200 focus:border-[#C8102E] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#C8102E]/25 [&::-webkit-search-cancel-button]:hidden"
                  />
                  <button
                    v-if="recherche"
                    type="button"
                    @click="recherche = ''"
                    aria-label="Effacer la recherche"
                    class="absolute right-0.5 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-xl text-gray-400 transition duration-200 hover:text-[#1A1A1A] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40"
                  >
                    <font-awesome-icon :icon="faXmark" class="text-sm" />
                  </button>
                </div>

                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Filtrer par rôle">
                  <button
                    type="button"
                    @click="roleFiltre = 'tous'"
                    :aria-pressed="roleFiltre === 'tous'"
                    class="inline-flex min-h-[44px] shrink-0 items-center gap-2 rounded-xl border px-3.5 text-xs font-semibold transition duration-200 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:min-h-[36px]"
                    :class="roleFiltre === 'tous'
                      ? 'border-[#1A1A1A] bg-[#1A1A1A] text-white'
                      : 'border-gray-200 bg-white text-[#1A1A1A] hover:border-gray-300 hover:bg-gray-50'"
                  >
                    Tous
                    <span class="tabular-nums" :class="roleFiltre === 'tous' ? 'text-white/70' : 'text-gray-400'">{{ usersRecherches.length }}</span>
                  </button>
                  <button
                    v-for="chip in chipsRoles"
                    :key="chip.key"
                    type="button"
                    @click="roleFiltre = chip.key"
                    :aria-pressed="roleFiltre === chip.key"
                    class="inline-flex min-h-[44px] shrink-0 items-center gap-2 rounded-xl border px-3.5 text-xs font-semibold transition duration-200 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:min-h-[36px]"
                    :class="roleFiltre === chip.key
                      ? 'border-[#1A1A1A] bg-[#1A1A1A] text-white'
                      : 'border-gray-200 bg-white text-[#1A1A1A] hover:border-gray-300 hover:bg-gray-50'"
                  >
                    {{ chip.label }}
                    <span class="tabular-nums" :class="roleFiltre === chip.key ? 'text-white/70' : 'text-gray-400'">{{ chip.count }}</span>
                  </button>
                </div>
              </div>

              <div class="space-y-3 p-4 sm:p-6">

                <template v-for="[roleKey, usersList] in groupesVisibles" :key="roleKey">
                  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">

                    <!-- Sous-menu rôle -->
                    <button
                      type="button"
                      @click="toggleRole(roleKey)"
                      :aria-expanded="isRoleOpen(roleKey)"
                      class="flex min-h-[56px] w-full items-center justify-between gap-3 p-3.5 text-left transition duration-200 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#C8102E]/40 sm:p-4"
                    >
                      <div class="flex min-w-0 items-center gap-3">
                        <span
                          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm"
                          :class="ROLES_CONFIG[roleKey]?.tint || 'bg-gray-100 text-gray-700'"
                        >
                          <font-awesome-icon :icon="ROLES_CONFIG[roleKey]?.icone || faUserGroup" />
                        </span>
                        <h3 class="truncate text-sm font-bold text-[#1A1A1A]">
                          {{ ROLES_CONFIG[roleKey]?.label || 'Autres rôles' }}
                        </h3>
                      </div>

                      <div class="flex shrink-0 items-center gap-3">
                        <span class="min-w-[1.75rem] rounded-full bg-gray-100 px-2 py-0.5 text-center text-xs font-bold tabular-nums text-gray-700">
                          {{ usersList.length }}
                        </span>
                        <font-awesome-icon
                          :icon="faChevronDown"
                          class="kg-ease text-xs text-gray-400 transition-transform duration-300"
                          :class="{ 'rotate-180': isRoleOpen(roleKey) }"
                        />
                      </div>
                    </button>

                    <!-- Cartes employés (hauteur animée) -->
                    <div class="kg-collapse" :class="{ 'kg-open': isRoleOpen(roleKey) }" :inert="!isRoleOpen(roleKey)">
                      <div class="kg-collapse-inner">
                        <div class="border-t border-gray-100 bg-gray-50/50 p-3.5 sm:p-5">
                          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
                            <article
                              v-for="user in usersList"
                              :key="user.id"
                              class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-4 transition duration-300 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md"
                            >
                              <div>
                                <div class="flex items-start gap-3">
                                  <span
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold"
                                    :class="ROLES_CONFIG[user.role]?.avatar || 'bg-gray-100 text-gray-700'"
                                    aria-hidden="true"
                                  >
                                    {{ initials(user.name) }}
                                  </span>
                                  <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                      <h4 class="truncate text-sm font-bold text-[#1A1A1A]" :title="user.name">{{ user.name }}</h4>
                                      <span
                                        v-if="user.id === meId"
                                        class="shrink-0 rounded-full bg-[#C8102E]/10 px-2 py-0.5 text-[11px] font-semibold leading-4 text-[#C8102E]"
                                      >
                                        Vous
                                      </span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-[#6B6E70]">{{ formatRole(user.role) }}</p>
                                  </div>
                                </div>

                                <ul class="mt-4 space-y-2 text-xs text-[#6B6E70]">
                                  <li class="flex min-w-0 items-center gap-2.5">
                                    <font-awesome-icon :icon="faEnvelope" class="w-3.5 shrink-0 text-gray-400" />
                                    <span class="truncate" :title="user.email">{{ user.email }}</span>
                                  </li>
                                  <li v-if="user.siege" class="flex items-center gap-2.5">
                                    <font-awesome-icon :icon="faLocationDot" class="w-3.5 shrink-0 text-gray-400" />
                                    <span>Siège {{ user.siege }}</span>
                                  </li>
                                  <li class="flex items-center gap-2.5">
                                    <font-awesome-icon :icon="faCalendarDays" class="w-3.5 shrink-0 text-gray-400" />
                                    <span>Créé le {{ formatDate(user.created_at) }}</span>
                                  </li>
                                </ul>
                              </div>

                              <div class="mt-4 flex items-center justify-end gap-2 border-t border-gray-100 pt-3 text-xs font-semibold">
                                <Link
                                  :href="route('admin.users.edit', user.id)"
                                  class="inline-flex min-h-[44px] items-center gap-2 rounded-lg px-3 text-blue-700 transition duration-200 hover:bg-blue-50 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400/50"
                                >
                                  <font-awesome-icon :icon="faPen" class="text-[11px]" />
                                  <span>Modifier</span>
                                </Link>
                                <button
                                  v-if="user.id !== meId"
                                  type="button"
                                  @click="demanderSuppression(user)"
                                  class="inline-flex min-h-[44px] items-center gap-2 rounded-lg px-3 text-red-700 transition duration-200 hover:bg-red-50 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400/50"
                                >
                                  <font-awesome-icon :icon="faTrashCan" class="text-[11px]" />
                                  <span>Supprimer</span>
                                </button>
                              </div>
                            </article>
                          </div>
                        </div>
                      </div>
                    </div>

                  </div>
                </template>

                <!-- Aucun résultat pour la recherche -->
                <div
                  v-if="users && users.length > 0 && groupesVisibles.length === 0"
                  class="flex flex-col items-center rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center"
                >
                  <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-500">
                    <font-awesome-icon :icon="faMagnifyingGlass" class="text-lg" />
                  </span>
                  <p class="mt-4 text-sm font-semibold text-[#1A1A1A]">Aucun collaborateur ne correspond à « {{ recherche }} »</p>
                  <p class="mt-1 text-xs text-[#6B6E70]">Vérifiez l'orthographe ou essayez avec une partie de l'adresse e-mail.</p>
                  <button
                    type="button"
                    @click="reinitialiserFiltres"
                    class="mt-5 inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-[#1A1A1A] transition duration-200 hover:border-gray-300 hover:bg-gray-50 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40"
                  >
                    <font-awesome-icon :icon="faXmark" class="text-xs" />
                    <span>Effacer les filtres</span>
                  </button>
                </div>

                <!-- État vide -->
                <div
                  v-if="!users || users.length === 0"
                  class="flex flex-col items-center rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center"
                >
                  <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-500">
                    <font-awesome-icon :icon="faUserGroup" class="text-lg" />
                  </span>
                  <p class="mt-4 text-sm font-semibold text-[#1A1A1A]">Aucun collaborateur pour le moment</p>
                  <p class="mt-1 text-xs text-[#6B6E70]">Ajoutez votre premier employé pour constituer l'équipe du garage.</p>
                  <Link
                    :href="route('admin.users.create')"
                    class="mt-5 inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-[#C8102E] px-4 text-sm font-semibold text-white transition duration-200 hover:bg-[#a90d26] active:scale-[0.98]"
                  >
                    <font-awesome-icon :icon="faPlus" class="text-xs" />
                    <span>Ajouter un employé</span>
                  </Link>
                </div>

              </div>
            </div>
          </div>
        </div>
      </section>

    </div>
  </div>

  <!-- ═══ CONFIRMATION DE SUPPRESSION ═══ -->
  <Transition name="kg-modal">
    <div
      v-if="userASupprimer"
      class="fixed inset-0 z-40 flex items-end justify-center sm:items-center sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="kg-suppr-titre"
    >
      <div class="kg-backdrop absolute inset-0 bg-[#1A1A1A]/50" @click="fermerSuppression"></div>

      <div class="kg-sheet relative w-full max-w-md rounded-t-2xl bg-white p-5 shadow-2xl sm:rounded-2xl sm:p-6">
        <div class="flex items-start gap-4">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#C8102E]/10 text-[#C8102E]">
            <font-awesome-icon :icon="faTriangleExclamation" />
          </span>
          <div class="min-w-0">
            <h2 id="kg-suppr-titre" class="text-base font-bold text-[#1A1A1A]">Supprimer ce collaborateur ?</h2>
            <p class="mt-1 text-sm leading-relaxed text-[#6B6E70]">
              Le compte de <span class="font-semibold text-[#1A1A1A]">{{ userASupprimer.name }}</span>
              sera définitivement supprimé. Cette action est irréversible.
            </p>
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button
            ref="boutonAnnuler"
            type="button"
            @click="fermerSuppression"
            :disabled="suppressionEnCours"
            class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-[#1A1A1A] transition duration-200 hover:bg-gray-50 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 disabled:opacity-50"
          >
            Annuler
          </button>
          <button
            type="button"
            @click="confirmerSuppression"
            :disabled="suppressionEnCours"
            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-[#C8102E] px-4 text-sm font-semibold text-white transition duration-200 hover:bg-[#a90d26] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/50 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
          >
            <font-awesome-icon :icon="suppressionEnCours ? faCircleNotch : faTrashCan" :class="{ 'animate-spin': suppressionEnCours }" class="text-xs" />
            <span>{{ suppressionEnCours ? 'Suppression…' : 'Supprimer' }}</span>
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
/* Courbe "premium" partagée */
.kg-ease {
  transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

/* Disparition douce du loader */
.kg-fade-leave-active {
  transition: opacity 0.4s ease-out;
}
.kg-fade-leave-to {
  opacity: 0;
}

/* Barre de chargement : une seule progression, sans boucle */
@keyframes kgLoading {
  from { width: 0%; }
  to { width: 100%; }
}
.kg-loader {
  animation: kgLoading 1.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Engrenage du loader : un tour lent */
@keyframes kgGear {
  from { transform: rotate(0deg); }
  to { transform: rotate(180deg); }
}
.kg-gear {
  animation: kgGear 1.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Moment fort : entrée échelonnée des sections après le chargement */
@keyframes kgRise {
  from { opacity: 0; transform: translateY(14px); }
  to { opacity: 1; transform: translateY(0); }
}
.kg-rise {
  animation: kgRise 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
  animation-delay: var(--d, 0ms);
}

/* Accordéons : hauteur animée sans valeur fixe */
.kg-collapse {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.kg-collapse.kg-open {
  grid-template-rows: 1fr;
}
.kg-collapse-inner {
  min-height: 0;
  overflow: hidden;
}

/* Feuille de confirmation : fond qui s'estompe, panneau qui monte */
.kg-modal-enter-active,
.kg-modal-leave-active {
  transition: opacity 0.25s ease-out;
}
.kg-modal-enter-active .kg-sheet,
.kg-modal-leave-active .kg-sheet {
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease-out;
}
.kg-modal-enter-from,
.kg-modal-leave-to {
  opacity: 0;
}
.kg-modal-enter-from .kg-sheet,
.kg-modal-leave-to .kg-sheet {
  transform: translateY(24px);
  opacity: 0;
}

/* Accessibilité : mouvement réduit */
@media (prefers-reduced-motion: reduce) {
  .kg-rise,
  .kg-loader,
  .kg-gear {
    animation: none;
  }
  .kg-loader {
    width: 100%;
  }
  .kg-collapse,
  .kg-fade-leave-active,
  .kg-modal-enter-active,
  .kg-modal-leave-active,
  .kg-modal-enter-active .kg-sheet,
  .kg-modal-leave-active .kg-sheet {
    transition: none;
  }
}
</style>