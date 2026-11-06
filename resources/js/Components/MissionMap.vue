<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
})
const emit = defineEmits(['point-selected', 'route-updated', 'routes-updated', 'route-selected'])

const mapElement = ref(null)
const mapError = ref('')
const routeError = ref('')
const loading = ref(true)
let map
let RouteClass
let MarkerClass
let PinElementClass
let PolylineClass
let startMarker
let endMarker
let driverMarker
let routeLayers = []
let routeCandidates = []
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
    if (props.followDriver) map.setCenter(target)
  } else {
    animateDriverMarker(target)
  }

  if (props.followDriver) map.panTo(target)
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
  if (!bounds.isEmpty()) {
    map.fitBounds(bounds, 40)
    hasFittedBounds = true
  }
}

function emitSelectedRoute() {
  const selected = routeCandidates[props.selectedRouteIndex] || routeCandidates[0]
  emit('route-updated', selected ? {
    distanceMeters: selected.distanceMeters ?? null,
    durationMillis: selected.durationMillis ?? null,
  } : { distanceMeters: null, durationMillis: null })
}

function updateRouteStyles() {
  routeLayers.forEach((layer, index) => {
    const selected = index === props.selectedRouteIndex
    layer.setOptions({
      strokeColor: selected ? props.routeColor : '#7b8b9b',
      strokeOpacity: selected ? 0.95 : 0.5,
      strokeWeight: selected ? 7 : 4,
      zIndex: selected ? 3 : 2,
    })
  })
  emitSelectedRoute()
}

function updateRoutePaths(routes) {
  routeLayers.forEach(layer => layer.setMap(null))
  routeCandidates = routes.filter(route => route.path?.length > 1)
  routeLayers = routeCandidates.map((route, index) => {
    const layer = new PolylineClass({
      map,
      path: route.path,
      strokeColor: '#7b8b9b',
      strokeOpacity: 0.5,
      strokeWeight: 4,
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
    label: index === 0 ? 'Itinéraire principal' : `Alternative ${index}`,
  }))
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
      fields: ['path', 'distanceMeters', 'durationMillis', 'routeLabels', 'legs.distanceMeters', 'legs.durationMillis'],
    })
    if (requestId !== routeRequestId) return

    if (!routes?.length || !routes[0].path?.length) {
      throw new Error('Aucun itinéraire routier n’a été trouvé.')
    }

    updateRoutePaths(routes.map((route, index) => ({
      path: route.path,
      distanceMeters: route.distanceMeters ?? route.legs?.[0]?.distanceMeters,
      durationMillis: route.durationMillis ?? route.legs?.[0]?.durationMillis,
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
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly&loading=async&callback=${callbackName}`
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
      clickableIcons: false,
      gestureHandling: 'greedy',
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

onMounted(initializeMap)

watch(
  () => [props.startPosition, props.endPosition, props.driverPosition, props.routeColor, props.showTraffic, props.selectedRouteIndex, props.followDriver],
  updateMap,
  { deep: true },
)

onBeforeUnmount(() => {
  routeRequestId++
  if (routeTimer) window.clearTimeout(routeTimer)
  if (driverAnimationFrame) cancelAnimationFrame(driverAnimationFrame)
  clickListener?.remove()
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
    <div ref="mapElement" class="mission-map" aria-label="Carte Google Maps du trajet" />
    <p v-if="loading" class="mt-2 text-sm text-gray-500" role="status">Chargement de Google Maps…</p>
    <p v-if="mapError" class="mt-2 text-sm text-rose-700" role="alert">{{ mapError }}</p>
    <p v-if="routeError" class="mt-2 text-sm text-amber-700" role="status">{{ routeError }}</p>
  </div>
</template>

<style scoped>
.mission-map{width:100%;height:clamp(300px,48vh,520px);min-height:280px;border-radius:1rem;overflow:hidden}
</style>
