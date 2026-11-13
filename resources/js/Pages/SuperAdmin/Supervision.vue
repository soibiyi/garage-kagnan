<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    utilisateurs: { type: Array, default: () => [] },
    logsInitiaux: { type: Array, default: () => [] },
    selectionInitiale: { type: Number, default: null },
    roleInitial: { type: String, default: null },
});

const ROLES = {
    superadmin: { label: 'Super administrateur', classe: 'bg-[#0B0F19] text-white' },
    admin: { label: 'Administrateur', classe: 'bg-gray-900/90 text-white' },
    receptionniste: { label: 'Réceptionniste', classe: 'bg-blue-50 text-blue-700' },
    mecanicien: { label: 'Mécanicien', classe: 'bg-amber-50 text-amber-800' },
    administratif: { label: 'Administratif', classe: 'bg-purple-50 text-purple-700' },
    charge_client: { label: 'Chargé client', classe: 'bg-rose-50 text-rose-700' },
    secretaire: { label: 'Secrétaire', classe: 'bg-teal-50 text-teal-700' },
    magasinier: { label: 'Magasinier', classe: 'bg-orange-50 text-orange-700' },
    chauffeur: { label: 'Chauffeur', classe: 'bg-sky-50 text-sky-700' },
    depanneur: { label: 'Dépanneur', classe: 'bg-sky-50 text-sky-700' },
    depaneur: { label: 'Dépanneur', classe: 'bg-sky-50 text-sky-700' },
};
const METHODES = {
    GET: 'bg-gray-100 text-gray-600',
    POST: 'bg-emerald-100 text-emerald-800',
    PUT: 'bg-amber-100 text-amber-800',
    PATCH: 'bg-amber-100 text-amber-800',
    DELETE: 'bg-red-100 text-red-700',
};

