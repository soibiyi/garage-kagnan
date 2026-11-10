<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import './espace-client.css'
import './dashboard.css'
import { state, loadSession, logout, go, ROUTES } from './store.js'
import Accueil from './sections/Accueil.vue'
import Devis from './sections/Devis.vue'
import Historique from './sections/Historique.vue'
import Suivi from './sections/Suivi.vue'
import Notifs from './sections/Notifs.vue'
import Demandes from './sections/Demandes.vue'
import DemandesMission from './sections/DemandesMission.vue'
import Conversation from './sections/Conversation.vue'
import Parametres from './sections/Parametres.vue'
import Vehicules from './sections/Vehicules.vue'

const sections = {
  accueil: { label: 'Tableau de bord', short: 'Accueil', icon: 'fa-gauge-high', group: 'Mon dossier', sub: 'Votre dossier en un coup d’œil', comp: Accueil },
  suivi: { label: 'Suivi atelier', short: 'Suivi', icon: 'fa-screwdriver-wrench', group: 'Mon dossier', sub: 'Où en sont vos véhicules', comp: Suivi },
  devis: { label: 'Mes devis', short: 'Devis', icon: 'fa-file-invoice', group: 'Mon dossier', sub: 'Retenez les lignes qui vous conviennent', comp: Devis },
  historique: { label: 'Historique', short: 'Historique', icon: 'fa-clock-rotate-left', group: 'Mon dossier', sub: 'Interventions, factures et paiements', comp: Historique },
  rdv: { label: 'Rendez-vous', short: 'Rendez-vous', icon: 'fa-calendar-check', group: 'Mes demandes', sub: 'Réservez un créneau à l’atelier', comp: Demandes, props: { type: 'rendez_vous' } },
  depannage: { label: 'Dépannage', short: 'Dépannage', icon: 'fa-truck-medical', group: 'Mes demandes', sub: 'Votre véhicule est en panne', comp: DemandesMission, props: { type: 'depannage' } },
  convoyage: { label: 'Convoyage', short: 'Convoyage', icon: 'fa-truck-pickup', group: 'Mes demandes', sub: 'Faites déplacer votre véhicule', comp: DemandesMission, props: { type: 'convoyage' } },
  messages: { label: 'Messages', short: 'Messages', icon: 'fa-comments', group: 'Échanges', sub: 'Écrivez directement au garage', comp: Conversation, props: { audience: 'client' } },
  notifications: { label: 'Notifications', short: 'Alertes', icon: 'fa-bell', group: 'Échanges', sub: 'Ce qui attend une action de votre part', comp: Notifs },
  vehicules: { label: 'Mes véhicules', short: 'Véhicules', icon: 'fa-car-side', group: 'Mon compte', sub: 'Vos véhicules et leurs informations', comp: Vehicules },
  parametres: { label: 'Paramètres', short: 'Paramètres', icon: 'fa-sliders', group: 'Mon compte', sub: 'Profil et mot de passe', comp: Parametres },
}

const groupes = Object.entries(sections).reduce((acc, [key, s]) => {
  (acc[s.group] ||= []).push(key)
  return acc
}, {})
const rapides = ['accueil', 'suivi', 'devis', 'messages']

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
  window.scrollTo({ top: 0 })
}
const reload = () => window.location.reload()
const onKey = e => { if (e.key === 'Escape') menuOpen.value = false }

onMounted(async () => {
  document.title = 'Mon Espace — Garage Kagnan'
  try {
    if (!await loadSession()) { window.location.href = `${ROUTES.home}#connexion`; return }
    readHash()
    window.addEventListener('hashchange', readHash)
    window.addEventListener('keydown', onKey)
  } catch {
    loadError.value = 'Impossible de charger votre dossier. Veuillez réessayer.'
  }
})
onBeforeUnmount(() => {
  window.removeEventListener('hashchange', readHash)
  window.removeEventListener('keydown', onKey)
})
</script>

