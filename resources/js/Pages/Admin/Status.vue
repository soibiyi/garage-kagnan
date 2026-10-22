<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faCar,
    faMagnifyingGlass,
    faArrowLeft,
    faClipboardList,
    faXmark,
    faArrowUp,
    faArrowDown,
    faClock,
    faChevronRight,
    faRotateLeft,
    faPhone,
    faEye,
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    vehicules: { type: Array, default: () => [] },
    siegeFiltre: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

/* -------------------------------------------------------------------------- */
/* Statuts : source unique pour les badges, les filtres et le tri              */
/* -------------------------------------------------------------------------- */

// L'ordre définit la priorité d'affichage : ce qui demande une action d'abord
const ORDRE = ['attente_accord', 'reception', 'atelier', 'en_cours', 'accepte', 'livre', 'aucun'];

const STATUTS = {
    attente_accord: { text: 'Attente accord devis', classe: 'bg-[#C8102E]/10 text-[#C8102E] ring-[#C8102E]/30' },
    reception:      { text: 'Sur le parc',          classe: 'bg-blue-50 text-blue-700 ring-blue-200' },
    atelier:        { text: 'En atelier',           classe: 'bg-amber-50 text-amber-700 ring-amber-200' },
    en_cours:       { text: 'En réparation',        classe: 'bg-purple-50 text-purple-700 ring-purple-200' },
    accepte:        { text: 'Devis accepté',        classe: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    livre:          { text: 'Livré',                classe: 'bg-gray-100 text-gray-500 ring-gray-200' },
    aucun:          { text: 'Aucune intervention',  classe: 'bg-gray-50 text-gray-400 ring-gray-200' },
};

/* -------------------------------------------------------------------------- */
/* Lien vers le détail (redirige vers vehiculeinfos)                           */
/* -------------------------------------------------------------------------- */

const lienDetail = (v) => route('charge_client.vehicules.infos', v.id);

/* -------------------------------------------------------------------------- */
/* Recherche et filtre (conservés dans l'URL)                                  */
/* -------------------------------------------------------------------------- */

const lireUrl = () => {
    if (typeof window === 'undefined') return { q: '', statut: 'tous' };
    const p = new URLSearchParams(window.location.search);
    const statut = p.get('statut');
    return {
        q: p.get('q') ?? '',
        statut: statut && statut in STATUTS ? statut : 'tous',
    };
};

const initial = lireUrl();
const search = ref(initial.q);
const filterStatut = ref(initial.statut);

watch([search, filterStatut], ([q, statut]) => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    q.trim() ? url.searchParams.set('q', q.trim()) : url.searchParams.delete('q');
    statut !== 'tous' ? url.searchParams.set('statut', statut) : url.searchParams.delete('statut');
    window.history.replaceState(window.history.state, '', url);
});

const filtreActif = computed(() => search.value.trim() !== '' || filterStatut.value !== 'tous');

const reinitialiser = () => {
    search.value = '';
    filterStatut.value = 'tous';
};

/* -------------------------------------------------------------------------- */
/* Données enrichies                                                           */
/* -------------------------------------------------------------------------- */

const normaliser = (s) =>
    String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

const joursDepuis = (date) => {
    if (!date) return null;
    const t = new Date(date).getTime();
    if (Number.isNaN(t)) return null;
    return Math.max(0, Math.floor((Date.now() - t) / 86400000));
};

const libelleAnciennete = (jours) => {
    if (jours === null) return null;
    if (jours === 0) return "Aujourd'hui";
    return `Depuis ${jours} jour${jours > 1 ? 's' : ''}`;
};

