<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    clients: Array,
    filters: Object,
    stats: Object,
});

/* ───────── Filtres (même requête qu'avant, avec un léger délai de frappe) ───────── */
const search = ref(props.filters?.search || '');
const statut = ref(props.filters?.statut || 'tous');
let searchTimer = null;

const fetchClients = () => {
    router.get(
        route('admin.clients.gestion'),
        { search: search.value, statut: statut.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchClients, 300);
});
watch(statut, fetchClients);
onBeforeUnmount(() => clearTimeout(searchTimer));

const statusOptions = [
    { value: 'tous', label: 'Tous' },
    { value: 'actifs', label: 'Actifs' },
    { value: 'bannis', label: 'Bannis' },
];

/* ───────── Données dérivées ───────── */
const totalActifs = computed(() => props.stats?.actifs ?? 0);
const totalBannis = computed(() => props.stats?.bannis ?? 0);
const clientsFiltres = computed(() => props.clients || []);

const isBanned = (client) => client.status === 'banni' || !!client.banned_at;
const initialsOf = (client) =>
    `${(client.nom || '?').charAt(0)}${(client.prenom || '?').charAt(0)}`.toUpperCase();

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

/* ───────── Reflet de lumière qui suit le pointeur ───────── */
const onMove = (e) => {
    const el = e.target?.closest?.('.glass');
    if (!el) return;
    const r = el.getBoundingClientRect();
    el.style.setProperty('--mx', `${e.clientX - r.left}px`);
    el.style.setProperty('--my', `${e.clientY - r.top}px`);
};

/* ───────── Actions (logique inchangée, confirmation via feuille de verre) ───────── */
const pending = ref(null); // { type: 'status' | 'delete', client }
const processing = ref(false);

const modalConfig = computed(() => {
    const client = pending.value?.client;
    if (!client) return {};
    const name = `${client.nom} ${client.prenom}`;

    if (pending.value.type === 'delete') {
        return {
            icon: 'fa-trash-can',
            tone: 'danger',
            title: 'Supprimer ce compte ?',
            text: `Le compte client de ${name} sera supprimé définitivement. Cette action est irréversible.`,
            button: 'Supprimer définitivement',
        };
    }
    return isBanned(client)
        ? {
              icon: 'fa-user-check',
              tone: 'success',
              title: 'Réactiver ce compte ?',
              text: `${name} pourra de nouveau accéder au portail client.`,
              button: 'Réactiver le compte',
          }
        : {
              icon: 'fa-ban',
              tone: 'danger',
              title: 'Bannir ce compte ?',
              text: `${name} ne pourra plus accéder au portail client.`,
              button: 'Bannir le compte',
          };
});

const askStatus = (client) => (pending.value = { type: 'status', client });
const askDelete = (client) => (pending.value = { type: 'delete', client });

const closeModal = () => {
    if (processing.value) return;
    pending.value = null;
};

const runAction = () => {
    if (!pending.value) return;
    processing.value = true;

    const { type, client } = pending.value;
    const options = {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            pending.value = null;
        },
    };

    if (type === 'delete') {
        router.delete(route('admin.clients.gestion.destroy', client.id), options);
    } else {
        router.patch(route('admin.clients.gestion.status', client.id), {}, options);
    }
};
</script>

