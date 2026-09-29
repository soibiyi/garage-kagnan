<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    clients: Array,
    filters: Object,
});

/* ------------------------------------------------------------------ */
/* Recherche (serveur) : envoyée 300 ms après la dernière frappe       */
/* ------------------------------------------------------------------ */
const search = ref(props.filters?.search || '');
const loading = ref(false);

let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('charge_client.clients.index'),
            { search: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => (loading.value = true),
                onFinish: () => (loading.value = false),
            }
        );
    }, 300);
});

onBeforeUnmount(() => clearTimeout(timer));

/* ------------------------------------------------------------------ */
/* Filtre d'affichage (client) : tous / à surveiller / à jour          */
/* ------------------------------------------------------------------ */
const filtre = ref('tous');

const onglets = [
    { key: 'tous', label: 'Tous' },
    { key: 'alertes', label: 'À surveiller' },
    { key: 'ajour', label: 'À jour' },
];

/* ------------------------------------------------------------------ */
/* Règles métier                                                       */
/* ------------------------------------------------------------------ */
const JOURS_ALERTE = 10;

// Véhicules ayant au moins une intervention au circuit "normal"
const getNormalVehicles = (vehicules) => {
    if (!vehicules) return [];
    return vehicules.filter(
        (v) =>
            v.interventions &&
            v.interventions.some((i) => i.circuit && i.circuit.toLowerCase() === 'normal')
    );
};

// Retourne null si la date est absente ou lointaine, sinon { date, expiree }
const echeance = (dateStr) => {
    if (!dateStr) return null;

    const date = new Date(dateStr);

    const limite = new Date();
    limite.setDate(limite.getDate() + JOURS_ALERTE);
    if (date > limite) return null;

    const aujourdhui = new Date();
    aujourdhui.setHours(0, 0, 0, 0);

    return { date, expiree: date < aujourdhui };
};

const formatDate = (date) =>
    new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });

/* ------------------------------------------------------------------ */
/* Données préparées pour l'affichage (calculées une seule fois)       */
/* ------------------------------------------------------------------ */
const rows = computed(() =>
    (props.clients || []).map((client) => {
        const vehicules = getNormalVehicles(client.vehicules);
        const alertes = [];

        vehicules.forEach((v) => {
            [
                ['Assurance', v.expiration_assurance],
                ['SICTA', v.expiration_sicta],
            ].forEach(([type, dateStr]) => {
                const e = echeance(dateStr);
                if (e) {
                    alertes.push({ type, immat: v.immatriculation, date: e.date, expiree: e.expiree });
                }
            });
        });

        // Les échéances les plus anciennes (donc les plus urgentes) en premier
        alertes.sort((a, b) => a.date - b.date);

        const initiales = `${(client.nom || '?').charAt(0)}${(client.prenom || '').charAt(0)}`.toUpperCase();

        return { client, vehicules, alertes, initiales };
    })
);

const compteurs = computed(() => ({
    tous: rows.value.length,
    alertes: rows.value.filter((r) => r.alertes.length > 0).length,
    ajour: rows.value.filter((r) => r.alertes.length === 0).length,
}));

const rowsFiltrees = computed(() => {
    if (filtre.value === 'alertes') return rows.value.filter((r) => r.alertes.length > 0);
    if (filtre.value === 'ajour') return rows.value.filter((r) => r.alertes.length === 0);
    return rows.value;
});

const filtresActifs = computed(() => search.value !== '' || filtre.value !== 'tous');

const reinitialiser = () => {
    search.value = '';
    filtre.value = 'tous';
};

const messageVide = computed(() => {
    if (search.value) {
        return {
            titre: 'Aucun client trouvé',
            texte: `Aucun résultat pour « ${search.value} ». Vérifiez l'orthographe ou essayez un autre nom.`,
        };
    }
    if (filtre.value === 'alertes') {
        return {
            titre: 'Rien à surveiller',
            texte: "Aucune assurance ni visite SICTA n'arrive à échéance dans les 10 prochains jours.",
        };
    }
    return {
        titre: 'Aucun client pour le moment',
        texte: 'Les clients apparaîtront ici dès qu\'un véhicule sera reçu au circuit normal.',
    };
});
</script>

