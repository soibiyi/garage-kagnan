<script setup>
import { computed } from 'vue'
import { state, go } from '../store.js'
import EtapesSuivi from './EtapesSuivi.vue'
import { libelleStatut, classeStatut } from './statuts.js'

const interventions = computed(() => state.user.interventions)
const date = value => value ? new Date(value).toLocaleDateString('fr-FR') : 'Date non renseignée'
</script>

<template>
  <div>
    <template v-if="interventions.length">
      <article v-for="(intervention, i) in interventions" :key="intervention.id" class="card job" :style="{ '--i': i }">
        <div class="job-head">
          <div class="job-id">
            <span v-if="intervention.vehicule?.immat" class="plate">{{ intervention.vehicule.immat }}</span>
            <h3>{{ intervention.vehicule?.modele }}</h3>
          </div>
          <span class="st" :class="classeStatut(intervention.statut)">{{ libelleStatut(intervention.statut) }}</span>
        </div>
        <p class="meta">
          <span><i class="fa-solid fa-hashtag"></i>{{ intervention.numero_ot || 'Ordre de travail non attribué' }}</span>
          <span><i class="fa-regular fa-calendar"></i>{{ date(intervention.date_reception) }}</span>
        </p>

        <EtapesSuivi :statut="intervention.statut" />

        <div v-if="intervention.rapport" class="report">
          <b>Rapport de l’atelier</b>
          <p>{{ intervention.rapport }}</p>
        </div>

        <div v-if="intervention.statut === 'attente_accord'" class="alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          Le garage attend votre accord pour poursuivre.
          <button class="link" @click="go('devis')">Voir le devis <i class="fa-solid fa-arrow-right"></i></button>
        </div>
      </article>
    </template>

    <div v-else class="card">
      <div class="empty">
        <i class="fa-solid fa-screwdriver-wrench"></i>
        <strong>Aucune intervention</strong>
        <p>Aucune intervention n’est associée à vos véhicules pour le moment.</p>
        <button class="btn btn-light" @click="go('rdv')"><i class="fa-solid fa-calendar-check"></i> Prendre rendez-vous</button>
      </div>
    </div>
  </div>
</template>
