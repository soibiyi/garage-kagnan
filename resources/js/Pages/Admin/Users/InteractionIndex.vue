<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';

const props = defineProps({
    chargesClients: Array,
    siegeFiltre: { type: String, default: null },
});

/* ------------------------------------------------------------------ */
/* Utilitaires                                                         */
/* ------------------------------------------------------------------ */
const lireDate = (valeur) => {
    if (!valeur) return null;
    const [a, m, j] = String(valeur).slice(0, 10).split('-').map(Number);
    if (!a || !m || !j) return null;
    return new Date(a, m - 1, j);
};

const formatDateLongue = (date) =>
    date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const formatDateCourte = (date) =>
    date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });

const formatHeure = (date) =>
    date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

const debutJour = (valeur) => {
    const d = new Date(valeur);
    d.setHours(0, 0, 0, 0);
    return d;
};

const ecartJours = (date) => Math.round((debutJour(new Date()) - debutJour(date)) / 86400000);

const quand = (date) => {
    const j = ecartJours(date);
    const jour = j <= 0 ? "aujourd'hui" : j === 1 ? 'hier' : formatDateLongue(date);
    return `${jour} à ${formatHeure(date)}`;
};

/* ------------------------------------------------------------------ */
/* Types d'interactions                                                */
/* ------------------------------------------------------------------ */
const TYPES = [
    { valeur: 'appel', label: 'Appel', icone: 'fa-solid fa-phone' },
    { valeur: 'whatsapp', label: 'WhatsApp', icone: 'fa-solid fa-comments' },
    { valeur: 'sms', label: 'SMS', icone: 'fa-solid fa-message' },
    { valeur: 'email', label: 'E-mail', icone: 'fa-solid fa-envelope' },
    { valeur: 'visite', label: 'Visite', icone: 'fa-solid fa-handshake' },
    { valeur: 'autre', label: 'Autre', icone: 'fa-solid fa-ellipsis' },
];

const iconeType = (valeur) => TYPES.find((t) => t.valeur === valeur)?.icone || 'fa-solid fa-comment';
const labelType = (valeur) => TYPES.find((t) => t.valeur === valeur)?.label || 'Interaction';

/* ------------------------------------------------------------------ */
/* Recherche & filtre de période (globaux)                             */
/* ------------------------------------------------------------------ */
const searchQuery = ref('');

const PERIODES = [
    { valeur: 'tout', label: 'Tout' },
    { valeur: 'aujourdhui', label: "Aujourd'hui" },
    { valeur: '7j', label: '7 jours' },
    { valeur: '30j', label: '30 jours' },
    { valeur: 'perso', label: 'Personnalisé' },
];

const periode = ref('tout');
const dateDebut = ref('');
const dateFin = ref('');

const filtreActif = computed(() => periode.value !== 'tout');

const bornes = computed(() => {
    const auj = debutJour(new Date());
    switch (periode.value) {
        case 'aujourdhui':
            return { debut: auj, fin: null };
        case '7j': {
            const d = new Date(auj);
            d.setDate(d.getDate() - 6);
            return { debut: d, fin: null };
        }
        case '30j': {
            const d = new Date(auj);
            d.setDate(d.getDate() - 29);
            return { debut: d, fin: null };
        }
        case 'perso': {
            const fin = lireDate(dateFin.value);
            if (fin) fin.setHours(23, 59, 59, 999);
            return { debut: lireDate(dateDebut.value), fin };
        }
        default:
            return { debut: null, fin: null };
    }
});

const libellePeriode = computed(() => {
    if (periode.value === 'perso') {
        const { debut, fin } = bornes.value;
        if (debut && fin) return `du ${formatDateCourte(debut)} au ${formatDateCourte(fin)}`;
        if (debut) return `depuis le ${formatDateCourte(debut)}`;
        if (fin) return `jusqu'au ${formatDateCourte(fin)}`;
        return 'période personnalisée';
    }
    return PERIODES.find((p) => p.valeur === periode.value)?.label.toLowerCase() || '';
});

