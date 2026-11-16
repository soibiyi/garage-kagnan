<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { etapesGoogle, etapesOsrm, formatDistance, formatDuree, heureArrivee } from '@/utils/itineraire'

const authFailureListeners = () => {
  window.__garageGoogleMapsAuthFailureListeners ||= new Set()
  return window.__garageGoogleMapsAuthFailureListeners
}

function installGoogleMapsAuthFailureHandler() {
  if (window.__garageGoogleMapsAuthFailureHandlerInstalled) return

  const previousHandler = window.gm_authFailure
  window.gm_authFailure = () => {
    authFailureListeners().forEach(listener => listener())
    previousHandler?.()
  }
  window.__garageGoogleMapsAuthFailureHandlerInstalled = true
}

const props = defineProps({
  startPosition: { type: Object, default: null },
  endPosition: { type: Object, default: null },
  driverPosition: { type: Object, default: null },
  interactive: { type: Boolean, default: false },
  selectionTarget: { type: String, default: 'start' },
  followDriver: { type: Boolean, default: false },
  routeColor: { type: String, default: '#1769e0' },
  showTraffic: { type: Boolean, default: false },
  selectedRouteIndex: { type: Number, default: 0 },
  // Encart durée / distance / heure d'arrivée affiché sur la carte
  showSummary: { type: Boolean, default: true },
  // Mission en plusieurs étapes : numéro de l'étape en cours (1-based) et nombre d'étapes
  etape: { type: Number, default: null },
  etapesTotal: { type: Number, default: null },
  // Étape suivante, calculée à part pour donner le total de la mission : { start, end }
  etapeSuivante: { type: Object, default: null },
})
const emit = defineEmits(['point-selected', 'route-updated', 'routes-updated', 'route-selected', 'mission-updated'])

const mapElement = ref(null)
const mapError = ref('')
const routeError = ref('')
const loading = ref(true)
// Itinéraire sélectionné, pour l'encart affiché sur la carte
const resume = ref(null)
// Le chauffeur a déplacé la carte à la main : on arrête de la recentrer automatiquement
const carteDeplacee = ref(false)
// Rafraîchit l'heure d'arrivée estimée chaque minute
const maintenant = ref(Date.now())
let horloge
// Étape suivante (statique) : { distanceMeters, durationMillis }
const suite = ref(null)
let suiteSignature = ''
let suiteRequestId = 0

// Total de la mission = étape en cours + étape suivante (si elle existe)
const total = computed(() => {
  if (!resume.value) return null
  if (!props.etapeSuivante) return { distanceMeters: resume.value.distanceMeters, durationMillis: resume.value.durationMillis }
  if (!suite.value) return null
  const somme = (a, b) => (Number.isFinite(a) && Number.isFinite(b) ? a + b : null)
  return {
    distanceMeters: somme(resume.value.distanceMeters, suite.value.distanceMeters),
    durationMillis: somme(resume.value.durationMillis, suite.value.durationMillis),
  }
})

const resumeTexte = computed(() => {
  if (!resume.value?.distanceMeters && !resume.value?.durationMillis) return null
  const depuis = Math.max(resume.value.calculeA, maintenant.value - 60000)
  const multi = Number.isFinite(props.etape) && Number.isFinite(props.etapesTotal) && props.etapesTotal > 1
  const fin = multi && props.etapeSuivante ? total.value : resume.value
  return {
    multi,
    etape: multi ? `Étape ${props.etape}/${props.etapesTotal}` : '',
    duree: formatDuree(resume.value.durationMillis),
    distance: formatDistance(resume.value.distanceMeters),
    total: multi && props.etapeSuivante && total.value
      ? [formatDuree(total.value.durationMillis), formatDistance(total.value.distanceMeters)].filter(Boolean).join(' · ')
      : '',
    // Heure d'arrivée à la destination finale de la mission
    arrivee: Number.isFinite(fin?.durationMillis) ? heureArrivee(fin.durationMillis, depuis) : '',
  }
})

