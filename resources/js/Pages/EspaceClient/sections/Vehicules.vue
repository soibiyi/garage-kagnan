<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { state, toast } from '../store.js'

const vehicules = computed(() => state.user.vehicules)
const vehiculeSelectionnee = ref(null)
const modalOuvert = ref(false)
const modalAjoutOuvert = ref(false)
const uneModaleOuverte = computed(() => modalOuvert.value || modalAjoutOuvert.value)
const ongletActif = ref('infos')
const erreur = ref('')
const erreurReparation = ref('')
const enregistrement = ref(false)
const enregistrementReparation = ref(false)
const vehicule = reactive({
  marque: '',
  modele: '',
  immatriculation: '',
  vin: '',
  expiration_assurance: '',
  expiration_sicta: '',
})
const modification = reactive({
  expiration_assurance: '',
  expiration_sicta: '',
  kilometrage: '',
})
const reparation = reactive({
  date_reparation: '',
  lieu: '',
  description: '',
  kilometrage: '',
  montant: '',
})
const bodyOverflowAvantModal = ref('')

function dateLisible(value) {
  if (!value) return 'Non renseignée'
  const [year, month, day] = value.slice(0, 10).split('-')
  return year && month && day ? `${day}/${month}/${year}` : 'Non renseignée'
}

function statutDate(value) {
  if (!value) return { classe: 'exp-none', label: 'Non renseignée' }
  const fin = new Date(value.slice(0, 10) + 'T23:59:59')
  const jours = Math.ceil((fin - new Date()) / 86400000)
  if (jours < 0) return { classe: 'exp-bad', label: 'Expirée' }
  if (jours <= 30) return { classe: 'exp-warn', label: `${jours} j restant${jours > 1 ? 's' : ''}` }
  return { classe: 'exp-ok', label: 'Valide' }
}

function formatKm(value) {
  if (value === null || value === undefined || value === '') return 'Non renseigné'
  return `${Number(value).toLocaleString('fr-FR')} km`
}

function ouvrirModal(item) {
  vehiculeSelectionnee.value = item
  Object.assign(modification, {
    expiration_assurance: item.expiration_assurance || '',
    expiration_sicta: item.expiration_sicta || '',
    kilometrage: item.kilometrage ?? '',
  })
  erreur.value = ''
  erreurReparation.value = ''
  ongletActif.value = 'infos'
  modalOuvert.value = true
}

function fermerModal() {
  modalOuvert.value = false
}

function ouvrirModalAjout() {
  reinitialiserFormulaire()
  erreur.value = ''
  modalAjoutOuvert.value = true
}

function fermerModalAjout() {
  if (!enregistrement.value) modalAjoutOuvert.value = false
}

async function enregistrerInformations() {
  if (!vehiculeSelectionnee.value) return
  erreur.value = ''
  enregistrement.value = true

  try {
    const { data } = await axios.patch(
      `/espace-client/api/vehicules/${vehiculeSelectionnee.value.id}`,
      modification,
    )
    Object.assign(vehiculeSelectionnee.value, data.vehicle)
    toast('Les informations du véhicule ont été mises à jour.')
  } catch (error) {
    erreur.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible de mettre à jour ce véhicule.'
  } finally {
    enregistrement.value = false
  }
}

function reinitialiserReparation() {
  Object.assign(reparation, {
    date_reparation: '',
    lieu: '',
    description: '',
    kilometrage: '',
    montant: '',
  })
}

async function ajouterReparation() {
  if (!vehiculeSelectionnee.value) return
  erreurReparation.value = ''
  enregistrementReparation.value = true

  try {
    const { data } = await axios.post(
      `/espace-client/api/vehicules/${vehiculeSelectionnee.value.id}/reparations`,
      reparation,
    )
    vehiculeSelectionnee.value.reparations_externes ||= []
    vehiculeSelectionnee.value.reparations_externes.push(data.repair)
    vehiculeSelectionnee.value.reparations_externes.sort((a, b) =>
      b.date_reparation.localeCompare(a.date_reparation) || b.id - a.id
    )
    reinitialiserReparation()
    toast('Réparation hors garage ajoutée à l’historique.')
  } catch (error) {
    erreurReparation.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible d’ajouter cette réparation.'
  } finally {
    enregistrementReparation.value = false
  }
}

function reinitialiserFormulaire() {
  Object.assign(vehicule, {
    marque: '',
    modele: '',
    immatriculation: '',
    vin: '',
    expiration_assurance: '',
    expiration_sicta: '',
  })
}