const periodeInvalide = computed(() => {
    const { debut, fin } = bornes.value;
    return periode.value === 'perso' && debut && fin && debut > fin;
});

const dansPeriode = (interaction) => {
    if (periode.value === 'tout') return true;
    const d = new Date(interaction.created_at);
    if (isNaN(d)) return false;
    const { debut, fin } = bornes.value;
    if (debut && d < debut) return false;
    if (fin && d > fin) return false;
    return true;
};

const reinitialiserFiltres = () => {
    searchQuery.value = '';
    periode.value = 'tout';
    dateDebut.value = '';
    dateFin.value = '';
};

/* ------------------------------------------------------------------ */
/* Données préparées : chargé -> clients -> interactions               */
/* ------------------------------------------------------------------ */
const preparerGroupe = (groupe, idx, chargeId) => {
    const toutes = [...(groupe.interactions || [])].sort(
        (a, b) => new Date(b.created_at) - new Date(a.created_at),
    );
    const interactions = toutes.filter(dansPeriode);

    return {
        cle: `${chargeId}-${groupe.client?.id ?? idx}`,
        client: groupe.client || {},
        toutes,
        interactions,
        consignes: groupe.client?.consignes || [],
        dernier: interactions[0] ? new Date(interactions[0].created_at) : null,
    };
};

const chargesVue = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return (props.chargesClients || [])
        .map((charge) => {
            const tous = (charge.clients_groups || []).map((g, i) => preparerGroupe(g, i, charge.id));

            const matchCharge =
                !query ||
                (charge.name || '').toLowerCase().includes(query) ||
                (charge.email || '').toLowerCase().includes(query);

            const clients = matchCharge
                ? tous
                : tous.filter((g) => {
                      const nom = (g.client.nom || '').toLowerCase();
                      const tel = (g.client.telephone || '').toLowerCase();
                      return nom.includes(query) || tel.includes(query);
                  });

            if (!matchCharge && !clients.length) return null;

            clients.sort(
                (a, b) =>
                    (b.dernier?.getTime() ?? -1) - (a.dernier?.getTime() ?? -1) ||
                    (a.client.nom || '').localeCompare(b.client.nom || '', 'fr'),
            );

            return {
                id: charge.id,
                name: charge.name || '',
                email: charge.email || '',
                clients,
                totalInteractions: clients.reduce((s, g) => s + g.interactions.length, 0),
                clientsActifs: clients.filter((g) => g.interactions.length > 0).length,
            };
        })
        .filter(Boolean);
});

/* ------------------------------------------------------------------ */
/* Onglets                                                             */
/* ------------------------------------------------------------------ */
const chargeActifId = ref(props.chargesClients?.[0]?.id ?? null);

const chargeActif = computed(
    () => chargesVue.value.find((c) => c.id === chargeActifId.value) || chargesVue.value[0] || null,
);

const choisirCharge = (id) => {
    chargeActifId.value = id;
};

watch(
    () => props.chargesClients,
    (liste) => {
        chargeActifId.value = liste?.[0]?.id ?? null;
        fermerModal();
    },
);

const initiale = (nom) => (nom ? nom.charAt(0).toUpperCase() : 'C');

/* ------------------------------------------------------------------ */
/* Modal du client & Consignes Admin                                   */
/* ------------------------------------------------------------------ */
const clientOuvertCle = ref(null);
const historiqueComplet = ref(false);
const boutonFermer = ref(null);
const afficherFormConsigne = ref(false);

const clientModal = computed(
    () => chargeActif.value?.clients.find((g) => g.cle === clientOuvertCle.value) || null,
);

const interactionsModal = computed(() => {
    if (!clientModal.value) return [];
    return historiqueComplet.value ? clientModal.value.toutes : clientModal.value.interactions;
});

// Formulaire pour l'ajout de consigne
const consigneForm = useForm({
    client_id: null,
    consigne: '',
});

