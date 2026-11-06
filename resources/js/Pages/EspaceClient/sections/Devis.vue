<script setup>
import { reactive, computed } from 'vue'
import axios from 'axios'
import { state, fcfa, toast, go } from '../store.js'
import { FAMILLES, FAMILLE_PAR_DEFAUT } from '@/constants/familles.js'

// Initialisation des sélections des lignes acceptées pour chaque devis
const selections = reactive(Object.fromEntries((state.user.devis || []).map(devis => [
  devis.id,
  (devis.lignes || []).filter(ligne => ligne.is_accepted).map(ligne => ligne.id),
])))

const submitting = reactive({})
const errors = reactive({})

const isSelected = (devis, ligne) => (selections[devis.id] || []).includes(ligne.id)

const statutAffiche = devis => {
  if (devis?.facture) {
    const total = Number(devis.facture.montant_total || 0)
    const paye = Number(devis.facture.montant_paye || 0)
    return total > 0 && paye >= total ? 'Soldé' : 'Facturé'
  }

  return devis?.statut || 'En attente'
}

const estSolde = devis => Boolean(devis?.facture) && Number(devis.facture.montant_total || 0) > 0 && Number(devis.facture.montant_paye || 0) >= Number(devis.facture.montant_total || 0)

// Montant de la remise par ligne (pour le calcul précis)
const montantRemise = l => Number(l.quantite || 0) * Number(l.pu_net || 0) * Number(l.remise || 0) / 100

// Calcul des totaux identiques à FacturationShow.vue (HT, Remises, TVA, Petite Fourniture, TTC)
const calculerTotaux = (devis, lignesSelectionnees) => {
  const ht = lignesSelectionnees.reduce((a, l) => a + Number(l.montant_ht || 0), 0)
  const ttcBrut = lignesSelectionnees.reduce((a, l) => a + Number(l.montant_ttc || 0), 0)
  const remises = Math.round(lignesSelectionnees.reduce((a, l) => a + montantRemise(l), 0))

  const actif = devis.petite_fourniture_active === null || devis.petite_fourniture_active === undefined
    ? true : !!devis.petite_fourniture_active
  const manuel = devis.petite_fourniture_montant !== null && devis.petite_fourniture_montant !== undefined

  let pf = 0
  if (ttcBrut > 0 && actif) {
    pf = manuel
      ? Math.round(ttcBrut + Number(devis.petite_fourniture_montant)) - Math.round(ttcBrut)
      : Math.round(ttcBrut * 1.03) - Math.round(ttcBrut)
  }

  const tva = ttcBrut - ht
  const ttc = Math.round(ttcBrut) + pf

  return { ht, remises, tva, pf, manuel, ttc }
}

// Totaux recalculés en direct à chaque case cochée ou décochée
const totaux = computed(() => Object.fromEntries((state.user.devis || []).map(devis => [
  devis.id,
  calculerTotaux(devis, (devis.lignes || []).filter(l => isSelected(devis, l))),
])))
const nbRetenues = devis => (devis.lignes || []).filter(l => isSelected(devis, l)).length

// Organiser et grouper les lignes par famille selon l'ordre prédéfini dans familles.js
function groupesParFamille(devis) {
  const lignes = devis.lignes || []
  if (!lignes.length) return []

  const groupes = {}

  lignes.forEach(ligne => {
    const familleNom = ligne.famille || FAMILLE_PAR_DEFAUT
    if (!groupes[familleNom]) {
      groupes[familleNom] = []
    }
    groupes[familleNom].push(ligne)
  })

  // Ordre d'affichage basé sur les clés de FAMILLES, puis FAMILLE_PAR_DEFAUT à la fin
  const ordreFamilles = [...Object.keys(FAMILLES), FAMILLE_PAR_DEFAUT]

  const resultat = []
  ordreFamilles.forEach(familleNom => {
    if (groupes[familleNom] && groupes[familleNom].length > 0) {
      resultat.push({
        famille: familleNom,
        lignes: groupes[familleNom],
      })
    }
  })

  // Récupérer les familles non listées s'il y en a
  Object.keys(groupes).forEach(familleNom => {
    if (!ordreFamilles.includes(familleNom) && groupes[familleNom].length > 0) {
      resultat.push({
        famille: familleNom,
        lignes: groupes[familleNom],
      })
    }
  })

  return resultat
}

