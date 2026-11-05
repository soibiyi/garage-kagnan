<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import '../../espace-client/espace-client.css'
import { state, loadSession, logout, go, ROUTES } from '../../espace-client/store.js'
import Accueil from '../../espace-client/sections/Accueil.vue'
import Ride from '../../espace-client/sections/Ride.vue'
import Rdv from '../../espace-client/sections/Rdv.vue'
import Devis from '../../espace-client/sections/Devis.vue'
import Suivi from '../../espace-client/sections/Suivi.vue'
import Historique from '../../espace-client/sections/Historique.vue'
import Messages from '../../espace-client/sections/Messages.vue'
import Notifs from '../../espace-client/sections/Notifs.vue'
import Parametres from '../../espace-client/sections/Parametres.vue'

const sections = {
  accueil: { label: 'Tableau de bord', comp: Accueil },
  depannage: { label: 'Commander une dépanneuse', comp: Ride, props: { mode: 'depannage' } },
  convoyage: { label: 'Convoyage du véhicule', comp: Ride, props: { mode: 'convoyage' } },
  rdv: { label: 'Rendez-vous atelier', comp: Rdv },
  devis: { label: 'Mes devis', comp: Devis },
  suivi: { label: 'Suivi hebdomadaire', comp: Suivi },
  historique: { label: 'Historique', comp: Historique },
  messages: { label: 'Service client', comp: Messages },
  notifs: { label: 'Notifications', comp: Notifs },
  parametres: { label: 'Paramètres', comp: Parametres },
}

const current = ref('accueil')
const menuOpen = ref(false)

const badges = computed(() => ({
  devis: state.user ? state.user.devis.filter(d => d.statut === 'En attente').length : 0,
  notifs: state.user ? state.user.notifs.filter(n => !n.lu).length : 0,
}))
const initials = computed(() => state.user.nom.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase())
const lastToast = computed(() => state.toasts[state.toasts.length - 1])

function readHash() {
  const h = window.location.hash.slice(1)
  current.value = sections[h] ? h : 'accueil'
  menuOpen.value = false
}

onMounted(() => {
  document.title = 'Mon Espace — Garage Kagnan'
  if (!loadSession()) { window.location.href = ROUTES.home; return }
  readHash()
  window.addEventListener('hashchange', readHash)
})
onBeforeUnmount(() => window.removeEventListener('hashchange', readHash))
</script>

<template>
  <div v-if="state.user" class="ec app">
    <aside class="sidebar" :class="{ open: menuOpen }">
      <a href="/" class="brand"><span class="logo">GK</span> Garage Kagnan</a>
      <nav>
        <a v-for="(s, key) in sections" :key="key" :class="{ on: current === key }" @click="go(key)">
          {{ s.label }}
          <b v-if="badges[key]" class="badge">{{ badges[key] }}</b>
        </a>
      </nav>
      <button class="btn btn-ghost full" @click="logout">Déconnexion</button>
    </aside>

    <main class="main">
      <header class="appbar">
        <button class="burger" @click="menuOpen = !menuOpen">Menu</button>
        <h1>{{ sections[current].label }}</h1>
        <div class="user"><span>{{ state.user.nom }}</span><span class="avatar">{{ initials }}</span></div>
      </header>
      <div class="view">
        <component :is="sections[current].comp" v-bind="sections[current].props || {}" :key="current" />
      </div>
    </main>

    <div v-if="lastToast" class="toast">{{ lastToast.t }}</div>
  </div>
</template>
