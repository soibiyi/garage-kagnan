<script setup>
import { computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3'; // 1. Ajoute Head ici
import FlashToast from '@/Components/FlashToast.vue';
import NotificationsMessages from '@/Components/NotificationsMessages.vue';
import ChargeClientInstructions from '@/Components/ChargeClientInstructions.vue';
import AdminMissionNotifications from '@/Components/AdminMissionNotifications.vue';
import SecretariatNotifications from '@/Components/SecretariatNotifications.vue';
import SuperAdminAlertes from '@/Components/SuperAdminAlertes.vue';


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
        { label: "Chiffre d'affaires", route: 'admin.chiffre-affaires', icon: 'fa-solid fa-chart-line', match: ['admin.chiffre-affaires'] },
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
        { label: 'Clients', route: 'charge_client.clients.index', icon: 'fa-solid fa-address-book', match: ['charge_client.*'] },
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
    { label: 'Petites fournitures', route: 'superadmin.petites-fournitures', icon: 'fa-solid fa-screwdriver-wrench', match: ['superadmin.petites-fournitures'] },
    { label: 'Espace admin', route: 'superadmin.accueil', hash: '#espace-admin', icon: 'fa-solid fa-user-shield', match: ['admin.*'], separe: true },
];

const menu = computed(() => (estSuperAdmin.value ? MENU_SUPERADMIN : MENUS[role.value] ?? []));

// « Voir en tant que » : le super administrateur navigue avec le compte d'un collaborateur
const impersonation = computed(() => page.props.auth.impersonation);
const revenirAMonCompte = () => router.post(route('superadmin.impersonate.stop'));

const logout = () => {
    sessionStorage.removeItem('dashboard_loaded');
    sessionStorage.removeItem('admin_loaded');
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
                            <NotificationsMessages v-if="role === 'charge_client'" />
                            <ChargeClientInstructions v-if="role === 'charge_client'" />
                            <AdminMissionNotifications v-if="role === 'admin'" />
                            <SecretariatNotifications v-if="role === 'admin' || role === 'secretaire'" />
                            <SuperAdminAlertes v-if="estSuperAdmin" />

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
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
            <FlashToast />

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