async function ajouterVehicule() {
  erreur.value = ''
  enregistrement.value = true

  try {
    const { data } = await axios.post('/espace-client/api/vehicules', vehicule)
    vehicules.value.push(data.vehicle)
    modalAjoutOuvert.value = false
    ouvrirModal(data.vehicle)
    reinitialiserFormulaire()
    toast('Véhicule ajouté à votre dossier.')
  } catch (error) {
    erreur.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible d’ajouter ce véhicule.'
  } finally {
    enregistrement.value = false
  }
}

function gererEchap(event) {
  if (event.key === 'Escape') {
    if (modalAjoutOuvert.value) fermerModalAjout()
    else fermerModal()
  }
}

watch(uneModaleOuverte, (ouvert) => {
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
  <div class="stack">
    <section class="card">
      <div class="card-head">
        <div>
          <h3><i class="fa-solid fa-car-side"></i> Mes véhicules</h3>
          <p class="hint">Sélectionnez un véhicule pour consulter ses informations.</p>
        </div>
        <span class="st st-info">{{ vehicules.length }} véhicule{{ vehicules.length === 1 ? '' : 's' }}</span>
      </div>

      <div v-if="vehicules.length" class="vehicle-list">
        <button
          v-for="item in vehicules"
          :key="item.id"
          type="button"
          class="vehicle-card"
          @click="ouvrirModal(item)"
        >
          <div class="vc-top">
            <span class="vc-icon"><i class="fa-solid fa-car"></i></span>
            <div class="vc-title">
              <span class="vc-brand">{{ item.marque || 'Marque non renseignée' }}</span>
              <strong class="vc-model">{{ item.modele || 'Véhicule' }}</strong>
            </div>
            <i class="fa-solid fa-chevron-right vc-arrow" aria-hidden="true"></i>
          </div>

          <span class="plate">{{ item.immat || 'Sans plaque' }}</span>

          <div class="vc-meta">
            <span><i class="fa-solid fa-gauge-high"></i> {{ formatKm(item.kilometrage) }}</span>
            <span><i class="fa-solid fa-screwdriver-wrench"></i> {{ item.reparations_externes?.length || 0 }} réparation(s) externe(s)</span>
          </div>

          <div class="vc-badges">
            <span class="exp" :class="statutDate(item.expiration_assurance).classe">
              <i class="fa-solid fa-shield-halved"></i> Assurance · {{ statutDate(item.expiration_assurance).label }}
            </span>
            <span class="exp" :class="statutDate(item.expiration_sicta).classe">
              <i class="fa-solid fa-file-circle-check"></i> SICTA · {{ statutDate(item.expiration_sicta).label }}
            </span>
          </div>
        </button>
      </div>

      <div v-else class="empty">
        <i class="fa-solid fa-car-side"></i>
        <strong>Aucun véhicule</strong>
        <p>Aucun véhicule n’est encore associé à votre dossier.</p>
      </div>

      <div class="add-vehicle bottom-add">
        <button type="button" class="btn btn-light full" @click="ouvrirModalAjout">
          <i class="fa-solid fa-plus"></i> Ajouter un nouveau véhicule
        </button>
      </div>
    </section>

    <div v-if="modalAjoutOuvert" class="vehicle-modal" role="dialog" aria-modal="true" aria-labelledby="add-vehicle-title" @click.self="fermerModalAjout">
      <section class="vehicle-modal-box vehicle-add-modal-box">
        <header class="modal-header">
          <div>
            <p class="hint">Mes véhicules</p>
            <h2 id="add-vehicle-title">Ajouter un véhicule</h2>
          </div>
          <button type="button" class="modal-close" aria-label="Fermer" @click="fermerModalAjout">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </header>
        <form class="modal-content vehicle-form vehicle-add-form" @submit.prevent="ajouterVehicule">
          <div class="row2">
            <label>Marque <span>(facultative)</span><input v-model="vehicule.marque" maxlength="100" autocomplete="organization"></label>
            <label>Modèle<input v-model="vehicule.modele" required maxlength="100"></label>
          </div>
          <label>Immatriculation<input v-model="vehicule.immatriculation" required maxlength="50" placeholder="2381 GK 01" autocapitalize="characters"></label>
          <label>VIN / numéro de châssis<input v-model="vehicule.vin" required maxlength="100" autocomplete="off"></label>
          <div class="row2">
            <label>Date d’expiration de l’assurance<input v-model="vehicule.expiration_assurance" type="date" required></label>
            <label>Date d’expiration SICTA<input v-model="vehicule.expiration_sicta" type="date" required></label>
          </div>
          <button class="btn btn-primary full" :disabled="enregistrement">
            <i class="fa-solid" :class="enregistrement ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
            {{ enregistrement ? 'Enregistrement…' : 'Enregistrer le véhicule' }}
          </button>
          <p v-if="erreur" class="err" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ erreur }}</p>
        </form>
      </section>
    </div>

    <div v-if="modalOuvert && vehiculeSelectionnee" class="vehicle-modal" role="dialog" aria-modal="true" aria-labelledby="vehicle-modal-title" @click.self="fermerModal">
      <section class="vehicle-modal-box">
        <header class="modal-header">
          <div>
            <p class="hint">Fiche véhicule</p>
            <h2 id="vehicle-modal-title">{{ vehiculeSelectionnee.modele || 'Véhicule' }}</h2>
            <span class="plate">{{ vehiculeSelectionnee.immat || 'Sans plaque' }}</span>
          </div>
          <button type="button" class="modal-close" aria-label="Fermer" @click="fermerModal">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </header>

        <nav class="tabs" role="tablist">
          <button
            type="button"
            role="tab"
            class="tab"
            :class="{ active: ongletActif === 'infos' }"
            :aria-selected="ongletActif === 'infos'"
            @click="ongletActif = 'infos'"
          >
            <i class="fa-solid fa-pen-to-square"></i> Informations
          </button>
          <button
            type="button"
            role="tab"
            class="tab"
            :class="{ active: ongletActif === 'reparations' }"
            :aria-selected="ongletActif === 'reparations'"
            @click="ongletActif = 'reparations'"
          >
            <i class="fa-solid fa-screwdriver-wrench"></i> Réparations
            <span class="tab-count">{{ vehiculeSelectionnee.reparations_externes?.length || 0 }}</span>
          </button>
        </nav>

        <div class="modal-content">
          <!-- Onglet 1 : informations et modifications -->
          <div v-show="ongletActif === 'infos'" role="tabpanel">
            <dl class="details-grid">
              <div><dt>Marque</dt><dd>{{ vehiculeSelectionnee.marque || 'Non renseignée' }}</dd></div>
              <div><dt>Modèle</dt><dd>{{ vehiculeSelectionnee.modele_court || 'Non renseigné' }}</dd></div>
              <div><dt>VIN / numéro de châssis</dt><dd>{{ vehiculeSelectionnee.vin || 'Non renseigné' }}</dd></div>
              <div><dt>Dernier kilométrage enregistré</dt><dd>{{ formatKm(vehiculeSelectionnee.kilometrage) }}</dd></div>
              <div>
                <dt>Assurance</dt>
                <dd>
                  {{ dateLisible(vehiculeSelectionnee.expiration_assurance) }}
                  <span class="exp" :class="statutDate(vehiculeSelectionnee.expiration_assurance).classe">{{ statutDate(vehiculeSelectionnee.expiration_assurance).label }}</span>
                </dd>
              </div>
              <div>
                <dt>SICTA</dt>
                <dd>
                  {{ dateLisible(vehiculeSelectionnee.expiration_sicta) }}
                  <span class="exp" :class="statutDate(vehiculeSelectionnee.expiration_sicta).classe">{{ statutDate(vehiculeSelectionnee.expiration_sicta).label }}</span>
                </dd>
              </div>
            </dl>

            <form class="vehicle-form" @submit.prevent="enregistrerInformations">
              <h3><i class="fa-solid fa-pen-to-square"></i> Assurance, SICTA et kilométrage</h3>
              <div class="row2">
                <label>Date d’expiration de l’assurance<input v-model="modification.expiration_assurance" type="date" required></label>
                <label>Date d’expiration SICTA<input v-model="modification.expiration_sicta" type="date" required></label>
              </div>
              <label>Kilométrage actuel (km)<input v-model="modification.kilometrage" type="number" min="0" max="99999999999" step="1" required></label>
              <button class="btn btn-primary" :disabled="enregistrement">
                <i class="fa-solid" :class="enregistrement ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                {{ enregistrement ? 'Enregistrement…' : 'Enregistrer les modifications' }}
              </button>
              <p v-if="erreur" class="err" role="alert">{{ erreur }}</p>
            </form>
          </div>

          <!-- Onglet 2 : réparations hors garage -->
          <div v-show="ongletActif === 'reparations'" role="tabpanel">
            <section class="repair-section">
              <div class="card-head">
                <h3><i class="fa-solid fa-screwdriver-wrench"></i> Réparations hors garage</h3>
                <span class="st st-info">{{ vehiculeSelectionnee.reparations_externes?.length || 0 }}</span>
              </div>
              <div v-if="vehiculeSelectionnee.reparations_externes?.length" class="repair-list">
                <article v-for="item in vehiculeSelectionnee.reparations_externes" :key="item.id" class="repair-item">
                  <div class="repair-heading">
                    <strong>{{ item.lieu }}</strong>
                    <time :datetime="item.date_reparation">{{ dateLisible(item.date_reparation) }}</time>
                  </div>
                  <p>{{ item.description }}</p>
                  <div class="repair-meta">
                    <span v-if="item.kilometrage !== null && item.kilometrage !== ''">{{ Number(item.kilometrage).toLocaleString('fr-FR') }} km</span>
                    <span v-if="item.montant !== null && item.montant !== ''">{{ Number(item.montant).toLocaleString('fr-FR') }} FCFA</span>
                  </div>
                </article>
              </div>
              <div v-else class="empty repair-empty">
                <i class="fa-solid fa-clipboard-list"></i>
                <strong>Aucune réparation enregistrée hors garage</strong>
                <p>Ajoutez ici les réparations effectuées ailleurs.</p>
              </div>
            </section>

            <form class="vehicle-form" @submit.prevent="ajouterReparation">
              <h3><i class="fa-solid fa-plus"></i> Ajouter une réparation hors garage</h3>
              <div class="row2">
                <label>Date de réparation<input v-model="reparation.date_reparation" type="date" :max="new Date().toISOString().slice(0, 10)" required></label>
                <label>Lieu / garage<input v-model="reparation.lieu" maxlength="255" required></label>
              </div>
              <label>Réparation effectuée<input v-model="reparation.description" maxlength="2000" required placeholder="Ex. remplacement des plaquettes de frein"></label>
              <div class="row2">
                <label>Kilométrage au moment de la réparation (facultatif)<input v-model="reparation.kilometrage" type="number" min="0" max="99999999999" step="1"></label>
                <label>Montant en FCFA (facultatif)<input v-model="reparation.montant" type="number" min="0" max="9999999999.99" step="1"></label>
              </div>
              <button class="btn btn-light" :disabled="enregistrementReparation">
                <i class="fa-solid" :class="enregistrementReparation ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                {{ enregistrementReparation ? 'Enregistrement…' : 'Ajouter à l’historique' }}
              </button>
              <p v-if="erreurReparation" class="err" role="alert">{{ erreurReparation }}</p>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* ---------- Liste des véhicules (cartes) ---------- */