const ouvrirModal = async (cle) => {
    historiqueComplet.value = false;
    afficherFormConsigne.value = false;
    clientOuvertCle.value = cle;
    await nextTick();
    boutonFermer.value?.focus();
};

function fermerModal() {
    clientOuvertCle.value = null;
    historiqueComplet.value = false;
    afficherFormConsigne.value = false;
    consigneForm.reset();
}

const soumettreConsigne = () => {
    if (!clientModal.value?.client?.id) return;
    consigneForm.client_id = clientModal.value.client.id;
    
    consigneForm.post(route('admin.clients.consignes.store', clientModal.value.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            consigneForm.reset('consigne');
            afficherFormConsigne.value = false;
        },
    });
};

watch(clientModal, (ouvert) => {
    document.body.style.overflow = ouvert ? 'hidden' : '';
});

const surTouche = (e) => {
    if (e.key === 'Escape' && clientModal.value) fermerModal();
};

onMounted(() => window.addEventListener('keydown', surTouche));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', surTouche);
    document.body.style.overflow = '';
});

const aucuneDonnee = computed(() => !(props.chargesClients && props.chargesClients.length));
</script>

<template>
    <Head title="Suivi des interactions par chargé client — Administration" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-black text-[#0B0F19]">Suivi des interactions</h2>
                    <p class="text-xs font-medium text-[#8A8D8F]">
                        Qui a fait quoi, pour quel client
                        <span v-if="siegeFiltre" class="font-bold text-[#E11D48]"> — Siège {{ siegeFiltre }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <SiegeSwitcher :current="siegeFiltre" />
                    <Link
                        :href="route('admin.users.index')"
                        class="inline-flex min-h-[40px] shrink-0 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-[#0B0F19] shadow-sm transition hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0B0F19]"
                    >
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        <span>Retour</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-white py-6 sm:py-10">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

                <!-- ============ BARRE D'OUTILS : RECHERCHE + PÉRIODE ============ -->
                <div v-if="!aucuneDonnee" class="space-y-3 rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Rechercher un chargé ou un client (nom, e-mail, téléphone)..."
                            class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-10 text-xs font-medium text-[#0B0F19] transition focus:border-[#0B0F19] focus:outline-none focus:ring-1 focus:ring-[#0B0F19]"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            aria-label="Effacer la recherche"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 transition hover:text-gray-600"
                        >
                            <i class="fa-solid fa-circle-xmark text-xs"></i>
                        </button>
                    </div>

                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Période">
                            <button
                                v-for="p in PERIODES"
                                :key="p.valeur"
                                type="button"
                                @click="periode = p.valeur"
                                :aria-pressed="periode === p.valeur"
                                class="inline-flex min-h-[36px] shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-xs font-bold transition active:scale-95"
                                :class="periode === p.valeur
                                    ? 'border-[#0B0F19] bg-[#0B0F19] text-white'
                                    : 'border-gray-200 bg-white text-[#0B0F19] hover:bg-gray-100'"
                            >
                                <i v-if="p.valeur === 'perso'" class="fa-regular fa-calendar text-[10px]"></i>
                                {{ p.label }}
                            </button>
                        </div>

                        <Transition name="fade">
                            <div v-if="periode === 'perso'" class="flex flex-wrap items-center gap-2">
                                <label class="flex items-center gap-2 text-[11px] font-semibold text-[#8A8D8F]">
                                    Du
                                    <input
                                        v-model="dateDebut"
                                        type="date"
                                        :max="dateFin || undefined"
                                        class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-[#0B0F19] focus:border-[#0B0F19] focus:outline-none focus:ring-1 focus:ring-[#0B0F19]"
                                    />
                                </label>
                                <label class="flex items-center gap-2 text-[11px] font-semibold text-[#8A8D8F]">
                                    au
                                    <input
                                        v-model="dateFin"
                                        type="date"
                                        :min="dateDebut || undefined"
                                        class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-[#0B0F19] focus:border-[#0B0F19] focus:outline-none focus:ring-1 focus:ring-[#0B0F19]"
                                    />
                                </label>
                            </div>
                        </Transition>
                    </div>

                    <p v-if="periodeInvalide" class="flex items-center gap-1.5 text-[11px] font-semibold text-[#E11D48]">
                        <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                        La date de début doit précéder la date de fin.
                    </p>
                </div>

                <!-- ============ CONTENU ============ -->
                <template v-if="chargeActif">

                    <!-- ONGLETS DES CHARGÉS CLIENTS -->
                    <div
                        class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                        role="tablist"
                        aria-label="Chargés clients"
                    >
                        <button
                            v-for="charge in chargesVue"
                            :key="charge.id"
                            type="button"
                            role="tab"
                            :aria-selected="charge.id === chargeActif.id"
                            @click="choisirCharge(charge.id)"
                            class="flex min-h-[48px] shrink-0 items-center gap-2.5 rounded-xl border px-3.5 py-2 text-left transition active:scale-[0.98]"
                            :class="charge.id === chargeActif.id
                                ? 'border-[#0B0F19] bg-[#0B0F19] text-white shadow-md'
                                : 'border-gray-200 bg-white text-[#0B0F19] hover:bg-gray-50'"
                        >
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-black"
                                :class="charge.id === chargeActif.id ? 'bg-white text-[#0B0F19]' : 'bg-gray-100 text-[#0B0F19]'"
                            >
                                {{ initiale(charge.name) }}
                            </span>
                            <span class="whitespace-nowrap text-xs font-extrabold">{{ charge.name }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                :class="charge.id === chargeActif.id
                                    ? 'bg-[#E11D48] text-white'
                                    : charge.totalInteractions
                                        ? 'bg-gray-200 text-[#0B0F19]'
                                        : 'bg-gray-100 text-gray-400'"
                            >
                                {{ charge.totalInteractions }}
                            </span>
                        </button>
                    </div>

                    <!-- PANNEAU DU CHARGÉ ACTIF -->
                    <Transition name="fade" mode="out-in">
                        <section
                            :key="chargeActif.id"
                            role="tabpanel"
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                        >
                            <header class="flex flex-col gap-2 border-b border-gray-100 bg-[#F8FAFC] p-5 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-[#0B0F19] text-sm font-black text-white">
                                        {{ initiale(chargeActif.name) }}
                                    </div>
                                    <div>
                                        <h3 class="text-base font-black text-[#0B0F19]">{{ chargeActif.name }}</h3>
                                        <p class="text-xs font-medium text-[#8A8D8F]">{{ chargeActif.email }}</p>
                                    </div>
                                </div>
                                <p class="text-xs font-semibold text-[#8A8D8F]">
                                    {{ pluriel(chargeActif.clientsActifs, 'client contacté') }}
                                    <span class="mx-1 text-gray-300">/</span>
                                    {{ pluriel(chargeActif.clients.length, 'client suivi') }}
                                    <span class="mx-1 text-gray-300">/</span>
                                    {{ pluriel(chargeActif.totalInteractions, 'interaction') }}
                                    <span v-if="filtreActif" class="font-bold text-[#E11D48]"> ({{ libellePeriode }})</span>
                                </p>
                            </header>

                            <!-- LISTE DES CLIENTS -->
                            <ul v-if="chargeActif.clients.length" class="divide-y divide-gray-100">
                                <li v-for="groupe in chargeActif.clients" :key="groupe.cle">
                                    <button
                                        type="button"
                                        @click="ouvrirModal(groupe.cle)"
                                        class="group flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none"
                                        :class="groupe.interactions.length ? '' : 'opacity-50'"
                                    >
                                        <div class="flex min-w-0 items-center gap-3">
                                            <i class="fa-solid fa-user-circle text-xl text-gray-300 transition group-hover:text-[#0B0F19]"></i>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-extrabold text-[#0B0F19]">{{ groupe.client.nom || 'Client sans nom' }}</p>
                                                <p v-if="groupe.client.telephone" class="text-xs font-medium text-[#8A8D8F]">{{ groupe.client.telephone }}</p>
                                            </div>
                                        </div>

                                        <div class="flex shrink-0 items-center gap-4">
                                            <div class="text-right">
                                                <p class="text-xs font-bold text-[#0B0F19]">
                                                    {{ groupe.interactions.length ? pluriel(groupe.interactions.length, 'échange') : 'Aucun échange' }}
                                                </p>
                                                <p class="text-[11px] font-medium text-[#8A8D8F]">
                                                    <template v-if="groupe.dernier">Dernier : {{ quand(groupe.dernier) }}</template>
                                                    <template v-else-if="filtreActif">sur la période</template>
                                                </p>
                                            </div>
                                            <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-[#0B0F19]"></i>
                                        </div>
                                    </button>
                                </li>
                            </ul>

                            <div v-else class="px-6 py-12 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8FAFC] text-[#8A8D8F] ring-1 ring-gray-200">
                                    <i class="fa-solid fa-user-slash"></i>
                                </div>
                                <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucun client suivi</h4>
                                <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                                    Ce chargé client n'a encore aucun client rattaché.
                                </p>
                            </div>
                        </section>
                    </Transition>
                </template>

                <!-- ============ ÉTATS VIDES ============ -->
                <div
                    v-else
                    class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-12 text-center"
                >
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                        <i :class="aucuneDonnee ? 'fa-solid fa-users-slash' : 'fa-solid fa-magnifying-glass'"></i>
                    </div>

                    <template v-if="aucuneDonnee">
                        <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucun chargé client</h4>
                        <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                            Aucun chargé client n'est enregistré<span v-if="siegeFiltre"> pour le siège {{ siegeFiltre }}</span>.
                        </p>
                    </template>

                    <template v-else>
                        <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucun résultat trouvé</h4>
                        <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                            Aucun chargé client ou client ne correspond à « {{ searchQuery }} ».
                        </p>
                        <button
                            type="button"
                            @click="reinitialiserFiltres"
                            class="mt-4 inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-xs font-bold text-[#0B0F19] transition hover:bg-gray-100 active:scale-95"
                        >
                            <i class="fa-solid fa-rotate-left text-[10px]"></i>
                            Réinitialiser la recherche
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- ============ MODAL : INTERACTIONS & CONSIGNES DU CLIENT ============ -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="clientModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-[#0B0F19]/60 sm:items-center sm:p-6"
                    @click.self="fermerModal"
                >
                    <div
                        class="modal-panel flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-h-[85vh] sm:rounded-2xl"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="modal-client-titre"
                    >
                        <!-- En-tête du modal -->
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 bg-[#F8FAFC] p-5">
                            <div class="min-w-0">
                                <h3 id="modal-client-titre" class="truncate text-base font-black text-[#0B0F19]">
                                    {{ clientModal.client.nom || 'Client sans nom' }}
                                </h3>
                                <p v-if="clientModal.client.telephone" class="mt-0.5 text-xs font-medium text-[#8A8D8F]">
                                    <i class="fa-solid fa-phone mr-1 text-[10px]"></i>{{ clientModal.client.telephone }}
                                </p>
                                <p class="mt-1.5 text-xs font-semibold text-[#0B0F19]">
                                    <i class="fa-solid fa-user-tie mr-1 text-[10px] text-[#8A8D8F]"></i>
                                    Géré par <span class="font-extrabold">{{ chargeActif.name }}</span>
                                </p>
                            </div>

                            <button
                                ref="boutonFermer"
                                type="button"
                                aria-label="Fermer"
                                @click="fermerModal"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-[#0B0F19] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0B0F19]"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <!-- SECTION CONSIGNES ADMIN -->
                        <div class="border-b border-rose-100 bg-rose-50/60 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 text-xs font-extrabold text-[#E11D48]">
                                    <i class="fa-solid fa-bullhorn text-sm"></i>
                                    <span>Consignes de la direction</span>
                                </div>
                                <button
                                    type="button"
                                    @click="afficherFormConsigne = !afficherFormConsigne"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#E11D48] px-2.5 py-1 text-[11px] font-bold text-white transition hover:bg-rose-700 active:scale-95"
                                >
                                    <i class="fa-solid" :class="afficherFormConsigne ? 'fa-minus' : 'fa-plus'"></i>
                                    <span>{{ afficherFormConsigne ? 'Masquer' : 'Laisser une consigne' }}</span>
                                </button>
                            </div>

                            <!-- Formulaire de saisie d'une consigne -->
                            <Transition name="fade">
                                <form v-if="afficherFormConsigne" @submit.prevent="soumettreConsigne" class="mt-3 space-y-2">
                                    <textarea
                                        v-model="consigneForm.consigne"
                                        rows="2"
                                        placeholder="Saisissez vos instructions/consignes pour le chargé client à propos de ce client..."
                                        class="w-full rounded-xl border border-rose-200 bg-white p-2.5 text-xs font-medium text-[#0B0F19] placeholder-gray-400 focus:border-[#E11D48] focus:outline-none focus:ring-1 focus:ring-[#E11D48]"
                                        required
                                    ></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="afficherFormConsigne = false"
                                            class="rounded-lg border border-gray-200 bg-white px-3 py-1 text-xs font-bold text-gray-600 hover:bg-gray-50"
                                        >
                                            Annuler
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="consigneForm.processing"
                                            class="rounded-lg bg-[#0B0F19] px-3 py-1 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50"
                                        >
                                            Enregistrer la consigne
                                        </button>
                                    </div>
                                </form>
                            </Transition>

                            <!-- Affichage des consignes actuelles -->
                            <div v-if="clientModal.consignes && clientModal.consignes.length" class="mt-3 space-y-2">
                                <div
                                    v-for="item in clientModal.consignes"
                                    :key="item.id"
                                    class="rounded-xl border border-rose-200/80 bg-white p-3 text-xs shadow-sm"
                                >
                                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-1.5 text-[11px] font-semibold text-gray-500">
                                        <span class="font-extrabold text-[#0B0F19]">
                                            <i class="fa-solid fa-user-shield text-[10px] text-[#E11D48] mr-1"></i>
                                            {{ item.auteur || 'Direction' }}
                                        </span>
                                        <span>{{ quand(new Date(item.created_at)) }}</span>
                                    </div>
                                    <p class="mt-1.5 whitespace-pre-line leading-relaxed text-gray-800 font-medium">
                                        {{ item.consigne }}
                                    </p>
                                </div>
                            </div>
                            <p v-else-if="!afficherFormConsigne" class="mt-2 text-[11px] font-medium italic text-gray-500">
                                Aucune consigne particulière enregistrée pour ce client.
                            </p>
                        </div>

                        <!-- Bandeau de période -->
                        <div
                            v-if="filtreActif || historiqueComplet"
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-2.5 text-[11px] font-semibold"
                        >
                            <span class="text-[#8A8D8F]">
                                <i class="fa-regular fa-calendar mr-1 text-[10px]"></i>
                                <template v-if="historiqueComplet">Historique complet</template>
                                <template v-else>Période : {{ libellePeriode }}</template>
                                · {{ pluriel(interactionsModal.length, 'échange') }}
                            </span>
                            <button
                                v-if="clientModal.toutes.length !== clientModal.interactions.length || historiqueComplet"
                                type="button"
                                @click="historiqueComplet = !historiqueComplet"
                                class="font-bold text-[#E11D48] underline-offset-2 transition hover:underline"
                            >
                                {{ historiqueComplet ? 'Revenir à la période' : `Voir tout l'historique (${clientModal.toutes.length})` }}
                            </button>
                        </div>

                        <!-- Frise des interactions -->
                        <div class="flex-1 overflow-y-auto p-5">
                            <ol v-if="interactionsModal.length" class="relative ml-4 space-y-6 border-l border-gray-200">
                                <li
                                    v-for="(interaction, iIdx) in interactionsModal"
                                    :key="interaction.id"
                                    class="relative pl-6"
                                >
                                    <span
                                        class="absolute -left-3 top-0 flex h-6 w-6 items-center justify-center rounded-full text-[10px] ring-1"
                                        :class="iIdx === 0 ? 'bg-[#0B0F19] text-white ring-[#0B0F19]' : 'bg-white text-[#0B0F19] ring-gray-200'"
                                        :title="labelType(interaction.type)"
                                    >
                                        <i :class="iconeType(interaction.type)"></i>
                                    </span>

                                    <div class="flex flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-3">
                                        <p class="text-xs font-bold text-[#0B0F19]">
                                            <span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-[#8A8D8F]">{{ labelType(interaction.type) }}</span>
                                            {{ interaction.objet || 'Sans objet' }}
                                        </p>
                                        <span class="shrink-0 text-[11px] font-medium text-[#8A8D8F]">
                                            {{ quand(new Date(interaction.created_at)) }}
                                        </span>
                                    </div>

                                    <p v-if="interaction.vehicule" class="mt-0.5 text-[11px] font-semibold text-gray-500">
                                        <i class="fa-solid fa-car mr-1 text-[10px]"></i>
                                        {{ interaction.vehicule.marque }} {{ interaction.vehicule.modele }} ({{ interaction.vehicule.immatriculation }})
                                    </p>

                                    <p v-if="interaction.notes" class="mt-1.5 whitespace-pre-line text-xs leading-relaxed text-gray-700">
                                        {{ interaction.notes }}
                                    </p>

                                    <div v-if="interaction.accord_convenu" class="mt-2 border-l-2 border-[#E11D48] pl-2">
                                        <p class="text-[11px] font-bold text-[#0B0F19]">Accord convenu</p>
                                        <p class="whitespace-pre-line text-xs text-gray-700">{{ interaction.accord_convenu }}</p>
                                    </div>

                                    <p
                                        v-if="interaction.date_relance_prevue && lireDate(interaction.date_relance_prevue)"
                                        class="mt-2 flex items-center gap-1.5 text-[11px] font-semibold text-gray-500"
                                    >
                                        <i class="fa-regular fa-calendar text-[10px]"></i>
                                        Relance prévue le {{ formatDateLongue(lireDate(interaction.date_relance_prevue)) }}
                                    </p>
                                </li>
                            </ol>

                            <div v-else class="py-10 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8FAFC] text-[#8A8D8F] ring-1 ring-gray-200">
                                    <i class="fa-regular fa-comment-dots"></i>
                                </div>
                                <h4 class="text-sm font-extrabold text-[#0B0F19]">
                                    {{ filtreActif ? 'Aucun échange sur cette période' : 'Aucune interaction enregistrée' }}
                                </h4>
                                <p class="mx-auto mt-1 max-w-xs text-xs font-medium leading-relaxed text-[#8A8D8F]">
                                    <template v-if="filtreActif && clientModal.toutes.length">
                                        Ce client a {{ pluriel(clientModal.toutes.length, 'échange') }} en dehors de la période choisie.
                                    </template>
                                    <template v-else>
                                        {{ chargeActif.name }} n'a encore enregistré aucun échange avec ce client.
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 180ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 220ms cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-active .modal-panel,
.modal-leave-active .modal-panel {
    transition: transform 300ms cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-from .modal-panel,
.modal-leave-to .modal-panel {
    transform: translateY(2.5rem);
}
@media (min-width: 640px) {
    .modal-enter-from .modal-panel,
    .modal-leave-to .modal-panel {
        transform: translateY(0.5rem) scale(0.97);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fade-enter-active,
    .fade-leave-active,
    .modal-enter-active,
    .modal-leave-active,
    .modal-enter-active .modal-panel,
    .modal-leave-active .modal-panel {
        transition: none;
    }
}
</style>