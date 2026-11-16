<script setup>
// Messagerie admin ↔ secrétaire (polling). Utilisée des deux côtés.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    secretaireId: { type: Number, required: true },
    // Tâches pouvant être citées dans un message
    taches: { type: Array, default: () => [] },
    titre: { type: String, default: 'Messagerie' },
    sousTitre: { type: String, default: '' },
});

const emit = defineEmits(['lu']);

const messages = ref([]);
const contenu = ref('');
const tacheId = ref(null);
const envoi = ref(false);
const erreur = ref('');
const chargement = ref(true);
const liste = ref(null);
const champ = ref(null);
const inputFichier = ref(null);
const fichier = ref(null);
const apercu = ref(null);
const progression = ref(0);
let timer;

const TAILLE_MAX = 10 * 1024 * 1024;
const ACCEPT = '.jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.txt,.csv,.zip';

const taille = (octets) => {
    if (octets == null) return '';
    if (octets < 1024) return `${octets} o`;
    if (octets < 1024 * 1024) return `${Math.round(octets / 1024)} Ko`;
    return `${(octets / (1024 * 1024)).toFixed(1).replace('.', ',')} Mo`;
};

const iconeFichier = (nom = '', mime = '') => {
    const ext = (nom || '').split('.').pop()?.toLowerCase();
    if (mime === 'application/pdf' || ext === 'pdf') return 'fa-file-pdf text-red-500';
    if (['doc', 'docx', 'odt'].includes(ext)) return 'fa-file-word text-blue-600';
    if (['xls', 'xlsx', 'ods', 'csv'].includes(ext)) return 'fa-file-excel text-emerald-600';
    if (['ppt', 'pptx'].includes(ext)) return 'fa-file-powerpoint text-orange-500';
    if (ext === 'zip') return 'fa-file-zipper text-amber-600';
    if (mime?.startsWith('image/')) return 'fa-file-image text-violet-500';
    return 'fa-file-lines text-gray-500';
};

function retirerFichier() {
    if (apercu.value) URL.revokeObjectURL(apercu.value);
    apercu.value = null;
    fichier.value = null;
    if (inputFichier.value) inputFichier.value.value = '';
}

function prendreFichier(f) {
    retirerFichier();
    if (!f) return;
    if (f.size > TAILLE_MAX) {
        erreur.value = 'Le fichier ne doit pas dépasser 10 Mo.';
        return;
    }
    erreur.value = '';
    fichier.value = f;
    if (/^image\/(jpeg|png|webp|gif)$/.test(f.type)) apercu.value = URL.createObjectURL(f);
    nextTick(() => champ.value?.focus());
}

const choisirFichier = (e) => prendreFichier(e.target.files?.[0]);

// --- Panneau des fichiers partagés ---
const panneauFichiers = ref(false);
const fichiers = ref([]);
const fichiersCharges = ref(false);
const chargementFichiers = ref(false);
const erreurFichiers = ref('');
const filtre = ref('tous');
const filtres = [
    { cle: 'tous', libelle: 'Tous' },
    { cle: 'photos', libelle: 'Photos' },
    { cle: 'documents', libelle: 'Documents' },
];

const correspond = (m, cle) =>
    cle === 'tous' || (cle === 'photos' ? m.piece_jointe.est_image : !m.piece_jointe.est_image);
const compte = (cle) => fichiers.value.filter((m) => correspond(m, cle)).length;
const fichiersFiltres = computed(() => fichiers.value.filter((m) => correspond(m, filtre.value)));

const dateComplete = (value) =>
    new Date(value).toLocaleString('fr-FR', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });

async function chargerFichiers() {
    chargementFichiers.value = true;
    erreurFichiers.value = '';
    try {
        const { data } = await axios.get(route('secretariat.chat.pieces-jointes', props.secretaireId));
        fichiers.value = data.fichiers;
        fichiersCharges.value = true;
    } catch {
        erreurFichiers.value = 'Impossible de charger les fichiers.';
    } finally {
        chargementFichiers.value = false;
    }
}

