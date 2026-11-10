<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { fcfa, state, go } from '../store.js'
import EtapesSuivi from './EtapesSuivi.vue'
import { libelleStatut, classeStatut } from './statuts.js'

const interventions = computed(() => state.user.interventions)
const interventionSelectionnee = ref(null)
const modalOuvert = computed(() => interventionSelectionnee.value !== null)
const bodyOverflowAvantModal = ref('')
const date = value => value ? new Date(value).toLocaleDateString('fr-FR') : 'Date non renseignée'
const libellesEquipements = {
  allume_cigare: 'Allume-cigare',
  rk7: 'RK7',
  rcd: 'RCD',
  essuie_glace_av: 'Essuie-glace avant',
  essuie_glace_ar: 'Essuie-glace arrière',
  retro_ext_gauche: 'Rétroviseur extérieur gauche',
  retro_ext_droit: 'Rétroviseur extérieur droit',
  retro_int: 'Rétroviseur intérieur',
  cric: 'Cric',
  manivelle: 'Manivelle',
  roue_secours: 'Roue de secours',
  trousse: 'Trousse',
  pare_brise_fissure: 'Pare-brise fissuré',
}

function ouvrirModal(intervention) {
  interventionSelectionnee.value = intervention
}

function fermerModal() {
  interventionSelectionnee.value = null
}

function gererEchap(event) {
  if (event.key === 'Escape') fermerModal()
}