watch(
  () => [resume.value, suite.value, props.etape, props.etapesTotal],
  () => emit('mission-updated', {
    etape: props.etape,
    etapesTotal: props.etapesTotal,
    etapeEnCours: resume.value ? { distanceMeters: resume.value.distanceMeters, durationMillis: resume.value.durationMillis } : null,
    etapeSuivante: suite.value,
    total: total.value,
  }),
)
const peutRecentrer = computed(() => !loading.value && !mapError.value && (carteDeplacee.value || !props.followDriver))
let map
let RouteClass
let MarkerClass
let PinElementClass
let PolylineClass
let startMarker
let endMarker
let driverMarker
let routeLayers = []
let routeCasing
let routeCandidates = []
let dragListener
let trafficLayer
let routeTimer
let lastRouteAt = 0
let lastRouteDriverPosition = null
let lastRouteSignature = ''
let lastStaticSignature = ''
let routeRequestId = 0
let driverAnimationFrame
let clickListener
let authFailureListener
let hasFittedBounds = false

function isValid(point) {
  return point && Number.isFinite(Number(point.lat)) && Number.isFinite(Number(point.lng))
}

function normalizedPoint(point) {
  return { lat: Number(point.lat), lng: Number(point.lng) }
}

function distanceInMeters(first, second) {
  if (!isValid(first) || !isValid(second)) return Infinity

  const radians = value => value * Math.PI / 180
  const latitudeDelta = radians(Number(second.lat) - Number(first.lat))
  const longitudeDelta = radians(Number(second.lng) - Number(first.lng))
  const latitude1 = radians(Number(first.lat))
  const latitude2 = radians(Number(second.lat))
  const value = Math.sin(latitudeDelta / 2) ** 2
    + Math.cos(latitude1) * Math.cos(latitude2) * Math.sin(longitudeDelta / 2) ** 2

  return 6371000 * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value))
}

function pointSignature(point) {
  return isValid(point) ? `${Number(point.lat).toFixed(5)},${Number(point.lng).toFixed(5)}` : ''
}

function createMapMarker(position, color, title, driver = false) {
  const pin = new PinElementClass({
    background: color,
    borderColor: '#ffffff',
    glyphColor: '#ffffff',
    glyphText: driver ? 'D' : '',
    scale: driver ? 1.15 : 1,
  })

  const marker = new MarkerClass({
    map,
    position: normalizedPoint(position),
    title,
    zIndex: driver ? 3 : 2,
  })
  marker.append(pin)
  return marker
}

function updateStaticMarker(marker, position, color, title) {
  if (!isValid(position)) {
    if (marker) marker.map = null
    return null
  }

  if (!marker) {
    return createMapMarker(position, color, title)
  }

  marker.position = normalizedPoint(position)
  return marker
}

function animateDriverMarker(target) {
  const from = driverMarker.position ? normalizedPoint(driverMarker.position) : target
  const startedAt = performance.now()
  const duration = 800

  if (driverAnimationFrame) cancelAnimationFrame(driverAnimationFrame)

  const animate = now => {
    const progress = Math.min((now - startedAt) / duration, 1)
    const eased = 1 - (1 - progress) ** 3
    driverMarker.position = {
      lat: from.lat + (target.lat - from.lat) * eased,
      lng: from.lng + (target.lng - from.lng) * eased,
    }

    if (progress < 1) driverAnimationFrame = requestAnimationFrame(animate)
  }

  driverAnimationFrame = requestAnimationFrame(animate)
}

function updateDriverMarker() {
  if (!isValid(props.driverPosition)) {
    if (driverMarker) driverMarker.map = null
    driverMarker = null
    if (driverAnimationFrame) cancelAnimationFrame(driverAnimationFrame)
    return
  }

  const target = normalizedPoint(props.driverPosition)
  if (!driverMarker) {
    driverMarker = createMapMarker(target, '#2563eb', 'Position du chauffeur', true)
    if (props.followDriver && !carteDeplacee.value) map.setCenter(target)
  } else {
    animateDriverMarker(target)
  }

  if (props.followDriver && !carteDeplacee.value) map.panTo(target)
}

