<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3'; // 1. Ajoute Head ici
import FlashToast from '@/Components/FlashToast.vue';
import NotificationsMessages from '@/Components/NotificationsMessages.vue';
import VehicleExpiryNotifications from '@/Components/VehicleExpiryNotifications.vue';
import ChargeClientInstructions from '@/Components/ChargeClientInstructions.vue';
import AdminMissionNotifications from '@/Components/AdminMissionNotifications.vue';
import SecretariatNotifications from '@/Components/SecretariatNotifications.vue';
import SuperAdminAlertes from '@/Components/SuperAdminAlertes.vue';
import AnnoncesBandeau from '@/Components/AnnoncesBandeau.vue';


const page = usePage();
const role = computed(() => page.props.auth.user?.role);

const ACCUEIL = { label: 'Accueil', route: 'dashboard', icon: 'fa-solid fa-house', match: ['dashboard'] };

const MENUS = {
    admin: [
        { label: 'Utilisateurs', route: 'admin.users.index', icon: 'fa-solid fa-users', match: ['admin.users.index', 'admin.users.create', 'admin.users.edit', 'admin.charge_clients.activities'] },
        { label: 'Missions terminées', route: 'admin.missions.history', icon: 'fa-solid fa-truck-fast', match: ['admin.missions.*'] },
        { label: 'Historique chauffeurs', route: 'admin.chauffeurs.history', icon: 'fa-solid fa-id-card-clip', match: ['admin.chauffeurs.*'] },
        { label: 'Secrétariat', route: 'admin.secretariat.index', icon: 'fa-solid fa-list-check', match: ['admin.secretariat.*'] },
        { label: 'Interactions', route: 'admin.users.interactionindex', icon: 'fa-solid fa-comments', match: ['admin.users.interactionindex'] },
        { label: 'Véhicules', route: 'admin.vehicules.status', icon: 'fa-solid fa-car', match: ['admin.vehicules.*'] },
        { label: 'Échéances véhicules', route: 'charge_client.vehicules.echeances.index', icon: 'fa-solid fa-calendar-days', match: ['charge_client.vehicules.echeances.*'] },
        { label: "Chiffre d'affaires", route: 'admin.chiffre-affaires', icon: 'fa-solid fa-chart-line', match: ['admin.chiffre-affaires'] },
        { label: 'Suppressions', route: 'admin.demandes-suppression.index', icon: 'fa-solid fa-trash-can', match: ['admin.demandes-suppression.*'], pastille: 'demandesSuppression' },
    ],
    receptionniste: [
        ACCUEIL,
        { label: 'Réception', route: 'reception.create', icon: 'fa-solid fa-clipboard-list', match: ['reception.*'] },
        { label: 'Parc véhicules', route: 'parc.index', icon: 'fa-solid fa-car-side', match: ['parc.*'] },
    ],
    mecanicien: [
        ACCUEIL,
        { label: 'Atelier', route: 'mecanicien.index', icon: 'fa-solid fa-wrench', match: ['mecanicien.*'] },
    ],
    administratif: [
        ACCUEIL,
        { label: 'Dossiers', route: 'administration.dossiers.index', icon: 'fa-solid fa-folder-open', match: ['administration.dossiers.*'] },
        { label: 'Facturation', route: 'administration.facturation.index', icon: 'fa-solid fa-file-invoice-dollar', match: ['administration.facturation.*'] },
        { label: 'Devis acceptés', route: 'administration.devis.acceptes', icon: 'fa-solid fa-circle-check', match: ['administration.devis.acceptes'] },
        { label: 'Historique', route: 'administration.devis.historique', icon: 'fa-solid fa-clock-rotate-left', match: ['administration.devis.historique'] },
        { label: 'Devis directs', route: 'administration.devis.directs.index', icon: 'fa-solid fa-bolt', match: ['administration.devis.directs.*'] },
        { label: 'Factures', route: 'administration.factures.index', icon: 'fa-solid fa-receipt', match: ['administration.factures.*'] },
        { label: 'Stocks', route: 'administration.stocks.index', icon: 'fa-solid fa-boxes-stacked', match: ['administration.stocks.*'] },
    ],
    charge_client: [
        ACCUEIL,
        { label: 'Clients', route: 'charge_client.clients.index', icon: 'fa-solid fa-address-book', match: ['charge_client.clients.*', 'charge_client.vehicules.show', 'charge_client.vehicules.infos', 'charge_client.vehicules.relances', 'charge_client.vehicules.interventions'] },
        { label: 'Échéances véhicules', route: 'charge_client.vehicules.echeances.index', icon: 'fa-solid fa-calendar-days', match: ['charge_client.vehicules.echeances.*'] },
    ],
    secretaire: [
        { label: 'Mes tâches', route: 'secretaire.index', icon: 'fa-solid fa-list-check', match: ['secretaire.*'] },
    ],
    chauffeur: [
        { label: 'Missions', route: 'chauffeur.index', icon: 'fa-solid fa-truck-fast', match: ['chauffeur.*'] },
    ],
    depanneur: [
        { label: 'Missions', route: 'depanneur.index', icon: 'fa-solid fa-truck-fast', match: ['depanneur.*'] },
    ],
    depaneur: [
        { label: 'Missions', route: 'depanneur.index', icon: 'fa-solid fa-truck-fast', match: ['depanneur.*'] },
    ],
};

