<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import MissionMap from '@/Components/MissionMap.vue'

const props = defineProps({
  activeMission: { type: Object, default: null },
  missionsDisponibles: { type: Array, default: () => [] },
  historiqueMissions: { type: Object, default: () => ({ data: [], links: [] }) },
  siege: { type: String, required: true },
})

const currentPosition = ref(props.activeMission?.position
  ? { lat: Number(props.activeMission.position.latitude), lng: Number(props.activeMission.position.longitude) }
  : null)
const error = ref('')
const sendingPosition = ref(false)
const startingMission = ref(false)
const routeDistanceMeters = ref(null)
const routeDurationMillis = ref(null)
const routeOptions = ref([])
const selectedRouteIndex = ref(0)
const gpsUpdatedAt = ref(null)
const offerSeconds = ref(20)
const offerSlide = ref(0)
const vehicleSecured = ref(false)
const vehicleConditionChecked = ref(false)
const missionSummary = ref(null)
let watchId = null
let lastSentAt = 0
let refreshTimer
let offerTimer
let timedOutOfferId = null

const garagePosition = { lat: 5.3133718, lng: -4.0893739 }
const featuredMission = computed(() => props.activeMission ? null : props.missionsDisponibles[0] || null)
const mapMission = computed(() => props.activeMission || featuredMission.value)
const mapRouteColor = computed(() => ['vehicule_recupere', 'en_route_garage'].includes(props.activeMission?.statut) ? '#16a34a' : '#2563eb')
const mapDestination = computed(() => ['vehicule_recupere', 'en_route_garage'].includes(mapMission.value?.statut)
  ? missionEndPosition(mapMission.value)
  : pickupPosition(mapMission.value))

const route = (name, id) => ({
  'chauffeur.missions.accept': `/chauffeur/missions/${id}/accepter`,
  'chauffeur.missions.decline': `/chauffeur/missions/${id}/refuser`,
  'chauffeur.missions.start': `/chauffeur/missions/${id}/demarrer`,
  'chauffeur.missions.arrive-pickup': `/chauffeur/missions/${id}/arrive-recuperation`,
  'chauffeur.missions.start-garage': `/chauffeur/missions/${id}/demarrer-garage`,
  'chauffeur.missions.complete': `/chauffeur/missions/${id}/terminer`,
  'chauffeur.missions.position': `/chauffeur/missions/${id}/position`,
})[name]
const details = mission => mission.details || {}
const prix = mission => mission.prix_estime === null || mission.prix_estime === undefined
  ? 'Tarif à confirmer'
  : `${Number(mission.prix_estime).toLocaleString('fr-FR')} FCFA`
const date = value => value ? new Date(value).toLocaleString('fr-FR') : '—'
const missionStateLabel = mission => ({
  accepte: 'Prête à démarrer',
  en_cours: 'En route vers le véhicule',
  en_route_recuperation: 'En route vers le véhicule',
  vehicule_recupere: 'Véhicule récupéré',
  en_route_garage: 'En route vers le garage',
  vehicule_arrive_au_garage: 'Véhicule arrivé au garage',
  terminee: 'Mission terminée',
})[mission?.statut] || 'Mission active'
const pickupPosition = mission => mission?.client_lat !== null && mission?.client_lat !== undefined
  ? { lat: Number(mission.client_lat), lng: Number(mission.client_lng) }
  : null
const missionEndPosition = mission => {
  if (!mission) return null
  return mission.garage_lat !== null && mission.garage_lng !== null
    ? { lat: Number(mission.garage_lat), lng: Number(mission.garage_lng) }
    : garagePosition
}
const pickupAddress = mission => mission?.details?.lieu || mission?.details?.lieu_depart || 'Position GPS indiquée par le client'
const offerDistanceLabel = computed(() => !currentPosition.value
  ? 'Activez le GPS pour estimer la distance'
  : routeDistanceMeters.value === null
    ? 'Calcul de l’itinéraire…'
  : `${(routeDistanceMeters.value / 1000).toFixed(1)} km jusqu’au véhicule`)
