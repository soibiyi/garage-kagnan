<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { FAMILLES, FAMILLE_PAR_DEFAUT } from '@/constants/familles.js';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';

const props = defineProps({
    dossiers: { type: Array, default: () => [] },
    // Optionnel : à envoyer depuis le contrôleur si l'admin peut filtrer par siège
    siegeFiltre: { type: String, default: undefined },
});

/* ------------------------------------------------------------------ */
/* Formats                                                             */
/* ------------------------------------------------------------------ */
const fcfa = (n) => `${Math.round(Number(n) || 0).toLocaleString('fr-FR')} FCFA`;
const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

const formatDate = (d) =>
    d ? d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const formatDateLongue = (d) =>
    d ? d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';

const debutJour = (valeur) => {
    const d = new Date(valeur);
    d.setHours(0, 0, 0, 0);
    return d;
};

const lireDate = (valeur) => {
    if (!valeur) return null;
    const [a, m, j] = String(valeur).slice(0, 10).split('-').map(Number);
    if (!a || !m || !j) return null;
    return new Date(a, m - 1, j);
};

const normaliser = (texte) =>
    String(texte || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

/* ------------------------------------------------------------------ */
/* Statuts                                                             */
/* ------------------------------------------------------------------ */
const STATUTS = {
    accepte: {
        label: 'Accepté',
        icone: 'fa-solid fa-circle-check',
        classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    },
    partiel: {
        label: 'Partiellement accepté',
        icone: 'fa-solid fa-circle-half-stroke',
        classes: 'bg-amber-50 text-amber-700 border-amber-200',
    },
    refuse: {
        label: 'Refusé',
        icone: 'fa-solid fa-circle-xmark',
        classes: 'bg-rose-50 text-rose-700 border-rose-200',
    },
    attente: {
        label: 'En attente',
        icone: 'fa-regular fa-clock',
        classes: 'bg-slate-100 text-slate-600 border-slate-200',
    },
};

const STATUTS_REFUSES = ['refuse', 'refusé', 'rejete', 'rejeté', 'annule', 'annulé'];

/** Statut réel déduit du statut du devis ET des lignes. */
const statutDe = (dossier) => {
    const brut = normaliser(dossier.devis?.statut);
    const lignes = dossier.devis?.lignes || [];

    if (STATUTS_REFUSES.map(normaliser).includes(brut)) return 'refuse';
    if (brut !== 'accepte') return 'attente';

    const acceptees = lignes.filter((l) => l.is_accepted).length;
    if (!lignes.length || acceptees === lignes.length) return 'accepte';
    return acceptees === 0 ? 'refuse' : 'partiel';
};

/** État d'une ligne : "en attente" tant que le devis n'est pas tranché. */
const etatLigne = (statutDevis, ligne) => {
    if (statutDevis === 'attente') return 'attente';
    return ligne.is_accepted ? 'accepte' : 'refuse';
};

const ETATS_LIGNE = {
    accepte: { label: 'Accepté', icone: 'fa-solid fa-check', classes: 'bg-emerald-100 text-emerald-800' },
    refuse: { label: 'Refusé', icone: 'fa-solid fa-xmark', classes: 'bg-rose-100 text-rose-800' },
    attente: { label: 'En attente', icone: 'fa-regular fa-clock', classes: 'bg-slate-100 text-slate-600' },
};

/* ------------------------------------------------------------------ */
/* Dossiers enrichis (calculés une seule fois)                         */
/* ------------------------------------------------------------------ */
const nomClient = (dossier) => {
    const c = dossier.vehicule?.client;
    const nom = [c?.nom, c?.prenom || c?.name].filter(Boolean).join(' ').trim();
    return nom || 'Client inconnu';
};

const dateDossier = (dossier) => {
    const brut = dossier.devis?.created_at || dossier.created_at;
    if (!brut) return null;
    const d = new Date(brut);
    return isNaN(d) ? null : d;
};

const numeroDossier = (dossier) =>
    dossier.devis?.numero || dossier.numero_ot || dossier.numero || null;

const dossiersEnrichis = computed(() =>
    props.dossiers.map((dossier) => {
        const statut = statutDe(dossier);
        const lignes = dossier.devis?.lignes || [];
        const montant = (l) => Number(l.montant_ht) || 0;

        const propose = lignes.reduce((s, l) => s + montant(l), 0);
        const accepte =
            statut === 'attente' ? 0 : lignes.filter((l) => l.is_accepted).reduce((s, l) => s + montant(l), 0);
        const refuse = statut === 'attente' ? 0 : propose - accepte;

        const client = nomClient(dossier);
        const v = dossier.vehicule || {};

        return {
            id: dossier.id,
            dossier,
            statut,
            date: dateDossier(dossier),
            numero: numeroDossier(dossier),
            client,
            immat: v.immatriculation || '—',
            vehicule: [v.marque, v.modele].filter(Boolean).join(' ') || 'Véhicule inconnu',
            propose,
            accepte,
            refuse,
            nbLignes: lignes.length,
            recherche: normaliser(
                [v.immatriculation, v.marque, v.modele, client, numeroDossier(dossier)].join(' '),
            ),
        };
    }),
);

/* ------------------------------------------------------------------ */
/* Recherche, statut, période                                          */
/* ------------------------------------------------------------------ */
const recherche = ref('');
const filtreStatut = ref('tous');
const periode = ref('tout');
const dateDebut = ref('');
const dateFin = ref('');

const FILTRES_STATUT = [
    { valeur: 'tous', label: 'Tous' },
    { valeur: 'accepte', label: 'Acceptés' },
    { valeur: 'partiel', label: 'Partiels' },
    { valeur: 'refuse', label: 'Refusés' },
    { valeur: 'attente', label: 'En attente' },
];

const PERIODES = [
    { valeur: 'tout', label: 'Tout' },
    { valeur: '7j', label: '7 jours' },
    { valeur: '30j', label: '30 jours' },
    { valeur: 'perso', label: 'Personnalisé' },
];

const bornes = computed(() => {
    const auj = debutJour(new Date());
    if (periode.value === '7j') {
        const d = new Date(auj);
        d.setDate(d.getDate() - 6);
        return { debut: d, fin: null };
    }
    if (periode.value === '30j') {
        const d = new Date(auj);
        d.setDate(d.getDate() - 29);
        return { debut: d, fin: null };
    }
    if (periode.value === 'perso') {
        const fin = lireDate(dateFin.value);
        if (fin) fin.setHours(23, 59, 59, 999);
        return { debut: lireDate(dateDebut.value), fin };
    }
    return { debut: null, fin: null };
});

const periodeInvalide = computed(() => {
    const { debut, fin } = bornes.value;
    return periode.value === 'perso' && debut && fin && debut > fin;
});

const dansPeriode = (date) => {
    if (periode.value === 'tout') return true;
    if (!date) return false;
    const { debut, fin } = bornes.value;
    if (debut && date < debut) return false;
    if (fin && date > fin) return false;
    return true;
};

const filtresActifs = computed(
    () => recherche.value.trim() !== '' || filtreStatut.value !== 'tous' || periode.value !== 'tout',
);

const reinitialiser = () => {
    recherche.value = '';
    filtreStatut.value = 'tous';
    periode.value = 'tout';
    dateDebut.value = '';
    dateFin.value = '';
};

const dossiersFiltres = computed(() => {
    const mots = normaliser(recherche.value).split(/\s+/).filter(Boolean);

    return dossiersEnrichis.value
        .filter((d) => {
            if (filtreStatut.value !== 'tous' && d.statut !== filtreStatut.value) return false;
            if (!dansPeriode(d.date)) return false;
            return mots.every((m) => d.recherche.includes(m));
        })
        .sort((a, b) => (b.date?.getTime() ?? 0) - (a.date?.getTime() ?? 0));
});

/* ------------------------------------------------------------------ */
/* Bandeau de chiffres (sur la sélection filtrée)                      */
/* ------------------------------------------------------------------ */
const kpi = computed(() => {
    const liste = dossiersFiltres.value;
    const traites = liste.filter((d) => d.statut !== 'attente');
    const convertis = traites.filter((d) => d.statut === 'accepte' || d.statut === 'partiel');

    return {
        total: liste.length,
        enAttente: liste.length - traites.length,
        taux: traites.length ? Math.round((convertis.length / traites.length) * 100) : null,
        propose: liste.reduce((s, d) => s + d.propose, 0),
        accepte: liste.reduce((s, d) => s + d.accepte, 0),
    };
});

/* ------------------------------------------------------------------ */
/* Affichage progressif                                                */
/* ------------------------------------------------------------------ */
const PAS = 20;
const nbVisibles = ref(PAS);

watch([recherche, filtreStatut, periode, dateDebut, dateFin], () => {
    nbVisibles.value = PAS;
});

const dossiersVisibles = computed(() => dossiersFiltres.value.slice(0, nbVisibles.value));

/* ------------------------------------------------------------------ */
/* Modal de détail                                                     */
/* ------------------------------------------------------------------ */
const dossierOuvertId = ref(null);
const boutonFermer = ref(null);

const dossierModal = computed(
    () => dossiersEnrichis.value.find((d) => d.id === dossierOuvertId.value) || null,
);

const ouvrir = async (id) => {
    dossierOuvertId.value = id;
    await nextTick();
    boutonFermer.value?.focus();
};

const fermer = () => {
    dossierOuvertId.value = null;
};

watch(dossierModal, (ouvert) => {
    document.body.style.overflow = ouvert ? 'hidden' : '';
});

const surTouche = (e) => {
    if (e.key === 'Escape' && dossierModal.value) fermer();
};

onMounted(() => window.addEventListener('keydown', surTouche));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', surTouche);
    document.body.style.overflow = '';
});

