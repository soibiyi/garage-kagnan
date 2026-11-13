<script setup>
// Tableau de bord administrateur (CA, statistiques, accès rapides, employés).
// Utilisé par la page admin (Admin/Users/Index) et intégré à l'accueil du super administrateur
// (SuperAdmin/Accueil) avec `integre` : sans écran de chargement, sans en-tête ni fond de page.
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
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
    faListCheck,
    faUserTie,
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    users: Array,
    stats: Object,
    siegeFiltre: { type: String, default: null },
    integre: { type: Boolean, default: false },
});

const page = usePage();
const meId = computed(() => page.props.auth?.user?.id);
const estSuperAdmin = computed(() => !!page.props.auth?.user?.is_superadmin);
// Les comptes administrateurs ne sont gérés que par le super administrateur (l'admin garde la main sur le sien)
const peutGerer = (user) => estSuperAdmin.value || user.role !== 'admin' || user.id === meId.value;

// ─── Écran de chargement (une seule fois par session) ───────────────
const isLoading = ref(false);
const ready = ref(false); // déclenche l'entrée du contenu + le compteur du CA

const prefersReducedMotion = () =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    const hasLoaded = sessionStorage.getItem('admin_loaded');

    if (props.integre) {
        ready.value = true;
    } else if (!hasLoaded) {
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

// ─── Reflet de lumière qui suit le pointeur ─────────────────────────
const onMove = (e) => {
    const el = e.target?.closest?.('.glass');
    if (!el) return;
    const r = el.getBoundingClientRect();
    el.style.setProperty('--mx', `${e.clientX - r.left}px`);
    el.style.setProperty('--my', `${e.clientY - r.top}px`);
};

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
    { label: 'Secrétariat', description: 'Confier des tâches avec un délai et échanger avec les secrétaires.', icone: faListCheck, href: route('admin.secretariat.index') },
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
    secretaire: { label: 'Secrétaires', icone: faUserTie, tint: 'bg-teal-50 text-teal-700', avatar: 'bg-teal-50 text-teal-700' },
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
    secretaire: false,
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
  <div class="page" :class="{ 'page-integree': integre }" @pointermove="onMove">
    <!-- Lumière d'arrière-plan : ce que le verre réfracte -->
    <div v-if="!integre" class="orbs" aria-hidden="true">
      <span class="orb orb-1"></span>
      <span class="orb orb-2"></span>
      <span class="orb orb-3"></span>
    </div>

    <!-- ÉCRAN DE CHARGEMENT -->
    <Transition name="kg-fade">
      <div v-if="isLoading" class="loader fixed inset-0 z-50 flex items-center justify-center px-6">
        <div class="glass glass-lg w-full max-w-xs p-8 text-center">
          <span class="tile-base tile-red-solid mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-2xl text-white">
            <font-awesome-icon :icon="faGear" class="kg-gear text-2xl" />
          </span>
          <h1 class="text-xl font-extrabold tracking-tight text-[#1A1A1A]">
            Garage Kagnan <span class="text-[#C8102E]">Administration</span>
          </h1>
          <p class="mt-1.5 text-xs font-medium text-[#6B6E70]">Chargement de l'espace d'administration…</p>

          <div class="progress mt-6">
            <div class="kg-loader h-full rounded-full bg-[#C8102E]"></div>
          </div>
        </div>
      </div>
    </Transition>

    <div
      class="relative space-y-5 sm:space-y-7"
      :class="integre ? '' : 'mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-10 lg:px-8'"
    >

      <!-- ═══ EN-TÊTE ═══ -->
      <header
        v-if="!integre"
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        :class="ready ? 'kg-rise' : 'opacity-0'"
        style="--d: 0ms"
      >
        <div class="flex items-center gap-4">
          <span class="tile-base tile-red-solid flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white">
            <font-awesome-icon :icon="faGear" class="text-xl" />
          </span>
          <div class="min-w-0">
            <h1 class="text-2xl font-black tracking-tight text-[#1A1A1A] sm:text-3xl">Tableau de bord</h1>
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
          <button type="button" @click="logout" aria-label="Se déconnecter" class="gbtn gbtn-neutral shrink-0 !px-3.5 sm:!px-5">
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
        <!-- Chiffre d'affaires : verre sombre, le seul bloc « fort » de l'écran -->
        <div class="glass glass-dark glass-lg col-span-2 flex flex-col justify-between p-5 sm:p-7 lg:col-span-6">
          <span class="absolute left-0 top-0 h-1 w-16 rounded-br-full bg-[#C8102E]"></span>

          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="tile-base flex h-10 w-10 items-center justify-center rounded-xl bg-white/12 text-white">
                <font-awesome-icon :icon="faSackDollar" />
              </span>
              <div>
                <p class="text-sm font-semibold text-white">Chiffre d'affaires</p>
                <p v-if="moisLabel" class="text-xs text-white/55">{{ moisLabel }}</p>
              </div>
            </div>

            <div class="relative">
              <select v-model="moisSelectionne" aria-label="Mois du chiffre d'affaires" class="select-dark">
                <option v-for="m in stats?.chiffre_affaires_mensuel" :key="m.cle" :value="m.cle" class="bg-white text-[#1A1A1A]">
                  {{ m.label }}
                </option>
              </select>
              <font-awesome-icon :icon="faChevronDown" class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[10px] text-white/60" />
            </div>
          </div>

          <div class="mt-8 flex flex-wrap items-end justify-between gap-x-4 gap-y-4 sm:mt-10">
            <p class="min-w-0 text-[clamp(2rem,6vw,3.25rem)] font-black leading-none tabular-nums tracking-tight text-white" aria-live="polite">
              {{ chiffreAffairesAffiche }}
              <span class="ml-1 text-base font-bold text-white/55 sm:text-lg">FCFA</span>
            </p>
            <Link :href="route('admin.chiffre-affaires')" class="gbtn gbtn-red group shrink-0">
              <span>Voir le détail</span>
              <font-awesome-icon :icon="faArrowRight" class="text-xs transition-transform duration-300 group-hover:translate-x-1" />
            </Link>
          </div>
        </div>

        <!-- Voitures enregistrées (cliquable) -->
        <Link :href="route('admin.vehicules.status')" class="glass glass-lg lift group flex flex-col justify-between p-4 sm:p-6 lg:col-span-3">
          <div class="flex items-center justify-between">
            <span class="tile-base tile-red tile-hover flex h-10 w-10 items-center justify-center rounded-xl">
              <font-awesome-icon :icon="faCar" />
            </span>
            <font-awesome-icon :icon="faArrowRight" class="text-xs text-gray-400 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
          </div>
          <div class="mt-6">
            <p class="text-3xl font-black tabular-nums tracking-tight text-[#1A1A1A] sm:text-4xl">{{ stats?.nombre_voitures || '0' }}</p>
            <p class="mt-1 text-sm font-medium text-[#6B6E70]">Voitures enregistrées</p>
          </div>
        </Link>

        <!-- Clients totaux -->
        <Link :href="route('admin.clients.gestion')" class="glass glass-lg lift group flex flex-col justify-between p-4 sm:p-6 lg:col-span-3">
          <div class="flex items-center justify-between">
            <span class="tile-base tile-ink flex h-10 w-10 items-center justify-center rounded-xl">
              <font-awesome-icon :icon="faUsers" />
            </span>
            <font-awesome-icon :icon="faArrowRight" class="text-xs text-gray-400 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
          </div>
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
        <div class="glass glass-lg">
          <div class="card-head">
            <div>
              <h2 class="text-base font-black text-[#1A1A1A]">Devis</h2>
              <p class="mt-0.5 text-xs text-[#6B6E70]">De la validation client à l'archivage.</p>
            </div>
          </div>
          <ul class="p-2">
            <li v-for="acces in accesDevis" :key="acces.label">
              <Link :href="acces.href" class="nav-row group">
                <span class="tile-base tile-link flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                  <font-awesome-icon :icon="acces.icone" />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-bold text-[#1A1A1A]">{{ acces.label }}</span>
                  <span class="mt-0.5 block text-xs leading-snug text-[#6B6E70]">{{ acces.description }}</span>
                </span>
                <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-400 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
              </Link>
            </li>
          </ul>
        </div>

        <!-- Colonne droite : pièces + suivi -->
        <div class="flex flex-col gap-4 sm:gap-5">
          <Link :href="route('admin.pieces.index')" class="glass glass-lg lift group flex items-center gap-4 p-4 sm:p-5">
            <span class="tile-base tile-red tile-hover flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-xl">
              <font-awesome-icon :icon="faWrench" />
            </span>
            <div class="min-w-0 flex-1">
              <h2 class="text-base font-black text-[#1A1A1A]">Gestion de pièces</h2>
              <p class="mt-0.5 text-sm leading-snug text-[#6B6E70]">Entrées, prix d'achat, marges, sorties et stock en temps réel par siège.</p>
            </div>
            <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-400 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
          </Link>

          <div class="glass glass-lg flex-1">
            <div class="card-head">
              <div>
                <h2 class="text-base font-black text-[#1A1A1A]">Suivi de l'activité</h2>
                <p class="mt-0.5 text-xs text-[#6B6E70]">Équipes, secrétariat, missions et chauffeurs.</p>
              </div>
            </div>
            <ul class="p-2">
              <li v-for="acces in accesSuivi" :key="acces.label">
                <Link :href="acces.href" class="nav-row group">
                  <span class="tile-base tile-link flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                    <font-awesome-icon :icon="acces.icone" />
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-[#1A1A1A]">{{ acces.label }}</span>
                    <span class="mt-0.5 block text-xs leading-snug text-[#6B6E70]">{{ acces.description }}</span>
                  </span>
                  <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-400 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
                </Link>
              </li>
            </ul>
          </div>
        </div>
      </nav>

      <!-- ═══ LISTE DES EMPLOYÉS ═══ -->
      <section id="employes" class="glass glass-lg scroll-mt-4" :class="ready ? 'kg-rise' : 'opacity-0'" style="--d: 270ms">
        <!-- En-tête du menu -->
        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:p-6">
          <button
            type="button"
            @click="toggleEmployeesMenu"
            :aria-expanded="isEmployeesMenuOpen"
            aria-controls="employees-panel"
            class="focus-ring flex min-h-[44px] flex-1 items-center gap-4 rounded-2xl text-left"
          >
            <span class="tile-base tile-ink-solid flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-white">
              <font-awesome-icon :icon="faPeopleGroup" class="text-lg" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-lg font-black tracking-tight text-[#1A1A1A] sm:text-xl">Liste des employés</span>
              <span class="block text-xs text-[#6B6E70]">
                {{ users?.length || 0 }} {{ (users?.length || 0) > 1 ? 'collaborateurs' : 'collaborateur' }}
                <template v-if="nombreRoles > 0">répartis sur {{ nombreRoles }} {{ nombreRoles > 1 ? 'rôles' : 'rôle' }}</template>
              </span>
            </span>
            <span class="icon-pill">
              <font-awesome-icon
                :icon="faChevronDown"
                class="kg-ease text-sm transition-transform duration-300"
                :class="{ 'rotate-180': isEmployeesMenuOpen }"
              />
            </span>
          </button>

          <Link :href="route('admin.users.create')" class="gbtn gbtn-red">
            <font-awesome-icon :icon="faPlus" class="text-xs" />
            <span>Ajouter un employé</span>
          </Link>
        </div>

        <!-- Panneau principal (hauteur animée) -->
        <div id="employees-panel" class="kg-collapse" :class="{ 'kg-open': isEmployeesMenuOpen }" :inert="!isEmployeesMenuOpen">
          <div class="kg-collapse-inner">
            <div class="panel-divider">

              <!-- Barre d'outils : recherche + filtre par rôle -->
              <div v-if="users && users.length > 0" class="space-y-3 p-4 sm:p-6 sm:pb-2">
                <div class="relative">
                  <font-awesome-icon :icon="faMagnifyingGlass" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-500" />
                  <input
                    v-model="recherche"
                    type="search"
                    inputmode="search"
                    autocomplete="off"
                    placeholder="Rechercher un collaborateur par nom ou e-mail"
                    aria-label="Rechercher un collaborateur"
                    class="field-input [&::-webkit-search-cancel-button]:hidden"
                  />
                  <button
                    v-if="recherche"
                    type="button"
                    @click="recherche = ''"
                    aria-label="Effacer la recherche"
                    class="icon-btn focus-ring absolute right-1.5 top-1/2 -translate-y-1/2"
                  >
                    <font-awesome-icon :icon="faXmark" class="text-sm" />
                  </button>
                </div>

                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Filtrer par rôle">
                  <button
                    type="button"
                    @click="roleFiltre = 'tous'"
                    :aria-pressed="roleFiltre === 'tous'"
                    class="pill"
                    :class="roleFiltre === 'tous' ? 'pill-active' : ''"
                  >
                    Tous
                    <span class="tabular-nums opacity-60">{{ usersRecherches.length }}</span>
                  </button>
                  <button
                    v-for="chip in chipsRoles"
                    :key="chip.key"
                    type="button"
                    @click="roleFiltre = chip.key"
                    :aria-pressed="roleFiltre === chip.key"
                    class="pill"
                    :class="roleFiltre === chip.key ? 'pill-active' : ''"
                  >
                    {{ chip.label }}
                    <span class="tabular-nums opacity-60">{{ chip.count }}</span>
                  </button>
                </div>
              </div>

              <div class="space-y-3 p-4 sm:p-6">

                <template v-for="[roleKey, usersList] in groupesVisibles" :key="roleKey">
                  <div class="surface">

                    <!-- Sous-menu rôle -->
                    <button
                      type="button"
                      @click="toggleRole(roleKey)"
                      :aria-expanded="isRoleOpen(roleKey)"
                      class="role-head focus-ring"
                    >
                      <div class="flex min-w-0 items-center gap-3">
                        <span
                          class="tile-base flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm"
                          :class="ROLES_CONFIG[roleKey]?.tint || 'bg-gray-100 text-gray-700'"
                        >
                          <font-awesome-icon :icon="ROLES_CONFIG[roleKey]?.icone || faUserGroup" />
                        </span>
                        <h3 class="truncate text-sm font-bold text-[#1A1A1A]">
                          {{ ROLES_CONFIG[roleKey]?.label || 'Autres rôles' }}
                        </h3>
                      </div>

                      <div class="flex shrink-0 items-center gap-3">
                        <span class="count">{{ usersList.length }}</span>
                        <font-awesome-icon
                          :icon="faChevronDown"
                          class="kg-ease text-xs text-gray-500 transition-transform duration-300"
                          :class="{ 'rotate-180': isRoleOpen(roleKey) }"
                        />
                      </div>
                    </button>

                    <!-- Cartes employés (hauteur animée) -->
                    <div class="kg-collapse" :class="{ 'kg-open': isRoleOpen(roleKey) }" :inert="!isRoleOpen(roleKey)">
                      <div class="kg-collapse-inner">
                        <div class="panel-divider p-3.5 sm:p-5">
                          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
                            <article v-for="user in usersList" :key="user.id" class="surface surface-card lift-soft flex flex-col justify-between p-4">
                              <div>
                                <div class="flex items-start gap-3">
                                  <span
                                    class="tile-base flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-extrabold"
                                    :class="ROLES_CONFIG[user.role]?.avatar || 'bg-gray-100 text-gray-700'"
                                    aria-hidden="true"
                                  >
                                    {{ initials(user.name) }}
                                  </span>
                                  <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                      <h4 class="truncate text-sm font-bold text-[#1A1A1A]" :title="user.name">{{ user.name }}</h4>
                                      <span v-if="user.id === meId" class="chip-you">Vous</span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs" :class="user.is_superadmin ? 'font-bold text-[#C8102E]' : 'text-[#6B6E70]'">{{ user.is_superadmin ? 'Super administrateur' : formatRole(user.role) }}</p>
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

                              <div class="card-foot">
                                <Link v-if="peutGerer(user)" :href="route('admin.users.edit', user.id)" class="gbtn gbtn-sm gbtn-ghost-blue">
                                  <font-awesome-icon :icon="faPen" class="text-[11px]" />
                                  <span>Modifier</span>
                                </Link>
                                <button v-if="user.id !== meId && peutGerer(user)" type="button" @click="demanderSuppression(user)" class="gbtn gbtn-sm gbtn-ghost-red">
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
                <div v-if="users && users.length > 0 && groupesVisibles.length === 0" class="empty">
                  <span class="empty-icon"><font-awesome-icon :icon="faMagnifyingGlass" class="text-lg" /></span>
                  <p class="mt-4 text-sm font-semibold text-[#1A1A1A]">Aucun collaborateur ne correspond à « {{ recherche }} »</p>
                  <p class="mt-1 text-xs text-[#6B6E70]">Vérifiez l'orthographe ou essayez avec une partie de l'adresse e-mail.</p>
                  <button type="button" @click="reinitialiserFiltres" class="gbtn gbtn-neutral mt-5">
                    <font-awesome-icon :icon="faXmark" class="text-xs" />
                    <span>Effacer les filtres</span>
                  </button>
                </div>

                <!-- État vide -->
                <div v-if="!users || users.length === 0" class="empty">
                  <span class="empty-icon"><font-awesome-icon :icon="faUserGroup" class="text-lg" /></span>
                  <p class="mt-4 text-sm font-semibold text-[#1A1A1A]">Aucun collaborateur pour le moment</p>
                  <p class="mt-1 text-xs text-[#6B6E70]">Ajoutez votre premier employé pour constituer l'équipe du garage.</p>
                  <Link :href="route('admin.users.create')" class="gbtn gbtn-red mt-5">
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

    <!-- ═══ CONFIRMATION DE SUPPRESSION ═══ -->
    <Transition name="kg-modal">
      <div
        v-if="userASupprimer"
        class="fixed inset-0 z-40 flex items-end justify-center p-3 sm:items-center sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="kg-suppr-titre"
      >
        <div class="kg-backdrop absolute inset-0" @click="fermerSuppression"></div>

        <div class="kg-sheet glass glass-strong glass-lg relative w-full max-w-md p-6 pt-4 sm:pt-6">
          <span class="mx-auto mb-4 block h-1 w-9 rounded-full bg-[#1A1A1A]/20 sm:hidden" aria-hidden="true"></span>

          <div class="flex items-start gap-4">
            <span class="tile-base tile-red flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
              <font-awesome-icon :icon="faTriangleExclamation" />
            </span>
            <div class="min-w-0">
              <h2 id="kg-suppr-titre" class="text-base font-black text-[#1A1A1A]">Supprimer ce collaborateur ?</h2>
              <p class="mt-1.5 text-sm leading-relaxed text-[#6B6E70]">
                Le compte de <span class="font-semibold text-[#1A1A1A]">{{ userASupprimer.name }}</span>
                sera définitivement supprimé. Cette action est irréversible.
              </p>
            </div>
          </div>

          <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button ref="boutonAnnuler" type="button" @click="fermerSuppression" :disabled="suppressionEnCours" class="gbtn gbtn-neutral">
              Annuler
            </button>
            <button type="button" @click="confirmerSuppression" :disabled="suppressionEnCours" class="gbtn gbtn-red">
              <font-awesome-icon :icon="suppressionEnCours ? faCircleNotch : faTrashCan" :class="{ 'animate-spin': suppressionEnCours }" class="text-xs" />
              <span>{{ suppressionEnCours ? 'Suppression…' : 'Supprimer' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Intégré dans une autre page (accueil super administrateur) */
.page.page-integree {
  min-height: 0;
  overflow: visible;
  background: transparent;
}
/* ════════════ Tokens ════════════ */
.page {
  --red: #c8102e;
  --red-dark: #a90d26;
  --ink: #1a1a1a;
  --muted: #6b6e70;
  --ease: cubic-bezier(0.16, 1, 0.3, 1);

  position: relative;
  min-height: 100vh;
  overflow-x: clip;
  background: #f5f5f6;
}
.kg-ease {
  transition-timing-function: var(--ease);
}

/* ════════════ Lumière d'arrière-plan ════════════ */
.orbs {
  position: fixed;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}
.orb {
  position: absolute;
  border-radius: 9999px;
  filter: blur(70px);
  will-change: transform;
}
.orb-1 {
  top: -12%;
  left: -8%;
  height: 46vmax;
  width: 46vmax;
  background: radial-gradient(circle, rgba(200, 16, 46, 0.34), rgba(200, 16, 46, 0) 70%);
  animation: drift-a 22s ease-in-out infinite alternate;
}
.orb-2 {
  top: 30%;
  right: -14%;
  height: 40vmax;
  width: 40vmax;
  background: radial-gradient(circle, rgba(26, 26, 26, 0.2), rgba(26, 26, 26, 0) 70%);
  animation: drift-b 28s ease-in-out infinite alternate;
}
.orb-3 {
  bottom: -18%;
  left: 22%;
  height: 38vmax;
  width: 38vmax;
  background: radial-gradient(circle, rgba(200, 16, 46, 0.2), rgba(255, 255, 255, 0) 70%);
  animation: drift-a 32s ease-in-out infinite alternate-reverse;
}
@keyframes drift-a {
  to { transform: translate3d(6vmax, 5vmax, 0) scale(1.1); }
}
@keyframes drift-b {
  to { transform: translate3d(-7vmax, -4vmax, 0) scale(1.08); }
}

/* ════════════ Liquid Glass ════════════ */
.glass {
  --mx: 50%;
  --my: 0%;
  position: relative;
  overflow: hidden;
  isolation: isolate;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.66), rgba(255, 255, 255, 0.38));
  -webkit-backdrop-filter: blur(26px) saturate(190%);
  backdrop-filter: blur(26px) saturate(190%);
  border: 1px solid rgba(255, 255, 255, 0.75);
  box-shadow:
    inset 0 1.5px 0 rgba(255, 255, 255, 0.95),
    inset 0 -1px 0 rgba(255, 255, 255, 0.35),
    inset 1px 0 0 rgba(255, 255, 255, 0.5),
    0 1px 2px rgba(26, 26, 26, 0.05),
    0 18px 40px -18px rgba(26, 26, 26, 0.28);
}
.glass-lg { border-radius: 28px; }
.glass-strong {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.7));
}
.glass::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  border-radius: inherit;
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 38%);
  pointer-events: none;
}
.glass::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  border-radius: inherit;
  background: radial-gradient(260px circle at var(--mx) var(--my), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0) 65%);
  opacity: 0;
  transition: opacity 0.35s ease;
  pointer-events: none;
}
.glass:hover::after { opacity: 1; }

