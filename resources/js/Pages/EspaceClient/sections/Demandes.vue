<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { state, loadSession, toast } from '../store.js'

const props = defineProps({
  type: {
    type: String,
    required: true,
  },
})

const config = {
  rendez_vous: { title: 'Demander un rendez-vous', submit: 'Envoyer ma demande', icon: 'fa-calendar-check', vide: 'Aucun rendez-vous demandé pour le moment.' },
  depannage: { title: 'Demander un dépannage', submit: 'Envoyer ma demande', icon: 'fa-truck-medical', vide: 'Aucune demande de dépannage envoyée.' },
  convoyage: { title: 'Demander un convoyage', submit: 'Envoyer ma demande', icon: 'fa-truck-pickup', vide: 'Aucune demande de convoyage envoyée.' },
}
const activeConfig = computed(() => config[props.type])
const today = new Date().toISOString().slice(0, 10)
const form = reactive({
  vehicule_id: '',
  prestation: 'Révision / vidange',
  date_souhaitee: '',
  lieu: '',
  lieu_depart: '',
  lieu_arrivee: '',
  precision: '',
})
const busy = ref(false)
const error = ref('')
const services = ['Révision / vidange', 'Freinage', 'Diagnostic électronique', 'Climatisation', 'Carrosserie', 'Autre']
let refreshTimer

const demandes = computed(() => state.user.demandes.filter(demande => demande.type === props.type))

const titresDetails = {
  prestation: 'Prestation',
  date_souhaitee: 'Date souhaitée',
  lieu: 'Lieu du véhicule',
  lieu_depart: 'Départ',
  lieu_arrivee: 'Arrivée',
  precision: 'Précisions',
}
const statutLisible = statut => ({
  en_attente: 'En attente de confirmation',
  accepte: 'Acceptée par le garage',
  refuse: 'Refusée',
})[statut] || statut.replaceAll('_', ' ')
const classeStatut = statut => ({ en_attente: 'st-wait', accepte: 'st-ok', refuse: 'st-no' })[statut] || 'st-info'
const iconeStatut = statut => ({ en_attente: 'fa-hourglass-half', accepte: 'fa-circle-check', refuse: 'fa-circle-xmark' })[statut] || 'fa-circle-info'
const dateLisible = date => date ? new Date(date).toLocaleDateString('fr-FR') : '—'

// Un convoyage affiche un trajet (départ vers arrivée) ; les autres détails restent en liste
const trajet = demande => (demande.details?.lieu_depart && demande.details?.lieu_arrivee
  ? { depart: demande.details.lieu_depart, arrivee: demande.details.lieu_arrivee }
  : null)
const autresDetails = demande => Object.entries(demande.details || {})
  .filter(([key]) => !(trajet(demande) && ['lieu_depart', 'lieu_arrivee'].includes(key)))
  .map(([key, value]) => [titresDetails[key] || key, key === 'date_souhaitee' ? dateLisible(value) : value])

async function submit() {
  busy.value = true
  error.value = ''

  const payload = {
    type: props.type,
    vehicule_id: form.vehicule_id || null,
  }
  for (const key of ['prestation', 'date_souhaitee', 'lieu', 'lieu_depart', 'lieu_arrivee', 'precision']) {
    if (form[key]) payload[key] = form[key]
  }

  try {
    const { data } = await axios.post('/espace-client/api/demandes', payload)
    await loadSession()
    toast(data.message)
    Object.assign(form, {
      date_souhaitee: '',
      lieu: '',
      lieu_depart: '',
      lieu_arrivee: '',
      precision: '',
    })
  } catch (requestError) {
    error.value = requestError.response?.data?.message
      || Object.values(requestError.response?.data?.errors || {}).flat()[0]
      || 'Impossible de transmettre votre demande. Réessayez.'
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  refreshTimer = window.setInterval(() => {
    loadSession().catch(() => {
      error.value = 'Impossible d’actualiser le statut de vos demandes.'
    })
  }, 10000)
})

