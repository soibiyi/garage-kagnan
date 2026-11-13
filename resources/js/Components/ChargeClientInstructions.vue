<script>
// État partagé au niveau du module : le layout (et donc ce composant) est recréé à chaque
// navigation Inertia. Sans ça, une consigne arrivée entre deux pages ne déclencherait
// jamais l'alerte.
let idsConnus = null // null tant que le premier chargement de la session n'est pas fait
const CLE_RESUME_CONNEXION = 'instructions_resume_connexion'
</script>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

const notifications = ref([])
const unreadCount = ref(0)
const open = ref(false)
const toast = ref(null)
const racine = ref(null)
const resumeConnexion = ref(false) // panneau ouvert automatiquement à l'arrivée sur le dashboard
let timer
let toastTimer

const nonLues = computed(() => notifications.value.filter((n) => !n.read_at))
const listeAffichee = computed(() => (resumeConnexion.value ? nonLues.value : notifications.value))

function lireSession(cle) {
  try { return window.sessionStorage.getItem(cle) } catch { return null }
}
function ecrireSession(cle, valeur) {
  try { window.sessionStorage.setItem(cle, valeur) } catch { /* navigation privée */ }
}

/**
 * Juste après la connexion, sur le tableau de bord : on ouvre la liste des consignes
 * reçues pendant l'absence (une seule fois par session ; la clé est effacée à la déconnexion).
 */
function ouvrirResumeSiConnexion() {
  if (lireSession(CLE_RESUME_CONNEXION)) return
  if (!route().current('dashboard')) return
  ecrireSession(CLE_RESUME_CONNEXION, '1')
  if (nonLues.value.length) {
    resumeConnexion.value = true
    open.value = true
  }
}

function basculer() {
  resumeConnexion.value = false
  toast.value = null
  open.value = !open.value
}

function afficherToast(notification) {
  toast.value = notification
  window.clearTimeout(toastTimer)
  toastTimer = window.setTimeout(() => { toast.value = null }, 8000)
}

async function refresh() {
  if (document.hidden) return
  try {
    // route() (Ziggy) respecte le sous-dossier / domaine de production,
    // contrairement à une URL écrite en dur.
    const { data } = await axios.get(route('charge_client.notifications.instructions'), {
      headers: { Accept: 'application/json' },
    })
    notifications.value = data.notifications || []
    unreadCount.value = data.unread_count || 0

    // Consigne arrivée pendant que la chargée est en ligne → alerte immédiate
    const nouvelles = notifications.value.filter((n) => !n.read_at && idsConnus && !idsConnus.has(n.id))
    if (nouvelles.length && !open.value) afficherToast(nouvelles[0])
    const premierChargement = idsConnus === null
    idsConnus = new Set([...(idsConnus || []), ...notifications.value.map((n) => n.id)])

    if (premierChargement) ouvrirResumeSiConnexion()
  } catch {
    // Le prochain passage réessaiera après une coupure réseau temporaire.
  }
}

async function openNotification(notification) {
  try {
    await axios.patch(route('charge_client.notifications.instructions.read', notification.id))
  } catch {
    // La redirection reste prioritaire même si le marquage échoue.
  }
  if (!notification.read_at) {
    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }
  open.value = false
  toast.value = null
  const cible = notification.url
    || route('charge_client.clients.show', notification.data.client_id)
  router.visit(cible)
}

function fermerSiExterieur(event) {
  if (open.value && racine.value && !racine.value.contains(event.target)) {
    open.value = false
    resumeConnexion.value = false
  }
}

onMounted(() => {
  refresh()
  timer = window.setInterval(refresh, 8000)
  document.addEventListener('visibilitychange', refresh)
  document.addEventListener('click', fermerSiExterieur)
})
onBeforeUnmount(() => {
  window.clearInterval(timer)
  window.clearTimeout(toastTimer)
  document.removeEventListener('visibilitychange', refresh)
  document.removeEventListener('click', fermerSiExterieur)
})
</script>