.vehicle-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1rem}
.vehicle-card{display:flex;flex-direction:column;gap:.85rem;width:100%;padding:1.1rem;border:1px solid var(--line);border-radius:var(--rl, var(--r));background:#fff;text-align:left;color:var(--ink);cursor:pointer;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
.vehicle-card:hover{transform:translateY(-2px);border-color:var(--red);box-shadow:0 8px 22px rgba(11,15,25,.1)}
.vehicle-card:focus-visible{outline:2px solid var(--red);outline-offset:2px}
.vc-top{display:flex;align-items:center;gap:.8rem}
.vc-icon{display:grid;place-items:center;flex:none;width:46px;height:46px;border-radius:12px;background:rgba(225,29,72,.1);color:var(--red);font-size:1.15rem}
.vc-title{display:grid;min-width:0}
.vc-brand{font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:var(--mut)}
.vc-model{font-size:1.05rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vc-arrow{margin-left:auto;color:var(--mut);transition:transform .15s ease}
.vehicle-card:hover .vc-arrow{transform:translateX(3px);color:var(--red)}
.vc-meta{display:flex;flex-wrap:wrap;gap:.4rem 1rem;color:var(--mut);font-size:.85rem}
.vc-meta i{margin-right:.3rem}
.vc-badges{display:flex;flex-wrap:wrap;gap:.45rem;padding-top:.85rem;border-top:1px dashed var(--line)}

/* Plaque d'immatriculation */
.plate{display:inline-block;align-self:flex-start;padding:.3rem .8rem;border:2px solid var(--ink);border-radius:6px;background:#fff;font-weight:800;font-size:.95rem;letter-spacing:.12em;text-transform:uppercase;box-shadow:inset 0 0 0 2px #fff,inset 0 0 0 3px var(--line)}

/* Pastilles de validité */
.exp{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .6rem;border-radius:999px;font-size:.78rem;font-weight:600;white-space:nowrap}
.exp-ok{background:#dcfce7;color:#166534}
.exp-warn{background:#fef3c7;color:#92400e}
.exp-bad{background:#ffe4e6;color:#9f1239}
.exp-none{background:var(--bg);color:var(--mut)}
.details-grid .exp{margin-left:.4rem;vertical-align:middle}

/* ---------- Formulaires ---------- */
.vehicle-form{display:grid;gap:1rem;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--line)}
.vehicle-form label{display:grid;gap:.4rem;font-weight:600}
.vehicle-form label span{font-weight:400;color:var(--mut)}
.vehicle-form input{width:100%;min-height:44px;padding:.65rem .8rem;border:1px solid var(--line);border-radius:var(--r);background:#fff;color:var(--ink);font:inherit}
.add-vehicle{margin-top:1.25rem}
.bottom-add{padding-top:1rem;border-top:1px solid var(--line)}

/* ---------- Modal ---------- */
.vehicle-modal{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:1rem;background:rgba(11,15,25,.68)}
.vehicle-modal-box{display:grid;grid-template-rows:auto auto minmax(0,1fr);width:min(900px,100%);max-height:min(92vh,950px);overflow:hidden;border-radius:var(--rl);background:#fff;box-shadow:var(--sh)}
.vehicle-add-modal-box{grid-template-rows:auto minmax(0,1fr);width:min(650px,100%)}
.vehicle-add-form{margin:0;padding:1.25rem 1.5rem 1.5rem;border:0}
.modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.25rem 1.5rem;border-bottom:1px solid var(--line)}
.modal-header h2{margin:.25rem 0 .6rem;font-size:1.7rem}
.modal-close{display:grid;place-items:center;flex:none;width:44px;height:44px;border:1px solid var(--line);border-radius:50%;background:#fff;color:var(--ink);font-size:1.1rem;cursor:pointer}
.modal-content{overflow-y:auto;padding:1.25rem 1.5rem 1.5rem}

/* Onglets */
.tabs{display:flex;gap:.25rem;padding:0 1.5rem;border-bottom:1px solid var(--line);background:#fff;overflow-x:auto}
.tab{display:inline-flex;align-items:center;gap:.5rem;padding:.9rem 1rem;border:0;border-bottom:3px solid transparent;background:none;color:var(--mut);font:inherit;font-weight:600;white-space:nowrap;cursor:pointer;transition:color .15s ease,border-color .15s ease}
.tab:hover{color:var(--ink)}
.tab.active{color:var(--red);border-bottom-color:var(--red)}
.tab-count{display:inline-grid;place-items:center;min-width:22px;height:22px;padding:0 .4rem;border-radius:999px;background:var(--bg);color:var(--ink);font-size:.78rem}
.tab.active .tab-count{background:rgba(225,29,72,.12);color:var(--red)}

/* Fiche */
.details-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.75rem}
.details-grid>div{padding:.8rem 1rem;border-radius:var(--r);background:var(--bg)}
.details-grid dt{color:var(--mut);font-size:.82rem}
.details-grid dd{margin-top:.25rem;font-weight:600;overflow-wrap:anywhere}

/* Réparations */
.repair-section{margin-top:0}
.repair-list{display:grid;gap:.65rem;margin-top:.85rem}
.repair-item{padding:1rem;border:1px solid var(--line);border-left:4px solid var(--red);border-radius:var(--r);background:#fff}
.repair-heading{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.4rem}
.repair-heading time,.repair-meta{color:var(--mut);font-size:.88rem}
.repair-item p{margin-top:.55rem;line-height:1.45;overflow-wrap:anywhere}
.repair-meta{display:flex;flex-wrap:wrap;gap:.8rem;margin-top:.55rem}
.repair-empty{padding:1rem}

@media(max-width:600px){
  .vehicle-list{grid-template-columns:1fr}
  .vehicle-modal{padding:.5rem}
  .vehicle-modal-box{max-height:96vh;border-radius:var(--r)}
  .vehicle-add-form{padding:1rem}
  .modal-header,.modal-content{padding:1rem}
  .tabs{padding:0 .5rem}
  .tab{flex:1;justify-content:center;padding:.8rem .6rem}
  .details-grid{grid-template-columns:1fr}
}
</style>