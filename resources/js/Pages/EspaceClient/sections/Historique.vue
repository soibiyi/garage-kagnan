<script setup>
import { computed, ref } from 'vue'
import { state, fcfa, go } from '../store.js'
import { libelleStatut, classeStatut } from './statuts.js'

const filtre = ref('tous')
const interventions = computed(() => state.user.interventions)

const estClos = statut => ['termine', 'facture'].includes(statut)
const estOuvert = statut => !estClos(statut) && statut !== 'annule'

const filtres = computed(() => [
  { k: 'tous', label: 'Toutes', n: interventions.value.length },
  { k: 'cours', label: 'En cours', n: interventions.value.filter(i => estOuvert(i.statut)).length },
  { k: 'finies', label: 'Terminées', n: interventions.value.filter(i => estClos(i.statut)).length },
])
const liste = computed(() => interventions.value.filter(i => (
  filtre.value === 'tous' || (filtre.value === 'finies' ? estClos(i.statut) : estOuvert(i.statut))
)))

function repere(value) {
  const d = value ? new Date(value) : null
  if (!d || Number.isNaN(d.getTime())) return null
  return { jour: d.getDate(), mois: d.toLocaleDateString('fr-FR', { month: 'short' }), annee: d.getFullYear() }
}
const reste = f => Math.max(Number(f.montant_total || 0) - Number(f.montant_paye || 0), 0)
const soldee = f => Number(f.montant_total) > 0 && reste(f) === 0
const libelleFacture = f => (soldee(f) ? 'Soldée' : f.statut?.replaceAll('_', ' ') || 'À régler')
</script>

<template>
  <div>
    <div v-if="interventions.length" class="chips" role="group" aria-label="Filtrer les interventions">
      <button v-for="f in filtres" :key="f.k" class="chip-btn" :class="{ on: filtre === f.k }" :aria-pressed="filtre === f.k" @click="filtre = f.k">
        {{ f.label }} <b>{{ f.n }}</b>
      </button>
    </div>

    <div v-if="liste.length">
      <article v-for="(intervention, i) in liste" :key="intervention.id" class="hist" :style="{ '--i': i }">
        <div v-if="repere(intervention.date_reception)" class="stamp">
          <b>{{ repere(intervention.date_reception).jour }}</b>
          <span>{{ repere(intervention.date_reception).mois }}</span>
          <small>{{ repere(intervention.date_reception).annee }}</small>
        </div>
        <div v-else class="stamp"><b><i class="fa-regular fa-calendar"></i></b><small>Sans date</small></div>

        <div class="hist-body">
          <div class="hist-top">
            <div>
              <span v-if="intervention.vehicule?.immat" class="plate">{{ intervention.vehicule.immat }}</span>
              <strong>{{ intervention.vehicule?.modele }}</strong>
            </div>
            <span class="st" :class="classeStatut(intervention.statut)">{{ libelleStatut(intervention.statut) }}</span>
          </div>
          <p class="meta"><span><i class="fa-solid fa-hashtag"></i>{{ intervention.numero_ot || 'Sans numéro d’OT' }}</span></p>

          <div v-if="intervention.devis || intervention.facture" class="figures">
            <div v-if="intervention.devis" class="fig">
              <span>Devis TTC</span>
              <strong>{{ fcfa(intervention.devis.montant_total_ttc) }}</strong>
            </div>
            <div v-if="intervention.facture" class="fig">
              <span>Facture {{ intervention.facture.numero_facture }}</span>
              <strong>{{ fcfa(intervention.facture.montant_total) }}</strong>
              <progress
                class="pg"
                :class="{ paid: soldee(intervention.facture) }"
                :value="Number(intervention.facture.montant_paye || 0)"
                :max="Number(intervention.facture.montant_total || 0) || 1"
                :aria-label="`Règlement de la facture ${intervention.facture.numero_facture}`"
              ></progress>
              <small>
                <span class="st" :class="soldee(intervention.facture) ? 'st-ok' : 'st-wait'">{{ libelleFacture(intervention.facture) }}</span>
                Réglé {{ fcfa(intervention.facture.montant_paye) }}<template v-if="!soldee(intervention.facture)">, reste {{ fcfa(reste(intervention.facture)) }}</template>
              </small>
            </div>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="card">
      <div class="empty">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <strong>{{ interventions.length ? 'Rien dans cette catégorie' : 'Aucun historique' }}</strong>
        <p>{{ interventions.length ? 'Essayez un autre filtre pour voir vos interventions.' : 'Aucune intervention n’est associée à votre dossier.' }}</p>
        <button v-if="interventions.length" class="btn btn-light" @click="filtre = 'tous'">Tout afficher</button>
        <button v-else class="btn btn-light" @click="go('rdv')"><i class="fa-solid fa-calendar-check"></i> Prendre rendez-vous</button>
      </div>
    </div>
  </div>
</template>