<template>
    <Head title="Gestion des comptes clients" />

    <div class="page" @pointermove="onMove">
        <!-- Lumière d'arrière-plan : ce que le verre réfracte -->
        <div class="orbs" aria-hidden="true">
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
        </div>

        <div class="relative mx-auto max-w-7xl space-y-5 px-4 pb-12 pt-6 sm:space-y-6 sm:px-6 sm:pt-10 lg:px-8">
            <!-- En-tête -->
            <header class="reveal glass glass-lg p-5 sm:p-7" style="--d: 0ms">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-4 sm:gap-5">
                        <span class="tile tile-xl" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#C8102E]">Administration</p>
                            <h1 class="mt-1 text-xl font-black leading-tight tracking-tight text-[#1A1A1A] sm:text-2xl">Comptes clients</h1>
                            <p class="mt-1 max-w-xl text-sm text-[#6B6E70]">
                                Vue centralisée des comptes client, leur statut et leurs droits d'accès au portail.
                            </p>
                        </div>
                    </div>
                    <Link :href="route('admin.users.index')" class="gbtn gbtn-neutral self-start lg:self-auto">
                        <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                        Retour au dashboard
                    </Link>
                </div>
            </header>

            <!-- Statistiques -->
            <section class="grid grid-cols-3 gap-3 sm:gap-4">
                <div class="reveal glass glass-md stat" style="--d: 60ms">
                    <span class="tile tile-sm hidden sm:inline-flex"><i class="fa-solid fa-user-group" aria-hidden="true"></i></span>
                    <div>
                        <p class="stat-label">Total</p>
                        <p class="stat-value text-[#1A1A1A]">{{ props.stats?.total ?? 0 }}</p>
                    </div>
                </div>
                <div class="reveal glass glass-md stat" style="--d: 110ms">
                    <span class="tile tile-sm tile-green hidden sm:inline-flex"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                    <div>
                        <p class="stat-label !text-emerald-700">Actifs</p>
                        <p class="stat-value text-emerald-800">{{ totalActifs }}</p>
                    </div>
                </div>
                <div class="reveal glass glass-md stat" style="--d: 160ms">
                    <span class="tile tile-sm tile-red hidden sm:inline-flex"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></span>
                    <div>
                        <p class="stat-label !text-[#C8102E]">Bannis</p>
                        <p class="stat-value text-[#C8102E]">{{ totalBannis }}</p>
                    </div>
                </div>
            </section>

            <!-- Recherche + filtre -->
            <section class="reveal glass glass-lg p-3 sm:p-4" style="--d: 210ms">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="field md:w-96">
                        <i class="fa-solid fa-magnifying-glass field-icon" aria-hidden="true"></i>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Rechercher nom, téléphone, email..."
                            class="field-input"
                            aria-label="Rechercher un client"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="icon-btn field-clear"
                            aria-label="Effacer la recherche"
                            @click="search = ''"
                        >
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="segmented" role="radiogroup" aria-label="Filtrer par statut">
                        <button
                            v-for="option in statusOptions"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="statut === option.value"
                            class="segment"
                            :class="statut === option.value ? 'segment-active' : ''"
                            @click="statut = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Liste : cartes sur mobile -->
            <section class="space-y-3 md:hidden">
                <div v-if="clientsFiltres.length === 0" class="reveal glass glass-lg empty" style="--d: 260ms">
                    <span class="empty-icon"><i class="fa-solid fa-user-magnifying-glass" aria-hidden="true"></i></span>
                    <p class="text-sm font-semibold text-[#1A1A1A]">Aucun client trouvé</p>
                    <p class="text-xs text-[#6B6E70]">Essayez une autre recherche ou un autre filtre.</p>
                </div>

                <article
                    v-for="(client, i) in clientsFiltres"
                    :key="client.id"
                    class="reveal glass glass-lg p-4"
                    :style="{ '--d': `${260 + Math.min(i, 6) * 45}ms` }"
                >
                    <div class="flex items-start gap-3">
                        <div class="avatar avatar-sm" aria-hidden="true">{{ initialsOf(client) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold text-[#1A1A1A]">{{ client.nom }} {{ client.prenom }}</p>
                            <p class="truncate text-xs text-[#6B6E70]">{{ client.siege || 'Sans siège' }}</p>
                        </div>
                        <span class="chip chip-sm" :class="isBanned(client) ? 'chip-red' : 'chip-green'">
                            <span class="dot" :class="isBanned(client) ? 'bg-[#C8102E]' : 'bg-emerald-500'"></span>
                            {{ isBanned(client) ? 'Banni' : 'Actif' }}
                        </span>
                    </div>

                    <dl class="well mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div class="min-w-0">
                            <dt class="label">Contact</dt>
                            <dd class="mt-1 truncate font-semibold text-[#1A1A1A]">{{ client.telephone || '—' }}</dd>
                            <dd class="truncate text-xs text-[#6B6E70]">{{ client.email || 'Email non renseigné' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="label">Portail</dt>
                            <dd class="mt-1 truncate font-semibold text-[#1A1A1A]">{{ client.portal_identifier || '—' }}</dd>
                            <dd class="flex items-center gap-1.5 text-xs text-[#6B6E70]">
                                <span class="dot" :class="client.portal_registered ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                                {{ client.portal_registered ? 'En ligne' : 'Hors ligne' }}
                            </dd>
                        </div>
                        <div class="col-span-2 text-xs text-[#6B6E70]">Créé le {{ formatDate(client.created_at) }}</div>
                    </dl>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <Link :href="route('admin.clients.gestion.show', client.id)" class="gbtn gbtn-sm gbtn-neutral">
                            <i class="fa-solid fa-eye text-[11px]" aria-hidden="true"></i> Voir
                        </Link>
                        <button type="button" class="gbtn gbtn-sm" :class="isBanned(client) ? 'gbtn-green' : 'gbtn-red'" @click="askStatus(client)">
                            <i class="fa-solid text-[11px]" :class="isBanned(client) ? 'fa-user-check' : 'fa-ban'" aria-hidden="true"></i>
                            {{ isBanned(client) ? 'Réactiver' : 'Bannir' }}
                        </button>
                        <button type="button" class="gbtn gbtn-sm gbtn-danger-outline" @click="askDelete(client)">
                            <i class="fa-solid fa-trash-can text-[11px]" aria-hidden="true"></i> Supprimer
                        </button>
                    </div>
                </article>
            </section>

            <!-- Liste : tableau sur tablette et desktop -->
            <section class="reveal glass glass-lg hidden md:block" style="--d: 260ms">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="thead">
                                <th class="th">Client</th>
                                <th class="th">Contact</th>
                                <th class="th">Compte portail</th>
                                <th class="th">Statut</th>
                                <th class="th">Créé le</th>
                                <th class="th text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="clientsFiltres.length === 0">
                                <td colspan="6">
                                    <div class="empty">
                                        <span class="empty-icon"><i class="fa-solid fa-user-magnifying-glass" aria-hidden="true"></i></span>
                                        <p class="text-sm font-semibold text-[#1A1A1A]">Aucun client trouvé</p>
                                        <p class="text-xs text-[#6B6E70]">Essayez une autre recherche ou un autre filtre.</p>
                                    </div>
                                </td>
                            </tr>

                            <tr v-for="client in clientsFiltres" :key="client.id" class="tr">
                                <td class="td">
                                    <div class="flex items-center gap-3">
                                        <div class="avatar avatar-sm" aria-hidden="true">{{ initialsOf(client) }}</div>
                                        <div class="min-w-0">
                                            <p class="truncate font-bold text-[#1A1A1A]">{{ client.nom }} {{ client.prenom }}</p>
                                            <p class="truncate text-xs text-[#6B6E70]">{{ client.siege || 'Sans siège' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="td">
                                    <p class="font-medium text-[#1A1A1A]">{{ client.telephone || '—' }}</p>
                                    <p class="text-xs text-[#6B6E70]">{{ client.email || 'Email non renseigné' }}</p>
                                </td>
                                <td class="td">
                                    <p class="font-mono text-[13px] font-semibold text-[#1A1A1A]">{{ client.portal_identifier || '—' }}</p>
                                    <p class="mt-0.5 flex items-center gap-1.5 text-xs text-[#6B6E70]">
                                        <span class="dot" :class="client.portal_registered ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                                        {{ client.portal_registered ? 'Compte en ligne' : 'Compte hors ligne' }}
                                    </p>
                                </td>
                                <td class="td">
                                    <span class="chip chip-sm" :class="isBanned(client) ? 'chip-red' : 'chip-green'">
                                        <span class="dot" :class="isBanned(client) ? 'bg-[#C8102E]' : 'bg-emerald-500'"></span>
                                        {{ isBanned(client) ? 'Banni' : 'Actif' }}
                                    </span>
                                </td>
                                <td class="td text-[#6B6E70]">{{ formatDate(client.created_at) }}</td>
                                <td class="td">
                                    <div class="flex justify-end gap-2">
                                        <Link :href="route('admin.clients.gestion.show', client.id)" class="gbtn gbtn-sm gbtn-neutral">
                                            <i class="fa-solid fa-eye text-[11px]" aria-hidden="true"></i> Voir
                                        </Link>
                                        <button type="button" class="gbtn gbtn-sm" :class="isBanned(client) ? 'gbtn-green' : 'gbtn-red'" @click="askStatus(client)">
                                            <i class="fa-solid text-[11px]" :class="isBanned(client) ? 'fa-user-check' : 'fa-ban'" aria-hidden="true"></i>
                                            {{ isBanned(client) ? 'Réactiver' : 'Bannir' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="gbtn gbtn-sm gbtn-danger-outline gbtn-square"
                                            aria-label="Supprimer le compte"
                                            @click="askDelete(client)"
                                        >
                                            <i class="fa-solid fa-trash-can text-[11px]" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Feuille de confirmation -->
        <Teleport to="body">
            <Transition name="sheet">
                <div
                    v-if="pending"
                    class="fixed inset-0 z-50 flex items-end justify-center p-3 sm:items-center sm:p-4"
                    role="dialog"
                    aria-modal="true"
                    @keydown.esc="closeModal"
                >
                    <div class="sheet-backdrop absolute inset-0" @click="closeModal"></div>

                    <div class="sheet-panel glass glass-strong glass-lg relative w-full max-w-md p-6 pt-4 sm:pt-6">
                        <span class="mx-auto mb-4 block h-1 w-9 rounded-full bg-[#1A1A1A]/20 sm:hidden" aria-hidden="true"></span>

                        <div class="flex items-start gap-4">
                            <span class="tile tile-lg" :class="modalConfig.tone === 'success' ? 'tile-green' : 'tile-red'">
                                <i class="fa-solid" :class="modalConfig.icon" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h3 class="text-base font-black text-[#1A1A1A]">{{ modalConfig.title }}</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-[#6B6E70]">{{ modalConfig.text }}</p>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" @click="closeModal" :disabled="processing" class="gbtn gbtn-neutral">Annuler</button>
                            <button
                                type="button"
                                @click="runAction"
                                :disabled="processing"
                                class="gbtn"
                                :class="modalConfig.tone === 'success' ? 'gbtn-green' : 'gbtn-red'"
                            >
                                <i v-if="processing" class="fa-solid fa-circle-notch fa-spin text-xs" aria-hidden="true"></i>
                                {{ processing ? 'Traitement…' : modalConfig.button }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* ════════════ Tokens ════════════ */
.page {
    --red: #c8102e;
    --red-dark: #a90d26;
    --ink: #1a1a1a;
    --muted: #6b6e70;
    --ease: cubic-bezier(0.16, 1, 0.3, 1);

    position: relative;
    min-height: 100vh;
    overflow-x: clip;
    background: #f5f5f6;
}

/* ════════════ Lumière d'arrière-plan ════════════ */
.orbs {
    position: fixed;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}
.orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(70px);
    will-change: transform;
}
.orb-1 {
    top: -12%;
    left: -8%;
    height: 46vmax;
    width: 46vmax;
    background: radial-gradient(circle, rgba(200, 16, 46, 0.34), rgba(200, 16, 46, 0) 70%);
    animation: drift-a 22s ease-in-out infinite alternate;
}
.orb-2 {
    top: 30%;
    right: -14%;
    height: 40vmax;
    width: 40vmax;
    background: radial-gradient(circle, rgba(26, 26, 26, 0.2), rgba(26, 26, 26, 0) 70%);
    animation: drift-b 28s ease-in-out infinite alternate;
}
.orb-3 {
    bottom: -18%;
    left: 22%;
    height: 38vmax;
    width: 38vmax;
    background: radial-gradient(circle, rgba(200, 16, 46, 0.2), rgba(255, 255, 255, 0) 70%);
    animation: drift-a 32s ease-in-out infinite alternate-reverse;
}
@keyframes drift-a {
    to {
        transform: translate3d(6vmax, 5vmax, 0) scale(1.1);
    }
}
@keyframes drift-b {
    to {
        transform: translate3d(-7vmax, -4vmax, 0) scale(1.08);
    }
}

/* ════════════ Liquid Glass ════════════ */
.glass {
    --mx: 50%;
    --my: 0%;
    position: relative;
    overflow: hidden;
    isolation: isolate;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.66), rgba(255, 255, 255, 0.38));
    -webkit-backdrop-filter: blur(26px) saturate(190%);
    backdrop-filter: blur(26px) saturate(190%);
    border: 1px solid rgba(255, 255, 255, 0.75);
    box-shadow:
        inset 0 1.5px 0 rgba(255, 255, 255, 0.95),
        inset 0 -1px 0 rgba(255, 255, 255, 0.35),
        inset 1px 0 0 rgba(255, 255, 255, 0.5),
        0 1px 2px rgba(26, 26, 26, 0.05),
        0 18px 40px -18px rgba(26, 26, 26, 0.28);
}
.glass-lg {
    border-radius: 28px;
}
.glass-md {
    border-radius: 22px;
}
.glass-strong {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.7));
}
.glass::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    border-radius: inherit;
    background: linear-gradient(160deg, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 38%);
    pointer-events: none;
}
.glass::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    border-radius: inherit;
    background: radial-gradient(260px circle at var(--mx) var(--my), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0) 65%);
    opacity: 0;
    transition: opacity 0.35s ease;
    pointer-events: none;
}
.glass:hover::after {
    opacity: 1;
}
@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
    .glass {
        background: rgba(255, 255, 255, 0.94);
    }
}
@media (prefers-reduced-transparency: reduce) {
    .glass {
        background: #fff;
        -webkit-backdrop-filter: none;
        backdrop-filter: none;
    }
}

/* ════════════ Tuiles d'icônes ════════════ */
.tile {
    display: inline-flex;
    height: 32px;
    width: 32px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    font-size: 0.75rem;
    color: var(--red);
    background: linear-gradient(160deg, rgba(200, 16, 46, 0.16), rgba(200, 16, 46, 0.07));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 0 0 1px rgba(200, 16, 46, 0.12);
}
.tile-sm {
    height: 38px;
    width: 38px;
    border-radius: 13px;
    font-size: 0.85rem;
    color: var(--muted);
    background: linear-gradient(160deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.5));
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.06), 0 4px 10px -6px rgba(26, 26, 26, 0.25);
}
.tile-lg {
    height: 44px;
    width: 44px;
    border-radius: 15px;
    font-size: 1rem;
}
.tile-xl {
    height: 56px;
    width: 56px;
    border-radius: 20px;
    font-size: 1.2rem;
}
.tile-red {
    color: var(--red);
    background: linear-gradient(160deg, rgba(200, 16, 46, 0.16), rgba(200, 16, 46, 0.07));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 0 0 1px rgba(200, 16, 46, 0.12);
}
.tile-green {
    color: #059669;
    background: linear-gradient(160deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.08));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 0 0 1px rgba(16, 185, 129, 0.18);
}

