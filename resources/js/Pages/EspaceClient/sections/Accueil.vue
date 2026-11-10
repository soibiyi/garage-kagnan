<script setup>
import { computed } from 'vue'
import { state, go } from '../store.js'
import EtapesSuivi from './EtapesSuivi.vue'
import { estInterventionTerminee, libelleStatut, classeStatut } from './statuts.js'

const client = computed(() => state.user)
const activeInterventions = computed(() =>
  client.value.interventions.filter(({ statut }) => !estInterventionTerminee(statut) && statut !== 'annule')
)
const pendingQuotes = computed(() => client.value.devis.filter(devis => devis.statut === 'En attente'))

const prenom = computed(() => client.value.prenom || client.value.nom)
const salut = new Date().getHours() >= 18 ? 'Bonsoir' : 'Bonjour'
const enAtelier = vehicule => activeInterventions.value.some(i => i.vehicule?.immat && i.vehicule.immat === vehicule.immat)

// Prochaine chose à faire : une seule mise en avant, la plus utile pour le client
const next = computed(() => {
  const n = pendingQuotes.value.length
  if (n) {
    return {
      icon: 'fa-file-invoice',
      title: n > 1 ? `${n} devis attendent votre choix` : 'Un devis attend votre choix',
      text: 'Retenez les prestations et pièces qui vous conviennent.',
      cta: 'Consulter mes devis',
      to: 'devis',
    }
  }
  const job = activeInterventions.value[0]
  if (job) {
    return {
      icon: 'fa-screwdriver-wrench',
      title: `${job.vehicule?.modele || 'Votre véhicule'} : ${libelleStatut(job.statut).toLowerCase()}`,
      text: job.numero_ot ? `Ordre de travail ${job.numero_ot}` : 'Suivez l’avancement de l’intervention.',
      cta: 'Suivre l’intervention',
      to: 'suivi',
    }
  }
  return {
    icon: 'fa-calendar-check',
    title: 'Rien en cours pour le moment',
    text: 'Besoin d’un entretien ? Réservez un créneau à l’atelier.',
    cta: 'Prendre rendez-vous',
    to: 'rdv',
  }
})

const kpis = computed(() => [
  { label: 'Véhicules enregistrés', value: client.value.vehicules.length, icon: 'fa-car-side' },
  { label: 'Interventions en cours', value: activeInterventions.value.length, icon: 'fa-screwdriver-wrench' },
  { label: 'Devis en attente', value: pendingQuotes.value.length, icon: 'fa-file-invoice', hot: pendingQuotes.value.length > 0 },
  { label: 'Interventions terminées', value: client.value.reparations.length, icon: 'fa-circle-check' },
])

const tiles = computed(() => [
  { to: 'rdv', icon: 'fa-calendar-check', title: 'Prendre rendez-vous', sub: 'Choisir une date' },
  { to: 'depannage', icon: 'fa-truck-medical', title: 'Demander un dépannage', sub: 'Véhicule en panne' },
  { to: 'convoyage', icon: 'fa-truck-pickup', title: 'Demander un convoyage', sub: 'Départ et arrivée' },
  { to: 'devis', icon: 'fa-file-invoice', title: 'Mes devis', sub: `${client.value.devis.length} au total` },
  { to: 'historique', icon: 'fa-clock-rotate-left', title: 'Factures et paiements', sub: 'Tout l’historique' },
  { to: 'vehicules', icon: 'fa-car-side', title: 'Mes véhicules', sub: `${client.value.vehicules.length} enregistré${client.value.vehicules.length === 1 ? '' : 's'}` },
  { to: 'parametres', icon: 'fa-sliders', title: 'Mon profil', sub: 'Mes informations' },
])
</script>

<template>
  <div>
    <section class="welcome">
      <div>
        <h2>{{ salut }} {{ prenom }}</h2>
        <p class="welcome-sub">Voici l’état de votre dossier au Garage Kagnan.</p>
      </div>
      <div class="next">
        <i class="fa-solid" :class="next.icon"></i>
        <div><strong>{{ next.title }}</strong><span>{{ next.text }}</span></div>
        <button class="btn btn-primary" @click="go(next.to)">{{ next.cta }} <i class="fa-solid fa-arrow-right go"></i></button>
      </div>
    </section>

    <div class="kpis">
      <div v-for="(k, i) in kpis" :key="k.label" class="kpi" :class="{ hot: k.hot }" :style="{ '--i': i }">
        <i class="fa-solid" :class="k.icon"></i>
        <span>{{ k.label }}</span>
        <strong>{{ k.value }}</strong>
      </div>
    </div>

    <div class="card">
      <h3><i class="fa-solid fa-bolt"></i> Accès rapides</h3>
      <div class="quick-actions">
        <button v-for="t in tiles" :key="t.to" class="qa" @click="go(t.to)">
          <i class="fa-solid" :class="t.icon"></i>
          <span><strong>{{ t.title }}</strong><small>{{ t.sub }}</small></span>
          <i class="fa-solid fa-chevron-right go-i"></i>
        </button>
      </div>
    </div>

    <div class="cols">
      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-car-side"></i> Mes véhicules</h3>
          <button class="link" @click="go('vehicules')">Gérer <i class="fa-solid fa-arrow-right"></i></button>
        </div>
        <button v-for="vehicle in client.vehicules" :key="vehicle.id" class="veh vehicle-link" @click="go('vehicules')">
          <span class="plate">{{ vehicle.immat || 'Sans plaque' }}</span>
          <div><strong>{{ vehicle.modele }}</strong></div>
          <span v-if="enAtelier(vehicle)" class="st st-info"><i class="fa-solid fa-screwdriver-wrench"></i> À l’atelier</span>
          <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
        <div v-if="!client.vehicules.length" class="empty">
          <i class="fa-solid fa-car-side"></i>
          <strong>Aucun véhicule</strong>
          <p>Aucun véhicule n’est associé à votre dossier.</p>
          <button class="btn btn-light" @click="go('vehicules')"><i class="fa-solid fa-plus"></i> Ajouter un véhicule</button>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-screwdriver-wrench"></i> Suivi atelier</h3>
          <button v-if="activeInterventions.length" class="link" @click="go('suivi')">Tout voir <i class="fa-solid fa-arrow-right"></i></button>
        </div>
        <article v-for="intervention in activeInterventions" :key="intervention.id" class="job-mini">
          <div class="row-line">
            <strong>{{ intervention.vehicule.modele }}</strong>
            <span class="st" :class="classeStatut(intervention.statut)">{{ libelleStatut(intervention.statut) }}</span>
          </div>
          <EtapesSuivi :statut="intervention.statut" compact />
          <p v-if="intervention.numero_ot" class="meta"><span><i class="fa-solid fa-hashtag"></i>{{ intervention.numero_ot }}</span></p>
          <p v-if="intervention.rapport" class="hint" style="margin-top:.5rem">{{ intervention.rapport }}</p>
        </article>
        <div v-if="!activeInterventions.length" class="empty good">
          <i class="fa-solid fa-circle-check"></i>
          <strong>Aucune intervention en cours</strong>
          <p>Vos véhicules ne sont pas à l’atelier actuellement.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.vehicle-link{width:100%;text-align:left;color:inherit;cursor:pointer}
.vehicle-link>i{margin-left:auto;color:#5B5F66}
</style>
