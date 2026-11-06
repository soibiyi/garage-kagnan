<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import MissionMap from '@/Components/MissionMap.vue'

const props = defineProps({
  requestId: { type: Number, required: true },
  clientId: { type: Number, default: null },
  audience: { type: String, required: true },
  initialClientPosition: { type: Object, default: null },
  initialGaragePosition: { type: Object, default: null },
  initialDestinationPosition: { type: Object, default: null },
})

const defaultGaragePosition = { lat: 5.3133718, lng: -4.0893739 }

const driverPosition = ref(null)
const clientPosition = ref(props.initialClientPosition)
const missionStatus = ref('accepte')
const garagePosition = ref(props.initialGaragePosition || defaultGaragePosition)
const lastUpdated = ref(null)
const error = ref('')
const loading = ref(true)
let timer
let polling = false

const endpoint = computed(() => props.audience === 'client'
  ? `/espace-client/api/demandes/${props.requestId}/position`
  : `/suivi-client/clients/${props.clientId}/portail/demandes/${props.requestId}/position`)

async function refreshPosition() {
  if (polling) return
  polling = true
  try {
    const { data } = await axios.get(endpoint.value)
    driverPosition.value = data.position
    clientPosition.value = data.client_position || clientPosition.value
    missionStatus.value = data.statut || missionStatus.value
    garagePosition.value = data.garage_position || props.initialGaragePosition || defaultGaragePosition
    lastUpdated.value = data.position?.updated_at || null
    error.value = ''
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Impossible de récupérer la position du chauffeur.'
  } finally {
    loading.value = false
    polling = false
  }
}

const updatedLabel = computed(() => lastUpdated.value
  ? `Dernière position reçue à ${new Date(lastUpdated.value).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`
  : 'En attente de la première position du chauffeur.')
const statusLabel = computed(() => ({
  accepte: 'Chauffeur affecté',
  en_cours: 'En route vers le véhicule',
  en_route_recuperation: 'En route vers le véhicule',
  vehicule_recupere: 'Véhicule récupéré',
  en_route_garage: 'En route vers le garage',
  vehicule_arrive_au_garage: 'Véhicule arrivé au garage',
})[missionStatus.value] || 'Suivi de la mission')
const nextDestination = computed(() => ['vehicule_recupere', 'en_route_garage', 'vehicule_arrive_au_garage'].includes(missionStatus.value)
  ? garagePosition.value
  : clientPosition.value)

onMounted(async () => {
  await refreshPosition()
  timer = window.setInterval(refreshPosition, 5000)
})
onBeforeUnmount(() => window.clearInterval(timer))
</script>

<template>
  <section class="mission-tracking">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <strong>{{ statusLabel }}</strong>
      <span class="text-xs text-gray-500">{{ updatedLabel }}</span>
    </div>
    <MissionMap
      class="mt-3"
      :start-position="clientPosition"
      :end-position="nextDestination"
      :driver-position="driverPosition"
      :route-color="missionStatus === 'en_route_garage' ? '#16a34a' : '#2563eb'"
      :show-traffic="true"
    />
    <p v-if="loading" class="mt-2 text-sm text-gray-500">Chargement de la position…</p>
    <p v-if="error" class="mt-2 text-sm text-rose-700" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped>
.mission-tracking{display:grid;gap:.45rem}
</style>
