<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  audience: {
    type: String,
    required: true,
    validator: value => ['client', 'staff'].includes(value),
  },
  clientId: {
    type: Number,
    default: null,
  },
})

const messages = ref([])
const draft = ref('')
const error = ref('')
const loading = ref(true)
const sending = ref(false)
const thread = ref(null)
const field = ref(null)
let pollTimer
let polling = false

const endpoint = props.audience === 'client'
  ? '/espace-client/api/messages'
  : `/suivi-client/clients/${props.clientId}/portail/messages`

// Entrée envoie sur ordinateur ; sur téléphone, Entrée reste un retour à la ligne
const entreeEnvoie = typeof window !== 'undefined' && !!window.matchMedia?.('(pointer: fine)').matches
const suggestions = [
  'Où en est mon véhicule ?',
  'J’ai une question sur mon devis.',
  'Je souhaite prendre rendez-vous.',
]

async function scrollToLatest() {
  await nextTick()
  if (thread.value) thread.value.scrollTop = thread.value.scrollHeight
}

async function fetchMessages(initial = false) {
  if (polling) return
  polling = true
  const afterId = messages.value.at(-1)?.id

  try {
    const params = afterId ? { after_id: afterId } : {}
    const { data } = await axios.get(endpoint, { params })
    if (initial) messages.value = data.messages
    else if (data.messages.length) messages.value.push(...data.messages)
    error.value = ''
    await scrollToLatest()
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Impossible de charger la conversation.'
  } finally {
    loading.value = false
    polling = false
  }
}

async function send() {
  const message = draft.value.trim()
  if (!message || sending.value) return

  sending.value = true
  error.value = ''
  try {
    const url = props.audience === 'client'
      ? endpoint
      : `/suivi-client/clients/${props.clientId}/portail/messages`
    const { data } = await axios.post(url, { message })
    messages.value.push(data.message)
    draft.value = ''
    await scrollToLatest()
  } catch (requestError) {
    error.value = requestError.response?.data?.message
      || Object.values(requestError.response?.data?.errors || {}).flat()[0]
      || 'Impossible d’envoyer le message.'
  } finally {
    sending.value = false
  }
}

function onEnter(event) {
  if (!entreeEnvoie) return
  event.preventDefault()
  send()
}

function suggere(texte) {
  draft.value = texte
  field.value?.focus()
}

const dateLisible = date => new Date(date).toLocaleString('fr-FR', {
  day: '2-digit',
  month: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
})

onMounted(async () => {
  await fetchMessages(true)
  pollTimer = window.setInterval(() => fetchMessages(), 3000)
})
onBeforeUnmount(() => window.clearInterval(pollTimer))
</script>

<template>
  <section class="portal-chat">
    <header class="portal-chat-header">
      <span class="portal-chat-icon"><i class="fa-solid" :class="audience === 'client' ? 'fa-headset' : 'fa-user'"></i></span>
      <div class="portal-chat-title">
        <h3>{{ audience === 'client' ? 'Discussion avec le chargé client' : 'Discussion avec le client' }}</h3>
        <p>{{ audience === 'client' ? 'Vos messages sont transmis directement à l’équipe du garage.' : 'Les nouveaux messages apparaissent automatiquement.' }}</p>
      </div>
      <span class="portal-chat-live"><i /> Direct</span>
    </header>

    <div ref="thread" class="portal-chat-thread" aria-live="polite">
      <template v-if="loading">
        <span class="portal-chat-sk" style="width:62%"></span>
        <span class="portal-chat-sk me" style="width:48%"></span>
        <span class="portal-chat-sk" style="width:70%"></span>
        <span class="sr-only">Chargement de la conversation…</span>
      </template>
      <template v-else-if="messages.length">
        <article
          v-for="item in messages"
          :key="item.id"
          class="portal-chat-message"
          :class="{ mine: (audience === 'client') === (item.sender_type === 'client') }"
        >
          <strong>{{ item.sender_name }}</strong>
          <p>{{ item.message }}</p>
          <time :datetime="item.created_at">{{ dateLisible(item.created_at) }}</time>
        </article>
      </template>
      <div v-else class="portal-chat-empty">
        <span class="portal-chat-empty-icon"><i class="fa-solid fa-comments"></i></span>
        <p>Aucun message pour le moment. Commencez la conversation.</p>
        <div v-if="audience === 'client'" class="portal-chat-ideas">
          <button v-for="s in suggestions" :key="s" type="button" @click="suggere(s)">{{ s }}</button>
        </div>
      </div>
    </div>

    <form class="portal-chat-compose" @submit.prevent="send">
      <label class="sr-only" :for="`portal-chat-input-${audience}-${clientId || 'current'}`">Votre message</label>
      <textarea
        :id="`portal-chat-input-${audience}-${clientId || 'current'}`"
        ref="field"
        v-model="draft"
        rows="2"
        maxlength="2000"
        placeholder="Écrire un message…"
        required
        @keydown.enter.exact="onEnter"
      />
      <button class="portal-chat-send" :disabled="sending || !draft.trim()" aria-label="Envoyer le message">
        <i class="fa-solid" :class="sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
        <span>{{ sending ? 'Envoi…' : 'Envoyer' }}</span>
      </button>
    </form>
    <p v-if="entreeEnvoie" class="portal-chat-tip">Entrée pour envoyer, Maj + Entrée pour passer à la ligne.</p>
    <p v-if="error" class="portal-chat-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> {{ error }}</p>
  </section>
</template>