/** Recentre sur le chauffeur (mode suivi) ou sur l'ensemble du trajet. */
function recentrer() {
  if (!map) return
  carteDeplacee.value = false
  if (props.followDriver && isValid(props.driverPosition)) {
    map.panTo(normalizedPoint(props.driverPosition))
    if (map.getZoom() < 15) map.setZoom(16)
    return
  }
  hasFittedBounds = false
  fitRoute()
}

function routePoints() {
  if (props.followDriver && !isValid(props.driverPosition)) return []

  const points = isValid(props.driverPosition)
    ? [props.driverPosition, props.endPosition]
    : [props.startPosition, props.endPosition]

  return points
    .filter(isValid)
    .map(normalizedPoint)
    .filter((point, index, normalizedPoints) => index === 0 || distanceInMeters(normalizedPoints[index - 1], point) > 1)
}

function fitRoute() {
  if (hasFittedBounds) return

  const bounds = new google.maps.LatLngBounds()
  for (const point of [props.startPosition, props.endPosition, props.driverPosition].filter(isValid)) {
    bounds.extend(normalizedPoint(point))
  }
  // Tout le tracé sélectionné doit être visible, pas seulement ses extrémités
  const selected = routeCandidates[props.selectedRouteIndex] || routeCandidates[0]
  for (const point of selected?.path || []) bounds.extend(point)
  if (!bounds.isEmpty()) {
    map.fitBounds(bounds, { top: 70, right: 50, bottom: 50, left: 50 })
    hasFittedBounds = true
  }
}

function emitSelectedRoute() {
  const selected = routeCandidates[props.selectedRouteIndex] || routeCandidates[0]
  resume.value = selected ? {
    distanceMeters: selected.distanceMeters ?? null,
    durationMillis: selected.durationMillis ?? null,
    calculeA: selected.calculeA ?? Date.now(),
  } : null
  emit('route-updated', selected ? {
    distanceMeters: selected.distanceMeters ?? null,
    durationMillis: selected.durationMillis ?? null,
    steps: selected.steps || [],
  } : { distanceMeters: null, durationMillis: null, steps: [] })
}

function updateRouteStyles() {
  const selectedIndex = routeCandidates[props.selectedRouteIndex] ? props.selectedRouteIndex : 0
  const arrows = window.google?.maps?.SymbolPath
    ? [{
        icon: {
          path: window.google.maps.SymbolPath.FORWARD_OPEN_ARROW,
          scale: 2.2,
          strokeColor: '#ffffff',
          strokeOpacity: 0.95,
          strokeWeight: 2,
        },
        offset: '30px',
        repeat: '110px',
      }]
    : []

  routeLayers.forEach((layer, index) => {
    const selected = index === selectedIndex
    layer.setOptions({
      strokeColor: selected ? props.routeColor : '#94a3b8',
      strokeOpacity: selected ? 1 : 0.75,
      strokeWeight: selected ? 6 : 5,
      zIndex: selected ? 4 : 2,
      icons: selected ? arrows : [],
    })
  })

  // Liseré blanc sous l'itinéraire sélectionné : lisible même sur la vue satellite
  const selectedPath = routeCandidates[selectedIndex]?.path
  if (selectedPath?.length > 1 && PolylineClass) {
    if (!routeCasing) {
      routeCasing = new PolylineClass({ map, clickable: false, strokeColor: '#ffffff', strokeOpacity: 0.95, strokeWeight: 10, zIndex: 3 })
    }
    routeCasing.setPath(selectedPath)
    routeCasing.setMap(map)
  } else {
    routeCasing?.setMap(null)
  }
  emitSelectedRoute()
}