const vehiculesEnrichis = computed(() =>
    props.vehicules.map((v) => {
        const ot = v.interventions?.[0] ?? null;
        const statut = ot && ot.statut in STATUTS ? ot.statut : 'aucun';
        const jours = ot && statut !== 'livre' ? joursDepuis(ot.updated_at ?? ot.created_at) : null;

        return {
            ...v,
            ot,
            statut,
            badge: STATUTS[statut],
            numeroOt: ot ? ot.numero_ot || '#' + ot.id : null,
            jours,
            anciennete: libelleAnciennete(jours),
            nomComplet: v.client ? `${v.client.nom ?? ''} ${v.client.prenom ?? ''}`.trim() : '',
            nomVehicule: `${v.marque ?? ''} ${v.modele ?? ''}`.trim(),
            texte: normaliser([
                v.immatriculation,
                v.marque,
                v.modele,
                v.client?.nom,
                v.client?.prenom,
                v.client?.telephone,
                ot?.numero_ot,
            ].join(' ')),
        };
    })
);

const compteurs = computed(() => {
    const acc = { tous: vehiculesEnrichis.value.length };
    for (const v of vehiculesEnrichis.value) acc[v.statut] = (acc[v.statut] ?? 0) + 1;
    return acc;
});

const pastilles = computed(() =>
    [
        { cle: 'tous', text: 'Tous', n: compteurs.value.tous },
        ...ORDRE.map((cle) => ({ cle, text: STATUTS[cle].text, n: compteurs.value[cle] ?? 0 })),
    ].filter((p) => p.cle === 'tous' || p.n > 0 || p.cle === filterStatut.value)
);

/* -------------------------------------------------------------------------- */
/* Tri                                                                         */
/* -------------------------------------------------------------------------- */

const tri = ref({ cle: 'priorite', dir: 'asc' });

const comparer = (a, b) => String(a ?? '').localeCompare(String(b ?? ''), 'fr', { sensitivity: 'base' });

const comparateurs = {
    priorite: (a, b) =>
        ORDRE.indexOf(a.statut) - ORDRE.indexOf(b.statut) || (b.jours ?? 0) - (a.jours ?? 0),
    vehicule: (a, b) => comparer(a.nomVehicule, b.nomVehicule),
    immatriculation: (a, b) => comparer(a.immatriculation, b.immatriculation),
    proprietaire: (a, b) => comparer(a.nomComplet, b.nomComplet),
};

const trierPar = (cle) => {
    tri.value =
        tri.value.cle === cle
            ? { cle, dir: tri.value.dir === 'asc' ? 'desc' : 'asc' }
            : { cle, dir: 'asc' };
};

const ariaSort = (cle) =>
    tri.value.cle === cle ? (tri.value.dir === 'asc' ? 'ascending' : 'descending') : 'none';

/* -------------------------------------------------------------------------- */
/* Résultat filtré                                                             */
/* -------------------------------------------------------------------------- */

const vehiculesFiltres = computed(() => {
    const termes = normaliser(search.value).split(/\s+/).filter(Boolean);
    const sens = tri.value.dir === 'asc' ? 1 : -1;

    return vehiculesEnrichis.value
        .filter(
            (v) =>
                (filterStatut.value === 'tous' || v.statut === filterStatut.value) &&
                termes.every((t) => v.texte.includes(t))
        )
        .sort((a, b) => sens * comparateurs[tri.value.cle](a, b));
});

const total = computed(() => vehiculesEnrichis.value.length);
const pluriel = (n) => (n > 1 ? 's' : '');
</script>