<template>
    <Head title="Liste des Clients — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 py-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-black tracking-tight text-[#0B0F19] sm:text-2xl">
                        Annuaire des clients
                    </h2>
                    <p class="mt-0.5 text-sm font-medium text-[#8A8D8F]">
                        Circuit normal : choisissez un client pour consulter ses véhicules et son historique.
                    </p>
                </div>

                <Link
                    :href="route('dashboard')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl border border-gray-200 bg-white px-4 text-xs font-bold text-[#0B0F19] transition duration-200 hover:border-[#0B0F19] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95 sm:self-auto"
                >
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                    <span>Retour au tableau de bord</span>
                </Link>
            </div>
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

                <!-- BARRE D'OUTILS : filtres + recherche -->
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">

                    <!-- Filtres -->
                    <div
                        class="inline-flex w-full max-w-full overflow-x-auto rounded-xl bg-[#F8FAFC] p-1 ring-1 ring-gray-200 md:w-auto"
                        role="group"
                        aria-label="Filtrer les clients"
                    >
                        <button
                            v-for="onglet in onglets"
                            :key="onglet.key"
                            type="button"
                            :aria-pressed="filtre === onglet.key"
                            @click="filtre = onglet.key"
                            class="flex min-h-10 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-3.5 text-xs font-bold transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95 md:flex-none"
                            :class="
                                filtre === onglet.key
                                    ? 'bg-[#0B0F19] text-white shadow-sm'
                                    : 'text-[#8A8D8F] hover:text-[#0B0F19]'
                            "
                        >
                            <span>{{ onglet.label }}</span>
                            <span
                                class="rounded-md px-1.5 py-0.5 text-[10px] font-black tabular-nums transition-colors duration-200"
                                :class="
                                    filtre === onglet.key
                                        ? 'bg-white/15 text-white'
                                        : onglet.key === 'alertes' && compteurs.alertes > 0
                                          ? 'bg-[#E11D48]/10 text-[#E11D48]'
                                          : 'bg-gray-200/70 text-[#0B0F19]'
                                "
                            >
                                {{ compteurs[onglet.key] }}
                            </span>
                        </button>
                    </div>

                    <!-- Recherche -->
                    <div class="relative w-full md:w-96">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-gray-400">
                            <i v-if="loading" class="fa-solid fa-spinner fa-spin text-xs text-[#E11D48]"></i>
                            <i v-else class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            inputmode="search"
                            autocomplete="off"
                            placeholder="Rechercher par nom ou numéro d'OT..."
                            aria-label="Rechercher un client"
                            class="min-h-11 w-full rounded-xl border border-gray-200 bg-[#F8FAFC] pl-11 pr-11 text-sm font-medium text-[#0B0F19] placeholder:text-gray-400 transition duration-200 focus:border-[#E11D48] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#E11D48]/10"
                        />
                        <button
                            v-if="search"
                            type="button"
                            aria-label="Effacer la recherche"
                            @click="search = ''"
                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-400 transition hover:text-[#0B0F19] focus-visible:outline-none focus-visible:text-[#E11D48]"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- LISTE -->
                <div
                    v-if="rowsFiltrees.length > 0"
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-opacity duration-200"
                    :class="{ 'opacity-60': loading }"
                >
                    <!-- En-têtes de colonnes (desktop uniquement) -->
                    <div
                        class="hidden grid-cols-[minmax(0,2.2fr)_minmax(0,1.1fr)_minmax(0,1.6fr)_minmax(0,2fr)_auto] items-center gap-x-4 border-b border-gray-200 bg-[#F8FAFC] px-6 py-3 text-xs font-bold text-[#8A8D8F] md:grid"
                    >
                        <span>Client</span>
                        <span>Téléphone</span>
                        <span>Véhicules</span>
                        <span>Situation administrative</span>
                        <span class="w-[132px]"></span>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="(row, index) in rowsFiltrees"
                            :key="row.client.id"
                            :href="route('charge_client.clients.show', row.client.id)"
                            class="row-in group relative grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 px-4 py-4 transition-colors duration-200 before:absolute before:inset-y-0 before:left-0 before:w-0.5 before:origin-center before:scale-y-0 before:bg-[#E11D48] before:transition-transform before:duration-300 hover:bg-[#F8FAFC] hover:before:scale-y-100 focus-visible:bg-[#F8FAFC] focus-visible:outline-none focus-visible:before:scale-y-100 md:grid-cols-[minmax(0,2.2fr)_minmax(0,1.1fr)_minmax(0,1.6fr)_minmax(0,2fr)_auto] md:px-6"
                            :style="{ animationDelay: Math.min(index, 8) * 45 + 'ms' }"
                        >
                            <!-- Client -->
                            <div class="order-1 flex min-w-0 items-center gap-3 md:order-1">
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#0B0F19] text-sm font-black tracking-wide text-white transition duration-200 group-hover:bg-[#E11D48]"
                                >
                                    {{ row.initiales }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-extrabold text-[#0B0F19]">
                                        {{ row.client.nom }} {{ row.client.prenom }}
                                    </p>
                                    <!-- Téléphone (mobile : sous le nom) -->
                                    <p class="mt-0.5 flex items-center gap-1.5 truncate text-xs font-medium text-[#8A8D8F] md:hidden">
                                        <i class="fa-solid fa-phone text-[10px]"></i>
                                        {{ row.client.telephone || 'Non renseigné' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Action -->
                            <div class="order-2 md:order-5">
                                <span
                                    class="hidden w-[132px] items-center justify-center gap-2 rounded-xl bg-[#0B0F19] py-2.5 text-xs font-extrabold text-white transition duration-200 group-hover:bg-[#E11D48] md:inline-flex"
                                >
                                    Détails
                                    <i class="fa-solid fa-arrow-right text-[10px] transition-transform duration-200 group-hover:translate-x-0.5"></i>
                                </span>
                                <span
                                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-[#0B0F19] transition duration-200 group-hover:border-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white md:hidden"
                                    aria-hidden="true"
                                >
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </span>
                            </div>

                            <!-- Téléphone (desktop) -->
                            <div class="hidden min-w-0 text-sm font-medium text-gray-600 md:order-2 md:block">
                                <span class="truncate">
                                    {{ row.client.telephone || 'Non renseigné' }}
                                </span>
                            </div>

                            <!-- Véhicules -->
                            <div class="order-3 col-span-2 flex flex-wrap items-center gap-x-2 gap-y-1.5 md:order-3 md:col-span-1">
                                <span
                                    v-if="row.vehicules.length"
                                    class="rounded-lg bg-white px-2 py-1 text-xs font-bold text-[#0B0F19] ring-1 ring-inset ring-gray-200"
                                >
                                    {{ row.vehicules.length }} véhicule{{ row.vehicules.length > 1 ? 's' : '' }}
                                </span>
                                <span v-else class="text-xs font-medium text-[#8A8D8F]">Aucun véhicule</span>

                                <span
                                    v-for="v in row.vehicules.slice(0, 2)"
                                    :key="v.id"
                                    class="font-mono text-[11px] font-semibold text-[#8A8D8F]"
                                >
                                    {{ v.immatriculation }}
                                </span>
                                <span
                                    v-if="row.vehicules.length > 2"
                                    class="text-[11px] font-bold text-[#8A8D8F]"
                                >
                                    +{{ row.vehicules.length - 2 }}
                                </span>
                            </div>

                            <!-- Situation administrative -->
                            <div class="order-4 col-span-2 md:order-4 md:col-span-1">
                                <div v-if="row.alertes.length" class="space-y-1.5">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#E11D48]/10 px-2.5 py-1 text-xs font-bold text-[#E11D48] ring-1 ring-inset ring-[#E11D48]/25"
                                    >
                                        <i class="fa-solid fa-triangle-exclamation text-[11px]"></i>
                                        {{ row.alertes.length }} échéance{{ row.alertes.length > 1 ? 's' : '' }} à traiter
                                    </span>
                                    <ul class="space-y-0.5 text-[11px] font-medium leading-snug text-[#8A8D8F]">
                                        <li v-for="a in row.alertes.slice(0, 2)" :key="a.type + a.immat">
                                            <span class="font-bold text-[#0B0F19]">{{ a.type }}</span>
                                            {{ a.expiree ? 'expirée le' : 'expire le' }}
                                            {{ formatDate(a.date) }}
                                            <span class="font-mono">({{ a.immat }})</span>
                                        </li>
                                        <li v-if="row.alertes.length > 2" class="font-bold">
                                            + {{ row.alertes.length - 2 }} autre{{ row.alertes.length - 2 > 1 ? 's' : '' }}
                                        </li>
                                    </ul>
                                </div>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200"
                                >
                                    <i class="fa-solid fa-circle-check text-[11px]"></i>
                                    À jour
                                </span>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- ÉTAT VIDE -->
                <div
                    v-else
                    class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-14 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200"
                    >
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                    <h3 class="text-sm font-extrabold text-[#0B0F19]">{{ messageVide.titre }}</h3>
                    <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                        {{ messageVide.texte }}
                    </p>
                    <button
                        v-if="filtresActifs"
                        type="button"
                        @click="reinitialiser"
                        class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#0B0F19] px-5 text-xs font-extrabold text-white transition duration-200 hover:bg-[#E11D48] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95"
                    >
                        <i class="fa-solid fa-rotate-left text-[11px]"></i>
                        Réinitialiser les filtres
                    </button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Entrée des lignes : un seul mouvement, désactivé si l'utilisateur réduit les animations */
@media (prefers-reduced-motion: no-preference) {
    .row-in {
        animation: row-in 420ms cubic-bezier(0.16, 1, 0.3, 1) both;
    }
}

@keyframes row-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
</style>