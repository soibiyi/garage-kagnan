<script setup>
import { reactive } from 'vue'
import { state, save, notify, toast } from '../store.js'

const f = reactive({ km: '', assureur: '', echeance: '', lieu: '', derniere: '' })

function add() {
  const u = state.user
  const jours = (new Date(f.echeance) - Date.now()) / 864e5
  const r = { ...f, km: +f.km, ts: Date.now(), date: new Date().toLocaleDateString('fr-FR'), echeance: new Date(f.echeance).toLocaleDateString('fr-FR') }
  const prev = u.releves[0]
  u.releves.unshift(r); save()
  if (jours < 30) notify(`Votre assurance expire dans ${Math.max(0, Math.ceil(jours))} jours.`)
  if (prev && Math.floor(r.km / 10000) > Math.floor(prev.km / 10000)) notify('Vous avez franchi un palier de 10 000 km : une révision est recommandée.')
  toast('Relevé enregistré.')
  Object.assign(f, { km: '', assureur: '', echeance: '', lieu: '', derniere: '' })
}
</script>

<template>
  <div class="cols">
    <div class="card">
      <h3>Mon relevé de la semaine</h3>
      <p class="hint" style="margin-bottom:14px">Ces informations nous permettent d'anticiper vos entretiens et de vous alerter avant les échéances.</p>
      <form @submit.prevent="add">
        <label>Kilométrage actuel<input v-model="f.km" type="number" required min="0"></label>
        <div class="row2">
          <label>Assureur<input v-model="f.assureur" required></label>
          <label>Échéance assurance<input v-model="f.echeance" type="date" required></label>
        </div>
        <label>Lieu de la dernière réparation<input v-model="f.lieu" placeholder="Garage Kagnan ou autre" required></label>
        <label>Nature de la dernière réparation<input v-model="f.derniere"></label>
        <button class="btn btn-primary">Enregistrer</button>
      </form>
    </div>
    <div class="card">
      <h3>Mes relevés</h3>
      <table v-if="state.user.releves.length">
        <tr><th>Date</th><th>Km</th><th>Assurance</th></tr>
        <tr v-for="(r, i) in state.user.releves" :key="i">
          <td>{{ r.date }}</td><td>{{ (+r.km).toLocaleString('fr-FR') }}</td>
          <td>{{ r.assureur }}<br><small class="hint">fin {{ r.echeance }}</small></td>
        </tr>
      </table>
      <p v-else class="hint">Aucun relevé.</p>
    </div>
  </div>
</template>