<template>
    <div class="min-h-screen bg-gray-50 py-8 sm:py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- En-tête -->
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">Statut des véhicules</h1>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <p class="text-sm text-gray-500">Véhicules, propriétaires et avancement des interventions.</p>
                        <span
                            v-if="siegeFiltre"
                            class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-gray-900 text-white"
                        >
                            Siège {{ siegeFiltre }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <SiegeSwitcher v-if="user?.role === 'admin'" :current="siegeFiltre" />

                    <div class="text-sm font-bold bg-white px-4 py-2.5 rounded-xl border border-gray-200 text-gray-700 tabular-nums" aria-live="polite">
                        <template v-if="filtreActif">{{ vehiculesFiltres.length }} sur {{ total }} véhicule{{ pluriel(total) }}</template>
                        <template v-else>{{ total }} véhicule{{ pluriel(total) }}</template>
                    </div>

                    <Link
                        :href="route('dashboard')"
                        class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2.5 rounded-xl bg-white border border-gray-200 text-gray-700 text-sm font-semibold transition hover:bg-gray-100 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40"
                    >
                        <FontAwesomeIcon :icon="faArrowLeft" class="text-xs" />
                        <span>Retour</span>
                    </Link>
                </div>
            </div>

            <!-- Recherche + filtres par statut -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 space-y-4">
                <div class="relative w-full md:max-w-md">
                    <label for="recherche-vehicule" class="sr-only">Rechercher un véhicule</label>
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <FontAwesomeIcon :icon="faMagnifyingGlass" class="text-sm" />
                    </span>
                    <input
                        id="recherche-vehicule"
                        v-model="search"
                        type="text"
                        autocomplete="off"
                        placeholder="Plaque, marque, propriétaire, téléphone, n° d'OT"
                        class="w-full min-h-[44px] pl-10 pr-10 py-2 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#C8102E]/25 focus:border-[#C8102E] transition"
                    />
                    <button
                        v-if="search"
                        type="button"
                        @click="search = ''"
                        aria-label="Effacer la recherche"
                        class="absolute inset-y-0 right-0 px-3.5 flex items-center text-gray-400 hover:text-gray-700 transition focus:outline-none focus-visible:text-[#C8102E]"
                    >
                        <FontAwesomeIcon :icon="faXmark" />
                    </button>
                </div>

                <!-- Pastilles avec compteurs -->
                <div class="-mx-4 px-4 overflow-x-auto">
                    <div class="flex gap-2 w-max pb-1" role="group" aria-label="Filtrer par statut">
                        <button
                            v-for="p in pastilles"
                            :key="p.cle"
                            type="button"
                            @click="filterStatut = p.cle"
                            :aria-pressed="filterStatut === p.cle"
                            class="inline-flex items-center gap-2 min-h-[40px] px-3.5 py-1.5 rounded-full text-sm font-semibold border transition active:scale-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40"
                            :class="filterStatut === p.cle
                                ? 'bg-gray-900 text-white border-gray-900'
                                : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'"
                        >
                            <span>{{ p.text }}</span>
                            <span
                                class="text-xs font-bold px-1.5 py-0.5 rounded-full tabular-nums"
                                :class="filterStatut === p.cle
                                    ? 'bg-white/20 text-white'
                                    : p.cle === 'attente_accord' && p.n > 0
                                        ? 'bg-[#C8102E] text-white'
                                        : 'bg-gray-100 text-gray-600'"
                            >{{ p.n }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Aucun véhicule du tout -->
            <div
                v-if="total === 0"
                class="bg-white px-6 py-16 rounded-2xl border border-gray-200 text-center"
            >
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                    <FontAwesomeIcon :icon="faCar" class="text-xl text-gray-400" />
                </div>
                <p class="text-base font-extrabold text-gray-900">Aucun véhicule enregistré</p>
                <p class="mt-1 text-sm text-gray-500">Les véhicules apparaîtront ici dès leur première réception.</p>
            </div>

            <!-- Aucun résultat pour les filtres -->
            <div
                v-else-if="vehiculesFiltres.length === 0"
                class="bg-white px-6 py-14 rounded-2xl border border-gray-200 text-center"
            >
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                    <FontAwesomeIcon :icon="faClipboardList" class="text-xl text-gray-400" />
                </div>
                <p class="text-base font-extrabold text-gray-900">Aucun véhicule ne correspond</p>
                <p class="mt-1 text-sm text-gray-500">Essayez un autre mot-clé ou un autre statut.</p>
                <button
                    type="button"
                    @click="reinitialiser"
                    class="mt-5 inline-flex items-center gap-2 min-h-[44px] px-4 py-2.5 rounded-xl bg-gray-900 text-white text-sm font-semibold transition hover:bg-gray-800 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/50"
                >
                    <FontAwesomeIcon :icon="faRotateLeft" class="text-xs" />
                    Réinitialiser les filtres
                </button>
            </div>

            <template v-else>
                <!-- Mobile : cartes -->
                <ul class="md:hidden space-y-3">
                    <li
                        v-for="v in vehiculesFiltres"
                        :key="v.id"
                        class="bg-white rounded-xl border border-gray-200 p-4 space-y-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <span
                                v-if="v.immatriculation"
                                class="inline-block font-mono font-bold uppercase tracking-wider text-sm px-2 py-0.5 rounded-md border-2 border-gray-800 text-gray-900 bg-white"
                            >{{ v.immatriculation }}</span>
                            <span v-else class="text-sm italic text-gray-400">Plaque non renseignée</span>

                            <span
                                class="inline-flex px-2.5 py-1 text-xs font-bold rounded-lg ring-1 ring-inset text-right"
                                :class="v.badge.classe"
                            >{{ v.badge.text }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2 text-gray-900">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 shrink-0">
                                    <FontAwesomeIcon :icon="faCar" class="text-sm" />
                                </span>
                                <p class="font-bold text-base">{{ v.nomVehicule || 'Véhicule sans nom' }}</p>
                            </div>
                        </div>

                        <div class="text-sm">
                            <p v-if="v.client" class="font-semibold text-gray-900">{{ v.nomComplet }}</p>
                            <p v-else class="italic text-gray-400">Aucun propriétaire</p>
                            <p v-if="v.client?.telephone" class="mt-0.5 flex items-center gap-1.5 text-gray-500">
                                <FontAwesomeIcon :icon="faPhone" class="text-xs" />
                                <a :href="'tel:' + v.client.telephone" class="hover:text-[#C8102E] transition">{{ v.client.telephone }}</a>
                            </p>
                        </div>

                        <div class="pt-3 border-t border-gray-100 flex items-center justify-between gap-3 text-sm">
                            <span class="font-mono font-bold" :class="v.numeroOt ? 'text-[#C8102E]' : 'text-gray-400 font-normal'">
                                {{ v.numeroOt || 'Aucun OT' }}
                            </span>
                            
                            <!-- Bouton Consulter pour mobile -->
                            <Link
                                :href="lienDetail(v)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-900 text-white text-xs font-bold transition hover:bg-gray-800 active:scale-95"
                            >
                                <FontAwesomeIcon :icon="faEye" class="text-[10px]" />
                                <span>Consulter</span>
                            </Link>
                        </div>
                    </li>
                </ul>

                <!-- Tablette et bureau : tableau -->
                <div class="hidden md:block bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-500">
                                <tr>
                                    <th scope="col" class="py-3.5 px-4" :aria-sort="ariaSort('vehicule')">
                                        <button type="button" @click="trierPar('vehicule')" class="inline-flex items-center gap-1.5 hover:text-gray-900 transition focus:outline-none focus-visible:text-[#C8102E]">
                                            Véhicule
                                            <FontAwesomeIcon v-if="tri.cle === 'vehicule'" :icon="tri.dir === 'asc' ? faArrowUp : faArrowDown" class="text-[10px] text-[#C8102E]" />
                                        </button>
                                    </th>
                                    <th scope="col" class="py-3.5 px-4" :aria-sort="ariaSort('immatriculation')">
                                        <button type="button" @click="trierPar('immatriculation')" class="inline-flex items-center gap-1.5 hover:text-gray-900 transition focus:outline-none focus-visible:text-[#C8102E]">
                                            Immatriculation
                                            <FontAwesomeIcon v-if="tri.cle === 'immatriculation'" :icon="tri.dir === 'asc' ? faArrowUp : faArrowDown" class="text-[10px] text-[#C8102E]" />
                                        </button>
                                    </th>
                                    <th scope="col" class="py-3.5 px-4" :aria-sort="ariaSort('proprietaire')">
                                        <button type="button" @click="trierPar('proprietaire')" class="inline-flex items-center gap-1.5 hover:text-gray-900 transition focus:outline-none focus-visible:text-[#C8102E]">
                                            Propriétaire
                                            <FontAwesomeIcon v-if="tri.cle === 'proprietaire'" :icon="tri.dir === 'asc' ? faArrowUp : faArrowDown" class="text-[10px] text-[#C8102E]" />
                                        </button>
                                    </th>
                                    <th scope="col" class="py-3.5 px-4">Dernier OT</th>
                                    <th scope="col" class="py-3.5 px-4" :aria-sort="ariaSort('priorite')">
                                        <button type="button" @click="trierPar('priorite')" class="inline-flex items-center gap-1.5 hover:text-gray-900 transition focus:outline-none focus-visible:text-[#C8102E]">
                                            Statut
                                            <FontAwesomeIcon v-if="tri.cle === 'priorite'" :icon="tri.dir === 'asc' ? faArrowUp : faArrowDown" class="text-[10px] text-[#C8102E]" />
                                        </button>
                                    </th>
                                    <th scope="col" class="py-3.5 px-4 text-right">Actions</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="v in vehiculesFiltres" :key="v.id" class="hover:bg-gray-50/80 transition-colors">
                                    <!-- Véhicule -->
                                    <td class="py-4 px-4 font-bold text-gray-900">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 shrink-0">
                                                <FontAwesomeIcon :icon="faCar" class="text-sm" />
                                            </span>
                                            <span>{{ v.nomVehicule || 'Véhicule sans nom' }}</span>
                                        </div>
                                    </td>

                                    <!-- Immatriculation -->
                                    <td class="py-4 px-4">
                                        <span
                                            v-if="v.immatriculation"
                                            class="inline-block font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border-2 border-gray-800 text-gray-900 bg-white"
                                        >{{ v.immatriculation }}</span>
                                        <span v-else class="italic text-gray-400">Non renseignée</span>
                                    </td>

                                    <!-- Propriétaire -->
                                    <td class="py-4 px-4">
                                        <div v-if="v.client" class="flex flex-col">
                                            <span class="font-bold text-gray-900">{{ v.nomComplet }}</span>
                                            <a
                                                v-if="v.client.telephone"
                                                :href="'tel:' + v.client.telephone"
                                                class="text-xs text-gray-500 hover:text-[#C8102E] transition"
                                            >{{ v.client.telephone }}</a>
                                            <span v-else class="text-xs text-gray-400">Sans téléphone</span>
                                        </div>
                                        <span v-else class="italic text-gray-400">Aucun propriétaire</span>
                                    </td>

                                    <!-- Dernier OT -->
                                    <td class="py-4 px-4 font-mono">
                                        <span v-if="v.numeroOt" class="font-bold text-[#C8102E]">{{ v.numeroOt }}</span>
                                        <span v-else class="text-gray-400">Aucun OT</span>
                                    </td>

                                    <!-- Statut -->
                                    <td class="py-4 px-4">
                                        <span
                                            class="inline-flex px-2.5 py-1 text-xs font-bold rounded-lg ring-1 ring-inset"
                                            :class="v.badge.classe"
                                        >{{ v.badge.text }}</span>
                                        <p v-if="v.anciennete" class="mt-1.5 flex items-center gap-1.5 text-xs text-gray-500">
                                            <FontAwesomeIcon :icon="faClock" class="text-[10px]" />
                                            {{ v.anciennete }}
                                        </p>
                                    </td>

                                    <!-- Actions (Bouton Consulter) -->
                                    <td class="py-4 px-4 text-right">
                                        <Link
                                            :href="lienDetail(v)"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gray-900 text-white text-xs font-bold transition hover:bg-gray-800 active:scale-95 shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40"
                                        >
                                            <FontAwesomeIcon :icon="faEye" class="text-xs" />
                                            <span>Consulter</span>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>