/* ════════════ Statistiques ════════════ */
.stat {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.9rem 1rem;
}
@media (min-width: 640px) {
    .stat {
        flex-direction: row;
        align-items: center;
        gap: 1rem;
        padding: 1.15rem 1.35rem;
    }
}
.stat-label {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: var(--muted);
}
.stat-value {
    margin-top: 0.15rem;
    font-size: 1.6rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.02em;
}
@media (min-width: 640px) {
    .stat-value {
        font-size: 2rem;
    }
}

/* ════════════ Champ de recherche + segments ════════════ */
.field {
    position: relative;
}
.field-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.8rem;
    color: var(--muted);
    pointer-events: none;
}
.field-input {
    min-height: 46px;
    width: 100%;
    padding: 0 2.6rem 0 2.55rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    color: var(--ink);
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid transparent;
    box-shadow: inset 0 1px 3px rgba(26, 26, 26, 0.07), inset 0 0 0 1px rgba(26, 26, 26, 0.06), 0 1px 0 rgba(255, 255, 255, 0.8);
    outline: none;
    transition: box-shadow 0.25s ease, background 0.25s ease;
}
.field-input::placeholder {
    color: #8e9194;
}
.field-input:focus {
    background: rgba(255, 255, 255, 0.9);
    box-shadow: inset 0 1px 2px rgba(26, 26, 26, 0.04), 0 0 0 2px rgba(200, 16, 46, 0.55), 0 0 0 6px rgba(200, 16, 46, 0.12);
}
.field-clear {
    position: absolute;
    right: 0.4rem;
    top: 50%;
    transform: translateY(-50%);
    height: 32px;
    width: 32px;
}