function updateRoutePaths(routes) {
  routeLayers.forEach(layer => layer.setMap(null))
  const calculeA = Date.now()
  routeCandidates = routes.filter(route => route.path?.length > 1).map(route => ({ ...route, calculeA }))
  routeLayers = routeCandidates.map((route, index) => {
    const layer = new PolylineClass({
      map,
      path: route.path,
      strokeColor: '#94a3b8',
      strokeOpacity: 0.75,
      strokeWeight: 5,
      zIndex: 2,
      clickable: true,
    })
    layer.addListener('click', () => emit('route-selected', index))
    return layer
  })
  emit('routes-updated', routeCandidates.map((route, index) => ({
    index,
    label: route.label || (index === 0 ? 'Itinéraire principal' : `Alternative ${index}`),
    distanceMeters: route.distanceMeters ?? null,
    durationMillis: route.durationMillis ?? null,
  })))
  updateRouteStyles()
}

async function loadFallbackRoutes(points) {
  const coordinates = points.map(point => `${point.lng},${point.lat}`).join(';')
  const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson&alternatives=true&steps=true`)
  if (!response.ok) throw new Error('Le service de routage alternatif est indisponible.')

  const data = await response.json()
  if (data.code !== 'Ok' || !data.routes?.length) throw new Error('Aucun itinéraire routier n’a été trouvé.')

  return data.routes.map((route, index) => ({
    path: route.geometry.coordinates.map(([lng, lat]) => ({ lat, lng })),
    distanceMeters: route.distance,
    durationMillis: route.duration * 1000,
    steps: etapesOsrm(route),
    label: index === 0 ? 'Itinéraire principal' : `Alternative ${index}`,
  }))
}

/** Calcule (une fois par couple de points) la distance et la durée de l'étape suivante. */
async function chargerEtapeSuivante() {
  const { start, end } = props.etapeSuivante || {}
  if (!isValid(start) || !isValid(end)) {
    suiteSignature = ''
    suiteRequestId++
    suite.value = null
    return
  }

  const signature = `${pointSignature(start)}|${pointSignature(end)}`
  if (signature === suiteSignature) return
  suiteSignature = signature
  const requestId = ++suiteRequestId
  suite.value = null

  const origin = normalizedPoint(start)
  const destination = normalizedPoint(end)
  if (distanceInMeters(origin, destination) <= 1) {
    suite.value = { distanceMeters: 0, durationMillis: 0 }
    return
  }

  try {
    const { Route } = await window.google.maps.importLibrary('routes')
    // Sans trafic : l'étape suivante n'aura lieu que plus tard
    const { routes } = await Route.computeRoutes({
      origin,
      destination,
      travelMode: 'DRIVING',
      fields: ['distanceMeters', 'durationMillis'],
    })
    if (requestId !== suiteRequestId) return
    const route = routes?.[0]
    if (!route || !Number.isFinite(route.distanceMeters)) throw new Error('Aucun itinéraire')
    suite.value = { distanceMeters: route.distanceMeters, durationMillis: route.durationMillis ?? null }
  } catch {
    try {
      const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${origin.lng},${origin.lat};${destination.lng},${destination.lat}?overview=false`)
      const data = await response.json()
      if (requestId !== suiteRequestId) return
      const route = data.routes?.[0]
      suite.value = route ? { distanceMeters: route.distance, durationMillis: route.duration * 1000 } : null
    } catch {
      if (requestId === suiteRequestId) {
        suite.value = null
        suiteSignature = '' // nouvel essai à la prochaine mise à jour
      }
    }
  }
}

