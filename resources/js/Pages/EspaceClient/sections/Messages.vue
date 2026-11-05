<script setup>
import { ref, watch, nextTick, onMounted } from 'vue'
import { state, save, now } from '../store.js'

const CANAUX = { accueil: 'Accueil', atelier: 'Atelier', facturation: 'Facturation', depannage: 'Dépannage' }
const canal = ref('accueil')
const txt = ref('')
const box = ref(null)

const down = () => nextTick(() => { if (box.value) box.value.scrollTop = box.value.scrollHeight })
watch([canal, () => state.user.messages[canal.value].length], down)
onMounted(down)

function send() {
  const c = canal.value
  state.user.messages[c].push({ me: true, t: txt.value, d: now() }); txt.value = ''; save()
  setTimeout(() => {
    state.user.messages[c].push({ me: false, t: `Le service ${CANAUX[c]} a bien reçu votre message et vous répond rapidement.`, d: now() }); save()
  }, 1200)
}
</script>

<template>
  <div class="card chat">
    <div class="chat-list">
      <a v-for="(v, k) in CANAUX" :key="k" :class="{ on: k === canal }" @click="canal = k">{{ v }}</a>
    </div>
    <div class="chat-body">
      <div ref="box" class="msgs">
        <div v-for="(m, i) in state.user.messages[canal]" :key="i" class="msg" :class="{ me: m.me }">{{ m.t }}<small>{{ m.d }}</small></div>
        <p v-if="!state.user.messages[canal].length" class="hint">Démarrez la conversation avec le service {{ CANAUX[canal] }}.</p>
      </div>
      <form class="chat-in" @submit.prevent="send">
        <input v-model="txt" placeholder="Votre message..." required>
        <button class="btn btn-primary">Envoyer</button>
      </form>
    </div>
  </div>
</template>
