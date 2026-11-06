<script setup>
import { computed } from 'vue'
import { state, go } from '../store.js'

const DESTINATIONS = { message: 'messages', rendez_vous: 'rdv', depannage: 'depannage', convoyage: 'convoyage' }
const NOMS = {
  message: 'Votre message',
  rendez_vous: 'Votre demande de rendez-vous',
  depannage: 'Votre demande de dépannage',
  convoyage: 'Votre demande de convoyage',
}

const notifications = computed(() => {
  const items = state.user.devis
    .filter(devis => devis.statut === 'En attente')
    .map(devis => ({
      id: `devis-${devis.id}`,
      title: `Votre devis ${devis.ref} attend votre choix.`,
      date: devis.date,
      icon: 'fa-file-invoice',
      to: 'devis',
      cta: 'Choisir mes lignes',
    }))

  for (const intervention of state.user.interventions) {
    if (intervention.statut === 'attente_accord' && !state.user.devis.some(devis => devis.id === intervention.devis?.id)) {
      items.push({
        id: `intervention-${intervention.id}`,
        title: `Le dossier ${intervention.numero_ot || `#${intervention.id}`} attend une action.`,
        date: intervention.date_reception,
        icon: 'fa-screwdriver-wrench',
        to: 'suivi',
        cta: 'Voir le suivi',
      })
    }
  }

  for (const demande of state.user.demandes.filter(demande => demande.statut === 'en_attente')) {
    items.push({
      id: `demande-${demande.id}`,
      title: `${NOMS[demande.type] || 'Votre demande'} est en attente de confirmation.`,
      date: demande.created_at,
      icon: 'fa-paper-plane',
      to: DESTINATIONS[demande.type] || 'accueil',
      cta: 'Voir ma demande',
    })
  }

  // Les plus récentes d'abord ; les entrées sans date à la fin
  const time = value => (value ? new Date(value).getTime() || 0 : 0)
  return items.sort((a, b) => time(b.date) - time(a.date))
})

const formatDate = value => (value ? new Date(value).toLocaleDateString('fr-FR') : '—')
</script>

<template>
  <div class="card">
    <div class="card-head">
      <h3><i class="fa-solid fa-bell"></i> Notifications de mon dossier</h3>
      <span v-if="notifications.length" class="st st-wait">{{ notifications.length }} à traiter</span>
    </div>

    <div v-if="notifications.length" class="nt-list">
      <div v-for="(notification, i) in notifications" :key="notification.id" class="nt" :style="{ '--i': i }">
        <i class="fa-solid" :class="notification.icon"></i>
        <div>
          <strong>{{ notification.title }}</strong>
          <small>{{ formatDate(notification.date) }}</small>
        </div>
        <button class="btn btn-light" @click="go(notification.to)">{{ notification.cta }} <i class="fa-solid fa-arrow-right go"></i></button>
      </div>
    </div>

    <div v-else class="empty good">
      <i class="fa-solid fa-circle-check"></i>
      <strong>Tout est à jour</strong>
      <p>Aucune action en attente sur votre dossier.</p>
    </div>
  </div>
</template>
