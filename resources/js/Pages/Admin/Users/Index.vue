<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
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
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    users: Array,
    stats: Object,
    siegeFiltre: { type: String, default: null },
});

// ─── Écran de chargement (une seule fois par session) ───────────────
const isLoading = ref(false);
const ready = ref(false); // déclenche l'animation d'entrée du contenu

onMounted(() => {
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

// ─── Chiffre d'affaires par mois ────────────────────────────────────
const moisSelectionne = ref(props.stats?.chiffre_affaires_mensuel?.[0]?.cle ?? '');

const chiffreAffairesAffiche = computed(() => {
    const mois = props.stats?.chiffre_affaires_mensuel?.find(m => m.cle === moisSelectionne.value);
    const total = mois?.total ?? 0;
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(total);
});

// ─── Accès rapides : devis (mêmes routes que l'espace administratif) ─
const accesDevis = [
    { label: 'Devis en attente de validation', description: 'Saisir les choix du client et enregistrer.', icone: faReceipt, href: route('administration.facturation.index') },
    { label: 'Devis validés', description: "Dossiers dont l'accord client a été enregistré.", icone: faClipboardCheck, href: route('administration.devis.acceptes') },
    { label: 'Devis direct', description: 'Création et gestion des devis directs (comptoir).', icone: faFileInvoiceDollar, href: route('administration.devis.directs.index') },
    { label: 'Historique des devis', description: "Vue d'ensemble de tous les services (acceptés / refusés).", icone: faClockRotateLeft, href: route('administration.devis.historique') },
];

// ─── Rôles ──────────────────────────────────────────────────────────
const ROLES_CONFIG = {
    admin: { label: 'Administrateurs', icone: faUserShield, badgeColor: 'bg-gray-900 text-white', avatar: 'bg-[#1A1A1A] text-white' },
    receptionniste: { label: 'Réceptionnistes', icone: faClipboardUser, badgeColor: 'bg-blue-50 text-blue-700 border border-blue-200', avatar: 'bg-blue-50 text-blue-700' },
    mecanicien: { label: 'Mécaniciens', icone: faScrewdriverWrench, badgeColor: 'bg-amber-50 text-amber-800 border border-amber-200', avatar: 'bg-amber-50 text-amber-800' },
    administratif: { label: 'Administratifs', icone: faUserGear, badgeColor: 'bg-purple-50 text-purple-700 border border-purple-200', avatar: 'bg-purple-50 text-purple-700' },
    charge_client: { label: 'Chargés de Suivi Client', icone: faHeadset, badgeColor: 'bg-rose-50 text-rose-700 border border-rose-200', avatar: 'bg-rose-50 text-rose-700' },
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
});
const toggleRole = (roleKey) => {
    activeRoles.value[roleKey] = !activeRoles.value[roleKey];
};

// Regroupement des employés par rôle
const groupedUsers = computed(() => {
    if (!props.users) return {};

    const groups = {};
    Object.keys(ROLES_CONFIG).forEach(role => {
        groups[role] = [];
    });

    props.users.forEach(user => {
        if (groups[user.role]) {
            groups[user.role].push(user);
        } else {
            if (!groups['autre']) groups['autre'] = [];
            groups['autre'].push(user);
        }
    });

    return groups;
});

const formatRole = (role) => ROLES_CONFIG[role]?.label || role;

const initials = (name = '') =>
    name.split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase() || '?';

const formatDate = (date) =>
    new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });

const deleteUser = (id) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce collaborateur ?')) {
        router.delete(route('admin.users.destroy', id));
    }
};

const logout = () => {
    sessionStorage.removeItem('admin_loaded');
    router.post(route('logout'));
};
</script>

