<script setup>
import { reactive, computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
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

// ---------- Modal ----------
const devisOuvertId = ref(null)
const devisOuvert = computed(() => (state.user.devis || []).find(d => d.id === devisOuvertId.value) || null)
const bodyOverflowAvantModal = ref('')

function ouvrirDevis(devis) {
  errors[devis.id] = ''
  devisOuvertId.value = devis.id
}
function fermerDevis() {
  devisOuvertId.value = null
}
function gererEchap(event) {
  if (event.key === 'Escape') fermerDevis()
}

watch(devisOuvertId, (id) => {
  if (id !== null) {
    bodyOverflowAvantModal.value = document.body.style.overflow
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = bodyOverflowAvantModal.value
  }
})

onMounted(() => window.addEventListener('keydown', gererEchap))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', gererEchap)
  document.body.style.overflow = bodyOverflowAvantModal.value
})

// ---------- Logique devis ----------
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
const nbAValider = computed(() => (state.user.devis || []).filter(d => d.selectionPossible).length)

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
const teinteStatut = statut => {
  if (statut === 'Accepté' || statut === 'Soldé') return 'tone-ok'
  if (statut === 'Refusé') return 'tone-no'
  return 'tone-wait'
}
const formatDate = date => (date ? new Date(date).toLocaleDateString('fr-FR') : '—')
const f = n => `${Number(n || 0).toLocaleString('fr-FR')} F`
</script>

