<script setup>
import { computed } from 'vue'
import { state } from '../store.js'

const interventions = computed(() => state.user.interventions)
const libelleStatut = statut => ({
  reception: 'Réception du véhicule',
  atelier: 'À l’atelier',
  en_cours: 'Intervention en cours',
  diagnostic: 'Diagnostic',
  attente_accord: 'En attente de votre accord',
  accepte: 'Devis accepté',
  facture: 'Facturé',
  termine: 'Terminé',
  annule: 'Annulé',
})[statut] || statut?.replaceAll('_', ' ') || 'Statut non renseigné'
const date = value => value ? new Date(value).toLocaleDateString('fr-FR') : 'Date non renseignée'
</script>

<template>
  <div class="card">
    <h3>Suivi de mes interventions</h3>
    <div v-if="interventions.length">
      <article v-for="intervention in interventions" :key="intervention.id" class="notif">
        <div class="row-line">
          <strong>{{ intervention.vehicule.modele }}</strong>
          <span class="st st-info">{{ libelleStatut(intervention.statut) }}</span>
        </div>
        <p class="hint">
          {{ intervention.numero_ot || 'Ordre de travail non attribué' }}
          · {{ date(intervention.date_reception) }}
        </p>
        <p v-if="intervention.rapport" class="hint">{{ intervention.rapport }}</p>
      </article>
    </div>
    <p v-else class="hint">Aucune intervention n’est associée à vos véhicules pour le moment.</p>
  </div>
</template>