function basculerFichiers() {
    panneauFichiers.value = !panneauFichiers.value;
    if (panneauFichiers.value) chargerFichiers();
}

/** Ajoute en tête de liste les fichiers arrivés dans la discussion. */
function ajouterFichiers(nouveaux) {
    if (!fichiersCharges.value) return;
    const connus = new Set(fichiers.value.map((m) => m.id));
    const ajout = nouveaux.filter((m) => m.piece_jointe && !connus.has(m.id)).reverse();
    if (ajout.length) fichiers.value.unshift(...ajout);
}

/** Coller une capture d'écran directement dans le champ. */
function onPaste(e) {
    const f = e.clipboardData?.files?.[0];
    if (!f) return;
    e.preventDefault();
    prendreFichier(f);
}

const dernierId = computed(() => messages.value.at(-1)?.id ?? 0);
const tacheCitee = computed(() => props.taches.find((t) => t.id === tacheId.value) ?? null);

const heure = (value) => {
    const d = new Date(value);
    const memeJour = d.toDateString() === new Date().toDateString();
    return memeJour
        ? d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const enBas = () => {
    const el = liste.value;
    return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80;
};

const defiler = async () => {
    await nextTick();
    if (liste.value) liste.value.scrollTop = liste.value.scrollHeight;
};

async function charger(complet = false) {
    if (document.hidden && !complet) return;
    try {
        const coller = complet || enBas();
        const { data } = await axios.get(route('secretariat.chat.index', props.secretaireId), {
            params: complet ? {} : { after: dernierId.value },
        });
        if (complet) {
            messages.value = data.messages;
        } else if (data.messages.length) {
            const connus = new Set(messages.value.map((m) => m.id));
            const nouveaux = data.messages.filter((m) => !connus.has(m.id));
            messages.value.push(...nouveaux);
            ajouterFichiers(nouveaux);
        }
        emit('lu');
        if (coller) defiler();
    } catch {
        // Nouvel essai au prochain passage
    } finally {
        chargement.value = false;
    }
}

async function envoyer() {
    const texte = contenu.value.trim();
    if ((!texte && !fichier.value) || envoi.value) return;
    envoi.value = true;
    erreur.value = '';
    progression.value = 0;
    try {
        const form = new FormData();
        if (texte) form.append('contenu', texte);
        if (tacheId.value) form.append('tache_id', tacheId.value);
        if (fichier.value) form.append('fichier', fichier.value);

        const { data } = await axios.post(route('secretariat.chat.store', props.secretaireId), form, {
            onUploadProgress: (ev) => {
                if (ev.total) progression.value = Math.round((ev.loaded / ev.total) * 100);
            },
        });
        if (!messages.value.some((m) => m.id === data.message.id)) messages.value.push(data.message);
        ajouterFichiers([data.message]);
        contenu.value = '';
        tacheId.value = null;
        retirerFichier();
        defiler();
    } catch (e) {
        const erreurs = e.response?.data?.errors;
        erreur.value = (erreurs && Object.values(erreurs)[0]?.[0])
            || (e.response?.status === 413 ? 'Fichier trop volumineux pour le serveur.' : null)
            || e.response?.data?.message
            || "Le message n'a pas pu être envoyé.";
    } finally {
        envoi.value = false;
        progression.value = 0;
    }
}

const onKeydown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        envoyer();
    }
};

/** Prépare un message à propos d'une tâche (appelé depuis la page parente). */
function citerTache(id) {
    tacheId.value = id;
    nextTick(() => champ.value?.focus());
}
defineExpose({ citerTache });

watch(() => props.secretaireId, () => {
    messages.value = [];
    chargement.value = true;
    tacheId.value = null;
    retirerFichier();
    fichiers.value = [];
    fichiersCharges.value = false;
    filtre.value = 'tous';
    charger(true);
    chargerFichiers();
});

const onVisible = () => { if (!document.hidden) charger(); };

onMounted(() => {
    charger(true);
    chargerFichiers();
    timer = window.setInterval(charger, 5000);
    document.addEventListener('visibilitychange', onVisible);
});
onBeforeUnmount(() => {
    window.clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
    retirerFichier();
});
</script>

