<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { state, loadSession, toast } from '../store.js'
import MissionMap from '@/Components/MissionMap.vue'
import MissionTracking from '@/Components/MissionTracking.vue'

const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: value => ['depannage', 'convoyage'].includes(value),
  },
})

const title = computed(() => props.type === 'depannage' ? 'Demander une dépanneuse' : 'Demander un convoyage')
const demands = computed(() => state.user.demandes.filter(demand => demand.type === props.type))
const isConvoyage = computed(() => props.type === 'convoyage')
const form = reactive({
  vehicule_id: '',
  lieu: '',
  precision: '',
  categorie_tarif: 'voiture_particuliere',
})
const clientPosition = ref(null)
const gpsConsent = ref(false)
const busy = ref(false)
const locationBusy = ref(false)
const error = ref('')
let refreshTimer
const garagePosition = { lat: 5.3133718, lng: -4.0893739 }

const statusLabel = status => ({
  en_attente: 'En attente d’un chauffeur',
  accepte: 'Chauffeur affecté',
  en_cours: 'Mission en cours',
  en_route_recuperation: 'En route vers le véhicule',
  vehicule_recupere: 'Véhicule récupéré',
  en_route_garage: 'En route vers le garage',
  vehicule_arrive_au_garage: 'Véhicule arrivé au garage',
  terminee: 'Mission terminée',
  refuse: 'Refusée',
})[status] || status.replaceAll('_', ' ')
const dateLabel = value => value ? new Date(value).toLocaleString('fr-FR') : '—'
const priceLabel = price => price === null || price === undefined
  ? 'Tarif à confirmer par le garage'
  : `${Number(price).toLocaleString('fr-FR')} FCFA`

function choosePoint({ position }) {
  if (!gpsConsent.value) {
    error.value = 'Cochez votre consentement au partage GPS avant de choisir une position.'
    return
  }
  clientPosition.value = position
  if (!form.lieu) form.lieu = 'Position GPS partagée avec le chauffeur'
  error.value = ''
}

function capturePosition() {
  error.value = ''
  if (!gpsConsent.value) {
    error.value = 'Cochez votre consentement avant de partager votre position GPS.'
    return
  }
  if (!navigator.geolocation) {
    error.value = 'La géolocalisation n’est pas disponible sur cet appareil.'
    return
  }

  locationBusy.value = true
  navigator.geolocation.getCurrentPosition(
    position => {
      choosePoint({
        target: 'start',
        position: { lat: position.coords.latitude, lng: position.coords.longitude },
      })
      locationBusy.value = false
    },
    () => {
      error.value = 'Position indisponible. Autorisez le GPS ou choisissez un point sur la carte.'
      locationBusy.value = false
    },
    { enableHighAccuracy: true, maximumAge: 30000, timeout: 15000 },
  )
}

function validateForm() {
  if (!gpsConsent.value) return 'Le consentement au partage GPS est obligatoire pour ces missions.'
  if (!clientPosition.value) return 'Indiquez le lieu du véhicule avec le GPS ou sur la carte.'
  return ''
}

async function submit() {
  error.value = validateForm()
  if (error.value) return

  busy.value = true
  const payload = {
    type: props.type,
    vehicule_id: form.vehicule_id || null,
    gps_consent: true,
    client_lat: clientPosition.value.lat,
    client_lng: clientPosition.value.lng,
    precision: form.precision || null,
    ...(isConvoyage.value
      ? { lieu_depart: form.lieu || null }
      : { lieu: form.lieu || null, categorie_tarif: form.categorie_tarif }),
  }

  try {
    const { data } = await axios.post('/espace-client/api/demandes', payload)
    await loadSession()
    toast(`${data.message} ${priceLabel(data.request?.prix_estime)}.`)
    Object.assign(form, {
      vehicule_id: '',
      lieu: '',
      precision: '',
      categorie_tarif: 'voiture_particuliere',
    })
    clientPosition.value = null
    gpsConsent.value = false
  } catch (requestError) {
    error.value = requestError.response?.data?.message
      || Object.values(requestError.response?.data?.errors || {}).flat()[0]
      || 'Impossible de transmettre votre demande.'
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  refreshTimer = window.setInterval(() => {
    loadSession().catch(() => { error.value = 'Impossible d’actualiser le statut des demandes.' })
  }, 10000)
})
onBeforeUnmount(() => window.clearInterval(refreshTimer))
</script>

