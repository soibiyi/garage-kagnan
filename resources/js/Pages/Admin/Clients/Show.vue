<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    client: Object,
});

/* ───────── État ───────── */
const showPassword = ref(false);
const copiedField = ref(null);
const confirmAction = ref(null); // 'status' | 'delete' | null
const processing = ref(false);
let copyTimer = null;

const isBanned = computed(() => props.client?.status === 'banni' || !!props.client?.banned_at);
const statusLabel = computed(() => (isBanned.value ? 'Banni' : 'Actif'));
const fullName = computed(() => `${props.client?.nom || ''} ${props.client?.prenom || ''}`.trim());

const initials = computed(() => {
    const n = (props.client?.nom || '').trim().charAt(0);
    const p = (props.client?.prenom || '').trim().charAt(0);
    return (n + p).toUpperCase() || '?';
});

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const accountFields = computed(() => [
    { label: 'Nom', icon: 'fa-user', value: props.client?.nom || '—' },
    { label: 'Prénom', icon: 'fa-user', value: props.client?.prenom || '—' },
    { label: 'Téléphone', icon: 'fa-phone', value: props.client?.telephone || '—' },
    { label: 'Email', icon: 'fa-envelope', value: props.client?.email || '—' },
    { label: 'Siège', icon: 'fa-location-dot', value: props.client?.siege || '—' },
    { label: 'Type', icon: 'fa-tag', value: props.client?.type_client || '—' },
    { label: 'Créé le', icon: 'fa-calendar', value: formatDate(props.client?.created_at) },
    { label: 'Banni le', icon: 'fa-calendar-xmark', value: formatDate(props.client?.banned_at) },
]);

const requestBadge = (statut) => {
    const s = String(statut || '').toLowerCase();
    if (/(attente|nouveau|nouvelle|pending)/.test(s)) return 'chip-amber';
    if (/(trait|valid|termin|accept|approuv|clos)/.test(s)) return 'chip-green';
    if (/(refus|rejet|annul)/.test(s)) return 'chip-red';
    return 'chip-gray';
};

/* ───────── Copie ───────── */
const copy = async (field, text) => {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = field;
        clearTimeout(copyTimer);
        copyTimer = setTimeout(() => (copiedField.value = null), 1800);
    } catch (e) {
        copiedField.value = null;
    }
};
onBeforeUnmount(() => clearTimeout(copyTimer));

/* ───────── Reflet de lumière qui suit le pointeur ───────── */
const onMove = (e) => {
    const el = e.target?.closest?.('.glass');
    if (!el) return;
    const r = el.getBoundingClientRect();
    el.style.setProperty('--mx', `${e.clientX - r.left}px`);
    el.style.setProperty('--my', `${e.clientY - r.top}px`);
};

/* ───────── Actions (logique inchangée, confirmation via feuille de verre) ───────── */
const modalConfig = computed(() => {
    if (confirmAction.value === 'delete') {
        return {
            icon: 'fa-trash-can',
            tone: 'danger',
            title: 'Supprimer ce compte ?',
            text: `${fullName.value} sera supprimé définitivement. Cette action est irréversible.`,
            button: 'Supprimer définitivement',
        };
    }
    return isBanned.value
        ? {
              icon: 'fa-user-check',
              tone: 'success',
              title: 'Réactiver ce compte ?',
              text: `${fullName.value} pourra de nouveau accéder au portail client.`,
              button: 'Réactiver le compte',
          }
        : {
              icon: 'fa-ban',
              tone: 'danger',
              title: 'Bannir ce compte ?',
              text: `${fullName.value} ne pourra plus accéder au portail client.`,
              button: 'Bannir le compte',
          };
});

const closeModal = () => {
    if (processing.value) return;
    confirmAction.value = null;
};

const runAction = () => {
    processing.value = true;
    const options = {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            confirmAction.value = null;
        },
    };

    if (confirmAction.value === 'delete') {
        router.delete(route('admin.clients.gestion.destroy', props.client.id), options);
    } else {
        router.patch(route('admin.clients.gestion.status', props.client.id), {}, options);
    }
};
</script>

