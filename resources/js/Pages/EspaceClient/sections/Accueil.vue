<script setup>
import { computed } from 'vue'
import { state, go } from '../store.js'

const client = computed(() => state.user)
const activeInterventions = computed(() =>
  client.value.interventions.filter(({ statut }) => !['termine', 'facture', 'annule'].includes(statut))
)
const pendingQuotes = computed(() => client.value.devis.filter(devis => devis.statut === 'En attente'))

const statutLisible = statut => ({
  reception: 'Réception',
  atelier: 'À l’atelier',
  en_cours: 'En cours',
  attente_accord: 'En attente de votre accord',
  diagnostic: 'Diagnostic',
  facture: 'Facturé',
  termine: 'Terminé',
})[statut] || statut?.replaceAll('_', ' ') || 'Non renseigné'
</script>

<template>
  <div>
    <div class="kpis">
      <div class="kpi"><span>Véhicules enregistrés</span><strong>{{ client.vehicules.length }}</strong></div>
      <div class="kpi"><span>Interventions en cours</span><strong>{{ activeInterventions.length }}</strong></div>
      <div class="kpi"><span>Devis en attente</span><strong>{{ pendingQuotes.length }}</strong></div>
      <div class="kpi"><span>Interventions terminées</span><strong>{{ client.reparations.length }}</strong></div>
    </div>

    <div class="card">
      <h3>Accès rapides</h3>
      <div class="quick-actions">
        <button class="btn btn-light" @click="go('parametres')">Mes véhicules et mon profil</button>
        <button class="btn btn-light" @click="go('devis')">Mes devis ({{ client.devis.length }})</button>
        <button class="btn btn-light" @click="go('historique')">Interventions, factures et paiements</button>
      </div>
    </div>

    <div class="cols">
      <div class="card">
        <h3>Mes véhicules</h3>
        <div v-for="vehicle in client.vehicules" :key="vehicle.id" class="notif">
          <strong>{{ vehicle.modele }}</strong>
          <p class="hint">{{ vehicle.immat || 'Immatriculation non renseignée' }}</p>
        </div>
        <p v-if="!client.vehicules.length" class="hint">Aucun véhicule n’est associé à votre dossier.</p>
      </div>

      <div class="card">
        <h3>Suivi atelier</h3>
        <div v-for="intervention in activeInterventions" :key="intervention.id" class="notif">
          <strong>{{ intervention.vehicule.modele }} — {{ statutLisible(intervention.statut) }}</strong>
          <p v-if="intervention.numero_ot" class="hint">Ordre de travail : {{ intervention.numero_ot }}</p>
          <p v-if="intervention.rapport" class="hint">{{ intervention.rapport }}</p>
        </div>
        <p v-if="!activeInterventions.length" class="hint">Aucune intervention en cours.</p>
        <button v-if="pendingQuotes.length" class="btn btn-primary" style="margin-top:14px" @click="go('devis')">
          Consulter mes devis
        </button>
      </div>
    </div>
  </div>
</template>
