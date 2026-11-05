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
let pollTimer
let polling = false

const endpoint = props.audience === 'client'
  ? '/espace-client/api/messages'
  : `/suivi-client/clients/${props.clientId}/portail/messages`

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
      <div>
        <h3>{{ audience === 'client' ? 'Discussion avec le chargé client' : 'Discussion avec le client' }}</h3>
        <p>{{ audience === 'client' ? 'Vos messages sont transmis directement à l’équipe du garage.' : 'Les nouveaux messages apparaissent automatiquement.' }}</p>
      </div>
      <span class="portal-chat-live"><i /> Direct</span>
    </header>

    <div ref="thread" class="portal-chat-thread" aria-live="polite">
      <p v-if="loading" class="portal-chat-empty">Chargement de la conversation…</p>
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
      <p v-else class="portal-chat-empty">Aucun message pour le moment. Commencez la conversation.</p>
    </div>

    <form class="portal-chat-compose" @submit.prevent="send">
      <label class="sr-only" :for="`portal-chat-input-${audience}-${clientId || 'current'}`">Votre message</label>
      <textarea
        :id="`portal-chat-input-${audience}-${clientId || 'current'}`"
        v-model="draft"
        rows="2"
        maxlength="2000"
        placeholder="Écrire un message…"
        required
      />
      <button class="portal-chat-send" :disabled="sending || !draft.trim()">
        {{ sending ? 'Envoi…' : 'Envoyer' }}
      </button>
    </form>
    <p v-if="error" class="portal-chat-error" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped>
.portal-chat{overflow:hidden;border:1px solid #e5e7eb;border-radius:1rem;background:#fff}
.portal-chat-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.2rem 1.4rem;border-bottom:1px solid #e5e7eb}
.portal-chat-header h3{margin:0;color:#0b0f19;font-size:1.05rem;font-weight:800}
.portal-chat-header p{margin:.35rem 0 0;color:#6b7280;font-size:.82rem}
.portal-chat-live{display:inline-flex;align-items:center;gap:.45rem;color:#15803d;font-size:.75rem;font-weight:700}
.portal-chat-live i{width:.5rem;height:.5rem;border-radius:50%;background:#16a34a}
.portal-chat-thread{display:flex;min-height:260px;max-height:430px;flex-direction:column;gap:.75rem;overflow:auto;padding:1.2rem;background:#f8fafc}
.portal-chat-message{max-width:min(85%,38rem);align-self:flex-start;border:1px solid #e5e7eb;border-radius:1rem 1rem 1rem .25rem;background:#fff;padding:.75rem 1rem;box-shadow:0 2px 6px #0b0f190a}
.portal-chat-message.mine{align-self:flex-end;border-color:#fecdd3;border-radius:1rem 1rem .25rem 1rem;background:#fff1f2}
.portal-chat-message strong{display:block;color:#0b0f19;font-size:.75rem}
.portal-chat-message p{margin:.35rem 0;white-space:pre-wrap;overflow-wrap:anywhere;color:#1f2937;font-size:.92rem}
.portal-chat-message time{display:block;color:#6b7280;font-size:.68rem;text-align:right}
.portal-chat-empty{margin:auto;color:#6b7280;font-size:.88rem;text-align:center}
.portal-chat-compose{display:flex;align-items:flex-end;gap:.7rem;padding:1rem}
.portal-chat-compose textarea{min-height:48px;max-height:130px;flex:1;resize:vertical;border:1px solid #d1d5db;border-radius:.7rem;padding:.7rem;font:inherit}
.portal-chat-send{min-height:44px;border:0;border-radius:.7rem;background:#e11d48;padding:.65rem 1rem;color:#fff;font-weight:700;cursor:pointer}
.portal-chat-send:hover{background:#be123c}
.portal-chat-send:disabled{cursor:not-allowed;opacity:.55}
.portal-chat-error{margin:0 1rem 1rem;color:#be123c;font-size:.85rem}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
</style>
