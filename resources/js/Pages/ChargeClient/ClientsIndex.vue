<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    clients: Array,
    orphelins: { type: Array, default: () => [] },
    chargesClients: { type: Array, default: () => [] },
    peutVoirGroupes: { type: Boolean, default: false },
    peutAttribuer: { type: Boolean, default: false },
    filters: Object,
});

/* ------------------------------------------------------------------ */
/* Regroupement par chargé client                                      */
/* ------------------------------------------------------------------ */
const ORPHELINS = 'orphelins';
const maCarte = props.chargesClients.find((c) => c.est_moi);
// Par défaut : sa propre carte (chargé client), sinon la première carte accessible.
// Lien direct « ?vue=orphelins » (carte du tableau de bord admin) : ouvre les clients non attribués.
const vueDemandee = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('vue') : null;
const selection = ref(
    vueDemandee === ORPHELINS
        ? ORPHELINS
        : maCarte?.id ?? props.chargesClients.find((c) => c.accessible)?.id ?? (props.orphelins.length ? ORPHELINS : null)
);

const choisir = (carte) => {
    if (carte === ORPHELINS) {
        selection.value = ORPHELINS;
        return;
    }
    if (!carte.accessible) return;
    selection.value = selection.value === carte.id ? null : carte.id;
};

const carteSelectionnee = computed(() =>
    props.chargesClients.find((c) => c.id === selection.value) || null
);

const sourceClients = computed(() => {
    if (!props.peutVoirGroupes) return props.clients || [];
    if (selection.value === ORPHELINS) return props.orphelins || [];
    if (selection.value === null) return [];
    return (props.clients || []).filter((c) => (c.charge_ids || []).includes(selection.value));
});

/* ------------------------------------------------------------------ */
/* Attribution des clients non attribués (administrateur)              */
/* ------------------------------------------------------------------ */
const modeAttribution = computed(() => props.peutAttribuer && selection.value === ORPHELINS);
const chargesAttribuables = computed(() => props.chargesClients || []);
const libelleCharge = (c) => (c.siege ? `${c.name} · ${c.siege}` : c.name);

// Chargé choisi pour chaque fiche, et sélection multiple pour une attribution groupée
const chargeParClient = ref({});
const coches = ref([]);
const chargeGroupe = ref('');
const attributionEnCours = ref(false);
const erreurAttribution = ref('');

// Changer de carte vide la sélection en cours
watch(selection, () => { coches.value = []; chargeGroupe.value = ''; erreurAttribution.value = ''; });

const estCoche = (id) => coches.value.includes(id);
const basculerCoche = (id) => {
    coches.value = estCoche(id) ? coches.value.filter((c) => c !== id) : [...coches.value, id];
};

function attribuer(clientIds, chargeId) {
    if (!clientIds.length || !chargeId || attributionEnCours.value) return;
    erreurAttribution.value = '';
    router.post(
        route('admin.clients.attribuer'),
        { client_ids: clientIds, charge_id: chargeId },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { attributionEnCours.value = true; },
            onFinish: () => { attributionEnCours.value = false; },
            onSuccess: () => {
                coches.value = coches.value.filter((id) => !clientIds.includes(id));
                clientIds.forEach((id) => delete chargeParClient.value[id]);
                if (!coches.value.length) chargeGroupe.value = '';
            },
            onError: (erreurs) => { erreurAttribution.value = Object.values(erreurs)[0] || "L'attribution a échoué."; },
        }
    );
}

const initialesNom = (nom) =>
    String(nom || '?')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((m) => m.charAt(0).toUpperCase())
        .join('');

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
    sourceClients.value.map((client) => {
        const vehicules = client.vehicules || [];
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

        // 2 = échéance dépassée, 1 = échéance proche, 0 = à jour
        const urgence = alertes.some((a) => a.expiree) ? 2 : alertes.length ? 1 : 0;

        return { client, vehicules, alertes, initiales, urgence };
    })
);

const compteurs = computed(() => ({
    tous: rows.value.length,
    alertes: rows.value.filter((r) => r.alertes.length > 0).length,
    ajour: rows.value.filter((r) => r.alertes.length === 0).length,
}));