onBeforeUnmount(() => window.clearInterval(refreshTimer))
</script>

<template>
  <div class="cols">
    <div class="card">
      <h3><i class="fa-solid" :class="activeConfig.icon"></i> {{ activeConfig.title }}</h3>
      <p class="note">
        <i class="fa-solid fa-circle-info"></i>
        Votre demande sera transmise au garage et restera en attente jusqu’à confirmation par l’équipe.
      </p>
      <form @submit.prevent="submit">
        <label v-if="state.user.vehicules.length">Véhicule concerné
          <select v-model="form.vehicule_id">
            <option value="">Aucun véhicule précisé</option>
            <option v-for="vehicule in state.user.vehicules" :key="vehicule.id" :value="vehicule.id">
              {{ vehicule.modele }} — {{ vehicule.immat }}
            </option>
          </select>
        </label>

        <template v-if="type === 'rendez_vous'">
          <fieldset>
            <legend>Prestation souhaitée</legend>
            <div class="opts">
              <label v-for="service in services" :key="service" class="opt">
                <input v-model="form.prestation" type="radio" name="prestation" :value="service">
                <span>{{ service }}</span>
              </label>
            </div>
          </fieldset>
          <label>Date souhaitée<input v-model="form.date_souhaitee" type="date" :min="today" required></label>
          <label>Précisions<textarea v-model="form.precision" rows="3" maxlength="2000"></textarea></label>
        </template>
        <template v-else-if="type === 'depannage'">
          <label>Lieu du véhicule<input v-model="form.lieu" maxlength="500" required autocomplete="street-address"></label>
          <label>Description du besoin<textarea v-model="form.precision" rows="3" maxlength="2000"></textarea></label>
        </template>
        <template v-else>
          <label>Lieu de départ<input v-model="form.lieu_depart" maxlength="500" required></label>
          <label>Lieu d’arrivée<input v-model="form.lieu_arrivee" maxlength="500" required></label>
          <label>Précisions<textarea v-model="form.precision" rows="3" maxlength="2000"></textarea></label>
        </template>

        <button class="btn btn-primary full" :disabled="busy">
          <i class="fa-solid" :class="busy ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
          {{ busy ? 'Envoi…' : activeConfig.submit }}
        </button>
        <p v-if="error" class="err" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ error }}</p>
      </form>
    </div>

    <div class="card">
      <div class="card-head">
        <h3><i class="fa-solid fa-list-check"></i> Mes demandes</h3>
        <span v-if="demandes.length" class="st st-info">{{ demandes.length }}</span>
      </div>
      <article v-for="demande in demandes" :key="demande.id" class="req">
        <div class="req-top">
          <span class="st" :class="classeStatut(demande.statut)"><i class="fa-solid" :class="iconeStatut(demande.statut)"></i>{{ statutLisible(demande.statut) }}</span>
          <small class="hint">{{ dateLisible(demande.created_at) }}</small>
        </div>
        <p v-if="demande.vehicule" class="req-veh">
          <span v-if="demande.vehicule.immatriculation" class="plate">{{ demande.vehicule.immatriculation }}</span>
          {{ demande.vehicule.marque }} {{ demande.vehicule.modele }}
        </p>
        <div v-if="trajet(demande)" class="route">
          <div><small>Départ</small>{{ trajet(demande).depart }}</div>
          <div><small>Arrivée</small>{{ trajet(demande).arrivee }}</div>
        </div>
        <dl v-if="autresDetails(demande).length" class="kv">
          <div v-for="[titre, valeur] in autresDetails(demande)" :key="titre"><dt>{{ titre }}</dt><dd>{{ valeur }}</dd></div>
        </dl>
      </article>
      <div v-if="!demandes.length" class="empty">
        <i class="fa-solid" :class="activeConfig.icon"></i>
        <strong>Aucune demande</strong>
        <p>{{ activeConfig.vide }}</p>
      </div>
    </div>
  </div>
</template>
