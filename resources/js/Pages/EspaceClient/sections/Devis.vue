<script setup>
import { reactive, computed } from 'vue'
import axios from 'axios'
import { state, fcfa, toast } from '../store.js'
import { FAMILLES, FAMILLE_PAR_DEFAUT } from '@/constants/familles.js'

// Initialisation des sélections des lignes acceptées pour chaque devis
const selections = reactive(Object.fromEntries((state.user.devis || []).map(devis => [
  devis.id,
  (devis.lignes || []).filter(ligne => ligne.is_accepted).map(ligne => ligne.id),
])))

const submitting = reactive({})
const errors = reactive({})

const isSelected = (devis, ligne) => (selections[devis.id] || []).includes(ligne.id)

// Montant de la remise par ligne (pour le calcul précis)
const montantRemise = (l) => Number(l.quantite || 0) * Number(l.pu_net || 0) * Number(l.remise || 0) / 100

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
        lignes: groupes[familleNom]
      })
    }
  })

  // Récupérer les familles non listées s'il y en a
  Object.keys(groupes).forEach(familleNom => {
    if (!ordreFamilles.includes(familleNom) && groupes[familleNom].length > 0) {
      resultat.push({
        famille: familleNom,
        lignes: groupes[familleNom]
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

const classeStatut = statut => statut === 'Accepté' ? 'bg-emerald-100 text-emerald-800' : statut === 'Refusé' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'
const formatDate = date => date ? new Date(date).toLocaleDateString('fr-FR') : '—'
</script>

<template>
  <div class="space-y-6">
    <article 
      v-for="devis in state.user.devis" 
      :key="devis.id" 
      class="bg-white shadow-2xl shadow-gray-200/50 sm:rounded-2xl p-8 border border-gray-100 text-gray-800"
    >
      <!-- En-tête de devis -->
      <div class="flex justify-between items-start border-b-2 border-gray-900 pb-3 mb-3">
        <div>
          <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900">
            {{ devis.ref || `Devis #${devis.id}` }} — {{ devis.objet || 'Prestations & Réparations' }}
          </h3>
          <p class="text-xs text-gray-500 mt-0.5">
            Ordre de travail : <span class="font-semibold text-gray-700">{{ devis.numero_ot || '—' }}</span> · Date : {{ formatDate(devis.date || devis.created_at) }}
          </p>
        </div>
        <div class="text-right">
          <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider" :class="classeStatut(devis.statut)">
            {{ devis.statut }}
          </span>
        </div>
      </div>

      <p v-if="devis.selectionPossible" class="text-xs text-gray-600 mb-3 font-medium bg-gray-50 p-2 rounded border border-gray-200">
        <i class="fa-solid fa-circle-info text-[#E11D48] mr-1"></i>
        Décochez les prestations ou pièces que vous ne souhaitez pas retenir, puis enregistrez votre choix.
      </p>

      <!-- Tableau REGROUPÉ PAR FAMILLE (Design identique à FacturationShow.vue) -->
      <div class="mb-3">
        <div class="bg-gray-900 text-white px-3 py-1 rounded-t-lg">
          <p class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
            <i class="fa-solid fa-list-check text-gray-400 text-[11px]"></i>
            <span>Prestations & Pièces proposées par famille</span>
          </p>
        </div>

        <table class="min-w-full border-collapse border border-gray-900 text-xs">
          <thead>
            <tr class="bg-gray-100 border-b border-gray-900 text-center font-semibold">
              <th v-if="devis.selectionPossible" class="border-r border-gray-900 p-1 w-10">Acc.</th>
              <th class="border-r border-gray-900 p-1 w-16">Quantité</th>
              <th class="border-r border-gray-900 p-1 text-left">Désignation / Réf</th>
              <th class="border-r border-gray-900 p-1 w-20">PU Net</th>
              <th class="border-r border-gray-900 p-1 w-16">Remise</th>
              <th class="border-r border-gray-900 p-1 w-20">TVA</th>
              <th class="p-1 w-24">Montant HT</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="groupe in groupesParFamille(devis)" :key="groupe.famille">
              <!-- EN-TÊTE DE LA FAMILLE -->
              <tr class="bg-gray-200/80 border-y border-gray-900 font-bold">
                <td :colspan="devis.selectionPossible ? 7 : 6" class="px-3 py-1 text-gray-900 uppercase tracking-wider text-[11px]">
                  <i class="fa-solid fa-layer-group text-slate-600 mr-1.5"></i>
                  <span>{{ groupe.famille }}</span>
                </td>
              </tr>

              <!-- LIGNES DE LA FAMILLE -->
              <tr 
                v-for="ligne in groupe.lignes" 
                :key="ligne.id" 
                :class="{'bg-gray-50 opacity-60': devis.selectionPossible && !isSelected(devis, ligne)}" 
                class="border-b border-gray-300 text-center hover:bg-gray-50/50"
              >
                <td v-if="devis.selectionPossible" class="border-r border-gray-900 p-1">
                  <input 
                    type="checkbox" 
                    :value="ligne.id" 
                    v-model="selections[devis.id]"
                    class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]"
                  />
                </td>
                <td class="border-r border-gray-900 p-1 font-medium">{{ ligne.quantite }}</td>
                <td class="border-r border-gray-900 p-1 text-left">
                  <span class="font-medium text-gray-900">{{ ligne.designation }}</span>
                  <span v-if="ligne.sous_famille" class="text-[10px] text-gray-500 font-semibold ml-1">({{ ligne.sous_famille }})</span>
                  <span v-if="ligne.reference_piece" class="block text-[10px] text-gray-500 font-mono">Réf : {{ ligne.reference_piece }}</span>
                  <span v-if="devis.selectionPossible && !isSelected(devis, ligne)" class="text-[10px] italic text-red-600 ml-1">(Non retenu)</span>
                </td>
                <td class="border-r border-gray-900 p-1 text-right">{{ Number(ligne.pu_net).toLocaleString() }} F</td>
                <td class="border-r border-gray-900 p-1 text-right">{{ Number(ligne.remise || 0) }} %</td>
                <td class="border-r border-gray-900 p-1 text-center">
                  <span v-if="ligne.ne_pas_appliquer_tva" class="text-amber-600 font-semibold bg-amber-50 px-1 py-0.5 rounded border border-amber-200 text-[10px]">Exonéré</span>
                  <span v-else class="text-gray-600">18%</span>
                </td>
                <td class="p-1 text-right font-bold text-gray-900">{{ Number(ligne.montant_ht).toLocaleString() }} F</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Bloc Totaux (Identique à FacturationShow.vue) -->
      <div class="flex justify-end mb-3">
        <div class="w-72 border border-gray-900 text-xs rounded-lg overflow-hidden shadow-sm">
          <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
            <span class="font-semibold text-gray-700">Total HT (Accepté)</span>
            <span class="font-medium">
              {{ calculerTotaux(devis, devis.lignes.filter(l => isSelected(devis, l))).ht.toLocaleString() }} F
            </span>
          </div>
          <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
            <span class="font-semibold text-gray-700">Total Remises</span>
            <span class="font-medium">
              {{ calculerTotaux(devis, devis.lignes.filter(l => isSelected(devis, l))).remises.toLocaleString() }} F
            </span>
          </div>
          <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-white">
            <span class="font-semibold text-gray-700">TVA Totale</span>
            <span class="font-medium">
              {{ calculerTotaux(devis, devis.lignes.filter(l => isSelected(devis, l))).tva.toLocaleString() }} F
            </span>
          </div>
          <div class="flex justify-between border-b border-gray-900 px-3 py-1 bg-gray-50">
            <span class="font-semibold text-gray-700">
              Petite fourniture 
              <span v-if="calculerTotaux(devis, devis.lignes.filter(l => isSelected(devis, l))).manuel" class="text-[10px] font-normal text-gray-500">(saisie)</span>
              <span v-else class="text-[10px] font-normal text-gray-500">(3 %)</span>
            </span>
            <span class="font-medium">
              {{ calculerTotaux(devis, devis.lignes.filter(l => isSelected(devis, l))).pf.toLocaleString() }} F
            </span>
          </div>
          <div class="flex justify-between px-3 py-1.5 font-black bg-gray-200 text-sm text-gray-900">
            <span>Total TTC à Payer</span>
            <span class="text-[#E11D48]">
              {{ calculerTotaux(devis, devis.lignes.filter(l => isSelected(devis, l))).ttc.toLocaleString() }} F
            </span>
          </div>
        </div>
      </div>

      <!-- Actions pour validation client -->
      <div v-if="devis.selectionPossible" class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-200 mt-4">
        <p class="text-xs text-gray-500 italic">
          Le montant définitif pourra être ajusté par le garage selon les lignes retenues.
        </p>
        <button 
          class="px-4 py-2 bg-[#E11D48] hover:bg-rose-700 text-white font-bold uppercase tracking-wider rounded-lg shadow transition text-xs disabled:opacity-50" 
          :disabled="submitting[devis.id]" 
          @click="enregistrerChoix(devis)"
        >
          {{ submitting[devis.id] ? 'Enregistrement…' : 'Enregistrer mon choix' }}
        </button>
      </div>

      <p v-if="errors[devis.id]" class="mt-2 text-[11px] font-bold text-[#E11D48]" role="alert">
        {{ errors[devis.id] }}
      </p>
    </article>

    <div v-if="!state.user.devis || !state.user.devis.length" class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl p-12 border border-gray-100 text-center">
      <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
        <i class="fa-solid fa-file-invoice text-xl"></i>
      </div>
      <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun devis disponible</h3>
      <p class="text-xs text-gray-500 mt-1">Aucun devis n’est actuellement associé à votre dossier.</p>
    </div>
  </div>
</template>