<template>
  <!-- ÉCRAN DE CHARGEMENT -->
  <Transition name="kg-fade">
    <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-white px-6">
      <div class="w-full max-w-xs text-center">
        <span class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#C8102E] text-white shadow-lg shadow-[#C8102E]/25">
          <font-awesome-icon :icon="faGear" class="text-2xl" />
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
              <span v-if="siegeFiltre" class="font-semibold text-[#C8102E]">— Siège {{ siegeFiltre }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <SiegeSwitcher :current="siegeFiltre" />
          <button
            @click="logout"
            class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-[#1A1A1A] shadow-sm transition duration-200 hover:border-gray-300 hover:bg-gray-50 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40"
          >
            <font-awesome-icon :icon="faRightFromBracket" class="text-sm" />
            <span>Déconnexion</span>
          </button>
        </div>
      </header>

      <!-- ═══ STATISTIQUES ═══ -->
      <section
        class="grid grid-cols-1 gap-4 sm:gap-5 md:grid-cols-2 lg:grid-cols-12"
        :class="ready ? 'kg-rise' : 'opacity-0'"
        style="--d: 90ms"
        aria-label="Statistiques"
      >
        <!-- Chiffre d'affaires : le seul bloc "fort" de l'écran -->
        <div class="relative flex flex-col justify-between overflow-hidden rounded-2xl bg-[#1A1A1A] p-5 text-white shadow-lg shadow-black/10 sm:p-6 md:col-span-2 lg:col-span-6">
          <span class="absolute left-0 top-0 h-1 w-16 bg-[#C8102E]"></span>

          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white">
                <font-awesome-icon :icon="faSackDollar" />
              </span>
              <p class="text-sm font-semibold text-white/80">Chiffre d'affaires</p>
            </div>

            <select
              v-model="moisSelectionne"
              aria-label="Mois du chiffre d'affaires"
              class="min-h-[44px] max-w-[11rem] cursor-pointer rounded-xl border border-white/15 bg-white/10 py-2 pl-3 pr-8 text-xs font-semibold text-white transition duration-200 hover:bg-white/15 focus:border-[#C8102E] focus:ring-2 focus:ring-[#C8102E]/50"
            >
              <option v-for="m in stats?.chiffre_affaires_mensuel" :key="m.cle" :value="m.cle" class="bg-white text-[#1A1A1A]">
                {{ m.label }}
              </option>
            </select>
          </div>

          <div class="mt-8 flex items-end justify-between gap-4">
            <p class="min-w-0 truncate text-4xl font-black tabular-nums tracking-tight sm:text-5xl">
              {{ chiffreAffairesAffiche }}
              <span class="ml-1 text-base font-bold text-white/60 sm:text-lg">FCFA</span>
            </p>
            <Link
              :href="route('admin.chiffre-affaires')"
              class="group inline-flex min-h-[44px] shrink-0 items-center gap-2 rounded-xl bg-[#C8102E] px-4 text-sm font-semibold text-white transition duration-200 hover:bg-[#a90d26] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
            >
              <span>Détail</span>
              <font-awesome-icon :icon="faArrowRight" class="text-xs transition-transform duration-300 group-hover:translate-x-1" />
            </Link>
          </div>
        </div>

        <!-- Voitures enregistrées (cliquable) -->
        <Link
          :href="route('admin.vehicules.status')"
          class="group flex flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-[#C8102E] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:p-6 lg:col-span-3"
        >
          <div class="flex items-center justify-between">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#C8102E]/10 text-[#C8102E] transition duration-300 group-hover:bg-[#C8102E] group-hover:text-white">
              <font-awesome-icon :icon="faCar" />
            </span>
            <font-awesome-icon :icon="faArrowRight" class="text-xs text-gray-300 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
          </div>
          <div class="mt-6">
            <p class="text-4xl font-black tabular-nums tracking-tight text-[#1A1A1A]">{{ stats?.nombre_voitures || '0' }}</p>
            <p class="mt-1 text-sm font-medium text-[#6B6E70]">Voitures enregistrées</p>
          </div>
        </Link>

        <!-- Clients totaux (information, non cliquable : plus à plat) -->
        <div class="flex flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 lg:col-span-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-[#1A1A1A]">
            <font-awesome-icon :icon="faUsers" />
          </span>
          <div class="mt-6">
            <p class="text-4xl font-black tabular-nums tracking-tight text-[#1A1A1A]">{{ stats?.nombre_clients || '0' }}</p>
            <p class="mt-1 text-sm font-medium text-[#6B6E70]">Clients totaux</p>
          </div>
        </div>
      </section>

      <!-- ═══ NAVIGATION RAPIDE ═══ -->
      <nav
        class="grid grid-cols-1 gap-4 sm:gap-5 md:grid-cols-2"
        :class="ready ? 'kg-rise' : 'opacity-0'"
        style="--d: 180ms"
        aria-label="Accès rapide"
      >
        <Link
          :href="route('admin.pieces.index')"
          class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-[#C8102E] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:p-5"
        >
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-[#C8102E] transition duration-300 group-hover:bg-[#C8102E] group-hover:text-white">
            <font-awesome-icon :icon="faWrench" class="text-lg" />
          </span>
          <div class="min-w-0 flex-1">
            <h3 class="text-base font-bold text-[#1A1A1A]">Gestion de Pièces</h3>
            <p class="mt-0.5 text-sm leading-snug text-[#6B6E70]">Entrées, quantités, prix d'achat, marges, sorties et stock en temps réel par siège.</p>
          </div>
          <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-300 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
        </Link>

        <Link
          :href="route('admin.users.interactionindex')"
          class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-[#C8102E] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:p-5"
        >
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-[#C8102E] transition duration-300 group-hover:bg-[#C8102E] group-hover:text-white">
            <font-awesome-icon :icon="faFileLines" class="text-lg" />
          </span>
          <div class="min-w-0 flex-1">
            <h3 class="text-base font-bold text-[#1A1A1A]">Suivi des Interactions</h3>
            <p class="mt-0.5 text-sm leading-snug text-[#6B6E70]">Consulter les interactions entre les chargés client et les clients.</p>
          </div>
          <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-300 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
        </Link>

        <!-- Devis : mêmes pages que l'espace administratif -->
        <Link
          v-for="acces in accesDevis"
          :key="acces.label"
          :href="acces.href"
          class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-[#C8102E] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 sm:p-5"
        >
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-[#C8102E] transition duration-300 group-hover:bg-[#C8102E] group-hover:text-white">
            <font-awesome-icon :icon="acces.icone" class="text-lg" />
          </span>
          <div class="min-w-0 flex-1">
            <h3 class="text-base font-bold text-[#1A1A1A]">{{ acces.label }}</h3>
            <p class="mt-0.5 text-sm leading-snug text-[#6B6E70]">{{ acces.description }}</p>
          </div>
          <font-awesome-icon :icon="faArrowRight" class="shrink-0 text-xs text-gray-300 transition duration-300 group-hover:translate-x-1 group-hover:text-[#C8102E]" />
        </Link>
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
              <span class="block text-lg font-extrabold tracking-tight text-[#1A1A1A] sm:text-xl">Liste des Employés</span>
              <span class="block text-xs text-[#6B6E70]">
                {{ users?.length || 0 }} {{ (users?.length || 0) > 1 ? 'collaborateurs' : 'collaborateur' }} · par rôle
              </span>
            </span>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
              <font-awesome-icon
                :icon="faChevronDown"
                class="text-sm transition-transform duration-300 kg-ease"
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
            <div class="space-y-3 border-t border-gray-100 bg-gray-50/60 p-4 sm:p-6">

              <template v-for="(usersList, roleKey) in groupedUsers" :key="roleKey">
                <div v-if="usersList.length > 0" class="overflow-hidden rounded-xl border border-gray-200 bg-white">

                  <!-- Sous-menu rôle -->
                  <button
                    type="button"
                    @click="toggleRole(roleKey)"
                    :aria-expanded="!!activeRoles[roleKey]"
                    class="flex min-h-[56px] w-full items-center justify-between gap-3 p-3.5 text-left transition duration-200 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#C8102E]/40 sm:p-4"
                  >
                    <div class="flex min-w-0 items-center gap-3">
                      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-sm text-gray-700">
                        <font-awesome-icon :icon="ROLES_CONFIG[roleKey]?.icone || faUserGroup" />
                      </span>
                      <h3 class="truncate text-sm font-bold text-[#1A1A1A]">
                        {{ ROLES_CONFIG[roleKey]?.label || 'Autres Rôles' }}
                      </h3>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                      <span class="min-w-[1.75rem] rounded-full bg-gray-100 px-2 py-0.5 text-center text-xs font-bold tabular-nums text-gray-700">
                        {{ usersList.length }}
                      </span>
                      <font-awesome-icon
                        :icon="faChevronDown"
                        class="text-xs text-gray-400 transition-transform duration-300 kg-ease"
                        :class="{ 'rotate-180': activeRoles[roleKey] }"
                      />
                    </div>
                  </button>

                  <!-- Cartes employés (hauteur animée) -->
                  <div class="kg-collapse" :class="{ 'kg-open': activeRoles[roleKey] }" :inert="!activeRoles[roleKey]">
                    <div class="kg-collapse-inner">
                      <div class="border-t border-gray-100 bg-gray-50/50 p-3.5 sm:p-5">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
                          <article
                            v-for="user in usersList"
                            :key="user.id"
                            class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-md"
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
                                  <h4 class="truncate text-sm font-bold text-[#1A1A1A]" :title="user.name">{{ user.name }}</h4>
                                  <span
                                    class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold leading-4"
                                    :class="ROLES_CONFIG[user.role]?.badgeColor || 'bg-gray-100 text-gray-700'"
                                  >
                                    {{ formatRole(user.role) }}
                                  </span>
                                </div>
                              </div>

                              <ul class="mt-4 space-y-2 text-xs text-[#6B6E70]">
                                <li class="flex items-center gap-2.5 min-w-0">
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
                                v-if="user.id !== $page.props.auth.user.id"
                                type="button"
                                @click="deleteUser(user.id)"
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
      </section>

    </div>
  </div>
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

/* Accessibilité : mouvement réduit */
@media (prefers-reduced-motion: reduce) {
  .kg-rise,
  .kg-loader {
    animation: none;
  }
  .kg-loader {
    width: 100%;
  }
  .kg-collapse,
  .kg-fade-leave-active {
    transition: none;
  }
}
</style>