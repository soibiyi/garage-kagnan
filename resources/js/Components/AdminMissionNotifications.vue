<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'

const notifications = ref([])
const open = ref(false)
const toast = ref(null)
let timer
let latestId = null

async function refresh() {
  if (document.hidden) return
  try {
    const { data } = await axios.get('/admin/notifications/missions')
    notifications.value = data.notifications || []
    const newest = notifications.value[0]
    if (newest && latestId !== null && newest.id !== latestId) {
      toast.value = newest
      window.setTimeout(() => { if (toast.value?.id === newest.id) toast.value = null }, 7000)
    }
    if (newest) latestId = newest.id
  } catch {
    // The next polling pass retries after a temporary network failure.
  }
}

async function openNotification(notification) {
  await axios.patch(`/admin/notifications/missions/${notification.id}/read`)
  notifications.value = notifications.value.filter(item => item.id !== notification.id)
  toast.value = null
  open.value = false
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
  <div class="relative">
    <button
      type="button"
      class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-[#0B0F19]"
      aria-label="Notifications d’arrivées au garage"
      :aria-expanded="open"
      @click="open = !open"
    >
      <i class="fa-solid fa-bell text-sm" aria-hidden="true"></i>
      <span v-if="notifications.length" class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-[#E11D48] px-1 text-[10px] font-bold text-white">
        {{ notifications.length }}
      </span>
    </button>

    <div v-if="open" class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
      <p class="border-b px-4 py-3 text-sm font-black">Arrivées au garage</p>
      <button
        v-for="notification in notifications"
        :key="notification.id"
        type="button"
        class="block w-full border-b px-4 py-3 text-left hover:bg-gray-50"
        @click="openNotification(notification)"
      >
        <span class="block text-sm font-bold">{{ notification.data.message }}</span>
        <span class="mt-1 block text-xs text-gray-500">Mission #{{ notification.data.mission_id }} · {{ notification.data.siege }}</span>
      </button>
      <p v-if="!notifications.length" class="px-4 py-6 text-center text-sm text-gray-500">Aucune nouvelle arrivée.</p>
    </div>

    <Teleport to="body">
      <Link
        v-if="toast"
        :href="route('admin.missions.history')"
        class="fixed bottom-4 right-4 z-[60] max-w-[calc(100vw-2rem)] rounded-xl border border-emerald-200 bg-white p-4 text-sm font-bold text-[#0B0F19] shadow-xl"
        @click="toast = null"
      >{{ toast.data.message }} · Mission #{{ toast.data.mission_id }}</Link>
    </Teleport>
  </div>
</template>