const gpsUpdatedLabel = computed(() => gpsUpdatedAt.value
  ? `GPS mis à jour à ${new Date(gpsUpdatedAt.value).toLocaleTimeString('fr-FR')}`
  : 'En attente de la position GPS')
function refreshRequests() {
  router.reload({ only: ['activeMission', 'missionsDisponibles', 'historiqueMissions'], preserveScroll: true })
}

function refuser(mission) {
  if (!mission) return
  router.post(route('chauffeur.missions.decline', mission.id), {}, { preserveScroll: true })
}

function locateForOffer() {
  if (!featuredMission.value || currentPosition.value || !navigator.geolocation) return
  navigator.geolocation.getCurrentPosition(
    position => {
      currentPosition.value = { lat: position.coords.latitude, lng: position.coords.longitude }
      gpsUpdatedAt.value = position.timestamp || Date.now()
    },
    () => {},
    { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 },
  )
}

function resetOfferTimer() {
  window.clearInterval(offerTimer)
  offerSeconds.value = 20
  offerSlide.value = 0
  routeDistanceMeters.value = null
  timedOutOfferId = null
  if (!featuredMission.value) return

  locateForOffer()
  const offerId = featuredMission.value.id
  offerTimer = window.setInterval(() => {
    offerSeconds.value = Math.max(offerSeconds.value - 1, 0)
    if (offerSeconds.value === 0 && timedOutOfferId !== offerId) {
      timedOutOfferId = offerId
      refuser(featuredMission.value)
    }
  }, 1000)
}

function accepterOffre() {
  if (offerSlide.value < 95 || !featuredMission.value) {
    offerSlide.value = 0
    return
  }
  const mission = featuredMission.value
  offerSlide.value = 0
  window.clearInterval(offerTimer)
  router.post(route('chauffeur.missions.accept', mission.id), {}, { preserveScroll: true })
}

function confirmerArriveeRecuperation() {
  router.post(route('chauffeur.missions.arrive-pickup', props.activeMission.id), {}, {
    preserveScroll: true,
    onError: errors => { error.value = Object.values(errors).flat()[0] || 'L’arrivée n’a pas pu être confirmée.' },
  })
}

function demarrerTrajetGarage() {
  router.post(route('chauffeur.missions.start-garage', props.activeMission.id), {
    vehicule_securise: vehicleSecured.value,
    etat_vehicule_verifie: vehicleConditionChecked.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      vehicleSecured.value = false
      vehicleConditionChecked.value = false
    },
    onError: errors => { error.value = Object.values(errors).flat()[0] || 'Le trajet vers le garage n’a pas pu démarrer.' },
  })
}

function envoyerPosition(position) {
  currentPosition.value = { lat: position.coords.latitude, lng: position.coords.longitude }
  gpsUpdatedAt.value = position.timestamp || Date.now()
  const now = Date.now()
  if (sendingPosition.value || now - lastSentAt < 5000) return

  sendingPosition.value = true
  lastSentAt = now
  axios.put(route('chauffeur.missions.position', props.activeMission.id), {
    latitude: currentPosition.value.lat,
    longitude: currentPosition.value.lng,
  })
    .then(() => { error.value = '' })
    .catch(requestError => {
      error.value = requestError.response?.data?.message || 'La position GPS n’a pas pu être partagée.'
    })
    .finally(() => { sendingPosition.value = false })
}

function activerSuivi() {
  error.value = ''
  if (!navigator.geolocation) {
    error.value = 'La géolocalisation n’est pas disponible sur cet appareil.'
    return
  }
  if (watchId !== null) navigator.geolocation.clearWatch(watchId)

  watchId = navigator.geolocation.watchPosition(
    position => envoyerPosition(position),
    () => { error.value = 'Position indisponible. Autorisez le GPS et vérifiez que la page reste ouverte.' },
    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 },
  )
}