<template>
    <Head :title="`Compte client - ${fullName}`" />

    <div class="page" @pointermove="onMove">
        <!-- Lumière d'arrière-plan : ce que le verre réfracte -->
        <div class="orbs" aria-hidden="true">
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
        </div>

        <div class="relative mx-auto max-w-6xl space-y-5 px-4 pb-32 pt-6 sm:space-y-6 sm:px-6 sm:pb-12 sm:pt-10 lg:px-8">
            <!-- Fil d'Ariane -->
            <nav class="reveal flex items-center gap-2 px-1 text-xs font-semibold text-[#6B6E70]" style="--d: 0ms" aria-label="Fil d'Ariane">
                <Link :href="route('admin.clients.gestion')" class="transition-colors hover:text-[#C8102E]">Clients</Link>
                <i class="fa-solid fa-chevron-right text-[9px] opacity-60" aria-hidden="true"></i>
                <span class="truncate text-[#1A1A1A]">{{ fullName }}</span>
            </nav>

            <!-- En-tête -->
            <header class="reveal glass glass-lg p-5 sm:p-7" style="--d: 60ms">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-4 sm:gap-5">
                        <div class="avatar" aria-hidden="true">{{ initials }}</div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#C8102E]">Compte client</p>
                            <h1 class="mt-1 truncate text-xl font-black leading-tight text-[#1A1A1A] sm:text-2xl">
                                {{ client?.nom }} {{ client?.prenom }}
                            </h1>
                            <span class="chip mt-2" :class="isBanned ? 'chip-red' : 'chip-green'">
                                <span class="dot" :class="isBanned ? 'bg-[#C8102E]' : 'bg-emerald-500'"></span>
                                {{ statusLabel }}
                            </span>
                        </div>
                    </div>

                    <!-- Mobile : dock flottant en bas. Desktop : actions en ligne. -->
                    <div class="actions">
                        <Link :href="route('admin.clients.gestion')" class="gbtn gbtn-neutral">
                            <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                            <span>Retour</span>
                        </Link>
                        <button
                            type="button"
                            @click="confirmAction = 'status'"
                            class="gbtn"
                            :class="isBanned ? 'gbtn-green' : 'gbtn-red'"
                        >
                            <i class="fa-solid text-xs" :class="isBanned ? 'fa-user-check' : 'fa-ban'" aria-hidden="true"></i>
                            <span>{{ isBanned ? 'Réactiver' : 'Bannir' }}</span>
                        </button>
                        <button type="button" @click="confirmAction = 'delete'" class="gbtn gbtn-danger-outline">
                            <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                            <span>Supprimer</span>
                        </button>
                    </div>
                </div>
            </header>

            <section class="grid gap-5 sm:gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <!-- Colonne principale -->
                <div class="space-y-5 sm:space-y-6">
                    <div class="reveal glass glass-lg" style="--d: 120ms">
                        <div class="card-head">
                            <h2 class="card-title">
                                <span class="tile tile-red"><i class="fa-solid fa-id-badge" aria-hidden="true"></i></span>
                                Informations du compte
                            </h2>
                        </div>

                        <dl class="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2 sm:p-6">
                            <div v-for="field in accountFields" :key="field.label" class="flex items-start gap-3">
                                <span class="tile tile-sm">
                                    <i class="fa-solid" :class="field.icon" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#6B6E70]">{{ field.label }}</dt>
                                    <dd class="mt-0.5 break-words text-sm font-semibold text-[#1A1A1A]">{{ field.value }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>

                    <div class="reveal glass glass-lg" style="--d: 180ms">
                        <div class="card-head">
                            <h2 class="card-title">
                                <span class="tile tile-red"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
                                Accès portail client
                            </h2>
                        </div>

                        <div class="grid gap-3 p-4 sm:grid-cols-2 sm:gap-4 sm:p-5">
                            <!-- Identifiant -->
                            <div class="well">
                                <p class="label">Identifiant</p>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <p class="select-all truncate font-mono text-sm font-bold text-[#1A1A1A]">
                                        {{ client?.portal_identifier || '—' }}
                                    </p>
                                    <button
                                        v-if="client?.portal_identifier"
                                        type="button"
                                        @click="copy('id', client.portal_identifier)"
                                        class="icon-btn"
                                        :class="copiedField === 'id' ? '!text-emerald-600' : ''"
                                        :aria-label="copiedField === 'id' ? 'Copié' : 'Copier l\'identifiant'"
                                    >
                                        <i class="fa-solid" :class="copiedField === 'id' ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Mot de passe -->
                            <div class="well">
                                <p class="label">Mot de passe temporaire</p>
                                <div v-if="client?.portal_temp_password" class="mt-2 flex items-center justify-between gap-3">
                                    <p class="select-all truncate font-mono text-sm font-bold text-[#1A1A1A]">
                                        {{ showPassword ? client.portal_temp_password : '••••••••••••' }}
                                    </p>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="icon-btn"
                                            :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                                        >
                                            <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                        </button>
                                        <button
                                            type="button"
                                            @click="copy('pwd', client.portal_temp_password)"
                                            class="icon-btn"
                                            :class="copiedField === 'pwd' ? '!text-emerald-600' : ''"
                                            :aria-label="copiedField === 'pwd' ? 'Copié' : 'Copier le mot de passe'"
                                        >
                                            <i class="fa-solid" :class="copiedField === 'pwd' ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <p v-else class="mt-2 text-sm text-[#6B6E70]">Pas de mot de passe temporaire.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonne latérale -->
                <div class="space-y-5 sm:space-y-6">
                    <div class="reveal glass glass-lg" style="--d: 240ms">
                        <div class="card-head">
                            <h2 class="card-title">
                                <span class="tile tile-red"><i class="fa-solid fa-car" aria-hidden="true"></i></span>
                                Véhicules
                            </h2>
                            <span class="count">{{ client?.vehicules?.length || 0 }}</span>
                        </div>

                        <ul v-if="client?.vehicules?.length" class="list">
                            <li v-for="vehicule in client.vehicules" :key="vehicule.id" class="row">
                                <span class="tile tile-sm">
                                    <i class="fa-solid fa-car-side" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-[#1A1A1A]">{{ vehicule.marque }} {{ vehicule.modele }}</p>
                                    <p class="mt-0.5 text-xs text-[#6B6E70]">Immatriculation</p>
                                </div>
                                <span class="plate">{{ vehicule.immatriculation }}</span>
                            </li>
                        </ul>
                        <div v-else class="empty">
                            <span class="empty-icon"><i class="fa-solid fa-car" aria-hidden="true"></i></span>
                            <p class="text-sm font-semibold text-[#1A1A1A]">Aucun véhicule</p>
                            <p class="text-xs text-[#6B6E70]">Les véhicules de ce client apparaîtront ici.</p>
                        </div>
                    </div>

                    <div class="reveal glass glass-lg" style="--d: 300ms">
                        <div class="card-head">
                            <h2 class="card-title">
                                <span class="tile tile-red"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span>
                                Demandes portail
                            </h2>
                            <span class="count">{{ client?.portal_requests?.length || 0 }}</span>
                        </div>

                        <ul v-if="client?.portal_requests?.length" class="list">
                            <li v-for="request in client.portal_requests" :key="request.id" class="row justify-between">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-[#1A1A1A]">{{ request.type }}</p>
                                    <p class="mt-0.5 text-xs text-[#6B6E70]">{{ formatDate(request.created_at) }}</p>
                                </div>
                                <span class="chip chip-sm" :class="requestBadge(request.statut)">{{ request.statut }}</span>
                            </li>
                        </ul>
                        <div v-else class="empty">
                            <span class="empty-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
                            <p class="text-sm font-semibold text-[#1A1A1A]">Aucune demande</p>
                            <p class="text-xs text-[#6B6E70]">Ce client n'a encore rien soumis via le portail.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Feuille de confirmation -->
        <Teleport to="body">
            <Transition name="sheet">
                <div
                    v-if="confirmAction"
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
                            <button type="button" @click="closeModal" :disabled="processing" class="gbtn gbtn-neutral">
                                Annuler
                            </button>
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
.glass-strong {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.7));
}
/* Reflet spéculaire fixe (bord supérieur) */
.glass::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    border-radius: inherit;
    background: linear-gradient(160deg, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 38%);
    pointer-events: none;
}
/* Reflet mobile qui suit le pointeur */
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

/* Sans support du flou : on retombe sur des surfaces plus pleines */
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

/* ════════════ En-tête de carte ════════════ */
.card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(26, 26, 26, 0.06);
    box-shadow: 0 1px 0 rgba(255, 255, 255, 0.7);
}
@media (min-width: 640px) {
    .card-head {
        padding: 1.1rem 1.5rem;
    }
}
.card-title {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.95rem;
    font-weight: 900;
    color: var(--ink);
}
.count {
    min-width: 1.7rem;
    padding: 0.18rem 0.6rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.7);
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.06);
    text-align: center;
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--muted);
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
    height: 34px;
    width: 34px;
    border-radius: 12px;
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
.tile-green {
    color: #059669;
    background: linear-gradient(160deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.08));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 0 0 1px rgba(16, 185, 129, 0.18);
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
@media (min-width: 640px) {
    .avatar {
        height: 68px;
        width: 68px;
        font-size: 1.35rem;
    }
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
    font-size: 0.625rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.dot {
    height: 6px;
    width: 6px;
    border-radius: 9999px;
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
.chip-amber {
    color: #b45309;
    background: rgba(245, 158, 11, 0.16);
    border: 1px solid rgba(245, 158, 11, 0.28);
}
.chip-gray {
    color: var(--ink);
    background: rgba(26, 26, 26, 0.07);
    border: 1px solid rgba(26, 26, 26, 0.1);
}

/* ════════════ Boutons en capsule ════════════ */
.actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.4rem;
}
/* Mobile : dock flottant façon barre iOS */
@media (max-width: 639px) {
    .actions {
        position: fixed;
        left: 0.75rem;
        right: 0.75rem;
        bottom: max(0.75rem, env(safe-area-inset-bottom));
        z-index: 30;
        padding: 0.4rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.74), rgba(255, 255, 255, 0.5));
        -webkit-backdrop-filter: blur(26px) saturate(190%);
        backdrop-filter: blur(26px) saturate(190%);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow:
            inset 0 1.5px 0 rgba(255, 255, 255, 0.95),
            0 18px 36px -12px rgba(26, 26, 26, 0.4);
    }
}
@media (min-width: 640px) {
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
}

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
@media (max-width: 639px) {
    .gbtn {
        padding: 0 0.5rem;
        font-size: 0.78rem;
    }
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
    height: 34px;
    width: 34px;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    font-size: 0.8rem;
    color: var(--muted);
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
}
.icon-btn:hover {
    color: var(--ink);
    background: rgba(255, 255, 255, 0.85);
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.06);
}
.icon-btn:active {
    transform: scale(0.88);
}

/* ════════════ Contenus ════════════ */
.well {
    padding: 1rem;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.5);
    box-shadow: inset 0 1px 3px rgba(26, 26, 26, 0.06), inset 0 0 0 1px rgba(26, 26, 26, 0.05), 0 1px 0 rgba(255, 255, 255, 0.8);
}
.label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: var(--muted);
}

.list {
    padding: 0.4rem;
}
.row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.85rem;
    border-radius: 18px;
    transition: background-color 0.25s ease;
}
.row:hover {
    background: rgba(255, 255, 255, 0.6);
}
.row + .row {
    position: relative;
}
.row + .row::before {
    content: '';
    position: absolute;
    top: -1px;
    left: 0.85rem;
    right: 0.85rem;
    height: 1px;
    background: rgba(26, 26, 26, 0.06);
}
.plate {
    flex-shrink: 0;
    padding: 0.28rem 0.6rem;
    border-radius: 9px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--ink);
    background: linear-gradient(180deg, #fff, rgba(255, 255, 255, 0.7));
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px rgba(26, 26, 26, 0.18);
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 2rem 1.5rem;
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