<template>
  <div v-if="loadError" class="ec app">
    <main class="main">
      <div class="view">
        <div class="card" role="alert">
          <div class="empty">
            <i class="fa-solid fa-plug-circle-exclamation"></i>
            <strong>Dossier indisponible</strong>
            <p>{{ loadError }}</p>
            <button class="btn btn-primary" @click="reload"><i class="fa-solid fa-rotate-right"></i> Réessayer</button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div v-else-if="state.user" class="ec app">
    <aside id="client-navigation" class="sidebar" :class="{ open: menuOpen }">
      <a href="/" class="brand"><img src="/images/logo-kagnan.png" alt="Garage Kagnan" class="brand-logo"></a>
      <nav aria-label="Navigation de l’espace client">
        <div v-for="(keys, titre) in groupes" :key="titre" class="nav-group">
          <p class="nav-title">{{ titre }}</p>
          <button
            v-for="key in keys"
            :key="key"
            class="nav-item"
            :class="{ on: current === key }"
            :aria-current="current === key ? 'page' : null"
            @click="go(key)"
          >
            <i class="fa-solid" :class="sections[key].icon"></i>
            {{ sections[key].label }}
            <b v-if="badges[key]" class="badge">{{ badges[key] }}</b>
          </button>
        </div>
      </nav>
      <div class="side-user">
        <span class="avatar">{{ initials }}</span>
        <div>
          <strong>{{ state.user.nom }}</strong>
          <small v-if="state.user.siege"><i class="fa-solid fa-location-dot"></i>{{ state.user.siege }}</small>
        </div>
      </div>
      <button class="btn btn-ghost full" @click="logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Déconnexion</button>
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
          aria-label="Ouvrir le menu"
          :aria-expanded="menuOpen"
          aria-controls="client-navigation"
          @click="menuOpen = !menuOpen"
        ><i class="fa-solid fa-bars"></i></button>
        <div class="appbar-title">
          <h1>{{ sections[current].label }}</h1>
          <p>{{ sections[current].sub }}</p>
        </div>
        <button
          class="icon-btn"
          :aria-label="badges.notifications ? `Notifications, ${badges.notifications} en attente` : 'Notifications'"
          @click="go('notifications')"
        >
          <i class="fa-solid fa-bell"></i>
          <b v-if="badges.notifications" class="badge">{{ badges.notifications }}</b>
        </button>
        <div class="user"><span>{{ state.user.nom }}</span><span class="avatar">{{ initials }}</span></div>
      </header>

      <div class="view">
        <Transition name="ec-page" mode="out-in">
          <component :is="sections[current].comp" v-bind="sections[current].props || {}" :key="current" />
        </Transition>
      </div>
    </main>

    <nav class="tabbar" aria-label="Accès rapide">
      <button
        v-for="key in rapides"
        :key="key"
        :class="{ on: current === key }"
        :aria-current="current === key ? 'page' : null"
        @click="go(key)"
      >
        <i class="fa-solid" :class="sections[key].icon"></i>
        {{ sections[key].short }}
        <b v-if="badges[key]" class="badge">{{ badges[key] }}</b>
      </button>
      <button :class="{ on: menuOpen }" :aria-expanded="menuOpen" aria-controls="client-navigation" @click="menuOpen = !menuOpen">
        <i class="fa-solid fa-ellipsis"></i>
        Plus
      </button>
    </nav>

    <Transition name="ec-toast">
      <div v-if="lastToast" class="toast" role="status"><i class="fa-solid fa-circle-check"></i>{{ lastToast.t }}</div>
    </Transition>
  </div>

  <div v-else class="ec boot" role="status" aria-busy="true">
    <span class="sr-only">Chargement de votre espace</span>
    <div class="boot-bar">
      <span class="sk" style="width:44px;height:44px"></span>
      <span class="sk" style="width:180px;height:26px"></span>
    </div>
    <div class="view">
      <span class="sk" style="height:210px;border-radius:18px;margin-bottom:1.1rem"></span>
      <div class="kpis">
        <span v-for="n in 4" :key="n" class="sk" style="height:110px;border-radius:18px"></span>
      </div>
    </div>
  </div>
</template>