/* Verre sombre (chiffre d'affaires) */
.glass-dark {
  background: linear-gradient(140deg, rgba(34, 34, 36, 0.9), rgba(26, 26, 26, 0.72));
  border-color: rgba(255, 255, 255, 0.16);
  box-shadow:
    inset 0 1.5px 0 rgba(255, 255, 255, 0.22),
    inset 0 -1px 0 rgba(255, 255, 255, 0.05),
    0 22px 44px -18px rgba(26, 26, 26, 0.55);
}
.glass-dark::before {
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 42%);
}
.glass-dark::after {
  background: radial-gradient(300px circle at var(--mx) var(--my), rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0) 65%);
}

/* Carte cliquable qui se soulève */
.lift {
  transition: transform 0.4s var(--ease), box-shadow 0.4s ease;
}
.lift:hover {
  transform: translateY(-3px);
  box-shadow:
    inset 0 1.5px 0 rgba(255, 255, 255, 0.95),
    inset 0 -1px 0 rgba(255, 255, 255, 0.35),
    0 1px 2px rgba(26, 26, 26, 0.05),
    0 26px 48px -18px rgba(200, 16, 46, 0.3);
}
.lift:focus-visible,
.nav-row:focus-visible {
  outline: 2px solid var(--red);
  outline-offset: 3px;
}

