<script setup>
import { state, save, notify, fcfa, totalDevis } from '../store.js'

function repondre(i, s) {
  const u = state.user, d = u.devis[i]
  d.statut = s
  if (s === 'Accepté') u.suivi = { actif: true, etape: 2, objet: d.objet, montant: totalDevis(d) }
  save(); notify(`Devis ${d.ref} ${s.toLowerCase()}.`)
}
const imprimer = () => window.print()
const cls = s => s === 'Accepté' ? 'st-ok' : s === 'Refusé' ? 'st-no' : 'st-wait'
</script>

<template>
  <div>
    <div v-for="(d, i) in state.user.devis" :key="d.ref" class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">{{ d.ref }} — {{ d.objet }}</h3>
        <span class="st" :class="cls(d.statut)">{{ d.statut }}</span>
      </div>
      <table>
        <tr><th>Désignation</th><th style="text-align:right">Montant</th></tr>
        <tr v-for="(l, k) in d.lignes" :key="k"><td>{{ l[0] }}</td><td style="text-align:right">{{ fcfa(l[1]) }}</td></tr>
        <tr><td><b>Total TTC</b></td><td style="text-align:right"><b>{{ fcfa(totalDevis(d)) }}</b></td></tr>
      </table>
      <template v-if="d.statut === 'En attente'">
        <p class="hint" style="margin:12px 0">L'acceptation du devis nécessite un acompte de 70 % : {{ fcfa(totalDevis(d) * 0.7) }}</p>
        <button class="btn btn-ok" @click="repondre(i, 'Accepté')">Accepter le devis</button>
        <button class="btn btn-light" @click="repondre(i, 'Refusé')">Refuser</button>
      </template>
      <button class="btn btn-light" @click="imprimer">Imprimer</button>
    </div>
    <div v-if="!state.user.devis.length" class="card">Aucun devis.</div>
  </div>
</template>