const rowsFiltrees = computed(() => {
    let liste = rows.value;
    if (filtre.value === 'alertes') liste = liste.filter((r) => r.alertes.length > 0);
    if (filtre.value === 'ajour') liste = liste.filter((r) => r.alertes.length === 0);
    // Les clients les plus urgents d'abord (tri stable : l'ordre serveur est conservé sinon)
    return [...liste].sort((a, b) => b.urgence - a.urgence);
});

/* Chiffres clés affichés en haut de page */
const statistiques = computed(() => {
    const tous = (props.clients || []).length;
    const vehicules = (props.clients || []).reduce((s, c) => s + (c.vehicules || []).length, 0);
    return [
        { label: props.peutVoirGroupes ? 'Clients suivis' : 'Clients', valeur: tous, icone: 'fa-users', ton: 'sombre' },
        { label: 'Véhicules', valeur: vehicules, icone: 'fa-car-side', ton: 'sombre' },
        {
            label: 'À surveiller',
            valeur: (props.clients || []).filter((c) => (c.vehicules || []).some((v) =>
                echeance(v.expiration_assurance) || echeance(v.expiration_sicta))).length,
            icone: 'fa-triangle-exclamation', ton: 'rouge' },
        ...(props.peutVoirGroupes
            ? [{ label: 'Non attribués', valeur: (props.orphelins || []).length, icone: 'fa-user-plus', ton: 'ambre' }]
            : []),
    ];
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
    if (props.peutVoirGroupes && selection.value === null) {
        return {
            titre: 'Choisissez un chargé client',
            texte: 'Cliquez sur votre nom pour afficher vos clients, ou sur « Clients non attribués » pour voir les nouveaux clients.',
        };
    }
    if (selection.value === ORPHELINS) {
        return {
            titre: 'Aucun client non attribué',
            texte: 'Tous les clients du siège sont déjà suivis par un chargé client.',
        };
    }
    return {
        titre: 'Aucun client pour le moment',
        texte: 'Les clients ayant créé leur compte en ligne apparaissent ici, même sans véhicule. Les autres apparaissent après une intervention au circuit normal.',
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
                        Consultez les clients de votre siège, leurs véhicules et leur historique.
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

                <!-- CHIFFRES CLÉS -->
                <section aria-label="Chiffres clés" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div
                        v-for="stat in statistiques"
                        :key="stat.label"
                        class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                    >
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                            :class="{
                                'bg-[#0B0F19] text-white': stat.ton === 'sombre',
                                'bg-[#E11D48]/10 text-[#E11D48]': stat.ton === 'rouge',
                                'bg-amber-100 text-amber-600': stat.ton === 'ambre',
                            }"
                        >
                            <i class="fa-solid text-sm" :class="stat.icone" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-2xl font-black tabular-nums leading-none text-[#0B0F19]">{{ stat.valeur }}</span>
                            <span class="mt-1 block truncate text-xs font-bold text-[#8A8D8F]">{{ stat.label }}</span>
                        </span>
                    </div>
                </section>

                <!-- CARTES : un chargé client par carte + clients non attribués -->
                <section v-if="peutVoirGroupes" aria-label="Clients par chargé client">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <button
                            v-for="carte in chargesClients"
                            :key="carte.id"
                            type="button"
                            :disabled="!carte.accessible"
                            :aria-pressed="selection === carte.id"
                            :title="carte.accessible ? 'Afficher les clients' : 'Seul ce chargé client peut ouvrir sa liste'"
                            @click="choisir(carte)"
                            class="flex min-h-[84px] items-center gap-3 rounded-2xl border bg-white p-4 text-left shadow-sm transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40"
                            :class="[
                                selection === carte.id
                                    ? 'border-[#E11D48] ring-2 ring-[#E11D48]/20'
                                    : 'border-gray-200',
                                carte.accessible ? 'hover:border-[#0B0F19] active:scale-[0.98]' : 'cursor-not-allowed opacity-60',
                            ]"
                        >
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-black text-white"
                                :class="selection === carte.id ? 'bg-[#E11D48]' : 'bg-[#0B0F19]'"
                            >
                                {{ initialesNom(carte.name) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-extrabold text-[#0B0F19]">
                                    {{ carte.name }}
                                    <span v-if="carte.est_moi" class="ml-1 text-[10px] font-bold text-[#E11D48]">(vous)</span>
                                </span>
                                <span class="mt-0.5 block text-xs font-medium text-[#8A8D8F]">
                                    {{ carte.nb_clients }} client{{ carte.nb_clients > 1 ? 's' : '' }} suivi{{ carte.nb_clients > 1 ? 's' : '' }}
                                </span>
                            </span>
                            <i
                                class="fa-solid text-xs text-[#8A8D8F]"
                                :class="carte.accessible ? (selection === carte.id ? 'fa-chevron-up' : 'fa-chevron-down') : 'fa-lock'"
                                aria-hidden="true"
                            ></i>
                        </button>

                        <!-- Clients orphelins : à côté des cartes, ouverts à tous les chargés du siège -->
                        <button
                            type="button"
                            :aria-pressed="selection === ORPHELINS"
                            @click="choisir(ORPHELINS)"
                            class="flex min-h-[84px] items-center gap-3 rounded-2xl border border-dashed bg-amber-50/70 p-4 text-left shadow-sm transition duration-200 hover:border-amber-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/50 active:scale-[0.98]"
                            :class="selection === ORPHELINS ? 'border-amber-500 ring-2 ring-amber-400/30' : 'border-amber-300'"
                        >
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white">
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-extrabold text-[#0B0F19]">Clients non attribués</span>
                                <span class="mt-0.5 block text-xs font-medium text-amber-700">
                                    {{ orphelins.length }} en attente de prise en charge
                                </span>
                            </span>
                            <span
                                v-if="orphelins.length"
                                class="grid h-6 min-w-6 place-items-center rounded-full bg-amber-500 px-1.5 text-[11px] font-black text-white"
                            >
                                {{ orphelins.length }}
                            </span>
                        </button>
                    </div>

                    <p v-if="selection !== null" class="mt-4 text-sm font-bold text-[#0B0F19]">
                        <template v-if="selection === ORPHELINS">
                            Clients non attribués
                            <span v-if="peutAttribuer" class="font-medium text-[#8A8D8F]">— choisissez un chargé client sous chaque fiche, ou cochez plusieurs clients pour les attribuer d'un coup.</span>
                            <span v-else class="font-medium text-[#8A8D8F]">— enregistrez une interaction sur un véhicule pour prendre le client en charge.</span>
                        </template>
                        <template v-else-if="carteSelectionnee">
                            Clients suivis par {{ carteSelectionnee.est_moi ? 'vous' : carteSelectionnee.name }}
                        </template>
                    </p>
                </section>

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

                <!-- ATTRIBUTION GROUPÉE (admin, clients non attribués) -->
                <div
                    v-if="modeAttribution && rowsFiltrees.length > 0"
                    class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:flex-row sm:items-center"
                >
                    <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 text-xs font-bold text-[#0B0F19]">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-400/40"
                            :checked="coches.length > 0 && coches.length === rowsFiltrees.length"
                            :indeterminate.prop="coches.length > 0 && coches.length < rowsFiltrees.length"
                            @change="coches = $event.target.checked ? rowsFiltrees.map((r) => r.client.id) : []"
                        />
                        <span>{{ coches.length ? `${coches.length} client${coches.length > 1 ? 's' : ''} sélectionné${coches.length > 1 ? 's' : ''}` : 'Tout sélectionner' }}</span>
                    </label>
                    <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                        <label for="charge-groupe" class="sr-only">Chargé client pour la sélection</label>
                        <select
                            id="charge-groupe"
                            v-model="chargeGroupe"
                            :disabled="!coches.length"
                            class="min-h-10 rounded-xl border border-gray-200 bg-white px-3 text-xs font-bold text-[#0B0F19] focus:border-amber-500 focus:ring-amber-400/30 disabled:opacity-50 sm:w-64"
                        >
                            <option value="" disabled>Attribuer la sélection à…</option>
                            <option v-for="c in chargesAttribuables" :key="c.id" :value="c.id">{{ libelleCharge(c) }}</option>
                        </select>
                        <button
                            type="button"
                            :disabled="!coches.length || !chargeGroupe || attributionEnCours"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 text-xs font-extrabold text-white transition hover:bg-amber-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="attribuer([...coches], chargeGroupe)"
                        >
                            <i class="fa-solid text-[11px]" :class="attributionEnCours ? 'fa-spinner fa-spin' : 'fa-user-check'" aria-hidden="true"></i>
                            Attribuer
                        </button>
                    </div>
                    <p v-if="!chargesAttribuables.length" class="text-xs font-bold text-red-600">
                        Aucun chargé client n'existe encore : créez-en un dans la gestion des utilisateurs.
                    </p>
                </div>
                <p v-if="erreurAttribution" class="text-xs font-bold text-red-600" role="alert">{{ erreurAttribution }}</p>

                <!-- LISTE : fiches clients -->
                <div
                    v-if="rowsFiltrees.length > 0"
                    class="grid grid-cols-1 gap-4 transition-opacity duration-200 md:grid-cols-2 xl:grid-cols-3"
                    :class="{ 'opacity-60': loading }"
                >
                    <div
                        v-for="(row, index) in rowsFiltrees"
                        :key="row.client.id"
                        class="fiche-in flex flex-col"
                        :style="{ animationDelay: Math.min(index, 9) * 40 + 'ms' }"
                    >
                    <Link
                        :href="route('charge_client.clients.show', row.client.id)"
                        class="group relative flex flex-1 flex-col overflow-hidden border border-gray-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40"
                        :class="modeAttribution ? 'rounded-t-2xl' : 'rounded-2xl'"
                    >
                        <!-- Liseré de statut -->
                        <span
                            class="absolute inset-x-0 top-0 h-1"
                            :class="{
                                'bg-[#E11D48]': row.urgence === 2,
                                'bg-amber-400': row.urgence === 1,
                                'bg-emerald-400': row.urgence === 0,
                            }"
                            aria-hidden="true"
                        ></span>

                        <!-- En-tête : identité -->
                        <div class="flex items-start gap-3 px-5 pb-4 pt-5">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-sm font-black tracking-wide text-white transition duration-200"
                                :class="selection === ORPHELINS && peutVoirGroupes ? 'bg-amber-500' : 'bg-[#0B0F19] group-hover:bg-[#E11D48]'"
                            >
                                {{ row.initiales }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-extrabold leading-tight text-[#0B0F19]">
                                    {{ row.client.nom }} {{ row.client.prenom }}
                                </p>
                                <p class="mt-1 flex items-center gap-1.5 truncate text-xs font-medium text-[#8A8D8F]">
                                    <i class="fa-solid fa-phone text-[10px]"></i>
                                    {{ row.client.telephone || 'Téléphone non renseigné' }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <span
                                        v-if="selection === ORPHELINS && peutVoirGroupes"
                                        class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700"
                                    >
                                        <i class="fa-solid fa-user-plus text-[9px]"></i> Non attribué
                                    </span>
                                    <span
                                        v-if="row.client.portal_registered"
                                        class="inline-flex items-center gap-1 rounded-md bg-[#E11D48]/10 px-2 py-0.5 text-[10px] font-bold text-[#E11D48]"
                                    >
                                        <i class="fa-solid fa-globe text-[9px]"></i> Compte en ligne
                                    </span>
                                    <span
                                        v-if="row.client.type_client === 'entreprise'"
                                        class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600"
                                    >
                                        <i class="fa-solid fa-building text-[9px]"></i> Entreprise
                                    </span>
                                </div>
                            </div>
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-[#0B0F19] transition duration-200 group-hover:border-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white"
                                aria-hidden="true"
                            >
                                <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-0.5"></i>
                            </span>
                        </div>

                        <!-- Véhicules -->
                        <div class="border-t border-gray-100 px-5 py-3">
                            <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-[#8A8D8F]">
                                <i class="fa-solid fa-car-side mr-1"></i>
                                {{ row.vehicules.length ? `${row.vehicules.length} véhicule${row.vehicules.length > 1 ? 's' : ''}` : 'Aucun véhicule' }}
                            </p>
                            <div v-if="row.vehicules.length" class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="v in row.vehicules.slice(0, 3)"
                                    :key="v.id"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-[#F8FAFC] px-2 py-1 text-[11px] font-semibold text-[#0B0F19]"
                                >
                                    <span class="font-mono font-bold">{{ v.immatriculation }}</span>
                                    <span v-if="v.marque" class="text-[#8A8D8F]">{{ v.marque }}</span>
                                </span>
                                <span
                                    v-if="row.vehicules.length > 3"
                                    class="inline-flex items-center rounded-lg bg-gray-100 px-2 py-1 text-[11px] font-bold text-[#8A8D8F]"
                                >
                                    +{{ row.vehicules.length - 3 }}
                                </span>
                            </div>
                        </div>

                        <!-- Situation administrative -->
                        <div class="mt-auto border-t border-gray-100 px-5 py-3">
                            <ul v-if="row.alertes.length" class="space-y-1.5">
                                <li
                                    v-for="a in row.alertes.slice(0, 2)"
                                    :key="a.type + a.immat"
                                    class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold"
                                    :class="a.expiree ? 'bg-[#E11D48]/10 text-[#BE123C]' : 'bg-amber-50 text-amber-800'"
                                >
                                    <span class="flex min-w-0 items-center gap-1.5">
                                        <i class="fa-solid text-[10px]" :class="a.expiree ? 'fa-circle-exclamation' : 'fa-clock'"></i>
                                        <span class="font-bold">{{ a.type }}</span>
                                        <span class="truncate font-mono opacity-80">{{ a.immat }}</span>
                                    </span>
                                    <span class="shrink-0">{{ a.expiree ? 'expirée' : 'expire' }} le {{ formatDate(a.date) }}</span>
                                </li>
                                <li v-if="row.alertes.length > 2" class="px-2.5 text-[11px] font-bold text-[#8A8D8F]">
                                    + {{ row.alertes.length - 2 }} autre{{ row.alertes.length - 2 > 1 ? 's' : '' }} échéance{{ row.alertes.length - 2 > 1 ? 's' : '' }}
                                </li>
                            </ul>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700"
                            >
                                <i class="fa-solid fa-circle-check text-[11px]"></i>
                                Assurance et SICTA à jour
                            </span>
                        </div>
                    </Link>

                    <!-- Attribution de ce client (admin) : hors du lien pour rester cliquable -->
                    <div
                        v-if="modeAttribution"
                        class="flex items-center gap-2 rounded-b-2xl border border-t-0 px-3 py-2.5 transition"
                        :class="estCoche(row.client.id) ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-[#F8FAFC]'"
                    >
                        <input
                            type="checkbox"
                            class="h-4 w-4 shrink-0 rounded border-gray-300 text-amber-500 focus:ring-amber-400/40"
                            :checked="estCoche(row.client.id)"
                            :aria-label="`Sélectionner ${row.client.nom} ${row.client.prenom || ''}`"
                            @change="basculerCoche(row.client.id)"
                        />
                        <label :for="`charge-${row.client.id}`" class="sr-only">Chargé client pour {{ row.client.nom }}</label>
                        <select
                            :id="`charge-${row.client.id}`"
                            v-model="chargeParClient[row.client.id]"
                            class="min-h-9 min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-bold text-[#0B0F19] focus:border-amber-500 focus:ring-amber-400/30"
                        >
                            <option :value="undefined" disabled>Attribuer à…</option>
                            <option v-for="c in chargesAttribuables" :key="c.id" :value="c.id">{{ libelleCharge(c) }}</option>
                        </select>
                        <button
                            type="button"
                            :disabled="!chargeParClient[row.client.id] || attributionEnCours"
                            class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg bg-[#0B0F19] px-3 text-[11px] font-extrabold text-white transition hover:bg-amber-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="attribuer([row.client.id], chargeParClient[row.client.id])"
                        >
                            <i class="fa-solid fa-user-check text-[10px]" aria-hidden="true"></i>
                            Attribuer
                        </button>
                    </div>
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
    .fiche-in {
        animation: fiche-in 420ms cubic-bezier(0.16, 1, 0.3, 1) both;
    }
}

@keyframes fiche-in {
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