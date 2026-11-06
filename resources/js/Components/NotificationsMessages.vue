<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const INTERVALLE_MS = 8000;

const page = usePage();
const conversations = ref([]);
const ouvert = ref(false);
const toast = ref(null);
const racine = ref(null);

let minuteur = null;
let arrete = false;
let dernierIdVu = null; // null tant que le premier chargement n'est pas fait
let minuteurToast = null;

// Client dont le chat est ouvert en ce moment : inutile de le notifier
const clientOuvert = computed(() => {
    const m = String(page.url || '').match(/\/clients\/(\d+)\/chat/);
    return m ? Number(m[1]) : null;
});

const visibles = computed(() =>
    conversations.value.filter((c) => c.client_id !== clientOuvert.value)
);
const total = computed(() => visibles.value.reduce((s, c) => s + c.nb, 0));

const nomComplet = (c) => `${c.prenom || ''} ${c.nom || ''}`.trim() || 'Client';
const heure = (valeur) =>
    new Date(valeur).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

const afficherToast = (conv) => {
    toast.value = conv;
    clearTimeout(minuteurToast);
    minuteurToast = setTimeout(() => { toast.value = null; }, 7000);
};

const charger = async () => {
    if (arrete || document.hidden) return;
    try {
        const res = await fetch(route('charge_client.notifications.messages'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        // Pas concerné par ces notifications (autre rôle) ou déconnecté : on arrête
        if ([401, 403, 404].includes(res.status)) {
            arrete = true;
            clearInterval(minuteur);
            return;
        }
        if (!res.ok) return;

        const data = await res.json();
        conversations.value = data.conversations || [];

        const plusRecent = conversations.value.reduce((max, c) => Math.max(max, c.dernier_id), 0);
        if (dernierIdVu !== null && plusRecent > dernierIdVu) {
            const nouvelle = conversations.value.find((c) => c.dernier_id === plusRecent);
            if (nouvelle && nouvelle.client_id !== clientOuvert.value) afficherToast(nouvelle);
        }
        dernierIdVu = Math.max(dernierIdVu ?? 0, plusRecent);
    } catch (e) {
        // Réseau coupé : on réessaiera au prochain passage
    }
};

const fermerSiExterieur = (event) => {
    if (racine.value && !racine.value.contains(event.target)) ouvert.value = false;
};

onMounted(() => {
    charger();
    minuteur = setInterval(charger, INTERVALLE_MS);
    document.addEventListener('visibilitychange', charger);
    document.addEventListener('click', fermerSiExterieur);
});

onBeforeUnmount(() => {
    clearInterval(minuteur);
    clearTimeout(minuteurToast);
    document.removeEventListener('visibilitychange', charger);
    document.removeEventListener('click', fermerSiExterieur);
});
</script>

<template>
    <div ref="racine" class="relative">
        <button
            type="button"
            class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-[#0B0F19] transition duration-200 hover:border-[#0B0F19] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95"
            :aria-label="total ? `${total} message(s) non lu(s)` : 'Messages clients'"
            :aria-expanded="ouvert"
            @click.stop="ouvert = !ouvert"
        >
            <i class="fa-solid fa-bell text-sm"></i>
            <span
                v-if="total"
                class="absolute -right-1.5 -top-1.5 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-[#E11D48] px-1 text-[10px] font-bold text-white"
            >
                {{ total > 99 ? '99+' : total }}
            </span>
        </button>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-1 opacity-0"
        >
            <div
                v-if="ouvert"
                class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg"
            >
                <div class="border-b border-gray-100 px-4 py-3">
                    <p class="text-sm font-black text-[#0B0F19]">Messages des clients</p>
                </div>

                <ul v-if="visibles.length" class="max-h-96 divide-y divide-gray-100 overflow-y-auto">
                    <li v-for="c in visibles" :key="c.client_id">
                        <Link
                            :href="route('charge_client.clients.chat', c.client_id)"
                            class="flex items-start gap-3 px-4 py-3 transition hover:bg-[#F8FAFC]"
                            @click="ouvert = false"
                        >
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#E11D48]">
                                <i class="fa-solid fa-comments text-xs"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-extrabold text-[#0B0F19]">{{ nomComplet(c) }}</p>
                                    <span class="shrink-0 rounded-full bg-[#E11D48] px-2 py-0.5 text-[10px] font-bold text-white">{{ c.nb }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-xs font-medium text-[#8A8D8F]">{{ c.apercu }}</p>
                                <p class="mt-0.5 text-[10px] font-semibold text-[#8A8D8F]">{{ heure(c.created_at) }}</p>
                            </div>
                        </Link>
                    </li>
                </ul>

                <div v-else class="px-4 py-8 text-center">
                    <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#8A8D8F] ring-1 ring-gray-200">
                        <i class="fa-regular fa-bell"></i>
                    </div>
                    <p class="text-xs font-semibold text-[#8A8D8F]">Aucun nouveau message.</p>
                </div>
            </div>
        </Transition>

        <!-- Alerte à l'arrivée d'un message, quelle que soit la page -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-3 opacity-0"
                leave-active-class="transition duration-200 ease-in"
                leave-to-class="translate-y-3 opacity-0"
            >
                <Link
                    v-if="toast"
                    :href="route('charge_client.clients.chat', toast.client_id)"
                    class="fixed bottom-4 right-4 z-[60] flex w-80 max-w-[calc(100vw-2rem)] items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-lg transition hover:border-[#E11D48]"
                    @click="toast = null"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E11D48] text-white">
                        <i class="fa-solid fa-message text-xs"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-extrabold text-[#0B0F19]">Nouveau message de {{ nomComplet(toast) }}</p>
                        <p class="mt-0.5 truncate text-xs font-medium text-[#8A8D8F]">{{ toast.apercu }}</p>
                    </div>
                </Link>
            </Transition>
        </Teleport>
    </div>
</template>