async function loadRoute(points, signature) {
  const requestId = ++routeRequestId
  lastRouteAt = Date.now()
  lastRouteDriverPosition = isValid(props.driverPosition)
    ? normalizedPoint(props.driverPosition)
    : null
  lastRouteSignature = signature
  routeError.value = ''

  try {
    const importLibrary = window.google.maps.importLibrary.bind(window.google.maps)
    const { Route } = await importLibrary('routes')
    if (requestId !== routeRequestId) return
    RouteClass = Route
    const { routes } = await RouteClass.computeRoutes({
      origin: points[0],
      destination: points[points.length - 1],
      computeAlternativeRoutes: points.length === 2,
      travelMode: 'DRIVING',
      ...(props.showTraffic ? { routingPreference: 'TRAFFIC_AWARE' } : {}),
      language: 'fr',
      fields: ['path', 'distanceMeters', 'durationMillis', 'routeLabels', 'legs'],
    })
    if (requestId !== routeRequestId) return

    if (!routes?.length || !routes[0].path?.length) {
      throw new Error('Aucun itinéraire routier n’a été trouvé.')
    }

    updateRoutePaths(routes.map((route, index) => ({
      path: route.path,
      distanceMeters: route.distanceMeters ?? route.legs?.[0]?.distanceMeters,
      durationMillis: route.durationMillis ?? route.legs?.[0]?.durationMillis,
      steps: etapesGoogle(route),
      label: route.routeLabels?.includes('DEFAULT_ROUTE')
        ? 'Itinéraire recommandé'
        : index === 0 ? 'Itinéraire principal' : `Alternative ${index}`,
    })))
    fitRoute()
  } catch (error) {
    if (requestId !== routeRequestId) return
    try {
      const routes = await loadFallbackRoutes(points)
      if (requestId !== routeRequestId) return
      routeError.value = 'Itinéraire routier de secours affiché.'
      updateRoutePaths(routes)
    } catch (fallbackError) {
      if (requestId !== routeRequestId) return
      routeError.value = fallbackError.message || error.message || 'Impossible de calculer le trajet.'
      updateRoutePaths([{ path: points, label: 'Tracé direct' }])
    }
    fitRoute()
  }
}

function updateMap() {
  if (!map || !MarkerClass) return

  updateRouteStyles()
  startMarker = updateStaticMarker(startMarker, props.startPosition, '#e11d48', 'Position du client')
  const sameAsStart = isValid(props.startPosition)
    && isValid(props.endPosition)
    && distanceInMeters(props.startPosition, props.endPosition) <= 1
  endMarker = updateStaticMarker(endMarker, sameAsStart ? null : props.endPosition, '#111827', 'Destination')
  updateDriverMarker()
  chargerEtapeSuivante()

  const staticSignature = `${pointSignature(props.startPosition)}|${pointSignature(props.endPosition)}`
  const staticPointsChanged = staticSignature !== lastStaticSignature
  if (staticPointsChanged) {
    lastStaticSignature = staticSignature
    hasFittedBounds = false
  }

  const points = routePoints()
  if (points.length < 2) {
    routeRequestId++
    if (routeTimer) window.clearTimeout(routeTimer)
    routeTimer = null
    routeLayers.forEach(layer => layer.setMap(null))
    routeLayers = []
    routeCandidates = []
    routeCasing?.setMap(null)
    emit('routes-updated', [])
    emitSelectedRoute()
    if (points.length === 1 && !props.followDriver) map.setCenter(points[0])
    emit('route-updated', { distanceMeters: null, durationMillis: null })
    return
  }

  const driverIsPresent = isValid(props.driverPosition)
  const driverPresenceChanged = driverIsPresent !== isValid(lastRouteDriverPosition)
  const driverMoved = driverIsPresent
    && isValid(lastRouteDriverPosition)
    && distanceInMeters(lastRouteDriverPosition, props.driverPosition) >= 35
  if (!lastRouteSignature || staticPointsChanged || driverPresenceChanged || driverMoved) {
    if (routeTimer) return

    const wait = driverMoved && !staticPointsChanged
      ? Math.max(0, 10000 - (Date.now() - lastRouteAt))
      : 0
    routeTimer = window.setTimeout(() => {
      routeTimer = null
      loadRoute(routePoints(), `${staticSignature}|${pointSignature(props.driverPosition)}`)
    }, wait)
  }
}

