<script setup>
import { onMounted } from 'vue'
import { state, save } from '../store.js'

// Copie pour afficher l'etat "non lu" avant de tout marquer comme lu
const items = state.user.notifs.map(n => ({ ...n }))
onMounted(() => { state.user.notifs.forEach(n => { n.lu = true }); save() })
</script>

<template>
  <div class="card">
    <h3>Notifications</h3>
    <div v-for="(n, i) in items" :key="i" class="notif" :class="{ read: n.lu }">{{ n.t }}<br><small>{{ n.d }}</small></div>
    <p v-if="!items.length" class="hint">Aucune notification.</p>
  </div>
</template>