@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
  .glass { background: rgba(255, 255, 255, 0.94); }
  .glass-dark { background: rgba(26, 26, 26, 0.95); }
}
@media (prefers-reduced-transparency: reduce) {
  .glass {
    background: #fff;
    -webkit-backdrop-filter: none;
    backdrop-filter: none;
  }
  .glass-dark { background: #1a1a1a; }
}

/* ════════════ En-têtes et séparateurs ════════════ */
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid rgba(26, 26, 26, 0.06);
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.7);
}
@media (min-width: 640px) {
  .card-head { padding: 1.15rem 1.5rem; }
}
.panel-divider {
  border-top: 1px solid rgba(26, 26, 26, 0.06);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
  background: rgba(255, 255, 255, 0.22);
}

/* ════════════ Tuiles d'icônes ════════════ */
.tile-base {
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7), 0 0 0 1px rgba(26, 26, 26, 0.06);
}
.tile-red {
  color: var(--red);
  background: linear-gradient(160deg, rgba(200, 16, 46, 0.16), rgba(200, 16, 46, 0.07));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 0 0 1px rgba(200, 16, 46, 0.14);
}
.tile-ink {
  color: var(--ink);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.5));
  box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.07), 0 4px 10px -6px rgba(26, 26, 26, 0.25);
}
.tile-red-solid {
  background: linear-gradient(160deg, #e0213f, var(--red) 55%, var(--red-dark));
  box-shadow: inset 0 1.5px 0 rgba(255, 255, 255, 0.45), inset 0 -3px 8px rgba(0, 0, 0, 0.18), 0 10px 20px -10px rgba(200, 16, 46, 0.7);
}
.tile-ink-solid {
  background: linear-gradient(150deg, #3a3a3c, #1a1a1a);
  box-shadow: inset 0 1.5px 0 rgba(255, 255, 255, 0.35), inset 0 -4px 10px rgba(0, 0, 0, 0.25), 0 8px 18px -10px rgba(26, 26, 26, 0.6);
}
/* Tuile qui devient rouge quand la carte parente est survolée */
.tile-hover,
.tile-link {
  transition: background 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
}
.tile-link {
  color: var(--red);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.5));
  box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.07), 0 4px 10px -6px rgba(26, 26, 26, 0.25);
}
.group:hover .tile-hover,
.group:hover .tile-link {
  color: #fff;
  background: linear-gradient(160deg, #e0213f, var(--red) 55%, var(--red-dark));
  box-shadow: inset 0 1.5px 0 rgba(255, 255, 255, 0.45), 0 8px 16px -8px rgba(200, 16, 46, 0.7);
}

/* ════════════ Lignes de navigation ════════════ */
.nav-row {
  display: flex;
  min-height: 68px;
  align-items: center;
  gap: 1rem;
  padding: 0.7rem 0.9rem;
  border-radius: 20px;
  transition: background-color 0.25s ease;
}
.nav-row:hover {
  background: rgba(255, 255, 255, 0.6);
}
li + li > .nav-row {
  position: relative;
}

/* ════════════ Sélecteur du mois (verre sombre) ════════════ */
.select-dark {
  min-height: 44px;
  max-width: 11rem;
  cursor: pointer;
  appearance: none;
  border-radius: 9999px;
  padding: 0.5rem 2.25rem 0.5rem 1rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: #fff;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.18);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
  transition: background 0.2s ease, box-shadow 0.2s ease;
}
.select-dark:hover { background: rgba(255, 255, 255, 0.18); }
.select-dark:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(200, 16, 46, 0.7), 0 0 0 6px rgba(200, 16, 46, 0.2);
}