<template>
  <div>
    <section v-if="state.user.devis && state.user.devis.length" class="card">
      <div class="card-head">
        <div>
          <h3><i class="fa-solid fa-file-invoice"></i> Mes devis</h3>
          <p class="hint">Cliquez sur un devis pour l’ouvrir en entier.</p>
        </div>
        <div class="dv-summary">
          <span class="st st-info">{{ state.user.devis.length }} devis</span>
          <span v-if="nbAValider" class="st st-wait"><i class="fa-solid fa-bell"></i> {{ nbAValider }} à valider</span>
        </div>
      </div>

      <div class="dv-list">
        <button
          v-for="devis in state.user.devis"
          :key="devis.id"
          type="button"
          class="dv-item"
          :class="{ pending: devis.selectionPossible }"
          @click="ouvrirDevis(devis)"
        >
          <span class="dv-icon" :class="teinteStatut(statutAffiche(devis))">
            <i class="fa-solid" :class="iconeStatut(statutAffiche(devis))"></i>
          </span>

          <span class="dv-main">
            <span class="dv-title">
              <strong>{{ devis.ref || `Devis #${devis.id}` }}</strong>
              <span v-if="devis.selectionPossible" class="dv-flag">À valider</span>
            </span>
            <span class="dv-obj">{{ devis.objet || 'Prestations et réparations' }}</span>
            <span class="dv-meta">
              <span><i class="fa-solid fa-hashtag"></i>{{ devis.numero_ot || '—' }}</span>
              <span><i class="fa-regular fa-calendar"></i>{{ formatDate(devis.date || devis.created_at) }}</span>
              <span><i class="fa-solid fa-list-ul"></i>{{ (devis.lignes || []).length }} ligne{{ (devis.lignes || []).length > 1 ? 's' : '' }}</span>
            </span>
          </span>

          <span class="dv-side">
            <span class="dv-amount">{{ f(totaux[devis.id].ttc) }}</span>
            <span class="st" :class="classeStatut(statutAffiche(devis))"><i class="fa-solid" :class="iconeStatut(statutAffiche(devis))"></i>{{ statutAffiche(devis) }}</span>
          </span>

          <i class="fa-solid fa-chevron-right dv-arrow" aria-hidden="true"></i>
        </button>
      </div>
    </section>

    <div v-else class="card">
      <div class="empty">
        <i class="fa-solid fa-file-invoice"></i>
        <strong>Aucun devis disponible</strong>
        <p>Aucun devis n’est actuellement associé à votre dossier.</p>
        <button class="btn btn-light" @click="go('suivi')"><i class="fa-solid fa-screwdriver-wrench"></i> Voir le suivi atelier</button>
      </div>
    </div>

    <!-- Modal : devis complet -->
    <div v-if="devisOuvert" class="dv-modal" role="dialog" aria-modal="true" aria-labelledby="dv-modal-title" @click.self="fermerDevis">
      <section class="dv-box" :class="{ 'has-footer': devisOuvert.selectionPossible }">
        <header class="dv-header">
          <div class="dv-header-main">
            <p class="hint">Devis</p>
            <h2 id="dv-modal-title">{{ devisOuvert.ref || `Devis #${devisOuvert.id}` }}</h2>
            <p class="meta">
              <span><i class="fa-solid fa-screwdriver-wrench"></i>{{ devisOuvert.objet || 'Prestations et réparations' }}</span>
              <span><i class="fa-solid fa-hashtag"></i>{{ devisOuvert.numero_ot || '—' }}</span>
              <span><i class="fa-regular fa-calendar"></i>{{ formatDate(devisOuvert.date || devisOuvert.created_at) }}</span>
            </p>
          </div>
          <div class="dv-header-side">
            <span class="st" :class="classeStatut(statutAffiche(devisOuvert))"><i class="fa-solid" :class="iconeStatut(statutAffiche(devisOuvert))"></i>{{ statutAffiche(devisOuvert) }}</span>
            <button type="button" class="dv-close" aria-label="Fermer" @click="fermerDevis">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
        </header>

        <div class="dv-content">
          <p v-if="devisOuvert.selectionPossible" class="note">
            <i class="fa-solid fa-circle-info"></i>
            Décochez les prestations ou pièces que vous ne souhaitez pas retenir, puis enregistrez votre choix.
          </p>
          <p v-else-if="estSolde(devisOuvert)" class="note">
            <i class="fa-solid fa-circle-check"></i>
            Ce devis est entièrement soldé.
          </p>

          <div class="qwrap" tabindex="0" role="region" :aria-label="`Lignes du devis ${devisOuvert.ref || devisOuvert.id}`">
            <table class="qt" :class="{ 'no-chk': !devisOuvert.selectionPossible }">
              <caption class="sr-only">Prestations et pièces proposées, regroupées par famille</caption>
              <thead>
                <tr>
                  <th v-if="devisOuvert.selectionPossible" class="c-chk"><span class="sr-only">Retenu</span></th>
                  <th class="c-name">Désignation</th>
                  <th>Qté</th>
                  <th>PU net</th>
                  <th>Remise</th>
                  <th>TVA</th>
                  <th>Montant HT</th>
                </tr>
              </thead>
              <tbody v-for="groupe in groupesParFamille(devisOuvert)" :key="groupe.famille">
                <tr class="fam-row">
                  <th :colspan="devisOuvert.selectionPossible ? 7 : 6">
                    <i class="fa-solid fa-layer-group"></i>{{ groupe.famille }}<small>{{ groupe.lignes.length }} ligne{{ groupe.lignes.length > 1 ? 's' : '' }}</small>
                  </th>
                </tr>
                <tr
                  v-for="ligne in groupe.lignes"
                  :key="ligne.id"
                  class="ql"
                  :class="{ off: devisOuvert.selectionPossible && !isSelected(devisOuvert, ligne) }"
                >
                  <td v-if="devisOuvert.selectionPossible" class="c-chk">
                    <label class="tick">
                      <input type="checkbox" :value="ligne.id" v-model="selections[devisOuvert.id]">
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
            <div><span>Total HT {{ devisOuvert.selectionPossible ? '(retenu)' : '(accepté)' }}</span><b>{{ f(totaux[devisOuvert.id].ht) }}</b></div>
            <div><span>Total remises</span><b>{{ f(totaux[devisOuvert.id].remises) }}</b></div>
            <div><span>TVA totale</span><b>{{ f(totaux[devisOuvert.id].tva) }}</b></div>
            <div>
              <span>Petite fourniture <span class="sm">{{ totaux[devisOuvert.id].manuel ? '(saisie)' : '(3 %)' }}</span></span>
              <b>{{ f(totaux[devisOuvert.id].pf) }}</b>
            </div>
            <div class="quote-grand-total"><span>Total TTC à payer</span><strong>{{ f(totaux[devisOuvert.id].ttc) }}</strong></div>
          </div>

          <p v-if="errors[devisOuvert.id]" class="err" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ errors[devisOuvert.id] }}</p>
        </div>

        <footer v-if="devisOuvert.selectionPossible" class="dv-footer">
          <p>
            <b>{{ nbRetenues(devisOuvert) }} ligne{{ nbRetenues(devisOuvert) > 1 ? 's' : '' }}</b> retenue{{ nbRetenues(devisOuvert) > 1 ? 's' : '' }} sur {{ (devisOuvert.lignes || []).length }}.
            <span class="dv-footer-sub">Le montant définitif pourra être ajusté par le garage.</span>
          </p>
          <div class="dv-footer-right">
            <span class="dv-footer-total">{{ f(totaux[devisOuvert.id].ttc) }}</span>
            <button class="btn btn-primary" :disabled="submitting[devisOuvert.id]" @click="enregistrerChoix(devisOuvert)">
              <i class="fa-solid" :class="submitting[devisOuvert.id] ? 'fa-spinner fa-spin' : 'fa-check'"></i>
              {{ submitting[devisOuvert.id] ? 'Enregistrement…' : 'Enregistrer mon choix' }}
            </button>
          </div>
        </footer>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* ---------- Case à cocher ---------- */
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