function arreterSuivi() {
  if (watchId !== null) navigator.geolocation.clearWatch(watchId)
  watchId = null
}

function demarrerMission() {
  if (startingMission.value) return
  if (!navigator.geolocation) {
    error.value = 'La géolocalisation est nécessaire pour démarrer le suivi.'
    return
  }

  startingMission.value = true
  navigator.geolocation.getCurrentPosition(
    position => {
      currentPosition.value = { lat: position.coords.latitude, lng: position.coords.longitude }
      router.post(route('chauffeur.missions.start', props.activeMission.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
          startingMission.value = false
          lastSentAt = 0
          envoyerPosition(position)
          activerSuivi()
        },
        onError: errors => {
          startingMission.value = false
          error.value = Object.values(errors).flat()[0] || 'La mission n’a pas pu démarrer.'
        },
      })
    },
    () => {
      startingMission.value = false
      error.value = 'Autorisez le GPS pour démarrer la mission et partager votre position.'
    },
    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 },
  )
}

function terminerMission() {
  router.post(route('chauffeur.missions.complete', props.activeMission.id), {}, {
    preserveScroll: true,
    onSuccess: response => {
      missionSummary.value = response.props.flash?.missionSummary || null
      if (watchId !== null) navigator.geolocation.clearWatch(watchId)
      watchId = null
      currentPosition.value = null
    },
  })
}

function fermerRecapitulatif() {
  missionSummary.value = null
  refreshRequests()
}

function formatDuration(start, end) {
  if (!start || !end) return '—'
  const totalMinutes = Math.max(0, Math.round((new Date(end) - new Date(start)) / 60000))
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  return hours ? `${hours} h ${minutes} min` : `${minutes} min`
}

function updateRouteOptions(options) {
  routeOptions.value = options
  if (selectedRouteIndex.value >= options.length) selectedRouteIndex.value = 0
}

function updateRouteMetrics({ distanceMeters, durationMillis }) {
  routeDistanceMeters.value = Number.isFinite(distanceMeters) ? distanceMeters : null
  routeDurationMillis.value = Number.isFinite(durationMillis) ? durationMillis : null
}

function selectRoute(index) {
  selectedRouteIndex.value = index
}

watch(() => featuredMission.value?.id, resetOfferTimer, { immediate: true })

onMounted(() => {
  refreshTimer = window.setInterval(refreshRequests, 5000)
  if (['en_cours', 'en_route_recuperation', 'en_route_garage'].includes(props.activeMission?.statut)) {
    activerSuivi()
  }
})
watch(() => props.activeMission?.statut, status => {
  if (['en_cours', 'en_route_recuperation', 'en_route_garage'].includes(status)) {
    activerSuivi()
  } else {
    arreterSuivi()
  }
})
onBeforeUnmount(() => {
  window.clearInterval(refreshTimer)
  window.clearInterval(offerTimer)
  arreterSuivi()
})
</script>

