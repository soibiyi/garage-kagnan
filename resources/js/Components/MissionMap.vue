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
})
const emit = defineEmits(['point-selected'])

const mapElement = ref(null)
const mapError = ref('')
const routeError = ref('')
const loading = ref(true)
let map
let RouteClass
let MarkerClass
let PolylineClass
let startMarker
let endMarker
let driverMarker
let routeLayer
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

function markerIcon(color, driver = false) {
  return {
    path: driver ? 'M 0,-2 5,10 0,7 -5,10 z' : google.maps.SymbolPath.CIRCLE,
    fillColor: color,
    fillOpacity: 1,
    strokeColor: '#ffffff',
    strokeWeight: driver ? 2 : 3,
    scale: driver ? 1.8 : 8,
    anchor: new google.maps.Point(0, driver ? 3 : 0),
  }
}

function updateStaticMarker(marker, position, color, title) {
  if (!isValid(position)) {
    marker?.setMap(null)
    return null
  }

  if (!marker) {
    return new MarkerClass({
      map,
      position: normalizedPoint(position),
      title,
      icon: markerIcon(color),
      zIndex: 2,
    })
  }

  marker.setPosition(normalizedPoint(position))
  return marker
}

function animateDriverMarker(target) {
  const current = driverMarker.getPosition()
  const from = current ? { lat: current.lat(), lng: current.lng() } : target
  const startedAt = performance.now()
  const duration = 800

  if (driverAnimationFrame) cancelAnimationFrame(driverAnimationFrame)

  const animate = now => {
    const progress = Math.min((now - startedAt) / duration, 1)
    const eased = 1 - (1 - progress) ** 3
    driverMarker.setPosition({
      lat: from.lat + (target.lat - from.lat) * eased,
      lng: from.lng + (target.lng - from.lng) * eased,
    })

    if (progress < 1) driverAnimationFrame = requestAnimationFrame(animate)
  }

  driverAnimationFrame = requestAnimationFrame(animate)
}

function updateDriverMarker() {
  if (!isValid(props.driverPosition)) {
    driverMarker?.setMap(null)
    driverMarker = null
    if (driverAnimationFrame) cancelAnimationFrame(driverAnimationFrame)
    return
  }

  const target = normalizedPoint(props.driverPosition)
  if (!driverMarker) {
    driverMarker = new MarkerClass({
      map,
      position: target,
      title: 'Position du chauffeur',
      icon: markerIcon('#2563eb', true),
      zIndex: 3,
    })
    if (props.followDriver) map.setCenter(target)
  } else {
    animateDriverMarker(target)
  }

  if (props.followDriver) map.panTo(target)
}

function routePoints() {
  if (props.followDriver && !isValid(props.driverPosition)) return []

  return [props.driverPosition, props.startPosition, props.endPosition]
    .filter(isValid)
    .map(normalizedPoint)
}

function fitRoute() {
  if (hasFittedBounds || props.followDriver) return

  const bounds = new google.maps.LatLngBounds()
  for (const point of [props.startPosition, props.endPosition, props.driverPosition].filter(isValid)) {
    bounds.extend(normalizedPoint(point))
  }
  if (!bounds.isEmpty()) {
    map.fitBounds(bounds, 40)
    hasFittedBounds = true
  }
}

function updateRoutePath(path) {
  if (!routeLayer) {
    routeLayer = new PolylineClass({
      map,
      path,
      strokeColor: '#1769e0',
      strokeOpacity: 0.9,
      strokeWeight: 6,
      zIndex: 1,
    })
  } else {
    routeLayer.setPath(path)
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
      ...(points.length > 2 ? { intermediates: points.slice(1, -1) } : {}),
      travelMode: 'DRIVING',
      fields: ['path'],
    })
    if (requestId !== routeRequestId) return

    if (!routes?.length || !routes[0].path?.length) {
      throw new Error('Aucun itinéraire routier n’a été trouvé.')
    }

    updateRoutePath(routes[0].path)
    fitRoute()
  } catch (error) {
    if (requestId !== routeRequestId) return
    routeError.value = error.message || 'Impossible de calculer le trajet.'
    updateRoutePath(points)
    fitRoute()
  }
}

function updateMap() {
  if (!map || !MarkerClass) return

  startMarker = updateStaticMarker(startMarker, props.startPosition, '#e11d48', 'Position du client')
  endMarker = updateStaticMarker(endMarker, props.endPosition, '#111827', 'Destination')
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
    routeLayer?.setMap(null)
    routeLayer = null
    if (points.length === 1 && !props.followDriver) map.setCenter(points[0])
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
    if (!mapElement.value) return

    MarkerClass = window.google.maps.Marker
    if (!MarkerClass) throw new Error('Les repères Google Maps sont indisponibles.')
    PolylineClass = Polyline
    map = new Map(mapElement.value, {
      center: { lat: 5.35, lng: -4.01 },
      zoom: 11,
      mapTypeId: 'hybrid',
      fullscreenControl: true,
      streetViewControl: false,
      mapTypeControl: true,
      clickableIcons: false,
      gestureHandling: 'greedy',
    })
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
  () => [props.startPosition, props.endPosition, props.driverPosition],
  updateMap,
  { deep: true },
)

onBeforeUnmount(() => {
  routeRequestId++
  if (routeTimer) window.clearTimeout(routeTimer)
  if (driverAnimationFrame) cancelAnimationFrame(driverAnimationFrame)
  clickListener?.remove()
  if (authFailureListener) authFailureListeners().delete(authFailureListener)
  if (routeLayer) routeLayer.setMap(null)
  for (const marker of [startMarker, endMarker, driverMarker]) marker?.setMap(null)
})
</script>

<template>
  <div>
    <div ref="mapElement" class="mission-map" aria-label="Carte Google Maps du trajet" />
    <p v-if="loading" class="mt-2 text-sm text-gray-500" role="status">Chargement de Google Maps…</p>
    <p v-if="mapError" class="mt-2 text-sm text-rose-700" role="alert">{{ mapError }}</p>
    <p v-if="routeError" class="mt-2 text-sm text-amber-700" role="status">{{ routeError }} Un tracé direct est affiché.</p>
  </div>
</template>

<style scoped>
.mission-map{width:100%;height:clamp(300px,48vh,520px);min-height:280px;border-radius:1rem;overflow:hidden}
</style>