watch(modalOuvert, (ouvert) => {
  if (ouvert) {
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
</script>

<template>
  <div>
    <template v-if="interventions.length">
      <article
        v-for="(intervention, i) in interventions"
        :key="intervention.id"
        class="card job job-clickable"
        :style="{ '--i': i }"
        @click="ouvrirModal(intervention)"
      >
        <div class="job-head">
          <div class="job-id">
            <span v-if="intervention.vehicule?.immat" class="plate">{{ intervention.vehicule.immat }}</span>
            <h3>{{ intervention.vehicule?.modele }}</h3>
          </div>
          <span class="st" :class="classeStatut(intervention.statut)">{{ libelleStatut(intervention.statut) }}</span>
        </div>
        <p class="meta">
          <span><i class="fa-solid fa-hashtag"></i>{{ intervention.numero_ot || 'Ordre de travail non attribué' }}</span>
          <span><i class="fa-regular fa-calendar"></i>{{ date(intervention.date_reception) }}</span>
        </p>

        <EtapesSuivi :statut="intervention.statut" />

        <div v-if="intervention.rapport" class="report">
          <b>Rapport de l’atelier</b>
          <p>{{ intervention.rapport }}</p>
        </div>

        <div v-if="intervention.statut === 'attente_accord'" class="alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          Le garage attend votre accord pour poursuivre.
          <button class="link" @click.stop="go('devis')">Voir le devis <i class="fa-solid fa-arrow-right"></i></button>
        </div>
        <button type="button" class="link details-link" @click.stop="ouvrirModal(intervention)">
          Voir tous les détails <i class="fa-solid fa-arrow-right"></i>
        </button>
      </article>
    </template>

    <div v-else class="card">
      <div class="empty">
        <i class="fa-solid fa-screwdriver-wrench"></i>
        <strong>Aucune intervention</strong>
        <p>Aucune intervention n’est associée à vos véhicules pour le moment.</p>
        <button class="btn btn-light" @click="go('rdv')"><i class="fa-solid fa-calendar-check"></i> Prendre rendez-vous</button>
      </div>
    </div>

    <div
      v-if="interventionSelectionnee"
      class="intervention-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="intervention-modal-title"
      @click.self="fermerModal"
    >
      <section class="intervention-modal-box">
        <header class="modal-header">
          <div>
            <p class="hint">Détail de l’intervention</p>
            <h2 id="intervention-modal-title">{{ interventionSelectionnee.vehicule?.modele || 'Véhicule' }}</h2>
            <div class="modal-heading-meta">
              <span v-if="interventionSelectionnee.vehicule?.immat" class="plate">{{ interventionSelectionnee.vehicule.immat }}</span>
              <span class="st" :class="classeStatut(interventionSelectionnee.statut)">{{ libelleStatut(interventionSelectionnee.statut) }}</span>
            </div>
          </div>
          <button type="button" class="modal-close" aria-label="Fermer" @click="fermerModal">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </header>

        <div class="modal-content">
          <section class="modal-section">
            <h3><i class="fa-solid fa-clipboard-list"></i> Informations générales</h3>
            <dl class="details-grid">
              <div><dt>Ordre de travail</dt><dd>{{ interventionSelectionnee.numero_ot || 'Non attribué' }}</dd></div>
              <div><dt>Véhicule</dt><dd>{{ interventionSelectionnee.vehicule?.marque }} {{ interventionSelectionnee.vehicule?.modele_court || interventionSelectionnee.vehicule?.modele }}</dd></div>
              <div><dt>Immatriculation</dt><dd>{{ interventionSelectionnee.vehicule?.immat || 'Non renseignée' }}</dd></div>
              <div><dt>VIN / numéro de châssis</dt><dd>{{ interventionSelectionnee.vehicule?.vin || 'Non renseigné' }}</dd></div>
              <div><dt>Date de réception</dt><dd>{{ date(interventionSelectionnee.date_reception) }}</dd></div>
              <div><dt>Kilométrage à la réception</dt><dd>{{ interventionSelectionnee.kilometrage === null ? 'Non renseigné' : `${Number(interventionSelectionnee.kilometrage).toLocaleString('fr-FR')} km` }}</dd></div>
              <div><dt>Personne à contacter</dt><dd>{{ interventionSelectionnee.reception?.personne_a_contacter || 'Non renseignée' }}</dd></div>
              <div><dt>Circuit</dt><dd>{{ interventionSelectionnee.reception?.circuit === 'devis_direct' ? 'Devis direct' : 'Circuit normal' }}</dd></div>
              <div><dt>Essai effectué</dt><dd>{{ interventionSelectionnee.reception?.essai_effectue || interventionSelectionnee.statut === 'livre' ? 'Oui' : 'Non' }}</dd></div>
            </dl>
            <EtapesSuivi :statut="interventionSelectionnee.statut" />
          </section>

          <section v-if="interventionSelectionnee.rapport" class="modal-section">
            <h3><i class="fa-solid fa-file-lines"></i> Rapport de l’atelier</h3>
            <p class="modal-text">{{ interventionSelectionnee.rapport }}</p>
          </section>

          <section class="modal-section">
            <h3><i class="fa-solid fa-car-rear"></i> État à la réception</h3>
            <dl class="details-grid">
              <div><dt>Niveau de carburant</dt><dd>{{ interventionSelectionnee.reception?.niveau_carburant || 'Non renseigné' }}</dd></div>
              <div><dt>Précision carburant</dt><dd>{{ interventionSelectionnee.reception?.intervalle_niveau_carburant || 'Aucune précision' }}</dd></div>
              <div><dt>Enjoliveurs</dt><dd>{{ Array.isArray(interventionSelectionnee.reception?.enjoliveurs) ? interventionSelectionnee.reception.enjoliveurs.join(', ') || 'Aucun' : interventionSelectionnee.reception?.enjoliveurs || 'Non renseigné' }}</dd></div>
            </dl>
            <div class="equipment-grid">
              <div v-for="(label, key) in libellesEquipements" :key="key" class="equipment-item">
                <i class="fa-solid" :class="interventionSelectionnee.reception?.equipements?.[key] ? 'fa-circle-check equipment-present' : 'fa-circle-xmark equipment-absent'"></i>
                <span>{{ label }}</span>
              </div>
            </div>
            <p v-if="interventionSelectionnee.reception?.remarques" class="modal-text">
              <strong>Remarques :</strong> {{ interventionSelectionnee.reception.remarques }}
            </p>
          </section>

          <section v-if="interventionSelectionnee.reception?.photos?.length" class="modal-section">
            <h3><i class="fa-solid fa-camera"></i> Photos à la réception</h3>
            <div class="photo-grid">
              <a v-for="photo in interventionSelectionnee.reception.photos" :key="photo.label" :href="photo.url" target="_blank" rel="noopener noreferrer">
                <img :src="photo.url" :alt="`Vue ${photo.label.toLowerCase()} du véhicule`">
                <span>{{ photo.label }}</span>
              </a>
            </div>
          </section>

          <section v-if="interventionSelectionnee.facture" class="modal-section">
            <h3><i class="fa-solid fa-receipt"></i> Facture et paiements</h3>
            <dl class="details-grid">
              <div><dt>Numéro de facture</dt><dd>{{ interventionSelectionnee.facture.numero_facture }}</dd></div>
              <div><dt>Statut</dt><dd>{{ interventionSelectionnee.facture.statut }}</dd></div>
              <div><dt>Montant total</dt><dd>{{ fcfa(interventionSelectionnee.facture.montant_total) }}</dd></div>
              <div><dt>Montant réglé</dt><dd>{{ fcfa(interventionSelectionnee.facture.montant_paye) }}</dd></div>
            </dl>
            <div v-if="interventionSelectionnee.facture.paiements?.length" class="payment-list">
              <div v-for="(paiement, index) in interventionSelectionnee.facture.paiements" :key="`${paiement.date_paiement}-${index}`">
                <span>{{ date(paiement.date_paiement) }} · {{ paiement.mode_paiement }}<small v-if="paiement.notes">{{ paiement.notes }}</small></span>
                <strong>{{ fcfa(paiement.montant) }}</strong>
              </div>
            </div>
            <p v-else class="modal-text">Aucun paiement enregistré.</p>
          </section>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.job-clickable{cursor:pointer;transition:box-shadow .16s ease,transform .16s ease}
.job-clickable:hover{box-shadow:0 0 0 2px var(--red);transform:translateY(-1px)}
.details-link{margin-top:1rem}
.intervention-modal{position:fixed;inset:0;z-index:110;display:grid;place-items:center;padding:1rem;background:rgba(11,15,25,.68)}
.intervention-modal-box{display:grid;grid-template-rows:auto minmax(0,1fr);width:min(900px,100%);max-height:min(92vh,950px);overflow:hidden;border-radius:var(--rl);background:#fff;box-shadow:var(--sh)}
.modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.25rem 1.5rem;border-bottom:1px solid var(--line)}
.modal-header h2{margin:.25rem 0 .6rem;font-size:1.6rem}
.modal-heading-meta{display:flex;align-items:center;flex-wrap:wrap;gap:.55rem}
.modal-close{display:grid;place-items:center;flex:none;width:44px;height:44px;border:1px solid var(--line);border-radius:50%;background:#fff;color:var(--ink);font-size:1.1rem;cursor:pointer}
.modal-content{overflow-y:auto;padding:1.25rem 1.5rem 1.5rem}
.modal-section{padding:1rem 0;border-bottom:1px solid var(--line)}
.modal-section:last-child{border-bottom:0}
.modal-section h3{margin:0 0 .9rem}
.details-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.75rem}
.details-grid>div{padding:.75rem .9rem;border-radius:var(--r);background:var(--bg)}
.details-grid dt{color:var(--mut);font-size:.82rem}
.details-grid dd{margin-top:.25rem;font-weight:600;overflow-wrap:anywhere}
.modal-text{margin-top:.75rem;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere}
.equipment-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.5rem;margin-top:.85rem}
.equipment-item{display:flex;align-items:center;gap:.55rem;padding:.55rem .7rem;border:1px solid var(--line);border-radius:var(--r);font-size:.9rem}
.equipment-present{color:#16803c}.equipment-absent{color:var(--mut)}
.photo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem}
.photo-grid a{overflow:hidden;border:1px solid var(--line);border-radius:var(--r);color:var(--ink);text-decoration:none}
.photo-grid img{display:block;width:100%;height:130px;object-fit:cover;background:var(--bg)}
.photo-grid span{display:block;padding:.55rem .7rem;font-weight:600}
.payment-list{display:grid;gap:.4rem;margin-top:.8rem}
.payment-list>div{display:flex;justify-content:space-between;gap:1rem;padding:.65rem .75rem;border-radius:var(--r);background:var(--bg)}
.payment-list small{display:block;color:var(--mut)}
@media(max-width:600px){.intervention-modal{padding:.5rem}.intervention-modal-box{max-height:96vh;border-radius:var(--r)}.modal-header,.modal-content{padding:1rem}.details-grid,.equipment-grid{grid-template-columns:1fr}}
</style>