/* Lignes du devis ouvert, groupées par famille avec sous-totaux */
const groupesModal = computed(() => {
    if (!dossierModal.value) return [];
    const { dossier, statut } = dossierModal.value;
    const lignes = dossier.devis?.lignes || [];
    if (!lignes.length) return [];

    const groupes = {};
    lignes.forEach((ligne) => {
        const nom = ligne.famille || FAMILLE_PAR_DEFAUT;
        (groupes[nom] ||= []).push(ligne);
    });

    const ordre = [...Object.keys(FAMILLES), FAMILLE_PAR_DEFAUT];
    const noms = [
        ...ordre.filter((n) => groupes[n]?.length),
        ...Object.keys(groupes).filter((n) => !ordre.includes(n) && groupes[n].length),
    ];

    return noms.map((nom) => {
        const items = groupes[nom];
        return {
            famille: nom,
            lignes: items.map((l) => ({ ...l, etat: etatLigne(statut, l) })),
            total: items.reduce((s, l) => s + (Number(l.montant_ht) || 0), 0),
        };
    });
});
</script>

<template>
    <Head title="Historique Global des Devis" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="flex items-center gap-2 text-xl font-bold tracking-tight text-gray-900">
                        <i class="fa-solid fa-clock-rotate-left text-[#E11D48]"></i>
                        <span>Historique global des devis</span>
                    </h2>
                    <p class="mt-1 text-xs text-gray-500">
                        Tous les devis, avec ce que les clients ont accepté, refusé ou laissé en attente.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <SiegeSwitcher v-if="siegeFiltre !== undefined" :current="siegeFiltre" />
                    <Link
                        :href="route('dashboard')"
                        class="inline-flex min-h-[40px] shrink-0 items-center gap-2 rounded-xl bg-gray-100 px-4 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0B0F19]"
                    >
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        <span>Tableau de bord</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-white py-6 text-gray-900 sm:py-10">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

                <!-- ============ ÉTAT VIDE GLOBAL ============ -->
                <div
                    v-if="!dossiers.length"
                    class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-14 text-center"
                >
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-gray-400 ring-1 ring-gray-200">
                        <i class="fa-solid fa-folder-closed text-lg"></i>
                    </div>
                    <h3 class="text-sm font-extrabold text-gray-900">Aucun historique disponible</h3>
                    <p class="mt-1 text-xs text-gray-500">Il n'y a pas encore de devis enregistré.</p>
                </div>

                <template v-else>

                    <!-- ============ BANDEAU DE CHIFFRES ============ -->
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4">
                            <p class="text-[11px] font-semibold text-[#8A8D8F]">Devis</p>
                            <p class="mt-1 text-2xl font-black text-[#0B0F19]">{{ kpi.total }}</p>
                            <p class="mt-0.5 text-[11px] font-medium text-[#8A8D8F]">dont {{ kpi.enAttente }} en attente</p>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4">
                            <p class="text-[11px] font-semibold text-[#8A8D8F]">Taux d'acceptation</p>
                            <p class="mt-1 text-2xl font-black text-[#0B0F19]">
                                {{ kpi.taux === null ? '—' : `${kpi.taux} %` }}
                            </p>
                            <p class="mt-0.5 text-[11px] font-medium text-[#8A8D8F]">sur les devis tranchés</p>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4">
                            <p class="text-[11px] font-semibold text-[#8A8D8F]">Montant proposé</p>
                            <p class="mt-1 text-lg font-black text-[#0B0F19] sm:text-xl">{{ fcfa(kpi.propose) }}</p>
                            <p class="mt-0.5 text-[11px] font-medium text-[#8A8D8F]">HT</p>
                        </div>
                        <div class="rounded-2xl border border-[#0B0F19] bg-[#0B0F19] p-4">
                            <p class="text-[11px] font-semibold text-gray-400">Montant accepté</p>
                            <p class="mt-1 text-lg font-black text-white sm:text-xl">{{ fcfa(kpi.accepte) }}</p>
                            <p class="mt-0.5 text-[11px] font-medium text-gray-400">HT</p>
                        </div>
                    </div>

                    <!-- ============ RECHERCHE + FILTRES ============ -->
                    <div class="space-y-3 rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input
                                v-model="recherche"
                                type="text"
                                placeholder="Rechercher par immatriculation, véhicule, client ou n° de devis..."
                                class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-10 text-xs font-medium text-[#0B0F19] transition focus:border-[#0B0F19] focus:outline-none focus:ring-1 focus:ring-[#0B0F19]"
                            />
                            <button
                                v-if="recherche"
                                type="button"
                                aria-label="Effacer la recherche"
                                @click="recherche = ''"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 transition hover:text-gray-600"
                            >
                                <i class="fa-solid fa-circle-xmark text-xs"></i>
                            </button>
                        </div>

                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Statut">
                                <button
                                    v-for="s in FILTRES_STATUT"
                                    :key="s.valeur"
                                    type="button"
                                    :aria-pressed="filtreStatut === s.valeur"
                                    @click="filtreStatut = s.valeur"
                                    class="inline-flex min-h-[36px] shrink-0 items-center rounded-full border px-3.5 text-xs font-bold transition active:scale-95"
                                    :class="filtreStatut === s.valeur
                                        ? 'border-[#0B0F19] bg-[#0B0F19] text-white'
                                        : 'border-gray-200 bg-white text-[#0B0F19] hover:bg-gray-100'"
                                >
                                    {{ s.label }}
                                </button>
                            </div>

                            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Période">
                                <button
                                    v-for="p in PERIODES"
                                    :key="p.valeur"
                                    type="button"
                                    :aria-pressed="periode === p.valeur"
                                    @click="periode = p.valeur"
                                    class="inline-flex min-h-[36px] shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-xs font-bold transition active:scale-95"
                                    :class="periode === p.valeur
                                        ? 'border-[#E11D48] bg-[#E11D48] text-white'
                                        : 'border-gray-200 bg-white text-[#0B0F19] hover:bg-gray-100'"
                                >
                                    <i v-if="p.valeur === 'perso'" class="fa-regular fa-calendar text-[10px]"></i>
                                    {{ p.label }}
                                </button>
                            </div>
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

                        <p v-if="periodeInvalide" class="flex items-center gap-1.5 text-[11px] font-semibold text-[#E11D48]">
                            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                            La date de début doit précéder la date de fin.
                        </p>
                    </div>

                    <!-- ============ LISTE COMPACTE ============ -->
                    <div v-if="dossiersFiltres.length" class="space-y-3">
                        <p class="text-xs font-semibold text-[#8A8D8F]">
                            {{ pluriel(dossiersFiltres.length, 'devis') }}
                            <span v-if="filtresActifs">· filtres actifs</span>
                        </p>

                        <ul class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                            <li v-for="d in dossiersVisibles" :key="d.id">
                                <button
                                    type="button"
                                    @click="ouvrir(d.id)"
                                    class="group grid w-full grid-cols-[1fr_auto] items-center gap-x-4 gap-y-1 px-4 py-4 text-left transition hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none sm:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_auto_auto_auto] sm:px-5"
                                >
                                    <!-- Véhicule + immat -->
                                    <div class="min-w-0">
                                        <p class="font-mono text-[11px] font-bold text-[#E11D48]">{{ d.immat }}</p>
                                        <p class="truncate text-sm font-extrabold text-[#0B0F19]">{{ d.vehicule }}</p>
                                    </div>

                                    <!-- Statut (mobile : à droite du titre) -->
                                    <span
                                        class="inline-flex items-center gap-1.5 justify-self-end whitespace-nowrap rounded-md border px-2.5 py-1 text-[11px] font-semibold sm:order-4"
                                        :class="STATUTS[d.statut].classes"
                                    >
                                        <i :class="STATUTS[d.statut].icone" class="text-[10px]"></i>
                                        {{ STATUTS[d.statut].label }}
                                    </span>

                                    <!-- Client + date -->
                                    <div class="col-span-2 min-w-0 sm:order-2 sm:col-span-1">
                                        <p class="truncate text-xs font-semibold text-gray-700">{{ d.client }}</p>
                                        <p class="text-[11px] font-medium text-[#8A8D8F]">
                                            {{ formatDate(d.date) }}<span v-if="d.numero"> · n° {{ d.numero }}</span>
                                        </p>
                                    </div>

                                    <!-- Montant -->
                                    <div class="col-span-2 text-left sm:order-3 sm:col-span-1 sm:text-right">
                                        <p class="text-sm font-black text-[#0B0F19]">{{ fcfa(d.propose) }}</p>
                                        <p v-if="d.statut === 'partiel'" class="text-[11px] font-medium text-emerald-700">
                                            dont {{ fcfa(d.accepte) }} accepté
                                        </p>
                                    </div>

                                    <i class="fa-solid fa-chevron-right hidden text-xs text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-[#0B0F19] sm:order-5 sm:block"></i>
                                </button>
                            </li>
                        </ul>

                        <div v-if="dossiersFiltres.length > nbVisibles" class="text-center">
                            <button
                                type="button"
                                @click="nbVisibles += PAS"
                                class="inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 text-xs font-bold text-[#0B0F19] transition hover:bg-gray-100 active:scale-95"
                            >
                                <i class="fa-solid fa-arrow-down text-[10px]"></i>
                                Afficher plus ({{ dossiersFiltres.length - nbVisibles }} restants)
                            </button>
                        </div>
                    </div>

                    <!-- ============ AUCUN RÉSULTAT ============ -->
                    <div v-else class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-12 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucun devis ne correspond</h4>
                        <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                            Essayez un autre mot-clé, un autre statut ou élargissez la période.
                        </p>
                        <button
                            type="button"
                            @click="reinitialiser"
                            class="mt-4 inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-xs font-bold text-[#0B0F19] transition hover:bg-gray-100 active:scale-95"
                        >
                            <i class="fa-solid fa-rotate-left text-[10px]"></i>
                            Réinitialiser les filtres
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- ============ MODAL : DÉTAIL DU DEVIS ============ -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="dossierModal"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-[#0B0F19]/60 sm:items-center sm:p-6"
                    @click.self="fermer"
                >
                    <div
                        class="modal-panel flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-h-[88vh] sm:rounded-2xl"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="modal-devis-titre"
                    >
                        <!-- En-tête -->
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 bg-[#F8FAFC] p-5">
                            <div class="min-w-0">
                                <p class="font-mono text-[11px] font-bold text-[#E11D48]">{{ dossierModal.immat }}</p>
                                <h3 id="modal-devis-titre" class="truncate text-base font-black text-[#0B0F19]">
                                    {{ dossierModal.vehicule }}
                                </h3>
                                <p class="mt-1 text-xs font-semibold text-gray-700">
                                    <i class="fa-solid fa-user mr-1 text-[10px] text-[#8A8D8F]"></i>{{ dossierModal.client }}
                                </p>
                                <p class="mt-1 text-[11px] font-medium text-[#8A8D8F]">
                                    {{ formatDateLongue(dossierModal.date) }}
                                    <span v-if="dossierModal.numero"> · n° {{ dossierModal.numero }}</span>
                                </p>
                            </div>

                            <div class="flex shrink-0 items-start gap-2">
                                <span
                                    class="hidden items-center gap-1.5 whitespace-nowrap rounded-md border px-2.5 py-1 text-[11px] font-semibold sm:inline-flex"
                                    :class="STATUTS[dossierModal.statut].classes"
                                >
                                    <i :class="STATUTS[dossierModal.statut].icone" class="text-[10px]"></i>
                                    {{ STATUTS[dossierModal.statut].label }}
                                </span>
                                <button
                                    ref="boutonFermer"
                                    type="button"
                                    aria-label="Fermer"
                                    @click="fermer"
                                    class="flex h-10 w-10 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-[#0B0F19] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0B0F19]"
                                >
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto">
                            <!-- Statut (mobile) + totaux -->
                            <div class="space-y-3 p-5 pb-3">
                                <span
                                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md border px-2.5 py-1 text-[11px] font-semibold sm:hidden"
                                    :class="STATUTS[dossierModal.statut].classes"
                                >
                                    <i :class="STATUTS[dossierModal.statut].icone" class="text-[10px]"></i>
                                    {{ STATUTS[dossierModal.statut].label }}
                                </span>

                                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                                    <div class="rounded-xl border border-gray-200 bg-[#F8FAFC] p-3">
                                        <p class="text-[10px] font-semibold text-[#8A8D8F]">Proposé</p>
                                        <p class="mt-0.5 text-xs font-black text-[#0B0F19] sm:text-sm">{{ fcfa(dossierModal.propose) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                        <p class="text-[10px] font-semibold text-emerald-700">Accepté</p>
                                        <p class="mt-0.5 text-xs font-black text-emerald-800 sm:text-sm">
                                            {{ dossierModal.statut === 'attente' ? '—' : fcfa(dossierModal.accepte) }}
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                                        <p class="text-[10px] font-semibold text-rose-700">Refusé</p>
                                        <p class="mt-0.5 text-xs font-black text-rose-800 sm:text-sm">
                                            {{ dossierModal.statut === 'attente' ? '—' : fcfa(dossierModal.refuse) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Lignes groupées par famille -->
                            <div v-if="groupesModal.length" class="overflow-x-auto px-5 pb-5">
                                <table class="min-w-full text-left text-xs">
                                    <thead class="text-[10px] text-gray-500">
                                        <tr class="border-b border-gray-200">
                                            <th class="py-2 pr-3 font-semibold">Désignation</th>
                                            <th class="px-3 py-2 text-center font-semibold">Qté</th>
                                            <th class="px-3 py-2 text-right font-semibold">P.U. net</th>
                                            <th class="px-3 py-2 text-right font-semibold">Total HT</th>
                                            <th class="py-2 pl-3 text-center font-semibold">Choix client</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="groupe in groupesModal" :key="groupe.famille">
                                            <tr class="border-y border-slate-200 bg-slate-100/80">
                                                <td colspan="4" class="px-2 py-1.5 text-[11px] font-bold text-slate-800">
                                                    <i class="fa-solid fa-layer-group mr-1.5 text-slate-500"></i>{{ groupe.famille }}
                                                </td>
                                                <td class="px-2 py-1.5 text-right text-[11px] font-bold text-slate-700">
                                                    {{ fcfa(groupe.total) }}
                                                </td>
                                            </tr>
                                            <tr
                                                v-for="ligne in groupe.lignes"
                                                :key="ligne.id"
                                                class="border-b border-gray-100"
                                                :class="ligne.etat === 'refuse' ? 'text-gray-400' : 'text-gray-800'"
                                            >
                                                <td class="py-2.5 pr-3 font-medium">
                                                    {{ ligne.designation }}
                                                    <span v-if="ligne.sous_famille" class="ml-1 text-[10px] font-normal text-gray-500">({{ ligne.sous_famille }})</span>
                                                </td>
                                                <td class="px-3 py-2.5 text-center">{{ ligne.quantite }}</td>
                                                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ fcfa(ligne.pu_net) }}</td>
                                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-bold">{{ fcfa(ligne.montant_ht) }}</td>
                                                <td class="py-2.5 pl-3 text-center">
                                                    <span
                                                        class="inline-flex items-center gap-1 whitespace-nowrap rounded px-2 py-0.5 text-[10px] font-bold"
                                                        :class="ETATS_LIGNE[ligne.etat].classes"
                                                    >
                                                        <i :class="ETATS_LIGNE[ligne.etat].icone"></i>
                                                        {{ ETATS_LIGNE[ligne.etat].label }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div v-else class="px-5 py-10 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8FAFC] text-[#8A8D8F] ring-1 ring-gray-200">
                                    <i class="fa-solid fa-file-circle-question"></i>
                                </div>
                                <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucune ligne dans ce devis</h4>
                                <p class="mt-1 text-xs font-medium text-[#8A8D8F]">Le devis ne contient aucune prestation pour le moment.</p>
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