.segmented {
    display: inline-grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    gap: 2px;
    padding: 4px;
    border-radius: 9999px;
    background: rgba(26, 26, 26, 0.06);
    box-shadow: inset 0 1px 3px rgba(26, 26, 26, 0.08);
}
.segment {
    min-height: 38px;
    padding: 0 1.1rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--muted);
    transition: color 0.25s ease, background 0.3s var(--ease), box-shadow 0.3s ease, transform 0.15s ease;
}
.segment:hover:not(.segment-active) {
    color: var(--ink);
}
.segment:active {
    transform: scale(0.96);
}
.segment-active {
    color: var(--ink);
    background: linear-gradient(180deg, #fff, rgba(255, 255, 255, 0.8));
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.06), 0 4px 10px -4px rgba(26, 26, 26, 0.3);
}
.segment:focus-visible {
    outline: 2px solid var(--red);
    outline-offset: 2px;
}

/* ════════════ Avatar ════════════ */
.avatar {
    display: flex;
    height: 60px;
    width: 60px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 22px;
    font-size: 1.2rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    color: #fff;
    background: linear-gradient(150deg, #3a3a3c, #1a1a1a);
    box-shadow:
        inset 0 1.5px 0 rgba(255, 255, 255, 0.35),
        inset 0 -6px 12px rgba(0, 0, 0, 0.25),
        0 10px 22px -10px rgba(26, 26, 26, 0.6);
}
.avatar-sm {
    height: 42px;
    width: 42px;
    border-radius: 15px;
    font-size: 0.75rem;
    box-shadow:
        inset 0 1.5px 0 rgba(255, 255, 255, 0.35),
        inset 0 -4px 8px rgba(0, 0, 0, 0.25),
        0 6px 14px -8px rgba(26, 26, 26, 0.6);
}

/* ════════════ Puces de statut ════════════ */
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 800;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
}
.chip-sm {
    flex-shrink: 0;
    padding: 0.28rem 0.65rem;
    font-size: 0.7rem;
}
.dot {
    display: inline-block;
    height: 6px;
    width: 6px;
    flex-shrink: 0;
    border-radius: 9999px;
}
.chip .dot {
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.6);
}
.chip-green {
    color: #047857;
    background: rgba(16, 185, 129, 0.16);
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.chip-red {
    color: var(--red);
    background: rgba(200, 16, 46, 0.12);
    border: 1px solid rgba(200, 16, 46, 0.22);
}

/* ════════════ Boutons en capsule ════════════ */
.gbtn {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0 1.15rem;
    border-radius: 9999px;
    font-size: 0.85rem;
    font-weight: 800;
    white-space: nowrap;
    transition: transform 0.25s var(--ease), box-shadow 0.25s ease, background 0.25s ease, filter 0.25s ease;
}
.gbtn-sm {
    min-height: 38px;
    padding: 0 0.95rem;
    font-size: 0.78rem;
}
.gbtn-square {
    width: 38px;
    padding: 0;
}
.gbtn:hover:not(:disabled) {
    transform: translateY(-1px);
    filter: brightness(1.04);
}
.gbtn:active:not(:disabled) {
    transform: scale(0.96);
}
.gbtn:disabled {
    opacity: 0.6;
}
.gbtn:focus-visible,
.icon-btn:focus-visible {
    outline: 2px solid var(--red);
    outline-offset: 2px;
}
.gbtn-neutral {
    color: var(--ink);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.55));
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.08), 0 6px 14px -8px rgba(26, 26, 26, 0.3);
}
.gbtn-red {
    color: #fff;
    background: linear-gradient(180deg, #e0213f, var(--red) 55%, var(--red-dark));
    box-shadow: inset 0 1.5px 0 rgba(255, 255, 255, 0.45), inset 0 -2px 6px rgba(0, 0, 0, 0.18), 0 8px 18px -8px rgba(200, 16, 46, 0.7);
}
.gbtn-green {
    color: #fff;
    background: linear-gradient(180deg, #19c28a, #059669 55%, #047857);
    box-shadow: inset 0 1.5px 0 rgba(255, 255, 255, 0.45), inset 0 -2px 6px rgba(0, 0, 0, 0.15), 0 8px 18px -8px rgba(5, 150, 105, 0.7);
}
.gbtn-danger-outline {
    color: var(--red);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.85), rgba(253, 236, 239, 0.7));
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(200, 16, 46, 0.25), 0 6px 14px -8px rgba(200, 16, 46, 0.4);
}
.icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    font-size: 0.8rem;
    color: var(--muted);
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.15s ease;
}
.icon-btn:hover {
    color: var(--ink);
    background: rgba(255, 255, 255, 0.85);
}
.icon-btn:active {
    transform: scale(0.88);
}