/* ════════════ Boutons en capsule ════════════ */
.gbtn {
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0 1.15rem;
  border-radius: 9999px;
  font-size: 0.85rem;
  font-weight: 800;
  white-space: nowrap;
  transition: transform 0.25s var(--ease), box-shadow 0.25s ease, background 0.25s ease, filter 0.25s ease;
}
.gbtn-sm {
  min-height: 40px;
  padding: 0 0.95rem;
  font-size: 0.78rem;
}
.gbtn:hover:not(:disabled) {
  transform: translateY(-1px);
  filter: brightness(1.04);
}
.gbtn:active:not(:disabled) { transform: scale(0.96); }
.gbtn:disabled { opacity: 0.6; cursor: not-allowed; }
.gbtn:focus-visible,
.icon-btn:focus-visible,
.pill:focus-visible,
.focus-ring:focus-visible {
  outline: 2px solid var(--red);
  outline-offset: 2px;
}
.gbtn-neutral {
  color: var(--ink);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.55));
  box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.08), 0 6px 14px -8px rgba(26, 26, 26, 0.3);
}
.gbtn-red {
  color: #fff;
  background: linear-gradient(180deg, #e0213f, var(--red) 55%, var(--red-dark));
  box-shadow: inset 0 1.5px 0 rgba(255, 255, 255, 0.45), inset 0 -2px 6px rgba(0, 0, 0, 0.18), 0 8px 18px -8px rgba(200, 16, 46, 0.7);
}
.gbtn-ghost-blue {
  color: #1d4ed8;
}
.gbtn-ghost-blue:hover { background: rgba(37, 99, 235, 0.1); }
.gbtn-ghost-red {
  color: var(--red);
}
.gbtn-ghost-red:hover { background: rgba(200, 16, 46, 0.1); }
.gbtn-ghost-blue:hover,
.gbtn-ghost-red:hover { filter: none; }

.icon-btn {
  display: inline-flex;
  height: 36px;
  width: 36px;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  color: var(--muted);
  transition: background-color 0.2s ease, color 0.2s ease, transform 0.15s ease;
}
.icon-btn:hover {
  color: var(--ink);
  background: rgba(255, 255, 255, 0.85);
}
.icon-btn:active { transform: scale(0.88); }
.icon-pill {
  display: inline-flex;
  height: 36px;
  width: 36px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  color: var(--muted);
  background: rgba(255, 255, 255, 0.7);
  box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.06);
}