// Super administrateur : menu admin + supervision + accès direct à chaque espace métier
const estSuperAdmin = computed(() => !!page.props.auth.user?.is_superadmin);
// Menu court et clair : ses outils propres, puis l'accès à l'espace administrateur.
// Les espaces de chaque rôle sont sur son accueil (cartes « Agir en tant que »).
const MENU_SUPERADMIN = [
    { label: 'Accueil', route: 'superadmin.accueil', icon: 'fa-solid fa-crown', match: ['superadmin.accueil'] },
    { label: 'Supervision', route: 'superadmin.supervision.index', icon: 'fa-solid fa-satellite-dish', match: ['superadmin.supervision.*'] },
    { label: 'Erreurs', route: 'superadmin.erreurs.index', icon: 'fa-solid fa-bug', match: ['superadmin.erreurs.*'] },
    { label: 'Sièges', route: 'superadmin.sieges', icon: 'fa-solid fa-building', match: ['superadmin.sieges*'] },
    { label: 'Équipes', route: 'superadmin.equipes', icon: 'fa-solid fa-people-group', match: ['superadmin.equipes*'] },
    { label: 'Contrôle', route: 'superadmin.controle', icon: 'fa-solid fa-shield-halved', match: ['superadmin.controle*'] },
    { label: 'Sécurité', route: 'superadmin.securite', icon: 'fa-solid fa-user-lock', match: ['superadmin.securite*'] },
    { label: 'Rapports', route: 'superadmin.rapports.index', icon: 'fa-solid fa-file-lines', match: ['superadmin.rapports.*'] },
    { label: 'Système', route: 'superadmin.systeme', icon: 'fa-solid fa-gears', match: ['superadmin.systeme*'] },
    { label: 'Petites fournitures', route: 'superadmin.petites-fournitures', icon: 'fa-solid fa-screwdriver-wrench', match: ['superadmin.petites-fournitures'] },
    { label: 'Suppressions', route: 'admin.demandes-suppression.index', icon: 'fa-solid fa-trash-can', match: ['admin.demandes-suppression.*'], pastille: 'demandesSuppression' },
    { label: 'Espace admin', route: 'superadmin.espace-admin', icon: 'fa-solid fa-user-shield', match: ['admin.*', 'superadmin.espace-admin'], separe: true },
];

const menu = computed(() => (estSuperAdmin.value ? MENU_SUPERADMIN : MENUS[role.value] ?? []));

const paletteOuverte = ref(false);
const recherchePalette = ref('');
const indexPalette = ref(0);
const saisiePalette = ref(null);

const normaliserRecherche = (valeur) =>
    String(valeur).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

const destinationsPalette = computed(() => {
    const acces = [...(menu.value ?? [])];

    if (!acces.some((item) => item.route === 'dashboard') && !estSuperAdmin.value) {
        acces.unshift(ACCUEIL);
    }

    if (!acces.some((item) => item.route === 'profile.edit')) {
        acces.push({ label: 'Mon profil', route: 'profile.edit', icon: 'fa-solid fa-user' });
    }

    const requete = normaliserRecherche(recherchePalette.value.trim());
    const resultats = requete
        ? acces.filter((item) => normaliserRecherche(item.label).includes(requete))
        : acces;

    return resultats.slice(0, 10);
});

watch(recherchePalette, () => {
    indexPalette.value = 0;
});

const ouvrirPalette = () => {
    paletteOuverte.value = true;
    recherchePalette.value = '';
    indexPalette.value = 0;
    nextTick(() => saisiePalette.value?.focus());
};

const fermerPalette = () => {
    paletteOuverte.value = false;
    recherchePalette.value = '';
};

const visiterPalette = (item) => {
    if (!item) return;
    fermerPalette();
    router.visit(route(item.route));
};

