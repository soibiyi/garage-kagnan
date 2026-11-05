<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import './espace-client.css'
import { state, loadSession, logout, go, ROUTES } from './store.js'
import Accueil from './sections/Accueil.vue'
import Devis from './sections/Devis.vue'
import Historique from './sections/Historique.vue'
import Suivi from './sections/Suivi.vue'
import Notifs from './sections/Notifs.vue'
import Demandes from './sections/Demandes.vue'
import Conversation from './sections/Conversation.vue'
import Parametres from './sections/Parametres.vue'

const sections = {
  accueil: { label: 'Tableau de bord', comp: Accueil },
  suivi: { label: 'Suivi atelier', comp: Suivi },
  devis: { label: 'Mes devis', comp: Devis },
  historique: { label: 'Historique', comp: Historique },
  rdv: { label: 'Rendez-vous', comp: Demandes, props: { type: 'rendez_vous' } },
  depannage: { label: 'Dépannage', comp: Demandes, props: { type: 'depannage' } },
  convoyage: { label: 'Convoyage', comp: Demandes, props: { type: 'convoyage' } },
  messages: { label: 'Messages', comp: Conversation, props: { audience: 'client' } },
  notifications: { label: 'Notifications', comp: Notifs },
  parametres: { label: 'Paramètres', comp: Parametres },
}

const current = ref('accueil')
const menuOpen = ref(false)
const loadError = ref('')

const badges = computed(() => ({
  devis: state.user ? state.user.devis.filter(d => d.statut === 'En attente').length : 0,
  notifications: state.user
    ? state.user.devis.filter(d => d.statut === 'En attente').length
      + state.user.demandes.filter(d => d.statut === 'en_attente').length
    : 0,
}))
const initials = computed(() => state.user.nom.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase())
const lastToast = computed(() => state.toasts[state.toasts.length - 1])

function readHash() {
  const h = window.location.hash.slice(1)
  current.value = sections[h] ? h : 'accueil'
  menuOpen.value = false
}

onMounted(async () => {
  document.title = 'Mon Espace — Garage Kagnan'
  try {
    if (!await loadSession()) { window.location.href = `${ROUTES.home}#connexion`; return }
    readHash()
    window.addEventListener('hashchange', readHash)
  } catch {
    loadError.value = 'Impossible de charger votre dossier. Veuillez réessayer.'
  }
})
onBeforeUnmount(() => window.removeEventListener('hashchange', readHash))
</script>

<template>
  <div v-if="loadError" class="ec app">
    <main class="card" role="alert">{{ loadError }}</main>
  </div>
  <div v-else-if="state.user" class="ec app">
    <aside id="client-navigation" class="sidebar" :class="{ open: menuOpen }">
      <a href="/" class="brand"><img src="/images/logo-kagnan.png" alt="Garage Kagnan" class="brand-logo"></a>
      <nav aria-label="Navigation de l’espace client">
        <button v-for="(s, key) in sections" :key="key" class="nav-item" :class="{ on: current === key }" @click="go(key)">
          {{ s.label }}
          <b v-if="badges[key]" class="badge">{{ badges[key] }}</b>
        </button>
      </nav>
      <button class="btn btn-ghost full" @click="logout">Déconnexion</button>
    </aside>

    <button
      v-if="menuOpen"
      class="sidebar-backdrop"
      aria-label="Fermer le menu"
      @click="menuOpen = false"
    />

    <main class="main">
      <header class="appbar">
        <button
          class="burger"
          :aria-expanded="menuOpen"
          aria-controls="client-navigation"
          @click="menuOpen = !menuOpen"
        >Menu</button>
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
