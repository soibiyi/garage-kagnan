<script setup>
import { reactive } from 'vue'
import { state, save, notify } from '../store.js'

const today = new Date().toISOString().slice(0, 10)
const heures = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00']
const prestas = ['Révision / vidange', 'Freinage', 'Diagnostic électronique', 'Climatisation', 'Carrosserie', 'Autre']
const first = state.user.vehicules[0]
const f = reactive({ presta: prestas[0], date: '', heure: heures[0], veh: `${first.modele} — ${first.immat}`, desc: '' })

function add() {
  const r = { ...f, date: new Date(f.date).toLocaleDateString('fr-FR') }
  state.user.rdv.unshift(r); save()
  notify(`Rendez-vous confirmé le ${r.date} à ${r.heure}.`)
  f.date = ''; f.desc = ''
}
</script>

<template>
  <div class="cols">
    <div class="card">
      <h3>Prendre rendez-vous à l'atelier</h3>
      <form @submit.prevent="add">
        <label>Prestation<select v-model="f.presta"><option v-for="p in prestas" :key="p">{{ p }}</option></select></label>
        <div class="row2">
          <label>Date<input v-model="f.date" type="date" required :min="today"></label>
          <label>Heure<select v-model="f.heure"><option v-for="h in heures" :key="h">{{ h }}</option></select></label>
        </div>
        <label>Véhicule<select v-model="f.veh"><option v-for="v in state.user.vehicules" :key="v.immat">{{ v.modele }} — {{ v.immat }}</option></select></label>
        <label>Description<textarea v-model="f.desc" rows="3"></textarea></label>
        <button class="btn btn-primary">Confirmer le rendez-vous</button>
      </form>
    </div>
    <div class="card">
      <h3>Mes rendez-vous</h3>
      <template v-if="state.user.rdv.length">
        <div v-for="(r, i) in state.user.rdv" :key="i" class="notif"><b>{{ r.presta }}</b><br><small>{{ r.date }} à {{ r.heure }} — {{ r.veh }}</small></div>
      </template>
      <p v-else class="hint">Aucun rendez-vous.</p>
    </div>
  </div>
</template>