const gererClavierPalette = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLower?.() === 'k') {
        event.preventDefault();
        if (paletteOuverte.value) fermerPalette();
        else ouvrirPalette();
        return;
    }

    if (!paletteOuverte.value) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        fermerPalette();
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (destinationsPalette.value.length) {
            indexPalette.value = Math.min(indexPalette.value + 1, destinationsPalette.value.length - 1);
        }
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        indexPalette.value = Math.max(indexPalette.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        visiterPalette(destinationsPalette.value[indexPalette.value]);
    }
};

onMounted(() => document.addEventListener('keydown', gererClavierPalette));
onBeforeUnmount(() => document.removeEventListener('keydown', gererClavierPalette));

// « Voir en tant que » : le super administrateur navigue avec le compte d'un collaborateur
const impersonation = computed(() => page.props.auth.impersonation);
const revenirAMonCompte = () => router.post(route('superadmin.impersonate.stop'));

const logout = () => {
    // Prochaine connexion : réafficher les consignes reçues pendant l'absence
    sessionStorage.removeItem('instructions_resume_connexion');
    router.post(route('logout'));
};
</script>

<template>
    <div class="kg-internal-theme">
        <!-- 2. Ajoute la balise Head ici pour définir le titre de l'application -->
        <Head title="Garage Kagnan" />

        <div class="min-h-screen bg-gray-100 print:min-h-0 print:bg-white">
            <!-- Bandeau « Voir en tant que » -->
            <div
                v-if="impersonation"
                class="sticky top-0 z-[60] flex flex-wrap items-center justify-center gap-x-4 gap-y-2 bg-gradient-to-r from-[#0B0F19] via-[#1f2430] to-[#0B0F19] px-4 py-2.5 text-xs text-white shadow-lg print:hidden"
                role="status"
            >
                <span class="inline-flex items-center gap-2">
                    <i class="fa-solid fa-user-secret text-[#F43F5E]" aria-hidden="true"></i>
                    <span>
                        Vous naviguez en tant que <strong>{{ $page.props.auth.user.name }}</strong>
                        ({{ role }}) · ouvert par {{ impersonation.par }}. Toutes les actions sont tracées.
                    </span>
                </span>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#E11D48] px-3 py-1.5 font-bold text-white transition hover:bg-[#BE123C]"
                    @click="revenirAMonCompte"
                >
                    <i class="fa-solid fa-arrow-rotate-left text-[10px]" aria-hidden="true"></i>Revenir à mon compte
                </button>
            </div>

            <!-- Mode maintenance actif : rappel permanent pour le super administrateur -->
            <div
                v-if="estSuperAdmin && $page.props.parametres?.maintenance"
                class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-400 px-4 py-2 text-xs font-bold text-amber-950 print:hidden"
                role="status"
            >
                <span><i class="fa-solid fa-person-digging mr-1.5" aria-hidden="true"></i>Mode maintenance actif : les collaborateurs ne peuvent pas utiliser l'application.</span>
                <Link :href="route('superadmin.systeme')" class="underline underline-offset-2">Désactiver</Link>
            </div>

            <nav class="border-b border-gray-100 bg-white print:hidden">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-20 items-center justify-between">

                        <!-- GAUCHE : logo de l'entreprise -->
                        <Link :href="route('dashboard')" class="flex items-center">
                            <img
                                src="/images/logo-kagnan.png"
                                alt="Garage Kagnan"
                                class="h-16 w-auto object-contain sm:h-[72px]"
                            />
                        </Link>

                        <!-- DROITE : notifications (chargé client) + nom de l'utilisateur (lien profil) + Se déconnecter -->
                        <div class="flex items-center gap-2 sm:gap-3">
                            <VehicleExpiryNotifications v-if="role === 'admin' || role === 'charge_client'" />
                            <NotificationsMessages v-if="role === 'charge_client'" />
                            <ChargeClientInstructions v-if="role === 'charge_client'" />
                            <AdminMissionNotifications v-if="role === 'admin'" />
                            <SecretariatNotifications v-if="role === 'admin' || role === 'secretaire'" />
                            <SuperAdminAlertes v-if="estSuperAdmin" />

                            <button
                                type="button"
                                @click="ouvrirPalette"
                                class="inline-flex h-9 items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 text-xs font-bold text-[#6B6E70] transition hover:border-gray-300 hover:bg-gray-50"
                                aria-label="Ouvrir la recherche rapide"
                            >
                                <i class="fa-solid fa-magnifying-glass text-[11px]" aria-hidden="true"></i>
                                <span class="hidden md:inline">Rechercher</span>
                                <kbd class="hidden rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] font-bold text-gray-500 lg:inline">Ctrl K</kbd>
                            </button>

                            <Link
                                :href="route('profile.edit')"
                                class="inline-flex max-w-[140px] items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-[#0B0F19] transition hover:border-[#0B0F19] hover:bg-gray-50 sm:max-w-[240px] sm:px-4"
                            >
                                <i class="fa-solid fa-user text-[11px] text-[#E11D48]"></i>
                                <span class="truncate">{{ $page.props.auth.user.name }}</span>
                            </Link>

                            <button
                                type="button"
                                @click="logout"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#E11D48] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#BE123C] sm:px-4 cursor-pointer"
                            >
                                <i class="fa-solid fa-right-from-bracket text-[11px]"></i>
                                <span class="hidden sm:inline">Se déconnecter</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- MENU PAR RÔLE -->
                <div v-if="menu.length" class="border-t border-gray-100">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div class="flex gap-1 overflow-x-auto whitespace-nowrap">
                            <Link
                                v-for="item in menu"
                                :key="item.label"
                                :href="route(item.route) + (item.hash || '')"
                                class="inline-flex items-center gap-2 border-b-2 px-3 py-3 text-xs font-bold transition"
                                :style="item.separe ? 'margin-left: auto' : ''"
                                :class="item.match.some((p) => route().current(p))
                                    ? 'border-[#E11D48] text-[#0B0F19]'
                                    : 'border-transparent text-[#8A8D8F] hover:border-[#8A8D8F]/40 hover:text-[#0B0F19]'"
                            >
                                <i :class="item.icon" class="text-[11px]"></i>
                                <span>{{ item.label }}</span>
                                <span
                                    v-if="item.pastille && $page.props[item.pastille]"
                                    class="grid h-5 min-w-5 place-items-center rounded-full bg-[#E11D48] px-1 text-[10px] font-bold text-white"
                                    :aria-label="`${$page.props[item.pastille]} en attente`"
                                >{{ $page.props[item.pastille] }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
            <AnnoncesBandeau />
            <FlashToast />

            <div
                v-if="paletteOuverte"
                class="fixed inset-0 z-[100] flex items-start justify-center bg-[#0B0F19]/40 px-4 pt-[12vh] backdrop-blur-sm print:hidden"
                role="dialog"
                aria-modal="true"
                aria-label="Recherche rapide"
                @mousedown.self="fermerPalette"
            >
                <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl">
                    <div class="flex items-center gap-3 border-b border-gray-100 px-4">
                        <i class="fa-solid fa-magnifying-glass text-sm text-[#E11D48]" aria-hidden="true"></i>
                        <input
                            ref="saisiePalette"
                            v-model="recherchePalette"
                            type="search"
                            class="h-14 min-w-0 flex-1 border-0 bg-transparent text-sm font-semibold text-[#0B0F19] outline-none ring-0 placeholder:text-gray-400 focus:ring-0"
                            placeholder="Rechercher une page..."
                            aria-label="Rechercher une page"
                        />
                        <button type="button" class="rounded-md border border-gray-200 px-2 py-1 text-[10px] font-bold text-gray-500" @click="fermerPalette">
                            Échap
                        </button>
                    </div>

                    <div class="max-h-[55vh] overflow-y-auto p-2" role="listbox">
                        <button
                            v-for="(item, index) in destinationsPalette"
                            :key="item.label"
                            type="button"
                            role="option"
                            :aria-selected="indexPalette === index"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition"
                            :class="indexPalette === index ? 'bg-[#0B0F19] text-white' : 'text-[#0B0F19] hover:bg-gray-50'"
                            @mouseenter="indexPalette = index"
                            @click="visiterPalette(item)"
                        >
                            <span
                                class="grid h-9 w-9 shrink-0 place-items-center rounded-lg"
                                :class="indexPalette === index ? 'bg-white/10 text-white' : 'bg-[#F3F4F6] text-[#E11D48]'"
                            >
                                <i :class="item.icon" class="text-sm" aria-hidden="true"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold">{{ item.label }}</span>
                                <span class="mt-0.5 block text-[11px]" :class="indexPalette === index ? 'text-white/60' : 'text-gray-400'">Ouvrir cette page</span>
                            </span>
                            <i v-if="indexPalette === index" class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                        </button>

                        <div v-if="!destinationsPalette.length" class="px-4 py-10 text-center">
                            <p class="text-sm font-bold text-[#0B0F19]">Aucune page trouvée</p>
                            <p class="mt-1 text-xs text-gray-500">Essayez un autre terme.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Heading -->
            <header class="bg-white shadow print:hidden" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>