<template>
  <!--
    Téléporté dans <body> : la <nav> a un backdrop-filter (liquid glass) qui transforme
    « position: fixed » en positionnement relatif à la nav, ce qui sortait le bouton et
    sa liste de l'écran.
  -->
  <Teleport to="body">
    <div ref="racine" class="fixed bottom-6 right-6 z-[60]">
      <!-- Toast : nouvelle consigne reçue -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-2 opacity-0"
      >
        <button
          v-if="toast && !open"
          type="button"
          class="absolute bottom-20 right-0 w-80 max-w-[calc(100vw-3rem)] rounded-2xl border border-rose-200 bg-white p-4 text-left shadow-2xl"
          @click="openNotification(toast)"
        >
          <span class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-[#E11D48]">
            <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
            Nouvelle consigne de l’administrateur
          </span>
          <span class="mt-1 block text-sm font-extrabold text-[#0B0F19]">{{ toast.data.client_name }}</span>
          <span class="mt-1 block line-clamp-2 text-sm text-gray-700">{{ toast.data.consigne }}</span>
        </button>
      </Transition>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-2 opacity-0"
      >
        <div
          v-if="open"
          class="absolute bottom-20 right-0 w-80 max-w-[calc(100vw-3rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl"
        >
          <p class="border-b border-gray-100 px-4 py-3 text-sm font-black text-[#0B0F19]">
            <template v-if="resumeConnexion">
              {{ nonLues.length }} consigne{{ nonLues.length > 1 ? 's' : '' }} reçue{{ nonLues.length > 1 ? 's' : '' }} pendant votre absence
              <button
                type="button"
                class="mt-1 block text-xs font-bold text-[#E11D48] hover:underline"
                @click.stop="resumeConnexion = false"
              >
                Voir tout l’historique
              </button>
            </template>
            <template v-else>Instructions de l’administrateur</template>
          </p>
          <ul v-if="listeAffichee.length" class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
            <li v-for="notification in listeAffichee" :key="notification.id">
              <button
                type="button"
                class="block w-full px-4 py-3 text-left transition hover:bg-gray-50"
                :class="{ 'bg-rose-50/60': !notification.read_at }"
                @click="openNotification(notification)"
              >
                <span class="block text-sm font-extrabold text-[#E11D48]">{{ notification.data.client_name }}</span>
                <span class="mt-1 block line-clamp-2 text-sm font-bold text-[#0B0F19]">{{ notification.data.consigne }}</span>
                <span class="mt-2 flex items-center justify-between text-xs text-gray-500">
                  <span><i class="fa-solid fa-arrow-right mr-1" aria-hidden="true"></i>Suivi des interactions</span>
                  <span
                    v-if="notification.read_at"
                    class="text-emerald-600"
                    role="img"
                    aria-label="Consigne déjà consultée"
                    title="Consigne déjà consultée"
                  >
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                  </span>
                </span>
              </button>
            </li>
          </ul>
          <p v-else class="px-4 py-6 text-center text-sm text-gray-500">Aucune nouvelle instruction.</p>
        </div>
      </Transition>

      <button
        type="button"
        class="relative flex h-16 w-16 items-center justify-center rounded-full bg-[#E11D48] text-white shadow-xl transition hover:scale-105 hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-300"
        :aria-label="unreadCount ? `${unreadCount} instruction(s) non consultée(s)` : 'Ouvrir les instructions de l’administrateur'"
        :aria-expanded="open"
        @click="basculer"
      >
        <i class="fa-solid fa-triangle-exclamation text-2xl" aria-hidden="true"></i>
        <span v-if="unreadCount" class="absolute -right-2 -top-2 grid h-7 min-w-7 place-items-center rounded-full border-2 border-white bg-[#0B0F19] px-1.5 text-xs font-bold text-white">
          {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
      </button>
    </div>
  </Teleport>
</template>