/* ════════════ Recherche + filtres ════════════ */
.field-input {
  min-height: 46px;
  width: 100%;
  padding: 0 2.75rem 0 2.6rem;
  border-radius: 9999px;
  font-size: 0.875rem;
  color: var(--ink);
  background: rgba(255, 255, 255, 0.6);
  border: 1px solid transparent;
  box-shadow: inset 0 1px 3px rgba(26, 26, 26, 0.07), inset 0 0 0 1px rgba(26, 26, 26, 0.06), 0 1px 0 rgba(255, 255, 255, 0.8);
  outline: none;
  transition: box-shadow 0.25s ease, background 0.25s ease;
}
.field-input::placeholder { color: #8e9194; }
.field-input:focus {
  background: rgba(255, 255, 255, 0.92);
  box-shadow: inset 0 1px 2px rgba(26, 26, 26, 0.04), 0 0 0 2px rgba(200, 16, 46, 0.55), 0 0 0 6px rgba(200, 16, 46, 0.12);
}

.pill {
  display: inline-flex;
  min-height: 44px;
  flex-shrink: 0;
  align-items: center;
  gap: 0.5rem;
  padding: 0 1rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0.5));
  box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.07);
  transition: transform 0.2s var(--ease), background 0.25s ease, color 0.25s ease, box-shadow 0.25s ease;
}
@media (min-width: 640px) {
  .pill { min-height: 36px; }
}
.pill:hover:not(.pill-active) { background: rgba(255, 255, 255, 0.95); }
.pill:active { transform: scale(0.96); }
.pill-active {
  color: #fff;
  background: linear-gradient(180deg, #3a3a3c, #1a1a1a);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 6px 14px -6px rgba(26, 26, 26, 0.5);
}

/* ════════════ Surfaces imbriquées (sans flou, plus légères) ════════════ */
.surface {
  overflow: hidden;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.55);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 0 0 1px rgba(26, 26, 26, 0.06), 0 8px 20px -14px rgba(26, 26, 26, 0.25);
}
.surface-card {
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.78);
}
.lift-soft {
  transition: transform 0.35s var(--ease), box-shadow 0.35s ease;
}
.lift-soft:hover {
  transform: translateY(-2px);
  box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.08), 0 16px 28px -14px rgba(26, 26, 26, 0.3);
}
.role-head {
  display: flex;
  min-height: 58px;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  text-align: left;
  transition: background-color 0.25s ease;
}
.role-head:hover { background: rgba(255, 255, 255, 0.6); }
.count {
  min-width: 1.75rem;
  border-radius: 9999px;
  padding: 0.18rem 0.6rem;
  text-align: center;
  font-size: 0.72rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  color: var(--muted);
  background: rgba(255, 255, 255, 0.8);
  box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.06);
}
.chip-you {
  flex-shrink: 0;
  border-radius: 9999px;
  padding: 0.1rem 0.55rem;
  font-size: 11px;
  font-weight: 700;
  line-height: 1rem;
  color: var(--red);
  background: rgba(200, 16, 46, 0.1);
  border: 1px solid rgba(200, 16, 46, 0.2);
}
.card-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.4rem;
  margin-top: 1rem;
  padding-top: 0.7rem;
  border-top: 1px solid rgba(26, 26, 26, 0.07);
}

