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
let timer;

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
            messages.value.push(...data.messages.filter((m) => !connus.has(m.id)));
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
    if (!texte || envoi.value) return;
    envoi.value = true;
    erreur.value = '';
    try {
        const { data } = await axios.post(route('secretariat.chat.store', props.secretaireId), {
            contenu: texte,
            tache_id: tacheId.value,
        });
        if (!messages.value.some((m) => m.id === data.message.id)) messages.value.push(data.message);
        contenu.value = '';
        tacheId.value = null;
        defiler();
    } catch (e) {
        erreur.value = e.response?.data?.message || "Le message n'a pas pu être envoyé.";
    } finally {
        envoi.value = false;
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
    charger(true);
});

const onVisible = () => { if (!document.hidden) charger(); };

onMounted(() => {
    charger(true);
    timer = window.setInterval(charger, 5000);
    document.addEventListener('visibilitychange', onVisible);
});
onBeforeUnmount(() => {
    window.clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});
</script>

<template>
    <section class="flex h-[32rem] flex-col overflow-hidden rounded-2xl border bg-white shadow-sm lg:h-[calc(100vh-14rem)] lg:min-h-[28rem]">
        <header class="flex items-center gap-3 border-b px-4 py-3">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-[#0B0F19] text-white">
                <i class="fa-solid fa-comments text-sm" aria-hidden="true"></i>
            </span>
            <div class="min-w-0">
                <h3 class="truncate text-sm font-black text-[#0B0F19]">{{ titre }}</h3>
                <p v-if="sousTitre" class="truncate text-xs text-gray-500">{{ sousTitre }}</p>
            </div>
        </header>

        <div ref="liste" class="flex-1 space-y-3 overflow-y-auto bg-gray-50 px-4 py-4" aria-live="polite">
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
                    class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm shadow-sm"
                    :class="m.est_moi ? 'rounded-br-md bg-[#0B0F19] text-white' : 'rounded-bl-md border bg-white text-[#0B0F19]'"
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
                    <p class="whitespace-pre-line break-words">{{ m.contenu }}</p>
                    <p class="mt-1 text-right text-[10px]" :class="m.est_moi ? 'text-white/50' : 'text-gray-400'">
                        {{ heure(m.created_at) }}
                        <template v-if="m.est_moi"> · {{ m.lu_at ? 'Lu' : 'Envoyé' }}</template>
                    </p>
                </div>
            </div>
        </div>

        <form class="space-y-2 border-t p-3" @submit.prevent="envoyer">
            <div v-if="taches.length" class="flex items-center gap-2">
                <label class="sr-only" for="chat-tache">Message à propos d'une tâche</label>
                <select
                    id="chat-tache"
                    v-model="tacheId"
                    class="min-w-0 flex-1 rounded-xl border-gray-200 py-1.5 text-xs text-gray-700 focus:border-[#E11D48] focus:ring-[#E11D48]/20"
                >
                    <option :value="null">Message général</option>
                    <option v-for="t in taches" :key="t.id" :value="t.id">À propos de : {{ t.titre }}</option>
                </select>
                <button v-if="tacheCitee" type="button" class="text-xs font-bold text-gray-500 hover:text-[#0B0F19]" @click="tacheId = null">
                    Retirer
                </button>
            </div>
            <div class="flex items-end gap-2">
                <label class="sr-only" for="chat-contenu">Votre message</label>
                <textarea
                    id="chat-contenu"
                    ref="champ"
                    v-model="contenu"
                    rows="2"
                    maxlength="3000"
                    placeholder="Écrire un message… (Entrée pour envoyer)"
                    class="min-w-0 flex-1 resize-none rounded-xl border-gray-200 text-sm focus:border-[#E11D48] focus:ring-[#E11D48]/20"
                    @keydown="onKeydown"
                ></textarea>
                <button
                    type="submit"
                    :disabled="envoi || !contenu.trim()"
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#E11D48] text-white transition hover:bg-[#BE123C] disabled:opacity-40"
                    aria-label="Envoyer"
                >
                    <i class="fa-solid fa-paper-plane text-sm" :class="{ 'fa-beat-fade': envoi }" aria-hidden="true"></i>
                </button>
            </div>
            <p v-if="erreur" class="text-xs font-medium text-red-600">{{ erreur }}</p>
        </form>
    </section>
</template>