<style scoped>
/* Couleurs en dur : ce composant sert aussi côté personnel, hors de la classe .ec */
.portal-chat{overflow:hidden;border:1px solid #e5e7eb;border-radius:1.1rem;background:#fff;box-shadow:0 1px 2px #0b0f190d,0 8px 20px -14px #0b0f1940}
.portal-chat-header{display:flex;align-items:center;gap:.9rem;padding:1.1rem 1.3rem;border-bottom:1px solid #e5e7eb}
.portal-chat-icon{display:grid;flex:none;place-items:center;width:44px;height:44px;border-radius:.7rem;background:#0b0f19;color:#fff}
.portal-chat-title{flex:1;min-width:0}
.portal-chat-title h3{margin:0;color:#0b0f19;font-size:1.02rem;font-weight:800;line-height:1.25}
.portal-chat-title p{margin:.3rem 0 0;color:#6b7280;font-size:.82rem;line-height:1.4}
.portal-chat-live{display:inline-flex;align-items:center;gap:.45rem;color:#15803d;font-size:.75rem;font-weight:700}
.portal-chat-live i{width:.5rem;height:.5rem;border-radius:50%;background:#16a34a}
.portal-chat-thread{display:flex;min-height:300px;max-height:min(62vh,520px);flex-direction:column;gap:.75rem;overflow:auto;padding:1.2rem;background:#f4f5f7;overscroll-behavior:contain}
.portal-chat-message{max-width:min(86%,38rem);align-self:flex-start;border:1px solid #e5e7eb;border-radius:1rem 1rem 1rem .25rem;background:#fff;padding:.7rem 1rem;box-shadow:0 2px 6px #0b0f190a}
.portal-chat-message.mine{align-self:flex-end;border-color:#0b0f19;border-radius:1rem 1rem .25rem 1rem;background:#0b0f19;color:#fff}
.portal-chat-message strong{display:block;color:#0b0f19;font-size:.75rem}
.portal-chat-message.mine strong{color:#fda4af}
.portal-chat-message p{margin:.3rem 0;white-space:pre-wrap;overflow-wrap:anywhere;color:#1f2937;font-size:.93rem;line-height:1.45}
.portal-chat-message.mine p{color:#fff}
.portal-chat-message time{display:block;color:#6b7280;font-size:.68rem;text-align:right}
.portal-chat-message.mine time{color:#aeb3bd}
.portal-chat-empty{display:grid;justify-items:center;gap:.8rem;margin:auto;max-width:34rem;color:#6b7280;font-size:.9rem;text-align:center}
.portal-chat-empty-icon{display:grid;place-items:center;width:64px;height:64px;border-radius:50%;background:#fff;color:#e11d48;font-size:1.5rem;box-shadow:0 2px 8px #0b0f1914}
.portal-chat-ideas{display:flex;flex-wrap:wrap;justify-content:center;gap:.5rem;margin-top:.4rem}
.portal-chat-ideas button{min-height:44px;padding:0 1rem;border:1.5px solid #d9dbdf;border-radius:99px;background:#fff;color:#0b0f19;font-weight:600;font-size:.88rem;font-family:inherit;cursor:pointer;transition:border-color .2s,transform .18s}
.portal-chat-ideas button:hover{border-color:#0b0f19}
.portal-chat-ideas button:active{transform:scale(.97)}
.portal-chat-compose{display:flex;align-items:flex-end;gap:.7rem;padding:1rem;border-top:1px solid #e5e7eb}
.portal-chat-compose textarea{min-height:48px;max-height:130px;flex:1;resize:vertical;border:1.5px solid #d9dbdf;border-radius:.7rem;padding:.7rem .85rem;font:inherit;transition:border-color .2s,box-shadow .2s}
.portal-chat-compose textarea:focus{outline:0;border-color:#e11d48;box-shadow:0 0 0 4px #e11d4820}
.portal-chat-send{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-width:44px;min-height:48px;border:0;border-radius:.7rem;background:#e11d48;padding:0 1.1rem;color:#fff;font-weight:700;cursor:pointer;transition:background .2s,transform .18s}
.portal-chat-send:hover{background:#be123c}
.portal-chat-send:active{transform:scale(.96)}
.portal-chat-send:disabled{cursor:not-allowed;opacity:.5}
.portal-chat-send:focus-visible,.portal-chat-ideas button:focus-visible{outline:2px solid #e11d48;outline-offset:2px}
.portal-chat-tip{margin:-.4rem 1rem .8rem;color:#6b7280;font-size:.75rem}
.portal-chat-error{margin:0 1rem 1rem;color:#be123c;font-size:.85rem}
.portal-chat-sk{display:block;height:54px;align-self:flex-start;border-radius:1rem;background:linear-gradient(90deg,#e6e8ec 25%,#f1f2f5 50%,#e6e8ec 75%);background-size:200% 100%}
.portal-chat-sk.me{align-self:flex-end}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:520px){.portal-chat-send span{display:none}.portal-chat-live{display:none}}
@media(prefers-reduced-motion:no-preference){
  .portal-chat-live i{animation:portal-live 1.8s infinite}
  .portal-chat-sk{animation:portal-sk 1.3s linear infinite}
  .portal-chat-message{animation:portal-in .3s cubic-bezier(.16,1,.3,1) both}
}
@keyframes portal-live{0%{box-shadow:0 0 0 0 #16a34a80}70%,100%{box-shadow:0 0 0 8px #16a34a00}}
@keyframes portal-sk{to{background-position:-200% 0}}
@keyframes portal-in{from{opacity:0;transform:translateY(8px)}}
</style>