async function enregistrerChoix(devis) {
  submitting[devis.id] = true
  errors[devis.id] = ''

  try {
    await axios.patch(`/espace-client/api/devis/${devis.id}/choix`, {
      lignes_acceptees: selections[devis.id],
    })

    for (const ligne of devis.lignes) {
      ligne.is_accepted = isSelected(devis, ligne)
    }
    devis.selectionPossible = false
    devis.statut = 'Accepté'
    toast('Votre choix de devis a été transmis au Garage Kagnan.')
  } catch (error) {
    errors[devis.id] = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible d’enregistrer votre choix. Réessayez.'
  } finally {
    submitting[devis.id] = false
  }
}

const classeStatut = statut => {
  if (statut === 'Accepté' || statut === 'Soldé') return 'st-ok'
  if (statut === 'Refusé') return 'st-no'
  if (statut === 'Facturé') return 'st-wait'
  return 'st-wait'
}
const iconeStatut = statut => {
  if (statut === 'Accepté' || statut === 'Soldé') return 'fa-circle-check'
  if (statut === 'Refusé') return 'fa-circle-xmark'
  if (statut === 'Facturé') return 'fa-file-invoice-dollar'
  return 'fa-hourglass-half'
}
const formatDate = date => (date ? new Date(date).toLocaleDateString('fr-FR') : '—')
const f = n => `${Number(n || 0).toLocaleString('fr-FR')} F`
</script>

