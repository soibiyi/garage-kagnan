<script setup>
import { computed, ref } from 'vue'
import { state, fcfa, go } from '../store.js'
import { estInterventionTerminee, libelleStatut, classeStatut } from './statuts.js'

const filtre = ref('tous')
const interventions = computed(() => state.user.interventions)

const estClos = estInterventionTerminee
const estOuvert = statut => !estClos(statut) && statut !== 'annule'

const filtres = computed(() => [
  { k: 'tous', label: 'Toutes', icone: 'fa-list', n: interventions.value.length },
  { k: 'cours', label: 'En cours', icone: 'fa-screwdriver-wrench', n: interventions.value.filter(i => estOuvert(i.statut)).length },
  { k: 'finies', label: 'Terminées', icone: 'fa-circle-check', n: interventions.value.filter(i => estClos(i.statut)).length },
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
const pourcentage = f => {
  const total = Number(f.montant_total || 0)
  if (total <= 0) return 0
  return Math.min(100, Math.round((Number(f.montant_paye || 0) / total) * 100))
}

// Couleur de l'intervention selon son état
const teinte = statut => {
  if (statut === 'annule') return 'tone-no'
  return estClos(statut) ? 'tone-ok' : 'tone-wait'
}

</script>

<template>
  <div>
    <div v-if="interventions.length" class="hs-filters" role="group" aria-label="Filtrer les interventions">
      <button
        v-for="f in filtres"
        :key="f.k"
        type="button"
        class="hs-filter"
        :class="{ on: filtre === f.k }"
        :aria-pressed="filtre === f.k"
        @click="filtre = f.k"
      >
        <i class="fa-solid" :class="f.icone"></i>
        {{ f.label }}
        <b>{{ f.n }}</b>
      </button>
    </div>

    <div v-if="liste.length" class="hs-list">
      <article
        v-for="(intervention, i) in liste"
        :key="intervention.id"
        class="hs-item"
        :style="{ '--i': i }"
      >
        <!-- Pastille de date sur la timeline -->
        <div class="hs-date" :class="teinte(intervention.statut)">
          <template v-if="repere(intervention.date_reception)">
            <b>{{ repere(intervention.date_reception).jour }}</b>
            <span>{{ repere(intervention.date_reception).mois }}</span>
            <small>{{ repere(intervention.date_reception).annee }}</small>
          </template>
          <template v-else>
            <b><i class="fa-regular fa-calendar"></i></b>
            <small>Sans date</small>
          </template>
        </div>

        <!-- Carte -->
        <div class="hs-card" :class="teinte(intervention.statut)">
          <header class="hs-head">
            <div class="hs-vehicle">
              <span v-if="intervention.vehicule?.immat" class="plate">{{ intervention.vehicule.immat }}</span>
              <strong class="hs-model">{{ intervention.vehicule?.modele || 'Véhicule' }}</strong>
              <span class="hs-ot"><i class="fa-solid fa-hashtag"></i>{{ intervention.numero_ot || 'Sans numéro d’OT' }}</span>
            </div>
            <span class="st" :class="classeStatut(intervention.statut)">{{ libelleStatut(intervention.statut) }}</span>
          </header>

          <div v-if="intervention.facture" class="hs-figures">
            <!-- Facture -->
            <section v-if="intervention.facture" class="hs-fig">
              <div class="hs-fig-label">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                Facture {{ intervention.facture.numero_facture }}
                <span class="st" :class="soldee(intervention.facture) ? 'st-ok' : 'st-wait'">{{ libelleFacture(intervention.facture) }}</span>
              </div>
              <strong class="hs-fig-amount">{{ fcfa(intervention.facture.montant_total) }}</strong>

              <div
                class="hs-bar"
                role="progressbar"
                :aria-valuenow="pourcentage(intervention.facture)"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="`Règlement de la facture ${intervention.facture.numero_facture}`"
              >
                <span :class="{ paid: soldee(intervention.facture) }" :style="{ width: pourcentage(intervention.facture) + '%' }"></span>
              </div>

              <div class="hs-pay">
                <span class="hs-paid"><i class="fa-solid fa-circle-check"></i> Réglé {{ fcfa(intervention.facture.montant_paye) }}</span>
                <span v-if="!soldee(intervention.facture)" class="hs-left">Reste {{ fcfa(reste(intervention.facture)) }}</span>
                <span class="hs-pct">{{ pourcentage(intervention.facture) }} %</span>
              </div>
            </section>
          </div>

          <p v-else class="hs-nofig"><i class="fa-regular fa-clock"></i> Pas encore de facture pour cette intervention.</p>
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

<style scoped>
/* ---------- Filtres ---------- */
.hs-filters{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.25rem}
.hs-filter{display:inline-flex;align-items:center;gap:.5rem;padding:.55rem .95rem;border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--ink);font:inherit;font-weight:600;font-size:.92rem;cursor:pointer;transition:border-color .15s ease,background .15s ease,color .15s ease}
.hs-filter:hover{border-color:var(--red)}
.hs-filter i{color:var(--mut);font-size:.85rem}
.hs-filter b{display:inline-grid;place-items:center;min-width:22px;height:22px;padding:0 .4rem;border-radius:999px;background:var(--bg);font-size:.78rem}
.hs-filter.on{border-color:var(--red);background:var(--red);color:#fff}
.hs-filter.on i{color:#fff}
.hs-filter.on b{background:rgba(255,255,255,.22);color:#fff}
.hs-filter:focus-visible{outline:2px solid var(--red);outline-offset:2px}

/* ---------- Timeline ---------- */
.hs-list{position:relative;display:grid;gap:1.1rem}
.hs-list::before{content:"";position:absolute;top:10px;bottom:10px;left:36px;width:2px;background:var(--line)}

.hs-item{position:relative;display:grid;grid-template-columns:74px minmax(0,1fr);gap:1rem;align-items:start;animation:hs-in .35s ease both;animation-delay:calc(var(--i, 0) * 50ms)}
@keyframes hs-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.hs-item{animation:none}}

/* Pastille de date */
.hs-date{position:relative;z-index:1;display:grid;justify-items:center;gap:0;width:74px;padding:.55rem .3rem;border:2px solid var(--line);border-radius:16px;background:#fff;text-align:center;line-height:1.15}
.hs-date b{font-size:1.45rem;font-weight:800}
.hs-date span{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.hs-date small{font-size:.7rem;color:var(--mut)}
.hs-date.tone-ok{border-color:#86efac}
.hs-date.tone-ok b,.hs-date.tone-ok span{color:#166534}
.hs-date.tone-wait{border-color:#fcd34d}
.hs-date.tone-wait b,.hs-date.tone-wait span{color:#92400e}
.hs-date.tone-no{border-color:#fda4af}
.hs-date.tone-no b,.hs-date.tone-no span{color:#9f1239}

/* Carte */
.hs-card{padding:1.1rem 1.2rem;border:1px solid var(--line);border-left:5px solid var(--line);border-radius:var(--rl, var(--r));background:#fff;transition:box-shadow .15s ease,transform .15s ease}
.hs-card:hover{box-shadow:0 8px 22px rgba(11,15,25,.08);transform:translateY(-1px)}
.hs-card.tone-ok{border-left-color:#22c55e}
.hs-card.tone-wait{border-left-color:#f59e0b}
.hs-card.tone-no{border-left-color:#e11d48}

.hs-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}
.hs-vehicle{display:grid;gap:.4rem;justify-items:start;min-width:0}
.hs-model{font-size:1.1rem;overflow-wrap:anywhere}
.hs-ot{display:inline-flex;align-items:center;gap:.35rem;color:var(--mut);font-size:.86rem}

/* Montants */
.hs-figures{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.8rem;margin-top:1rem;padding-top:1rem;border-top:1px dashed var(--line)}
.hs-fig{display:grid;gap:.4rem;align-content:start;padding:.9rem 1rem;border-radius:var(--r);background:var(--bg)}
.hs-fig-label{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem;color:var(--mut);font-size:.84rem;font-weight:600}
.hs-fig-label i{color:var(--red)}
.hs-fig-amount{font-size:1.4rem;font-weight:800;letter-spacing:-.01em}
.hs-fig-sub{color:var(--mut);font-size:.84rem}

/* Barre de règlement */
.hs-bar{height:9px;border-radius:999px;background:#e5e7eb;overflow:hidden}
.hs-bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#f59e0b,#fbbf24);transition:width .4s ease}
.hs-bar span.paid{background:linear-gradient(90deg,#16a34a,#22c55e)}
.hs-pay{display:flex;flex-wrap:wrap;gap:.3rem .9rem;align-items:center;font-size:.84rem}
.hs-paid{color:#166534;font-weight:600}
.hs-paid i{margin-right:.25rem}
.hs-left{color:#9f1239;font-weight:600}
.hs-pct{margin-left:auto;color:var(--mut);font-weight:700}

.hs-nofig{display:flex;align-items:center;gap:.5rem;margin:1rem 0 0;padding-top:1rem;border-top:1px dashed var(--line);color:var(--mut);font-size:.88rem}

/* ---------- Mobile ---------- */
@media(max-width:600px){
  .hs-list::before{display:none}
  .hs-item{grid-template-columns:1fr;gap:.5rem}
  .hs-date{width:auto;justify-self:start;grid-auto-flow:column;align-items:baseline;gap:.4rem;padding:.35rem .8rem;border-radius:999px}
  .hs-date b{font-size:1.1rem}
  .hs-date small{font-size:.75rem}
  .hs-card{padding:1rem}
  .hs-head{flex-direction:column}
  .hs-figures{grid-template-columns:1fr}
}
</style>