<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { state, save, notify, toast, go, TARIFS, GARAGE, fcfa, now } from '../store.js'

const props = defineProps({ mode: { type: String, required: true } })

const mapEl = ref(null)
let map, mA, mB, line // objets Leaflet : volontairement non reactifs

const pick = ref('A')
const A = ref(null)
const B = ref(null)
const choix = ref(0)
const dist = ref(0)
const note = ref('')
const labelA = ref('Point de départ')
const labelB = ref(props.mode === 'depannage' ? 'Garage Kagnan (modifiable)' : 'Garage Kagnan')

const options = computed(() => TARIFS[props.mode])
const calc = t => (t.base + t.km * dist.value) * (t.mult || 1)
const prix = computed(() => dist.value ? fcfa(calc(options.value[choix.value])) : '—')

function place(ll, force) {
  const p = force || pick.value
  const pt = { lat: ll.lat, lng: ll.lng }
  if (p === 'A') {
    A.value = pt
    if (mA) map.removeLayer(mA)
    mA = L.circleMarker(pt, { radius: 9, color: '#17a865', fillOpacity: 1 }).addTo(map)
    labelA.value = `Départ : ${pt.lat.toFixed(4)}, ${pt.lng.toFixed(4)}`
    pick.value = 'B'
  } else {
    B.value = pt
    if (mB) map.removeLayer(mB)
    mB = L.circleMarker(pt, { radius: 9, color: '#e11d48', fillOpacity: 1 }).addTo(map)
    labelB.value = `Arrivée : ${pt.lat.toFixed(4)}, ${pt.lng.toFixed(4)}`
  }
  route()
}

async function route() {
  if (!A.value || !B.value) return
  if (line) map.removeLayer(line)
  const a = A.value, b = B.value
  try {
    const r = await fetch(`https://router.project-osrm.org/route/v1/driving/${a.lng},${a.lat};${b.lng},${b.lat}?overview=full&geometries=geojson`).then(r => r.json())
    dist.value = r.routes[0].distance / 1000
    line = L.geoJSON(r.routes[0].geometry, { style: { color: '#0b0f19', weight: 5 } }).addTo(map)
  } catch {
    const R = 6371, dLat = (b.lat - a.lat) * Math.PI / 180, dLng = (b.lng - a.lng) * Math.PI / 180
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * Math.PI / 180) * Math.cos(b.lat * Math.PI / 180) * Math.sin(dLng / 2) ** 2
    dist.value = R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h)) * 1.3
    line = L.polyline([a, b], { color: '#0b0f19', weight: 4, dashArray: '8' }).addTo(map)
  }
  map.fitBounds(line.getBounds(), { padding: [40, 40] })
}

function geoloc() {
  if (!navigator.geolocation) return toast('Géolocalisation non supportée')
  navigator.geolocation.getCurrentPosition(
    p => place({ lat: p.coords.latitude, lng: p.coords.longitude }, 'A'),
    () => toast('Position indisponible'))
}

function commander() {
  if (!A.value || !B.value || !dist.value) return toast('Veuillez placer le point de départ sur la carte.')
  const t = options.value[choix.value]
  const u = state.user
  u.missions.unshift({ type: props.mode === 'depannage' ? 'Dépannage' : 'Convoyage', option: t.nom, km: dist.value.toFixed(1), prix: calc(t), note: note.value, date: now(), statut: 'Recherche chauffeur' })
  u.suivi = { actif: true, etape: 0, objet: t.nom }
  save(); notify(`${t.nom} commandé(e) — ${fcfa(calc(t))}. Un chauffeur vous contacte sous peu.`)
  go('historique')
}

onMounted(() => {
  map = L.map(mapEl.value).setView(GARAGE, 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map)
  L.circleMarker(GARAGE, { radius: 8, color: '#0b0f19', fillOpacity: 1 }).addTo(map).bindPopup('Garage Kagnan')
  map.on('click', e => place(e.latlng))
  B.value = { lat: GARAGE[0], lng: GARAGE[1] }
  mB = L.circleMarker(B.value, { radius: 9, color: '#e11d48', fillOpacity: 1 }).addTo(map)
})
onBeforeUnmount(() => { if (map) map.remove() })
</script>

<template>
  <div class="ride">
    <div class="ride-panel">
      <h3>{{ mode === 'depannage' ? 'Où se trouve votre véhicule ?' : 'Où récupérer votre véhicule ?' }}</h3>
      <p class="hint">Cliquez sur la carte pour placer les points, ou utilisez votre position.</p>
      <div class="point" :class="{ sel: pick === 'A' }" @click="pick = 'A'"><span class="dot dA"></span><span>{{ labelA }}</span></div>
      <div class="point" :class="{ sel: pick === 'B' }" @click="pick = 'B'"><span class="dot dB"></span><span>{{ labelB }}</span></div>
      <button class="btn btn-light" @click="geoloc">Utiliser ma position actuelle</button>
      <div class="opts">
        <div v-for="(t, i) in options" :key="t.id" class="opt" :class="{ sel: i === choix }" @click="choix = i">
          <div><b>{{ t.nom }}</b><small>{{ t.info }}</small></div>
          <b>{{ dist ? fcfa(calc(t)) : fcfa(t.base) + '+' }}</b>
        </div>
      </div>
      <label>Précisions (repère, panne...)<textarea v-model="note" rows="2"></textarea></label>
      <div class="hint">Distance estimée : <b>{{ dist ? dist.toFixed(1) + ' km' : '—' }}</b></div>
      <div class="price">{{ prix }}</div>
      <button class="btn btn-primary full btn-lg" @click="commander">Confirmer la commande</button>
    </div>
    <div ref="mapEl" class="ec-map"></div>
  </div>
</template>