<template>
  <Head title="Missions chauffeur" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-black text-[#0B0F19]">Missions chauffeur</h2>
          <p class="text-sm text-gray-500">Demandes du siège {{ siege }}</p>
        </div>
        <button type="button" class="rounded-xl border px-4 py-2 text-sm font-bold" @click="refreshRequests">
          Actualiser les demandes
        </button>
      </div>
    </template>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-5 sm:px-6 lg:px-8">
      <p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">{{ error }}</p>

      <section class="driver-map-shell">
        <MissionMap
          class="driver-map"
          :start-position="null"
          :end-position="mapDestination"
          :driver-position="currentPosition"
          :follow-driver="Boolean(activeMission)"
          :route-color="mapRouteColor"
          :selected-route-index="selectedRouteIndex"
          :show-traffic="true"
          @route-updated="updateRouteMetrics"
          @routes-updated="updateRouteOptions"
          @route-selected="selectRoute"
        />

        <section v-if="missionSummary && !activeMission" class="driver-sheet" role="status">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-bold uppercase text-emerald-700">Livraison confirmée</p>
              <h3 class="mt-1 text-xl font-black">{{ missionSummary.type === 'depannage' ? 'Dépannage livré' : 'Convoyage livré' }}</h3>
            </div>
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Terminée</span>
          </div>
          <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
            <div><dt class="text-xs text-gray-500">Durée</dt><dd class="font-bold">{{ formatDuration(missionSummary.mission_started_at, missionSummary.mission_completed_at) }}</dd></div>
            <div><dt class="text-xs text-gray-500">Distance aller-retour</dt><dd class="font-bold">{{ Number(missionSummary.distance_parcourue_km || 0).toFixed(1) }} km</dd></div>
            <div><dt class="text-xs text-gray-500">Tarif</dt><dd class="font-bold">{{ prix(missionSummary) }}</dd></div>
          </dl>
          <p class="mt-3 text-xs font-semibold text-gray-500">Vous êtes maintenant en attente de commandes.</p>
          <button type="button" class="mt-4 w-full rounded-xl bg-[#0B0F19] px-4 py-3 font-bold text-white" @click="fermerRecapitulatif">
            Retour aux commandes
          </button>
        </section>

        <section v-else-if="activeMission" class="driver-sheet" aria-live="polite">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-bold uppercase text-gray-500">{{ activeMission.type === 'depannage' ? 'Dépannage' : 'Convoyage' }} · {{ activeMission.client?.telephone }}</p>
              <h3 class="mt-1 text-xl font-black">{{ missionStateLabel(activeMission) }}</h3>
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">{{ activeMission.client?.prenom }} {{ activeMission.client?.nom }}</span>
          </div>
          <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <p><span class="font-bold text-gray-500">Récupération</span><br>{{ pickupAddress(activeMission) }}</p>
            <p><span class="font-bold text-gray-500">Destination</span><br>Garage Kagnan · 1327 BP01 Abidjan, Azito · 8W76+87</p>
            <p v-if="activeMission.vehicule"><span class="font-bold text-gray-500">Véhicule</span><br>{{ activeMission.vehicule.marque }} {{ activeMission.vehicule.modele }} · {{ activeMission.vehicule.immatriculation }}</p>
            <p><span class="font-bold text-gray-500">Tarif estimé</span><br>{{ prix(activeMission) }}</p>
          </div>
          <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
            <span>{{ routeDistanceMeters === null ? 'Itinéraire GPS actif' : `${(routeDistanceMeters / 1000).toFixed(1)} km${routeDurationMillis ? ` · ${Math.max(1, Math.round(routeDurationMillis / 60000))} min` : ''} sur l’étape` }}</span>
            <span>{{ gpsUpdatedLabel }}</span>
          </div>
          <div v-if="routeOptions.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Itinéraires alternatifs">
            <button
              v-for="(option, index) in routeOptions"
              :key="`${option.label}-${index}`"
              type="button"
              class="min-w-36 rounded-xl border px-3 py-2 text-left text-xs"
              :class="selectedRouteIndex === index ? 'border-blue-700 bg-blue-50 text-blue-900' : 'border-gray-200 bg-white text-gray-600'"
              @click="selectRoute(index)"
            >
              <span class="block font-bold">{{ option.label }}</span>
              <span>{{ option.distanceMeters ? `${(option.distanceMeters / 1000).toFixed(1)} km` : 'Distance —' }}<template v-if="option.durationMillis"> · {{ Math.max(1, Math.round(option.durationMillis / 60000)) }} min</template></span>
            </button>
          </div>

          <button v-if="activeMission.statut === 'accepte'" type="button" class="driver-primary mt-4" :disabled="startingMission" @click="demarrerMission">
            {{ startingMission ? 'Démarrage…' : 'Démarrer vers le véhicule' }}
          </button>
          <button v-else-if="['en_cours', 'en_route_recuperation'].includes(activeMission.statut)" type="button" class="driver-primary mt-4" @click="confirmerArriveeRecuperation">
            Je suis arrivé sur les lieux
          </button>
          <div v-else-if="activeMission.statut === 'vehicule_recupere'" class="mt-4 space-y-3">
            <label class="driver-check"><input v-model="vehicleSecured" type="checkbox"><span>Véhicule sécurisé pour le transport</span></label>
            <label class="driver-check"><input v-model="vehicleConditionChecked" type="checkbox"><span>État du véhicule vérifié</span></label>
            <button type="button" class="driver-primary" :disabled="!vehicleSecured || !vehicleConditionChecked" @click="demarrerTrajetGarage">
              Valider la récupération et partir au garage
            </button>
          </div>
          <button v-else-if="activeMission.statut === 'en_route_garage'" type="button" class="driver-primary mt-4" @click="terminerMission">
            Véhicule livré / arrivé au garage
          </button>
        </section>

        <section v-else-if="featuredMission" class="driver-sheet" aria-live="polite">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-bold uppercase text-emerald-700">Nouvelle demande · {{ missionsDisponibles.length }} en attente</p>
              <h3 class="mt-1 text-xl font-black">{{ featuredMission.type === 'depannage' ? 'Dépannage' : 'Convoyage' }}</h3>
            </div>
            <span class="countdown" :class="{ urgent: offerSeconds <= 5 }">{{ offerSeconds }}s</span>
          </div>
          <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="font-bold text-gray-500">Distance jusqu’au véhicule</dt><dd>{{ offerDistanceLabel }}</dd></div>
            <div><dt class="font-bold text-gray-500">Récupération</dt><dd>{{ pickupAddress(featuredMission) }}</dd></div>
            <div><dt class="font-bold text-gray-500">Destination finale</dt><dd>Garage Kagnan · 1327 BP01 Abidjan, Azito · 8W76+87</dd></div>
            <div><dt class="font-bold text-gray-500">Tarif estimé</dt><dd>{{ prix(featuredMission) }}</dd></div>
          </dl>
          <div class="mt-4 flex gap-3">
            <button type="button" class="driver-refuse" @click="refuser(featuredMission)">Refuser</button>
            <label class="driver-slide">
              <span>Glisser pour accepter</span>
              <input v-model.number="offerSlide" type="range" min="0" max="100" aria-label="Glisser pour accepter la demande" @change="accepterOffre">
            </label>
          </div>
        </section>

        <section v-else class="driver-sheet driver-waiting" aria-live="polite">
          <p class="text-xs font-bold uppercase text-gray-500">{{ currentPosition ? 'Position GPS activée' : 'Position GPS en attente' }}</p>
          <h3 class="mt-1 text-xl font-black">En attente de commandes</h3>
          <p class="mt-1 text-sm text-gray-600">Les nouvelles demandes de votre siège apparaîtront ici.</p>
        </section>
      </section>

      <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="text-xl font-black">Historique des missions</h3>
            <p class="text-sm text-gray-500">Missions terminées par vous</p>
          </div>
          <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-bold">
            {{ historiqueMissions.total || 0 }}
          </span>
        </div>

        <article
          v-for="mission in historiqueMissions.data"
          :key="mission.id"
          class="space-y-3 rounded-2xl border bg-white p-5 shadow-sm"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h4 class="text-lg font-bold">
                {{ mission.type === 'depannage' ? 'Dépannage' : 'Convoyage' }}
                <span class="ml-2 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Terminée</span>
              </h4>
              <p class="mt-2 text-sm text-gray-600">
                Client : {{ mission.client?.prenom }} {{ mission.client?.nom }}
                <span v-if="mission.client?.telephone"> · {{ mission.client.telephone }}</span>
              </p>
              <p v-if="mission.vehicule" class="text-sm text-gray-600">
                {{ mission.vehicule.marque }} {{ mission.vehicule.modele }} — {{ mission.vehicule.immatriculation }}
              </p>
            </div>
            <strong>{{ prix(mission) }}</strong>
          </div>
          <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div v-for="(value, key) in details(mission)" :key="key">
              <dt class="font-bold capitalize text-gray-500">{{ key.replaceAll('_', ' ') }}</dt>
              <dd>{{ value }}</dd>
            </div>
            <div>
              <dt class="font-bold text-gray-500">Terminée le</dt>
              <dd>{{ date(mission.mission_completed_at) }}</dd>
            </div>
          </dl>
        </article>

        <p v-if="!historiqueMissions.data?.length" class="rounded-xl border border-dashed bg-white p-6 text-center text-sm text-gray-500">
          Aucune mission terminée pour le moment.
        </p>

        <nav
          v-if="historiqueMissions.last_page > 1"
          class="flex flex-wrap justify-center gap-2"
          aria-label="Pagination de l’historique des missions"
        >
          <template v-for="(link, index) in historiqueMissions.links" :key="index">
            <Link
              v-if="link.url"
              :href="link.url"
              preserve-scroll
              class="rounded-lg border px-3 py-2 text-sm"
              :class="link.active ? 'border-blue-700 bg-blue-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
              v-html="link.label"
            />
            <span v-else class="rounded-lg border bg-gray-50 px-3 py-2 text-sm text-gray-400" v-html="link.label" />
          </template>
        </nav>
      </section>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.driver-map-shell{position:relative;height:min(70vh,720px);min-height:500px;overflow:hidden;border:1px solid #d8dee8;border-radius:18px;background:#dce7ef}
.driver-map-shell :deep(.driver-map){height:100%}
.driver-map-shell :deep(.mission-map){height:100%;min-height:100%;border-radius:0}
.driver-map-empty{height:100%;background:linear-gradient(145deg,#d9e8ed 0%,#eef3ed 52%,#dce4e8 100%)}
.driver-sheet{position:absolute;right:10px;bottom:10px;left:10px;z-index:5;max-height:58%;overflow-y:auto;border:1px solid rgba(15,23,42,.1);border-radius:18px;background:rgba(255,255,255,.97);padding:16px;box-shadow:0 12px 40px rgba(15,23,42,.2);backdrop-filter:blur(12px)}
.driver-primary{display:flex;width:100%;min-height:52px;align-items:center;justify-content:center;border:0;border-radius:13px;background:#16834b;padding:12px 16px;color:#fff;font-size:15px;font-weight:800;transition:background .15s,opacity .15s}
.driver-primary:hover:not(:disabled){background:#116b3d}
.driver-primary:disabled{cursor:not-allowed;background:#9aa3ad;color:#f8fafc}
.driver-refuse{min-width:100px;border:1px solid #e11d48;border-radius:13px;background:#fff;padding:12px 16px;color:#be123c;font-weight:800}
.driver-slide{display:grid;min-width:0;flex:1;gap:6px;border-radius:13px;background:#e8f5ed;padding:9px 14px;color:#166534;font-size:13px;font-weight:800}
.driver-slide input{width:100%;height:28px;accent-color:#16834b;touch-action:pan-x}
.driver-check{display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;color:#1f2937}
.driver-check input{width:19px;height:19px;accent-color:#16834b}
.countdown{display:grid;min-width:44px;height:44px;place-items:center;border:2px solid #16834b;border-radius:50%;color:#166534;font-size:13px;font-weight:900}
.countdown.urgent{border-color:#e11d48;color:#be123c}
@media(min-width:768px){.driver-map-shell{height:min(72vh,760px)}.driver-sheet{right:18px;bottom:18px;left:auto;width:min(460px,calc(100% - 36px));max-height:62%;padding:20px}}
@media(max-width:640px){.driver-map-shell{height:68vh;min-height:480px;margin-right:-4px;margin-left:-4px;border-radius:14px}.driver-sheet{right:7px;bottom:7px;left:7px;max-height:62%;padding:14px}.driver-sheet h3{font-size:18px}}
</style>