/* ════════════ États vides ════════════ */
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2.5rem 1.5rem;
  text-align: center;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.45);
  border: 1px dashed rgba(26, 26, 26, 0.2);
}
.empty-icon {
  display: inline-flex;
  height: 48px;
  width: 48px;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  color: #8e9194;
  background: rgba(255, 255, 255, 0.8);
  box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.06);
}

/* ════════════ Écran de chargement ════════════ */
.loader {
  background: rgba(245, 245, 246, 0.55);
  -webkit-backdrop-filter: blur(30px) saturate(160%);
  backdrop-filter: blur(30px) saturate(160%);
}
.progress {
  height: 5px;
  width: 100%;
  overflow: hidden;
  border-radius: 9999px;
  background: rgba(26, 26, 26, 0.08);
  box-shadow: inset 0 1px 2px rgba(26, 26, 26, 0.1);
}
.kg-fade-leave-active { transition: opacity 0.4s ease-out; }
.kg-fade-leave-to { opacity: 0; }

@keyframes kgLoading {
  from { width: 0%; }
  to { width: 100%; }
}
.kg-loader { animation: kgLoading 1.5s var(--ease) forwards; }

@keyframes kgGear {
  from { transform: rotate(0deg); }
  to { transform: rotate(180deg); }
}
.kg-gear { animation: kgGear 1.5s var(--ease) forwards; }

