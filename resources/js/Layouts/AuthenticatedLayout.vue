<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import FlashToast from '@/Components/FlashToast.vue';

const page = usePage();
const role = computed(() => page.props.auth.user?.role);

const ACCUEIL = { label: 'Accueil', route: 'dashboard', icon: 'fa-solid fa-house', match: ['dashboard'] };

const MENUS = {
    admin: [
        { label: 'Utilisateurs', route: 'admin.users.index', icon: 'fa-solid fa-users', match: ['admin.users.index', 'admin.users.create', 'admin.users.edit', 'admin.charge_clients.activities'] },
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
};

const menu = computed(() => MENUS[role.value] ?? []);
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100 print:min-h-0 print:bg-white">
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

                        <!-- DROITE : nom de l'utilisateur (lien profil) + Se déconnecter -->
                        <div class="flex items-center gap-2 sm:gap-3">
                            <Link
                                :href="route('profile.edit')"
                                class="inline-flex max-w-[140px] items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-[#0B0F19] transition hover:border-[#0B0F19] hover:bg-gray-50 sm:max-w-[240px] sm:px-4"
                            >
                                <i class="fa-solid fa-user text-[11px] text-[#E11D48]"></i>
                                <span class="truncate">{{ $page.props.auth.user.name }}</span>
                            </Link>

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#E11D48] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#BE123C] sm:px-4"
                            >
                                <i class="fa-solid fa-right-from-bracket text-[11px]"></i>
                                <span class="hidden sm:inline">Se déconnecter</span>
                            </Link>
                        </div>
                    </div>
                </div>
                <!-- MENU PAR RÔLE (défilement horizontal sur mobile) -->
<div v-if="menu.length" class="border-t border-gray-100">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex gap-1 overflow-x-auto whitespace-nowrap">
            <Link
                v-for="item in menu"
                :key="item.label"
                :href="route(item.route)"
                class="inline-flex items-center gap-2 border-b-2 px-3 py-3 text-xs font-bold transition"
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