<template>
  <div class="space-y-6">
    <section class="card">
      <h3>{{ title }}</h3>
      <p class="note">
        Indiquez où récupérer le véhicule, en choisissant un point sur la carte ou votre position actuelle.
        Après la récupération, le chauffeur ou dépanneur sera dirigé vers le Garage Kagnan.
      </p>

      <form class="space-y-4" @submit.prevent="submit">
        <label v-if="state.user.vehicules.length">Véhicule concerné
          <select v-model="form.vehicule_id">
            <option value="">Aucun véhicule précisé</option>
            <option v-for="vehicle in state.user.vehicules" :key="vehicle.id" :value="vehicle.id">
              {{ vehicle.modele }} — {{ vehicle.immat }}
            </option>
          </select>
        </label>

        <label class="check consent">
          <input v-model="gpsConsent" type="checkbox">
          <span>
            J’autorise le garage et le chauffeur affecté à recevoir la position indiquée et le chauffeur à partager
            sa position pendant la mission. Les coordonnées précises sont supprimées à la clôture.
            Google Maps reçoit les positions pour afficher la carte et calculer l’itinéraire.
          </span>
        </label>

        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-light" :disabled="locationBusy" @click="capturePosition">
            {{ locationBusy ? 'Recherche de la position…' : 'Utiliser ma position actuelle' }}
          </button>
          <span class="hint self-center">Ou cliquez sur la carte pour choisir le lieu de récupération.</span>
        </div>

        <MissionMap
          :start-position="clientPosition"
          :end-position="garagePosition"
          :interactive="true"
          selection-target="start"
          @point-selected="choosePoint"
        />

        <label>Repère ou adresse du véhicule (facultatif)<input v-model="form.lieu" maxlength="500" autocomplete="street-address"></label>
        <label v-if="!isConvoyage">Catégorie du véhicule
            <select v-model="form.categorie_tarif">
              <option value="voiture_particuliere">Voiture particulière</option>
              <option value="autre">Autre catégorie — tarif à confirmer</option>
            </select>
        </label>
        <label>Précisions<textarea v-model="form.precision" rows="3" maxlength="2000"></textarea></label>

        <button class="btn btn-primary full" :disabled="busy">
          {{ busy ? 'Envoi…' : 'Envoyer la demande' }}
        </button>
        <p v-if="error" class="err" role="alert">{{ error }}</p>
      </form>
    </section>

    <section class="card">
      <h3>Mes demandes</h3>
      <article v-for="demand in demands" :key="demand.id" class="notif">
        <div class="row-line">
          <strong>{{ statusLabel(demand.statut) }}</strong>
          <small class="hint">{{ dateLabel(demand.created_at) }}</small>
        </div>
        <p v-if="demand.vehicule" class="hint">
          {{ demand.vehicule.marque }} {{ demand.vehicule.modele }} — {{ demand.vehicule.immatriculation }}
        </p>
        <p v-for="(value, key) in demand.details" :key="key" class="hint">
          <strong>{{ key.replaceAll('_', ' ') }} :</strong> {{ value }}
        </p>
        <p v-if="demand.chauffeur" class="hint">Chauffeur : {{ demand.chauffeur.name }}</p>
        <p class="hint">Prix : {{ priceLabel(demand.prix_estime) }}</p>
        <MissionTracking
          v-if="['accepte', 'en_cours', 'en_route_recuperation', 'vehicule_recupere', 'en_route_garage', 'vehicule_arrive_au_garage'].includes(demand.statut)"
          class="mt-4"
          audience="client"
          :request-id="Number(demand.id)"
          :initial-client-position="demand.client_position"
          :initial-garage-position="demand.garage_position"
          :initial-destination-position="demand.destination_position"
        />
      </article>
      <p v-if="!demands.length" class="hint">Aucune demande envoyée dans cette section.</p>
    </section>
  </div>
</template>

<style scoped>
.is-active{border-color:#e11d48;color:#be123c;background:#fff1f2}

.consent{
  align-items:flex-start;
  gap:.625rem;
  margin-bottom:0;
  padding:.625rem .75rem;
  border:1px solid #fde68a;
  border-radius:.75rem;
  background:#fffbeb;
  font-size:.75rem;
  font-weight:500;
  line-height:1.4;
}
.consent input{
  flex:none;
  appearance:none;
  -webkit-appearance:none;
  width:18px;
  height:18px;
  min-height:18px;
  margin:.1rem 0 0;
  padding:0;
  border:2px solid #8a8d8f;
  border-radius:4px;
  background:#fff;
  box-shadow:none;
  transition:none;
  cursor:pointer;
}
.consent input:checked{
  border-color:#e11d48;
  background:#e11d48 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3.5 8.5l3 3 6-7'/%3E%3C/svg%3E") center/80% no-repeat;
}
.consent input:focus-visible{
  outline:2px solid #e11d48;
  outline-offset:2px;
}
</style>