/* ════════════ Contenus ════════════ */
.well {
    padding: 0.9rem 1rem;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.5);
    box-shadow: inset 0 1px 3px rgba(26, 26, 26, 0.06), inset 0 0 0 1px rgba(26, 26, 26, 0.05), 0 1px 0 rgba(255, 255, 255, 0.8);
}
.label {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: var(--muted);
}

/* ════════════ Tableau ════════════ */
.thead {
    background: rgba(255, 255, 255, 0.4);
    box-shadow: inset 0 -1px 0 rgba(26, 26, 26, 0.06);
}
.th {
    padding: 0.95rem 1.1rem;
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: var(--muted);
    white-space: nowrap;
}
.tr {
    vertical-align: middle;
    transition: background-color 0.25s ease;
}
.tr + .tr {
    box-shadow: inset 0 1px 0 rgba(26, 26, 26, 0.06);
}
.tr:hover {
    background: rgba(255, 255, 255, 0.55);
}
.td {
    padding: 0.95rem 1.1rem;
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 2.5rem 1.5rem;
    text-align: center;
}
.empty-icon {
    margin-bottom: 0.45rem;
    display: inline-flex;
    height: 48px;
    width: 48px;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    color: #9a9da0;
    background: rgba(255, 255, 255, 0.65);
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.06);
}

