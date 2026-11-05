<script setup>
import { state, fcfa } from '../store.js'

const formatDate = date => date ? new Date(date).toLocaleDateString('fr-FR') : '—'
</script>

<template>
  <div class="card">
    <h3>Historique des interventions</h3>
    <div v-if="state.user.interventions.length" class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Véhicule</th><th>Ordre de travail</th><th>Statut</th><th>Devis TTC</th><th>Facture / paiement</th></tr></thead>
        <tbody>
          <tr v-for="intervention in state.user.interventions" :key="intervention.id">
            <td>{{ formatDate(intervention.date_reception) }}</td>
            <td>{{ intervention.vehicule.modele }}<br><small class="hint">{{ intervention.vehicule.immat }}</small></td>
            <td>{{ intervention.numero_ot || '—' }}</td>
            <td>{{ intervention.statut.replaceAll('_', ' ') }}</td>
            <td>{{ intervention.devis ? fcfa(intervention.devis.montant_total_ttc) : '—' }}</td>
            <td v-if="intervention.facture">
              {{ intervention.facture.numero_facture }}<br>
              <small class="hint">{{ intervention.facture.statut.replaceAll('_', ' ') }} — réglé {{ fcfa(intervention.facture.montant_paye) }} / {{ fcfa(intervention.facture.montant_total) }}</small>
            </td>
            <td v-else>—</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="hint">Aucune intervention n’est associée à votre dossier.</p>
  </div>
</template>
