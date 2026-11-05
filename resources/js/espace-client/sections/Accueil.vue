<script setup>
import { computed } from 'vue'
import { state, save, notify, go, ETAPES } from '../store.js'

const U = computed(() => state.user)
const last = computed(() => U.value.releves[0])
const relance = computed(() => !last.value || (Date.now() - last.value.ts) > 7 * 864e5)
const enAttente = computed(() => U.value.devis.filter(d => d.statut === 'En attente').length)

function avancer() {
  const u = U.value
  u.suivi.etape++; save()
  notify(`Votre ${u.vehicules[0].modele} : ${ETAPES[u.suivi.etape]}.`)
  if (u.suivi.etape === ETAPES.length - 1) {
    u.reparations.unshift({ date: new Date().toLocaleDateString('fr-FR'), objet: u.suivi.objet || 'Intervention atelier', lieu: 'Garage Kagnan', montant: u.suivi.montant || 0 })
    save()
  }
}
</script>

<template>
  <div>
    <div v-if="relance" class="alert">
      Pensez à renseigner votre suivi hebdomadaire (kilométrage, assurance).
      <a style="color:var(--or);font-weight:700" @click="go('suivi')">Mettre à jour</a>
    </div>
    <div class="kpis">
      <div class="kpi"><span>Véhicule</span><strong style="font-size:17px">{{ U.vehicules[0].modele }}</strong><span>{{ U.vehicules[0].immat }}</span></div>
      <div class="kpi"><span>Kilométrage</span><strong>{{ last ? last.km.toLocaleString('fr-FR') + ' km' : '—' }}</strong></div>
      <div class="kpi"><span>Devis en attente</span><strong>{{ enAttente }}</strong></div>
      <div class="kpi"><span>Réparations</span><strong>{{ U.reparations.length }}</strong></div>
    </div>
    <div class="cols">
      <div class="card timeline">
        <h3>Suivi de mon véhicule à l'atelier</h3>
        <template v-if="U.suivi.actif">
          <div v-for="(e, i) in ETAPES" :key="i" class="mock-step" :class="{ done: i < U.suivi.etape, active: i === U.suivi.etape }">{{ e }}</div>
          <button v-if="U.suivi.etape < ETAPES.length - 1" class="btn btn-light" style="margin-top:14px" @click="avancer">Simuler l'étape suivante (démo)</button>
        </template>
        <p v-else class="hint">Aucun véhicule à l'atelier actuellement. Prenez rendez-vous ou commandez un convoyage.</p>
      </div>
      <div class="card">
        <h3>Actions rapides</h3>
        <div style="display:grid;gap:10px">
          <button class="btn btn-primary" @click="go('depannage')">Commander une dépanneuse</button>
          <button class="btn btn-light" @click="go('convoyage')">Faire récupérer mon véhicule</button>
          <button class="btn btn-light" @click="go('rdv')">Prendre rendez-vous</button>
          <button class="btn btn-light" @click="go('messages')">Contacter le service client</button>
        </div>
      </div>
    </div>
  </div>
</template>
