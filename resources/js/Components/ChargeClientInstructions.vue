<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

const notifications = ref([])
const unreadCount = ref(0)
const open = ref(false)
let timer

async function refresh() {
  if (document.hidden) return
  try {
    const { data } = await axios.get('/suivi-client/notifications/instructions')
    notifications.value = data.notifications || []
    unreadCount.value = data.unread_count || 0
  } catch {
    // The next polling pass retries after a temporary network failure.
  }
}

async function openNotification(notification) {
  await axios.patch(`/suivi-client/notifications/instructions/${notification.id}/read`)
  if (!notification.read_at) {
    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }
  open.value = false
  const discussion = route('charge_client.clients.chat', notification.data.client_id)
  router.visit(`${discussion}?instruction=${encodeURIComponent(notification.data.consigne_id)}`)
}

onMounted(() => {
  refresh()
  timer = window.setInterval(refresh, 8000)
  document.addEventListener('visibilitychange', refresh)
})
onBeforeUnmount(() => {
  window.clearInterval(timer)
  document.removeEventListener('visibilitychange', refresh)
})
</script>

<template>
  <div class="fixed bottom-6 right-6 z-[60]">
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
          Instructions de l’administrateur
        </p>
        <ul v-if="notifications.length" class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
          <li v-for="notification in notifications" :key="notification.id">
            <button
              type="button"
              class="block w-full px-4 py-3 text-left transition hover:bg-gray-50"
              @click="openNotification(notification)"
            >
              <span class="block text-sm font-extrabold text-[#E11D48]">{{ notification.data.client_name }}</span>
              <span class="mt-1 block line-clamp-2 text-sm font-bold text-[#0B0F19]">{{ notification.data.consigne }}</span>
              <span
                v-if="notification.read_at"
                class="mt-2 inline-flex text-emerald-600"
                role="img"
                aria-label="Consigne déjà consultée"
                title="Consigne déjà consultée"
              >
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
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
      @click="open = !open"
    >
      <i class="fa-solid fa-triangle-exclamation text-2xl" aria-hidden="true"></i>
      <span v-if="unreadCount" class="absolute -right-2 -top-2 grid h-7 min-w-7 place-items-center rounded-full border-2 border-white bg-[#0B0F19] px-1.5 text-xs font-bold text-white">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>
  </div>
</template>