/* ════════════ Entrée échelonnée après le chargement ════════════ */
@keyframes kgRise {
  from { opacity: 0; transform: translateY(14px) scale(0.985); }
  to { opacity: 1; transform: none; }
}
.kg-rise {
  animation: kgRise 0.7s var(--ease) both;
  animation-delay: var(--d, 0ms);
}

/* ════════════ Accordéons : hauteur animée ════════════ */
.kg-collapse {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.35s var(--ease);
}
.kg-collapse.kg-open { grid-template-rows: 1fr; }
.kg-collapse-inner {
  min-height: 0;
  overflow: hidden;
}

/* ════════════ Feuille de confirmation ════════════ */
.kg-backdrop {
  background: rgba(26, 26, 26, 0.35);
  -webkit-backdrop-filter: blur(6px);
  backdrop-filter: blur(6px);
}
.kg-modal-enter-active,
.kg-modal-leave-active { transition: opacity 0.25s ease-out; }
.kg-modal-enter-active .kg-sheet,
.kg-modal-leave-active .kg-sheet {
  transition: transform 0.45s var(--ease), opacity 0.25s ease-out;
}
.kg-modal-enter-from,
.kg-modal-leave-to { opacity: 0; }
.kg-modal-enter-from .kg-sheet,
.kg-modal-leave-to .kg-sheet {
  transform: translateY(40px) scale(0.97);
  opacity: 0;
}

/* ════════════ Accessibilité : mouvement réduit ════════════ */
@media (prefers-reduced-motion: reduce) {
  .kg-rise,
  .kg-loader,
  .kg-gear,
  .orb { animation: none; }
  .kg-loader { width: 100%; }
  .kg-collapse,
  .kg-fade-leave-active,
  .kg-modal-enter-active,
  .kg-modal-leave-active,
  .kg-modal-enter-active .kg-sheet,
  .kg-modal-leave-active .kg-sheet,
  .lift,
  .lift-soft { transition: none; }
}
</style>