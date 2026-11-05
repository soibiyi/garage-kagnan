<script setup>
import { computed } from 'vue'
import { state } from '../store.js'

const notifications = computed(() => {
  const items = state.user.devis
    .filter(devis => devis.statut === 'En attente')
    .map(devis => ({
      id: `devis-${devis.id}`,
      title: `Votre devis ${devis.ref} attend votre choix.`,
      date: devis.date,
    }))

  for (const intervention of state.user.interventions) {
    if (intervention.statut === 'attente_accord' && !state.user.devis.some(devis => devis.id === intervention.devis?.id)) {
      items.push({
        id: `intervention-${intervention.id}`,
        title: `Le dossier ${intervention.numero_ot || `#${intervention.id}`} attend une action.`,
        date: intervention.date_reception,
      })
    }
  }

  for (const demande of state.user.demandes.filter(demande => demande.statut === 'en_attente')) {
    const noms = {
      message: 'Votre message',
      rendez_vous: 'Votre demande de rendez-vous',
      depannage: 'Votre demande de dépannage',
      convoyage: 'Votre demande de convoyage',
    }
    items.push({
      id: `demande-${demande.id}`,
      title: `${noms[demande.type] || 'Votre demande'} est en attente de confirmation.`,
      date: demande.created_at,
    })
  }

  return items
})

const formatDate = value => value ? new Date(value).toLocaleDateString('fr-FR') : '—'
</script>

<template>
  <div class="card">
    <h3>Notifications de mon dossier</h3>
    <div v-for="notification in notifications" :key="notification.id" class="notif">
      {{ notification.title }}<br><small class="hint">{{ formatDate(notification.date) }}</small>
    </div>
    <p v-if="!notifications.length" class="hint">Aucune action en attente sur votre dossier.</p>
  </div>
</template>