<template>
    <section class="sg-glass relative flex h-[34rem] flex-col overflow-hidden lg:h-[calc(100vh-12rem)] lg:min-h-[30rem]">
        <header class="sg-divider flex items-center gap-3 border-b px-4 py-3.5">
            <span class="sg-tile sg-tile-ink h-10 w-10">
                <i class="fa-solid fa-comments text-sm" aria-hidden="true"></i>
            </span>
            <div class="min-w-0">
                <h3 class="truncate text-sm font-black text-[#0B0F19]">{{ titre }}</h3>
                <p v-if="sousTitre" class="truncate text-xs text-gray-500">{{ sousTitre }}</p>
            </div>
            <span class="ml-auto inline-flex items-center gap-1.5 text-[11px] font-bold text-emerald-600">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500" aria-hidden="true"></span>En direct
            </span>
            <button
                type="button"
                class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition"
                :class="panneauFichiers ? 'bg-[#0B0F19] text-white' : 'text-gray-500 hover:bg-gray-100 hover:text-[#0B0F19]'"
                :aria-expanded="panneauFichiers"
                aria-label="Fichiers partagés dans la discussion"
                title="Fichiers partagés"
                @click="basculerFichiers"
            >
                <i class="fa-solid fa-folder-open text-sm" aria-hidden="true"></i>
                <span
                    v-if="fichiersCharges && fichiers.length"
                    class="absolute -right-1 -top-1 min-w-[1.1rem] rounded-full bg-[#E11D48] px-1 text-center text-[10px] font-bold leading-[1.1rem] text-white"
                >{{ fichiers.length > 99 ? '99+' : fichiers.length }}</span>
            </button>
        </header>

        <!-- Panneau : tous les fichiers échangés dans la discussion -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-full opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-x-full opacity-0"
        >
            <aside
                v-if="panneauFichiers"
                class="absolute inset-x-0 bottom-0 top-[calc(4.25rem+1px)] z-10 flex flex-col bg-white/95 backdrop-blur"
                aria-label="Fichiers partagés"
                @keydown.esc="panneauFichiers = false"
            >
                <div class="sg-divider flex items-center gap-2 border-b px-4 py-2.5">
                    <div class="flex gap-1 rounded-full bg-gray-100 p-1 text-xs font-bold">
                        <button
                            v-for="f in filtres"
                            :key="f.cle"
                            type="button"
                            class="rounded-full px-3 py-1 transition"
                            :class="filtre === f.cle ? 'bg-white text-[#0B0F19] shadow-sm' : 'text-gray-500 hover:text-[#0B0F19]'"
                            @click="filtre = f.cle"
                        >
                            {{ f.libelle }} <span class="font-medium text-gray-400">{{ compte(f.cle) }}</span>
                        </button>
                    </div>
                    <button
                        type="button"
                        class="ml-auto flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-[#0B0F19]"
                        aria-label="Fermer"
                        @click="panneauFichiers = false"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="sg-scroll flex-1 overflow-y-auto px-2 py-2">
                    <p v-if="chargementFichiers && !fichiersCharges" class="py-10 text-center text-sm text-gray-500">Chargement…</p>
                    <p v-else-if="erreurFichiers" class="py-10 text-center text-sm text-red-600">{{ erreurFichiers }}</p>
                    <p v-else-if="!fichiersFiltres.length" class="py-10 text-center text-sm text-gray-500">
                        Aucun fichier partagé pour le moment.
                    </p>

                    <ul v-else class="space-y-1">
                        <li v-for="m in fichiersFiltres" :key="m.id" class="flex items-center gap-1 rounded-xl pr-1 transition hover:bg-gray-100">
                            <a
                                :href="m.piece_jointe.est_image || m.piece_jointe.mime === 'application/pdf' ? m.piece_jointe.url : m.piece_jointe.url_telechargement"
                                :target="m.piece_jointe.est_image || m.piece_jointe.mime === 'application/pdf' ? '_blank' : null"
                                rel="noopener"
                                class="flex min-w-0 flex-1 items-center gap-3 px-2 py-2"
                            >
                                <img
                                    v-if="m.piece_jointe.est_image"
                                    :src="m.piece_jointe.url"
                                    alt=""
                                    loading="lazy"
                                    class="h-11 w-11 shrink-0 rounded-lg object-cover ring-1 ring-gray-200"
                                />
                                <span v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-gray-100">
                                    <i class="fa-solid text-lg" :class="iconeFichier(m.piece_jointe.nom, m.piece_jointe.mime)" aria-hidden="true"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-bold text-[#0B0F19]">{{ m.piece_jointe.nom }}</span>
                                    <span class="block truncate text-[11px] text-gray-500">
                                        {{ dateComplete(m.created_at) }} · {{ m.est_moi ? 'Vous' : (m.sender?.name || 'Utilisateur supprimé') }} · {{ taille(m.piece_jointe.taille) }}
                                    </span>
                                    <span v-if="m.tache" class="block truncate text-[11px] text-gray-400">
                                        <i class="fa-solid fa-list-check" aria-hidden="true"></i> {{ m.tache.titre }}
                                    </span>
                                </span>
                            </a>
                            <a
                                :href="m.piece_jointe.url_telechargement"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-white hover:text-[#0B0F19]"
                                :aria-label="`Télécharger ${m.piece_jointe.nom}`"
                                title="Télécharger"
                            >
                                <i class="fa-solid fa-download text-xs" aria-hidden="true"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </aside>
        </Transition>

        <div ref="liste" class="sg-chat-feed sg-scroll flex-1 space-y-3 overflow-y-auto px-4 py-4" aria-live="polite">
            <p v-if="chargement" class="py-10 text-center text-sm text-gray-500">Chargement…</p>
            <p v-else-if="!messages.length" class="py-10 text-center text-sm text-gray-500">
                Aucun message pour le moment.
            </p>

            <div
                v-for="m in messages"
                :key="m.id"
                class="flex"
                :class="m.est_moi ? 'justify-end' : 'justify-start'"
            >
                <div
                    class="max-w-[85%] rounded-[20px] px-4 py-2.5 text-sm"
                    :class="m.est_moi ? 'sg-bubble-me rounded-br-md' : 'sg-bubble-them rounded-bl-md'"
                >
                    <p v-if="!m.est_moi" class="mb-0.5 text-[11px] font-bold text-[#E11D48]">
                        {{ m.sender?.name || 'Utilisateur supprimé' }}
                    </p>
                    <p
                        v-if="m.tache"
                        class="mb-1.5 inline-flex max-w-full items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-bold"
                        :class="m.est_moi ? 'bg-white/10 text-white/80' : 'bg-gray-100 text-gray-600'"
                    >
                        <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                        <span class="truncate">{{ m.tache.titre }}</span>
                    </p>
                    <template v-if="m.piece_jointe">
                        <a
                            v-if="m.piece_jointe.est_image"
                            :href="m.piece_jointe.url"
                            target="_blank"
                            rel="noopener"
                            class="mb-1.5 block overflow-hidden rounded-xl"
                            :title="m.piece_jointe.nom"
                        >
                            <img
                                :src="m.piece_jointe.url"
                                :alt="m.piece_jointe.nom"
                                loading="lazy"
                                class="max-h-64 w-full object-cover"
                            />
                        </a>
                        <a
                            v-else
                            :href="m.piece_jointe.mime === 'application/pdf' ? m.piece_jointe.url : m.piece_jointe.url_telechargement"
                            :target="m.piece_jointe.mime === 'application/pdf' ? '_blank' : null"
                            rel="noopener"
                            class="mb-1.5 flex items-center gap-3 rounded-xl px-3 py-2 transition"
                            :class="m.est_moi ? 'bg-white/10 hover:bg-white/20' : 'bg-gray-100 hover:bg-gray-200'"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white">
                                <i class="fa-solid text-base" :class="iconeFichier(m.piece_jointe.nom, m.piece_jointe.mime)" aria-hidden="true"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-bold">{{ m.piece_jointe.nom }}</span>
                                <span class="block text-[10px]" :class="m.est_moi ? 'text-white/60' : 'text-gray-500'">{{ taille(m.piece_jointe.taille) }}</span>
                            </span>
                            <i class="fa-solid fa-download text-xs opacity-70" aria-hidden="true"></i>
                        </a>
                    </template>
                    <p v-if="m.contenu" class="whitespace-pre-line break-words">{{ m.contenu }}</p>
                    <p class="mt-1 text-right text-[10px]" :class="m.est_moi ? 'text-white/50' : 'text-gray-400'">
                        {{ heure(m.created_at) }}
                        <template v-if="m.est_moi"> · {{ m.lu_at ? 'Lu' : 'Envoyé' }}</template>
                    </p>
                </div>
            </div>
        </div>

        <form class="sg-divider space-y-2 border-t bg-white/40 p-3" @submit.prevent="envoyer">
            <div v-if="taches.length" class="flex items-center gap-2">
                <label class="sr-only" for="chat-tache">Message à propos d'une tâche</label>
                <select
                    id="chat-tache"
                    v-model="tacheId"
                    class="sg-field min-w-0 flex-1 py-1.5 !text-xs"
                >
                    <option :value="null">Message général</option>
                    <option v-for="t in taches" :key="t.id" :value="t.id">À propos de : {{ t.titre }}</option>
                </select>
                <button v-if="tacheCitee" type="button" class="text-xs font-bold text-gray-500 hover:text-[#0B0F19]" @click="tacheId = null">
                    Retirer
                </button>
            </div>
            <div v-if="fichier" class="flex items-center gap-3 rounded-xl bg-white/70 p-2 ring-1 ring-gray-200">
                <img v-if="apercu" :src="apercu" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover" />
                <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gray-100">
                    <i class="fa-solid text-lg" :class="iconeFichier(fichier.name, fichier.type)" aria-hidden="true"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-bold text-[#0B0F19]">{{ fichier.name }}</p>
                    <p class="text-[10px] text-gray-500">
                        {{ taille(fichier.size) }}<template v-if="envoi && progression"> · envoi {{ progression }} %</template>
                    </p>
                    <div v-if="envoi" class="mt-1 h-1 overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full bg-[#E11D48] transition-all" :style="{ width: `${progression}%` }"></div>
                    </div>
                </div>
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-[#0B0F19]"
                    :disabled="envoi"
                    aria-label="Retirer le fichier"
                    @click="retirerFichier"
                >
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="flex items-end gap-2">
                <input ref="inputFichier" type="file" class="hidden" :accept="ACCEPT" @change="choisirFichier" />
                <button
                    type="button"
                    :disabled="envoi"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-[#0B0F19]"
                    aria-label="Joindre une photo ou un document"
                    title="Joindre une photo ou un document (10 Mo max)"
                    @click="inputFichier?.click()"
                >
                    <i class="fa-solid fa-paperclip text-base" aria-hidden="true"></i>
                </button>
                <label class="sr-only" for="chat-contenu">Votre message</label>
                <textarea
                    id="chat-contenu"
                    ref="champ"
                    v-model="contenu"
                    rows="2"
                    maxlength="3000"
                    placeholder="Écrire un message… (Entrée pour envoyer)"
                    class="sg-field min-w-0 flex-1 resize-none"
                    @keydown="onKeydown"
                    @paste="onPaste"
                ></textarea>
                <button
                    type="submit"
                    :disabled="envoi || (!contenu.trim() && !fichier)"
                    class="sg-btn sg-btn-red h-11 w-11 shrink-0 !px-0"
                    aria-label="Envoyer"
                >
                    <i class="fa-solid fa-paper-plane text-sm" :class="{ 'fa-beat-fade': envoi }" aria-hidden="true"></i>
                </button>
            </div>
            <p v-if="erreur" class="text-xs font-medium text-red-600">{{ erreur }}</p>
        </form>
    </section>
</template>

<style src="../../css/secretariat-glass.css"></style>