/* ---------- Liste des devis ---------- */
.dv-summary{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:flex-end}
.dv-list{display:grid;gap:.8rem}
.dv-item{
  position:relative;
  display:flex;
  align-items:center;
  gap:1rem;
  width:100%;
  padding:1rem 1.1rem;
  border:1px solid var(--line);
  border-radius:var(--rl, var(--r));
  background:#fff;
  color:var(--ink);
  text-align:left;
  cursor:pointer;
  transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease;
}
.dv-item:hover{transform:translateY(-2px);border-color:var(--red);box-shadow:0 8px 22px rgba(11,15,25,.1)}
.dv-item:focus-visible{outline:2px solid var(--red);outline-offset:2px}
.dv-item.pending{border-left:4px solid #f59e0b}

.dv-icon{display:grid;place-items:center;flex:none;width:48px;height:48px;border-radius:14px;font-size:1.2rem}
.tone-ok{background:#dcfce7;color:#166534}
.tone-no{background:#ffe4e6;color:#9f1239}
.tone-wait{background:#fef3c7;color:#92400e}

.dv-main{display:grid;gap:.25rem;min-width:0;flex:1}
.dv-title{display:flex;align-items:center;flex-wrap:wrap;gap:.5rem;font-size:1.05rem}
.dv-flag{padding:.15rem .55rem;border-radius:999px;background:#fef3c7;color:#92400e;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.dv-obj{color:var(--ink);opacity:.85;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dv-meta{display:flex;flex-wrap:wrap;gap:.3rem 1rem;color:var(--mut);font-size:.84rem}
.dv-meta i{margin-right:.35rem}

.dv-side{display:grid;justify-items:end;gap:.45rem;flex:none}
.dv-amount{font-size:1.15rem;font-weight:800;white-space:nowrap}
.dv-arrow{flex:none;color:var(--mut);transition:transform .15s ease,color .15s ease}
.dv-item:hover .dv-arrow{transform:translateX(3px);color:var(--red)}

/* ---------- Modal ---------- */
.dv-modal{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:1rem;background:rgba(11,15,25,.68)}
.dv-box{
  display:grid;
  grid-template-rows:auto minmax(0,1fr);
  width:min(1040px,100%);
  max-height:min(94vh,980px);
  overflow:hidden;
  border-radius:var(--rl);
  background:#fff;
  box-shadow:var(--sh);
}
.dv-box.has-footer{grid-template-rows:auto minmax(0,1fr) auto}

.dv-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.25rem 1.5rem;border-bottom:1px solid var(--line);background:linear-gradient(180deg,var(--bg),#fff)}
.dv-header-main{min-width:0}
.dv-header h2{margin:.2rem 0 .5rem;font-size:1.7rem}
.dv-header-side{display:flex;align-items:center;gap:.75rem;flex:none}
.dv-close{display:grid;place-items:center;flex:none;width:44px;height:44px;border:1px solid var(--line);border-radius:50%;background:#fff;color:var(--ink);font-size:1.1rem;cursor:pointer;transition:border-color .15s ease,color .15s ease}
.dv-close:hover{border-color:var(--red);color:var(--red)}

.dv-content{display:grid;gap:1rem;align-content:start;overflow-y:auto;padding:1.25rem 1.5rem 1.5rem}

.dv-footer{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:1rem;
  padding:1rem 1.5rem;
  border-top:1px solid var(--line);
  background:#fff;
  box-shadow:0 -6px 18px rgba(11,15,25,.06);
}
.dv-footer p{margin:0;line-height:1.4}
.dv-footer-sub{display:block;color:var(--mut);font-size:.84rem}
.dv-footer-right{display:flex;align-items:center;gap:1rem;flex:none}
.dv-footer-total{font-size:1.25rem;font-weight:800;white-space:nowrap}

/* ---------- Mobile ---------- */
@media(max-width:700px){
  .dv-item{flex-wrap:wrap}
  .dv-main{flex-basis:calc(100% - 100px)}
  .dv-side{order:3;width:100%;grid-auto-flow:column;justify-content:space-between;align-items:center;padding-top:.7rem;border-top:1px dashed var(--line)}
  .dv-arrow{display:none}
  .dv-modal{padding:.5rem}
  .dv-box{max-height:97vh;border-radius:var(--r)}
  .dv-header,.dv-content,.dv-footer{padding:1rem}
  .dv-header h2{font-size:1.35rem}
  .dv-header-side{flex-direction:column-reverse;align-items:flex-end}
  .dv-footer{flex-direction:column;align-items:stretch}
  .dv-footer-right{justify-content:space-between}
}
</style>