/* ════════════ Entrée : un seul moment, échelonné ════════════ */
.reveal {
    animation: reveal 0.7s var(--ease) both;
    animation-delay: var(--d, 0ms);
}
@keyframes reveal {
    from {
        opacity: 0;
        transform: translateY(14px) scale(0.985);
        filter: blur(6px);
    }
    to {
        opacity: 1;
        transform: none;
        filter: blur(0);
    }
}

/* ════════════ Feuille de confirmation ════════════ */
.sheet-backdrop {
    background: rgba(26, 26, 26, 0.35);
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
}
.sheet-enter-active,
.sheet-leave-active {
    transition: opacity 0.25s ease;
}
.sheet-enter-active .sheet-panel,
.sheet-leave-active .sheet-panel {
    transition: transform 0.45s var(--ease), opacity 0.25s ease;
}
.sheet-enter-from,
.sheet-leave-to {
    opacity: 0;
}
.sheet-enter-from .sheet-panel,
.sheet-leave-to .sheet-panel {
    opacity: 0;
    transform: translateY(40px) scale(0.97);
}

@media (prefers-reduced-motion: reduce) {
    .reveal,
    .orb,
    .sheet-enter-active,
    .sheet-leave-active,
    .sheet-enter-active .sheet-panel,
    .sheet-leave-active .sheet-panel {
        animation: none;
        transition: none;
    }
}
</style>