// ─── Horloge (temps relatifs) ───────────────────────────────────────
const maintenant = ref(Date.now());
const ilYa = (date) => {
    if (!date) return 'jamais';
    const s = Math.max(0, Math.round((maintenant.value - new Date(date).getTime()) / 1000));
    if (s < 10) return "à l'instant";
    if (s < 60) return `il y a ${s} s`;
    if (s < 3600) return `il y a ${Math.floor(s / 60)} min`;
    if (s < 86400) return `il y a ${Math.floor(s / 3600)} h`;
    return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
};
const heure = (date) => new Date(date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
const initiales = (nom = '') => nom.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase() || '?';

// ─── Utilisateurs (présence, rafraîchie toutes les 5 s) ─────────────
const utilisateurs = ref(props.utilisateurs);
const recherche = ref('');
const filtreRole = ref(props.roleInitial === 'chauffeur' ? 'chauffeur' : 'tous');
const seulementEnLigne = ref(false);

const normaliser = (v = '') => String(v).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const utilisateursFiltres = computed(() => {
    const q = normaliser(recherche.value.trim());
    return utilisateurs.value.filter((u) =>
        (filtreRole.value === 'tous' || u.role === filtreRole.value || (filtreRole.value === 'chauffeur' && ['chauffeur', 'depanneur', 'depaneur'].includes(u.role)))
        && (!seulementEnLigne.value || u.en_ligne)
        && (!q || normaliser(u.name).includes(q) || normaliser(u.email).includes(q))
    );
});
const rolesPresents = computed(() => [...new Set(utilisateurs.value.map((u) => u.role))]);
const stats = computed(() => ({
    enLigne: utilisateurs.value.filter((u) => u.en_ligne).length,
    actifsAujourdhui: utilisateurs.value.filter((u) => u.last_seen_at && new Date(u.last_seen_at).toDateString() === new Date().toDateString()).length,
    actions: utilisateurs.value.reduce((t, u) => t + u.actions_du_jour, 0),
}));

// ─── Sélection + fil d'activité en direct ───────────────────────────
const selectionId = ref(props.selectionInitiale);
const selection = computed(() => utilisateurs.value.find((u) => u.id === selectionId.value) ?? null);
const logs = ref(props.logsInitiaux);
const nouveaux = ref(new Set());
const chargement = ref(false);
const plusAnciensDispo = ref(props.logsInitiaux.length >= 60);
const enDirect = ref(true);
const detailOuvert = ref(new Set());

const params = (extra = {}) => ({ ...(selectionId.value ? { user: selectionId.value } : {}), ...extra });

async function chargerFlux() {
    chargement.value = true;
    logs.value = [];
    try {
        const { data } = await axios.get(route('superadmin.supervision.flux'), { params: params() });
        logs.value = data.logs;
        plusAnciensDispo.value = data.logs.length >= 60;
    } finally {
        chargement.value = false;
    }
}

let enVol = false;
async function rafraichir() {
    if (document.hidden || enVol || chargement.value) return;
    enVol = true;
    const dernier = logs.value[0]?.id ?? 0;
    const pourQui = selectionId.value;
    try {
        const { data } = await axios.get(route('superadmin.supervision.direct'), { params: params({ after: dernier }) });
        utilisateurs.value = data.utilisateurs;
        // Fil ignoré si la sélection a changé pendant l'appel, ou si le direct est en pause
        if (enDirect.value && pourQui === selectionId.value && data.logs.length) {
            const connus = new Set(logs.value.map((l) => l.id));
            const frais = data.logs.filter((l) => !connus.has(l.id));
            logs.value = [...frais, ...logs.value].slice(0, 500);
            frais.forEach((l) => nouveaux.value.add(l.id));
            window.setTimeout(() => frais.forEach((l) => nouveaux.value.delete(l.id)), 2500);
        }
    } catch { /* nouvel essai */ } finally {
        enVol = false;
    }
}

async function plusAnciens() {
    const plusVieux = logs.value.at(-1)?.id;
    if (!plusVieux) return;
    const { data } = await axios.get(route('superadmin.supervision.flux'), { params: params({ before: plusVieux }) });
    logs.value = [...logs.value, ...data.logs];
    plusAnciensDispo.value = data.logs.length >= 60;
}

function choisir(id) {
    selectionId.value = id;
    detailOuvert.value = new Set();
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('user', id); else url.searchParams.delete('user');
    window.history.replaceState(window.history.state, '', url);
    document.getElementById('flux')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
watch(selectionId, chargerFlux);

const basculerDetail = (id) => (detailOuvert.value.has(id) ? detailOuvert.value.delete(id) : detailOuvert.value.add(id));

const voirEnTantQue = (u) => router.post(route('superadmin.impersonate', u.id));
const peutImpersonner = (u) => !['admin', 'superadmin'].includes(u.role);

const timers = [];
onMounted(() => {
    // Toutes les 4 s : un seul appel pour la présence ET les nouvelles actions
    timers.push(window.setInterval(rafraichir, 4000));
    timers.push(window.setInterval(() => { maintenant.value = Date.now(); }, 5000));
});
onBeforeUnmount(() => timers.forEach((t) => window.clearInterval(t)));

const valeur = (v) => (typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v ?? '—'));
</script>

<template>
    <Head title="Supervision en direct" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <!-- ═══ EN-TÊTE ═══ -->
                <header class="sg-glass sg-glass-dark sg-rise overflow-hidden p-5 sm:p-7">
                    <span class="absolute left-0 top-0 h-1 w-20 rounded-br-full bg-[#E11D48]"></span>
                    <Link :href="route('superadmin.accueil')" class="sg-retour">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Accueil super admin
                    </Link>
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="sg-tile sg-tile-red h-12 w-12 text-lg">
                                <i class="fa-solid fa-satellite-dish" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-white/50">Super administrateur</p>
                                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Supervision en direct</h1>
                                <p class="mt-0.5 text-sm text-white/60">Cliquez sur un collaborateur pour suivre tout ce qu'il fait, en temps réel.</p>
                            </div>
                        </div>
                        <dl class="grid grid-cols-3 gap-2 sm:gap-3">
                            <div class="rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10 sm:px-5">
                                <dt class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-white/50">
                                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400" aria-hidden="true"></span>En ligne
                                </dt>
                                <dd class="sg-stat-value mt-1 text-emerald-300">{{ stats.enLigne }}</dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10 sm:px-5">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-white/50">Actifs auj.</dt>
                                <dd class="sg-stat-value mt-1">{{ stats.actifsAujourdhui }}</dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10 sm:px-5">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-white/50">Actions auj.</dt>
                                <dd class="sg-stat-value mt-1">{{ stats.actions }}</dd>
                            </div>
                        </dl>
                    </div>
                </header>

                <div class="sv-grid">
                    <!-- ═══ UTILISATEURS ═══ -->
                    <aside class="sg-rise min-w-0 space-y-3" style="--d: 80ms">
                        <div class="sg-glass space-y-3 p-3">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-400" aria-hidden="true"></i>
                                <label for="sv-recherche" class="sr-only">Rechercher un collaborateur</label>
                                <input id="sv-recherche" v-model="recherche" type="search" placeholder="Rechercher un collaborateur" class="sg-field py-2 pl-9" />
                            </div>
                            <div class="flex items-center gap-2">
                                <label for="sv-role" class="sr-only">Filtrer par rôle</label>
                                <select id="sv-role" v-model="filtreRole" class="sg-field min-w-0 flex-1 py-1.5 !text-xs">
                                    <option value="tous">Tous les rôles</option>
                                    <option v-for="r in rolesPresents" :key="r" :value="r">{{ ROLES[r]?.label || r }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="sg-pill shrink-0 !min-h-[34px]"
                                    :class="{ 'sg-pill-active': seulementEnLigne }"
                                    :aria-pressed="seulementEnLigne"
                                    @click="seulementEnLigne = !seulementEnLigne"
                                >En ligne</button>
                            </div>

                            <ul class="sv-liste sg-scroll space-y-1 overflow-y-auto">
                                <li>
                                    <button type="button" class="sg-person" :class="{ 'sg-person-active': selectionId === null }" @click="choisir(null)">
                                        <span class="sg-tile sg-tile-ink h-10 w-10"><i class="fa-solid fa-wave-square text-sm" aria-hidden="true"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-[#0B0F19]">Toute l'activité</span>
                                            <span class="block text-xs text-gray-500">Tous les collaborateurs</span>
                                        </span>
                                    </button>
                                </li>
                                <li v-for="u in utilisateursFiltres" :key="u.id">
                                    <button
                                        type="button"
                                        class="sg-person"
                                        :class="{ 'sg-person-active': selectionId === u.id }"
                                        :aria-current="selectionId === u.id ? 'true' : undefined"
                                        @click="choisir(u.id)"
                                    >
                                        <span class="relative">
                                            <span class="sg-avatar">{{ initiales(u.name) }}</span>
                                            <span
                                                class="sv-dot"
                                                :class="u.en_ligne ? 'sv-dot-on' : ''"
                                                :title="u.en_ligne ? 'En ligne' : 'Hors ligne'"
                                            ></span>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-1.5">
                                                <span class="truncate text-sm font-bold text-[#0B0F19]">{{ u.name }}</span>
                                                <span v-if="u.actions_du_jour" class="sg-chip shrink-0 bg-emerald-50 !px-1.5 text-[10px] text-emerald-700" :title="`${u.actions_du_jour} action(s) aujourd'hui`">{{ u.actions_du_jour }}</span>
                                            </span>
                                            <span class="block truncate text-[11px] text-gray-500">
                                                {{ ROLES[u.role]?.label || u.role }}<template v-if="u.siege"> · {{ u.siege }}</template>
                                            </span>
                                            <span class="block truncate text-[11px]" :class="u.en_ligne ? 'font-semibold text-emerald-700' : 'text-gray-400'">
                                                {{ u.derniere_activite?.description || 'Aucune activité' }}
                                                <template v-if="u.last_seen_at"> · {{ ilYa(u.last_seen_at) }}</template>
                                            </span>
                                        </span>
                                    </button>
                                </li>
                                <li v-if="!utilisateursFiltres.length" class="px-3 py-6 text-center text-sm text-gray-500">Aucun collaborateur.</li>
                            </ul>
                        </div>
                    </aside>

                    <!-- ═══ FIL D'ACTIVITÉ ═══ -->
                    <section id="flux" class="sg-rise min-w-0 scroll-mt-4 space-y-4" style="--d: 140ms">
                        <!-- Fiche de la personne suivie -->
                        <div class="sg-glass sg-glass-strong flex flex-wrap items-center gap-4 p-4 sm:p-5">
                            <template v-if="selection">
                                <span class="relative">
                                    <span class="sg-avatar sv-avatar-lg">{{ initiales(selection.name) }}</span>
                                    <span class="sv-dot sv-dot-lg" :class="selection.en_ligne ? 'sv-dot-on animate-pulse' : ''"></span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-lg font-black tracking-tight text-[#0B0F19]">{{ selection.name }}</h2>
                                        <span class="sg-chip" :class="ROLES[selection.role]?.classe">{{ ROLES[selection.role]?.label || selection.role }}</span>
                                        <span v-if="selection.siege" class="sg-chip bg-white text-gray-600">Siège {{ selection.siege }}</span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-gray-500">{{ selection.email }}</p>
                                    <p class="mt-1 text-xs font-semibold" :class="selection.en_ligne ? 'text-emerald-700' : 'text-gray-500'">
                                        <template v-if="selection.en_ligne">
                                            En ligne · {{ selection.derniere_activite?.description || '—' }}
                                            <code v-if="selection.derniere_activite" class="ml-1 rounded bg-white/80 px-1.5 py-0.5 font-mono text-[10px] text-gray-600">{{ selection.derniere_activite.url }}</code>
                                        </template>
                                        <template v-else>Hors ligne · vu {{ ilYa(selection.last_seen_at) }}</template>
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button v-if="peutImpersonner(selection)" type="button" class="sg-btn sg-btn-sm sg-btn-ink" @click="voirEnTantQue(selection)">
                                        <i class="fa-solid fa-user-secret" aria-hidden="true"></i>Voir en tant que
                                    </button>
                                    <Link :href="route('admin.users.edit', selection.id)" class="sg-btn sg-btn-sm sg-btn-glass">
                                        <i class="fa-solid fa-pen text-[10px]" aria-hidden="true"></i>Compte
                                    </Link>
                                </div>
                            </template>
                            <template v-else>
                                <span class="sg-tile sg-tile-ink h-14 w-14 text-lg"><i class="fa-solid fa-wave-square" aria-hidden="true"></i></span>
                                <div class="flex-1">
                                    <h2 class="text-lg font-black tracking-tight text-[#0B0F19]">Toute l'activité</h2>
                                    <p class="text-xs text-gray-500">Chaque page ouverte et chaque action de tous les collaborateurs.</p>
                                </div>
                            </template>
                        </div>

                        <!-- Contrôles -->
                        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                            <p class="sg-section-title text-gray-500">
                                <i class="fa-solid fa-list-ul" aria-hidden="true"></i>Journal
                                <span class="sg-chip bg-white/80 text-gray-500">{{ logs.length }}</span>
                            </p>
                            <button
                                type="button"
                                class="sg-btn sg-btn-sm"
                                :class="enDirect ? 'sg-btn-green' : 'sg-btn-glass'"
                                :aria-pressed="enDirect"
                                @click="enDirect = !enDirect"
                            >
                                <i class="fa-solid" :class="enDirect ? 'fa-circle fa-beat-fade text-[8px]' : 'fa-pause text-[10px]'" aria-hidden="true"></i>
                                {{ enDirect ? 'En direct' : 'En pause' }}
                            </button>
                        </div>

                        <div class="sg-glass overflow-hidden">
                            <p v-if="chargement && !logs.length" class="px-6 py-14 text-center text-sm text-gray-500">Chargement…</p>
                            <div v-else-if="!logs.length" class="px-6 py-14 text-center">
                                <span class="sg-tile mx-auto h-12 w-12 text-xl text-gray-400"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                                <p class="mt-3 text-sm font-bold text-gray-600">Aucune activité enregistrée pour le moment.</p>
                                <p class="text-xs text-gray-400">Les nouvelles actions apparaîtront ici automatiquement.</p>
                            </div>

                            <ol v-else class="divide-y divide-[#0B0F19]/5" aria-live="polite">
                                <li
                                    v-for="log in logs"
                                    :key="log.id"
                                    class="transition-colors duration-1000"
                                    :class="nouveaux.has(log.id) ? 'bg-emerald-50/80' : ''"
                                >
                                    <button type="button" class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-white/50 sm:px-5" :aria-expanded="detailOuvert.has(log.id)" @click="basculerDetail(log.id)">
                                        <span class="w-16 shrink-0 pt-0.5 font-mono text-[11px] tabular-nums text-gray-400">{{ heure(log.created_at) }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-1.5">
                                                <span v-if="!selection && log.user" class="text-sm font-black text-[#0B0F19]">{{ log.user.name }}</span>
                                                <span class="text-sm" :class="log.method === 'GET' ? 'text-gray-600' : 'font-bold text-[#0B0F19]'">{{ log.description }}</span>
                                                <span v-if="log.impersonator" class="sg-chip bg-[#0B0F19] text-[10px] text-white">
                                                    <i class="fa-solid fa-user-secret" aria-hidden="true"></i>via {{ log.impersonator.name }}
                                                </span>
                                                <span v-if="log.status >= 400" class="sg-chip bg-red-100 text-[10px] text-red-700">Erreur {{ log.status }}</span>
                                            </span>
                                            <span class="mt-1 flex min-w-0 items-center gap-1.5">
                                                <span class="sg-chip !px-1.5 !py-0 font-mono text-[9px]" :class="METHODES[log.method] || 'bg-gray-100'">{{ log.method }}</span>
                                                <code class="truncate font-mono text-[11px] text-gray-500">{{ log.url }}</code>
                                            </span>
                                        </span>
                                        <i class="fa-solid fa-chevron-down mt-1.5 text-[10px] text-gray-300 transition-transform" :class="{ 'rotate-180': detailOuvert.has(log.id) }" aria-hidden="true"></i>
                                    </button>

                                    <div v-if="detailOuvert.has(log.id)" class="px-4 pb-4 sm:px-5 sm:pl-[5.75rem]">
                                        <dl class="sg-surface grid gap-x-6 gap-y-2 p-3 text-xs sm:grid-cols-2">
                                            <div><dt class="font-bold text-gray-500">Route</dt><dd class="font-mono text-[11px] text-[#0B0F19]">{{ log.route_name || '—' }}</dd></div>
                                            <div><dt class="font-bold text-gray-500">Statut · durée</dt><dd class="text-[#0B0F19]">{{ log.status }} · {{ log.duree_ms }} ms</dd></div>
                                            <div><dt class="font-bold text-gray-500">Adresse IP</dt><dd class="font-mono text-[11px] text-[#0B0F19]">{{ log.ip || '—' }}</dd></div>
                                            <div><dt class="font-bold text-gray-500">Date</dt><dd class="text-[#0B0F19]">{{ new Date(log.created_at).toLocaleString('fr-FR') }}</dd></div>
                                            <div v-if="log.parametres" class="sm:col-span-2">
                                                <dt class="font-bold text-gray-500">Éléments concernés</dt>
                                                <dd class="mt-1 flex flex-wrap gap-1">
                                                    <span v-for="(v, k) in log.parametres" :key="k" class="sg-chip bg-white text-[10px] text-gray-700">{{ k }} #{{ v }}</span>
                                                </dd>
                                            </div>
                                            <div v-if="log.donnees" class="sm:col-span-2">
                                                <dt class="font-bold text-gray-500">Données envoyées</dt>
                                                <dd class="mt-1 overflow-hidden rounded-xl bg-[#0B0F19] p-3 font-mono text-[11px] text-white/85">
                                                    <div v-for="(v, k) in log.donnees" :key="k" class="flex gap-2 break-all">
                                                        <span class="shrink-0 text-rose-300">{{ k }}:</span><span>{{ valeur(v) }}</span>
                                                    </div>
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </li>
                            </ol>

                            <div v-if="logs.length && plusAnciensDispo" class="sg-divider border-t p-3 text-center">
                                <button type="button" class="sg-btn sg-btn-sm sg-btn-glass" @click="plusAnciens">
                                    <i class="fa-solid fa-clock-rotate-left text-[10px]" aria-hidden="true"></i>Afficher plus ancien
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>

<style scoped>
/* Mise en page propre à la page (ne dépend pas des classes Tailwind générées à la volée) */
.sv-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: minmax(0, 1fr);
}
@media (min-width: 1024px) {
    .sv-grid { grid-template-columns: minmax(0, 4fr) minmax(0, 8fr); align-items: start; }
}
.sv-liste { max-height: 60vh; }
@media (min-width: 1024px) {
    .sv-liste { max-height: calc(100vh - 22rem); min-height: 16rem; }
}
.sv-dot {
    position: absolute;
    right: -2px;
    bottom: -2px;
    height: 14px;
    width: 14px;
    border-radius: 9999px;
    background: #d1d5db;
    box-shadow: 0 0 0 2px #fff;
}
.sv-dot-lg { height: 16px; width: 16px; right: 0; bottom: 0; }
.sv-dot-on { background: #10b981; }
.sv-avatar-lg { height: 56px; width: 56px; font-size: 1rem; }
</style>
