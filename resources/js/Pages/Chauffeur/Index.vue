<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
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
let watchId = null
let lastSentAt = 0
let refreshTimer

const route = (name, id) => ({
  'chauffeur.missions.accept': `/chauffeur/missions/${id}/accepter`,
  'chauffeur.missions.decline': `/chauffeur/missions/${id}/refuser`,
  'chauffeur.missions.start': `/chauffeur/missions/${id}/demarrer`,
  'chauffeur.missions.complete': `/chauffeur/missions/${id}/terminer`,
  'chauffeur.missions.position': `/chauffeur/missions/${id}/position`,
})[name]
const details = mission => mission.details || {}
const prix = mission => mission.prix_estime === null || mission.prix_estime === undefined
  ? 'Tarif à confirmer'
  : `${Number(mission.prix_estime).toLocaleString('fr-FR')} FCFA`
const date = value => value ? new Date(value).toLocaleString('fr-FR') : '—'
const navigationUrl = position => {
  if (!position || !Number.isFinite(Number(position.lat)) || !Number.isFinite(Number(position.lng))) return null
  const destination = `${Number(position.lat)},${Number(position.lng)}`
  return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(destination)}&travelmode=driving`
}

function refreshRequests() {
  router.reload({ only: ['activeMission', 'missionsDisponibles', 'historiqueMissions'], preserveScroll: true })
}

function accepter(mission) {
  router.post(route('chauffeur.missions.accept', mission.id), {}, { preserveScroll: true })
}

function refuser(mission) {
  router.post(route('chauffeur.missions.decline', mission.id), {}, { preserveScroll: true })
}

function envoyerPosition(position) {
  currentPosition.value = { lat: position.coords.latitude, lng: position.coords.longitude }
  const now = Date.now()
  if (sendingPosition.value || now - lastSentAt < 5000) return

  sendingPosition.value = true
  lastSentAt = now
  axios.put(route('chauffeur.missions.position', props.activeMission.id), currentPosition.value)
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

function demarrerMission() {
  if (!navigator.geolocation) {
    error.value = 'La géolocalisation est nécessaire pour démarrer le suivi.'
    return
  }

  navigator.geolocation.getCurrentPosition(
    position => {
      currentPosition.value = { lat: position.coords.latitude, lng: position.coords.longitude }
      router.post(route('chauffeur.missions.start', props.activeMission.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
          lastSentAt = 0
          envoyerPosition(position)
          activerSuivi()
        },
        onError: errors => { error.value = Object.values(errors).flat()[0] || 'La mission n’a pas pu démarrer.' },
      })
    },
    () => { error.value = 'Autorisez le GPS pour démarrer la mission et partager votre position.' },
    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 },
  )
}

function terminerMission() {
  router.post(route('chauffeur.missions.complete', props.activeMission.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      if (watchId !== null) navigator.geolocation.clearWatch(watchId)
      watchId = null
      currentPosition.value = null
    },
  })
}

onMounted(() => {
  refreshTimer = window.setInterval(refreshRequests, 15000)
})
onBeforeUnmount(() => {
  window.clearInterval(refreshTimer)
  if (watchId !== null) navigator.geolocation.clearWatch(watchId)
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

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">{{ error }}</p>

      <section v-if="activeMission" class="space-y-4 rounded-2xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="text-xl font-black">{{ activeMission.type === 'depannage' ? 'Dépannage' : 'Convoyage' }} en cours</h3>
            <p class="mt-1 text-sm text-gray-600">
              {{ activeMission.client?.prenom }} {{ activeMission.client?.nom }} · {{ activeMission.client?.telephone }}
            </p>
            <p v-if="activeMission.vehicule" class="text-sm text-gray-600">
              {{ activeMission.vehicule.marque }} {{ activeMission.vehicule.modele }} — {{ activeMission.vehicule.immatriculation }}
            </p>
          </div>
          <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
            {{ activeMission.statut === 'en_cours' ? 'Mission démarrée' : 'À démarrer' }}
          </span>
        </div>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
          <div v-for="(value, key) in details(activeMission)" :key="key">
            <dt class="font-bold capitalize text-gray-500">{{ key.replaceAll('_', ' ') }}</dt>
            <dd>{{ value }}</dd>
          </div>
          <div><dt class="font-bold text-gray-500">Tarif estimé</dt><dd>{{ prix(activeMission) }}</dd></div>
          <div><dt class="font-bold text-gray-500">Demande reçue</dt><dd>{{ date(activeMission.created_at) }}</dd></div>
        </dl>
        <MissionMap
          :start-position="activeMission.client_lat !== null ? { lat: Number(activeMission.client_lat), lng: Number(activeMission.client_lng) } : null"
          :end-position="activeMission.destination_lat !== null ? { lat: Number(activeMission.destination_lat), lng: Number(activeMission.destination_lng) } : null"
          :driver-position="currentPosition"
          :follow-driver="true"
        />
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="text-xs text-gray-500">
            Votre position et le trajet se mettent à jour pendant le déplacement. Le partage GPS s’arrête à la fin de la mission.
          </p>
          <a
            v-if="activeMission.client_lat !== null && activeMission.client_lat !== undefined"
            :href="navigationUrl({ lat: activeMission.client_lat, lng: activeMission.client_lng })"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-bold text-blue-800 hover:bg-blue-100"
          >
            <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i>
            Ouvrir la navigation
          </a>
        </div>
        <div class="flex flex-wrap gap-3">
          <button
            v-if="activeMission.statut === 'accepte'"
            type="button"
            class="rounded-xl bg-[#E11D48] px-5 py-3 font-bold text-white"
            @click="demarrerMission"
          >Autoriser le GPS et démarrer</button>
          <button
            v-else
            type="button"
            class="rounded-xl border px-5 py-3 font-bold"
            @click="activerSuivi"
          >Réactiver le partage GPS</button>
          <button
            v-if="activeMission.statut === 'en_cours'"
            type="button"
            class="rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white"
            @click="terminerMission"
          >Terminer la mission</button>
        </div>
      </section>

      <section v-if="!activeMission" class="rounded-2xl border border-dashed bg-white p-8 text-center">
        <h3 class="text-lg font-bold">Aucune mission active</h3>
        <p class="mt-1 text-sm text-gray-500">Acceptez une demande pour commencer. Une seule mission peut être active à la fois.</p>
      </section>

      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-black">Demandes disponibles</h3>
          <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-bold">{{ missionsDisponibles.length }}</span>
        </div>
        <article v-for="mission in missionsDisponibles" :key="mission.id" class="space-y-4 rounded-2xl border bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h4 class="text-lg font-bold">{{ mission.type === 'depannage' ? 'Dépannage' : 'Convoyage' }}</h4>
              <p class="text-sm text-gray-600">
                {{ mission.client?.prenom }} {{ mission.client?.nom }} · {{ mission.client?.telephone }}
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
          </dl>
          <MissionMap
            :start-position="mission.client_lat !== null ? { lat: Number(mission.client_lat), lng: Number(mission.client_lng) } : null"
            :end-position="mission.destination_lat !== null ? { lat: Number(mission.destination_lat), lng: Number(mission.destination_lng) } : null"
          />
          <div class="flex flex-wrap gap-3">
            <button type="button" class="rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50" :disabled="!!activeMission" @click="accepter(mission)">
              Accepter la mission
            </button>
            <button type="button" class="rounded-xl border border-rose-200 px-5 py-3 font-bold text-rose-700" @click="refuser(mission)">
              Refuser
            </button>
          </div>
        </article>
        <p v-if="!missionsDisponibles.length" class="rounded-xl bg-white p-6 text-center text-sm text-gray-500">
          Aucune nouvelle demande pour le moment.
        </p>
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