async function initializeMap() {
  const key = import.meta.env.VITE_GOOGLE_MAPS_KEY?.trim()
  if (!key) {
    mapError.value = 'Configurez VITE_GOOGLE_MAPS_KEY dans le fichier .env pour afficher Google Maps.'
    loading.value = false
    return
  }

  try {
    installGoogleMapsAuthFailureHandler()
    authFailureListener = () => {
      mapError.value = 'Google Maps refuse cette clé. Vérifiez les restrictions de domaine, la facturation et l’API Maps JavaScript.'
      loading.value = false
    }
    authFailureListeners().add(authFailureListener)
    if (!window.google?.maps?.importLibrary) {
      window.__garageGoogleMapsPromise ||= new Promise((resolve, reject) => {
        const callbackName = '__garageGoogleMapsReady'
        const script = document.createElement('script')
        window[callbackName] = () => {
          delete window[callbackName]
          resolve()
        }
        script.onerror = () => {
          delete window[callbackName]
          delete window.__garageGoogleMapsPromise
          reject(new Error('Google Maps n’a pas pu être chargé. Vérifiez la clé API et les domaines autorisés.'))
        }
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly&language=fr&region=CI&loading=async&callback=${callbackName}`
        script.async = true
        script.defer = true
        document.head.appendChild(script)
      })
      await window.__garageGoogleMapsPromise
    }
    const importLibrary = window.google?.maps?.importLibrary?.bind(window.google.maps)
    if (!importLibrary) throw new Error('La bibliothèque Google Maps est indisponible.')

    const { Map, Polyline } = await importLibrary('maps')
    const { AdvancedMarkerElement, PinElement } = await importLibrary('marker')
    if (!mapElement.value) return

    MarkerClass = AdvancedMarkerElement
    PinElementClass = PinElement
    if (!MarkerClass || !PinElementClass) throw new Error('Les repères Google Maps avancés sont indisponibles.')
    PolylineClass = Polyline
    const mapId = import.meta.env.VITE_GOOGLE_MAPS_MAP_ID?.trim()
    if (!mapId && import.meta.env.PROD) throw new Error('Configurez VITE_GOOGLE_MAPS_MAP_ID pour utiliser les repères avancés en production.')
    map = new Map(mapElement.value, {
      mapId: mapId || 'DEMO_MAP_ID',
      center: { lat: 5.35, lng: -4.01 },
      zoom: 11,
      mapTypeId: 'hybrid',
      fullscreenControl: true,
      streetViewControl: false,
      mapTypeControl: true,
      mapTypeControlOptions: {
        style: window.google.maps.MapTypeControlStyle?.DROPDOWN_MENU,
        position: window.google.maps.ControlPosition?.TOP_LEFT,
      },
      clickableIcons: false,
      gestureHandling: 'greedy',
    })
    // Un déplacement manuel suspend le recentrage automatique sur le chauffeur
    dragListener = map.addListener('dragstart', () => {
      if (props.followDriver) carteDeplacee.value = true
    })
    if (props.showTraffic && window.google.maps.TrafficLayer) {
      trafficLayer = new window.google.maps.TrafficLayer({ map })
    }
    clickListener = map.addListener('click', event => {
      if (props.interactive) {
        emit('point-selected', {
          target: props.selectionTarget,
          position: { lat: event.latLng.lat(), lng: event.latLng.lng() },
        })
      }
    })
    loading.value = false
    updateMap()
  } catch (error) {
    mapError.value = error.message || 'Impossible d’initialiser Google Maps.'
    loading.value = false
  }
}

onMounted(() => {
  initializeMap()
  horloge = window.setInterval(() => { maintenant.value = Date.now() }, 30000)
})

defineExpose({ recentrer })

watch(
  () => [props.startPosition, props.endPosition, props.driverPosition, props.routeColor, props.showTraffic, props.selectedRouteIndex, props.followDriver, props.etapeSuivante],
  updateMap,
  { deep: true },
)

onBeforeUnmount(() => {
  routeRequestId++
  if (routeTimer) window.clearTimeout(routeTimer)
  if (driverAnimationFrame) cancelAnimationFrame(driverAnimationFrame)
  window.clearInterval(horloge)
  clickListener?.remove()
  dragListener?.remove()
  routeCasing?.setMap(null)
  if (authFailureListener) authFailureListeners().delete(authFailureListener)
  trafficLayer?.setMap(null)
  routeLayers.forEach(layer => layer.setMap(null))
  for (const marker of [startMarker, endMarker, driverMarker]) {
    if (marker) marker.map = null
  }
})
</script>

<template>
  <div>
    <div class="mission-map-wrap">
      <div ref="mapElement" class="mission-map" aria-label="Carte Google Maps du trajet" />

      <!-- Résumé du trajet sélectionné -->
      <div v-if="showSummary && resumeTexte" class="mission-map-summary" role="status">
        <template v-if="resumeTexte.multi">
          <span class="mission-map-summary-etape">
            {{ resumeTexte.etape }} : <strong>{{ resumeTexte.duree }}</strong> · {{ resumeTexte.distance }}
          </span>
          <span v-if="etapeSuivante" class="mission-map-summary-detail">
            Mission totale : {{ resumeTexte.total || 'calcul…' }}
          </span>
          <span v-if="resumeTexte.arrivee" class="mission-map-summary-detail">
            {{ etape === etapesTotal ? 'Arrivée' : 'Fin de mission' }} ~{{ resumeTexte.arrivee }}
          </span>
        </template>
        <template v-else>
          <span class="mission-map-summary-duree">{{ resumeTexte.duree }}</span>
          <span class="mission-map-summary-detail">
            {{ resumeTexte.distance }}<template v-if="resumeTexte.arrivee"> · arrivée ~{{ resumeTexte.arrivee }}</template>
          </span>
        </template>
      </div>

      <button
        v-if="peutRecentrer && (resume || driverPosition)"
        type="button"
        class="mission-map-recentrer"
        :class="{ 'is-active': carteDeplacee }"
        :aria-label="followDriver ? 'Recentrer sur ma position' : 'Voir tout le trajet'"
        :title="followDriver ? 'Recentrer sur ma position' : 'Voir tout le trajet'"
        @click="recentrer"
      >
        <i :class="followDriver ? 'fa-solid fa-location-crosshairs' : 'fa-solid fa-expand'" aria-hidden="true"></i>
        <span v-if="carteDeplacee">Recentrer</span>
      </button>
    </div>
    <p v-if="loading" class="mt-2 text-sm text-gray-500" role="status">Chargement de Google Maps…</p>
    <p v-if="mapError" class="mt-2 text-sm text-rose-700" role="alert">{{ mapError }}</p>
    <p v-if="routeError" class="mt-2 text-sm text-amber-700" role="status">{{ routeError }}</p>
  </div>
</template>

<style scoped>
.mission-map-wrap{position:relative;height:100%}
.mission-map{width:100%;height:clamp(300px,48vh,520px);min-height:280px;border-radius:1rem;overflow:hidden}
.mission-map-summary{position:absolute;top:10px;left:50%;z-index:2;display:flex;max-width:calc(100% - 140px);transform:translateX(-50%);flex-direction:column;align-items:center;border-radius:14px;background:rgba(11,15,25,.88);padding:6px 14px;color:#fff;line-height:1.15;box-shadow:0 6px 20px rgba(0,0,0,.25);pointer-events:none;backdrop-filter:blur(6px)}
.mission-map-summary-duree{font-size:16px;font-weight:900;white-space:nowrap}
.mission-map-summary-etape{font-size:13px;font-weight:600;white-space:nowrap}
.mission-map-summary-etape strong{font-size:15px;font-weight:900}
.mission-map-summary-detail{font-size:11px;font-weight:600;color:rgba(255,255,255,.75);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.mission-map-recentrer{position:absolute;top:60px;right:10px;z-index:2;display:inline-flex;height:40px;min-width:40px;align-items:center;justify-content:center;gap:6px;border:0;border-radius:999px;background:#fff;padding:0 12px;color:#0b0f19;font-size:12px;font-weight:800;box-shadow:0 2px 8px rgba(0,0,0,.3);transition:background .15s,color .15s}
.mission-map-recentrer:hover{background:#f1f5f9}
.mission-map-recentrer.is-active{background:#2563eb;color:#fff}
@media(max-width:480px){.mission-map-summary{max-width:calc(100% - 120px);padding:5px 10px}.mission-map-summary-duree{font-size:14px}}
</style>