<template>
  <div>
    <article v-for="(devis, i) in state.user.devis" :key="devis.id" class="card devis" :style="{ '--i': i }">
      <header class="devis-head">
        <div>
          <h3>{{ devis.ref || `Devis #${devis.id}` }}</h3>
          <p class="meta">
            <span><i class="fa-solid fa-screwdriver-wrench"></i>{{ devis.objet || 'Prestations et réparations' }}</span>
            <span><i class="fa-solid fa-hashtag"></i>{{ devis.numero_ot || '—' }}</span>
            <span><i class="fa-regular fa-calendar"></i>{{ formatDate(devis.date || devis.created_at) }}</span>
          </p>
        </div>
        <span class="st" :class="classeStatut(statutAffiche(devis))"><i class="fa-solid" :class="iconeStatut(statutAffiche(devis))"></i>{{ statutAffiche(devis) }}</span>
      </header>

      <div class="devis-body">
        <p v-if="devis.selectionPossible" class="note">
          <i class="fa-solid fa-circle-info"></i>
          Décochez les prestations ou pièces que vous ne souhaitez pas retenir, puis enregistrez votre choix.
        </p>
        <p v-else-if="estSolde(devis)" class="note">
          <i class="fa-solid fa-circle-check"></i>
          Ce devis est entièrement soldé.
        </p>

        <div class="qwrap" tabindex="0" role="region" :aria-label="`Lignes du devis ${devis.ref || devis.id}`">
          <table class="qt" :class="{ 'no-chk': !devis.selectionPossible }">
            <caption class="sr-only">Prestations et pièces proposées, regroupées par famille</caption>
            <thead>
              <tr>
                <th v-if="devis.selectionPossible" class="c-chk"><span class="sr-only">Retenu</span></th>
                <th class="c-name">Désignation</th>
                <th>Qté</th>
                <th>PU net</th>
                <th>Remise</th>
                <th>TVA</th>
                <th>Montant HT</th>
              </tr>
            </thead>
            <tbody v-for="groupe in groupesParFamille(devis)" :key="groupe.famille">
              <tr class="fam-row">
                <th :colspan="devis.selectionPossible ? 7 : 6">
                  <i class="fa-solid fa-layer-group"></i>{{ groupe.famille }}<small>{{ groupe.lignes.length }} ligne{{ groupe.lignes.length > 1 ? 's' : '' }}</small>
                </th>
              </tr>
              <tr
                v-for="ligne in groupe.lignes"
                :key="ligne.id"
                class="ql"
                :class="{ off: devis.selectionPossible && !isSelected(devis, ligne) }"
              >
                <td v-if="devis.selectionPossible" class="c-chk">
                  <label class="tick">
                    <input type="checkbox" :value="ligne.id" v-model="selections[devis.id]">
                    <span class="sr-only">Retenir {{ ligne.designation }}</span>
                  </label>
                </td>
                <td class="c-name">
                  <strong>{{ ligne.designation }}</strong>
                  <small v-if="ligne.sous_famille || ligne.reference_piece">
                    <template v-if="ligne.sous_famille">{{ ligne.sous_famille }}</template>
                    <template v-if="ligne.reference_piece"><template v-if="ligne.sous_famille"> – </template>Réf. {{ ligne.reference_piece }}</template>
                  </small>
                </td>
                <td class="c-qty" data-l="Qté">{{ ligne.quantite }}</td>
                <td class="c-pu" data-l="PU net">{{ f(ligne.pu_net) }}</td>
                <td class="c-rem" data-l="Remise">{{ Number(ligne.remise || 0) }} %</td>
                <td class="c-tva" data-l="TVA">
                  <span v-if="ligne.ne_pas_appliquer_tva" class="exo">Exonéré</span>
                  <template v-else>18 %</template>
                </td>
                <td class="c-ht">{{ f(ligne.montant_ht) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="quote-totals">
          <div><span>Total HT {{ devis.selectionPossible ? '(retenu)' : '(accepté)' }}</span><b>{{ f(totaux[devis.id].ht) }}</b></div>
          <div><span>Total remises</span><b>{{ f(totaux[devis.id].remises) }}</b></div>
          <div><span>TVA totale</span><b>{{ f(totaux[devis.id].tva) }}</b></div>
          <div>
            <span>Petite fourniture <span class="sm">{{ totaux[devis.id].manuel ? '(saisie)' : '(3 %)' }}</span></span>
            <b>{{ f(totaux[devis.id].pf) }}</b>
          </div>
          <div class="quote-grand-total"><span>Total TTC à payer</span><strong>{{ f(totaux[devis.id].ttc) }}</strong></div>
        </div>

        <div v-if="devis.selectionPossible" class="devis-actions">
          <p>
            <b>{{ nbRetenues(devis) }} ligne{{ nbRetenues(devis) > 1 ? 's' : '' }}</b> retenue{{ nbRetenues(devis) > 1 ? 's' : '' }} sur {{ (devis.lignes || []).length }}.
            Le montant définitif pourra être ajusté par le garage.
          </p>
          <button class="btn btn-primary" :disabled="submitting[devis.id]" @click="enregistrerChoix(devis)">
            <i class="fa-solid" :class="submitting[devis.id] ? 'fa-spinner fa-spin' : 'fa-check'"></i>
            {{ submitting[devis.id] ? 'Enregistrement…' : 'Enregistrer mon choix' }}
          </button>
        </div>

        <p v-if="errors[devis.id]" class="err" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ errors[devis.id] }}</p>
      </div>
    </article>

    <div v-if="!state.user.devis || !state.user.devis.length" class="card">
      <div class="empty">
        <i class="fa-solid fa-file-invoice"></i>
        <strong>Aucun devis disponible</strong>
        <p>Aucun devis n’est actuellement associé à votre dossier.</p>
        <button class="btn btn-light" @click="go('suivi')"><i class="fa-solid fa-screwdriver-wrench"></i> Voir le suivi atelier</button>
      </div>
    </div>
  </div>
</template>
<style scoped>
.tick{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  margin:0;
  cursor:pointer;
}
.tick input[type=checkbox]{
  flex:none;
  appearance:none;
  -webkit-appearance:none;
  display:inline-block;
  width:18px;
  height:18px;
  min-height:18px;
  margin:0;
  padding:0;
  border:2px solid #8a8d8f;
  border-radius:4px;
  background:#fff;
  box-shadow:none;
  transition:none;
  cursor:pointer;
}
.tick input[type=checkbox]:checked{
  border-color:#e11d48;
  background:#e11d48 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3.5 8.5l3 3 6-7'/%3E%3C/svg%3E") center/80% no-repeat;
}
.tick input[type=checkbox]:focus-visible{
  outline:2px solid #e11d48;
  outline-offset:2px;
}
</style>
