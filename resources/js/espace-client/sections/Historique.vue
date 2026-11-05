<script setup>
import { state, fcfa, totalDevis } from '../store.js'
</script>

<template>
  <div>
    <div class="card">
      <h3>Commandes (dépannage et convoyage)</h3>
      <table v-if="state.user.missions.length">
        <tr><th>Date</th><th>Service</th><th>Distance</th><th>Prix</th><th>Statut</th></tr>
        <tr v-for="(m, i) in state.user.missions" :key="i">
          <td>{{ m.date }}</td><td>{{ m.type }}<br><small class="hint">{{ m.option }}</small></td>
          <td>{{ m.km }} km</td><td>{{ fcfa(m.prix) }}</td><td><span class="st st-info">{{ m.statut }}</span></td>
        </tr>
      </table>
      <p v-else class="hint">Aucune commande.</p>
    </div>
    <div class="card">
      <h3>Réparations</h3>
      <table v-if="state.user.reparations.length">
        <tr><th>Date</th><th>Intervention</th><th>Lieu</th><th>Montant</th></tr>
        <tr v-for="(r, i) in state.user.reparations" :key="i"><td>{{ r.date }}</td><td>{{ r.objet }}</td><td>{{ r.lieu }}</td><td>{{ fcfa(r.montant) }}</td></tr>
      </table>
      <p v-else class="hint">Aucune réparation enregistrée.</p>
    </div>
    <div class="card">
      <h3>Devis</h3>
      <table>
        <tr><th>Référence</th><th>Objet</th><th>Montant</th><th>Statut</th></tr>
        <tr v-for="d in state.user.devis" :key="d.ref"><td>{{ d.ref }}</td><td>{{ d.objet }}</td><td>{{ fcfa(totalDevis(d)) }}</td><td>{{ d.statut }}</td></tr>
      </table>
    </div>
  </div>
</template>
