<script setup>
// Cloche du super administrateur : nouvelles erreurs de l'application (en direct) + personnes en ligne.
// Une notification du navigateur est aussi affichée, pour être prévenu même dans un autre onglet.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';

const notifications = ref([]);
const erreursOuvertes = ref(0);
const enLigne = ref(0);
const open = ref(false);
const toast = ref(null);
let timer;
let connues = null;

function prevenirNavigateur(n) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    try {
        const notif = new Notification('Erreur dans Garage Kagnan', {
            body: `${n.data.type} : ${n.data.message}`,
            tag: `erreur-${n.data.erreur_id}`,
        });
        notif.onclick = () => {
            window.focus();
            router.visit(route('superadmin.erreurs.index'));
        };
    } catch {
        // Certains navigateurs refusent les notifications hors service worker
    }
}

async function rafraichir() {
    try {
        const { data } = await axios.get(route('superadmin.alertes'));
        notifications.value = data.notifications || [];
        erreursOuvertes.value = data.erreurs_ouvertes;
        enLigne.value = data.en_ligne;

        const nouvelles = connues === null ? [] : notifications.value.filter((n) => !connues.has(n.id));
        connues = new Set(notifications.value.map((n) => n.id));
        if (nouvelles.length) {
            toast.value = nouvelles[0];
            window.setTimeout(() => { if (toast.value?.id === nouvelles[0].id) toast.value = null; }, 9000);
            nouvelles.forEach(prevenirNavigateur);
        }
    } catch {
        // Nouvel essai au prochain passage
    }
}

function basculer() {
    open.value = !open.value;
    // Première ouverture : on propose d'activer les notifications du navigateur
    if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
}

async function ouvrir(n) {
    try { await axios.patch(route('superadmin.alertes.lue', n.id)); } catch { /* rien */ }
    open.value = false;
    router.visit(route('superadmin.erreurs.index', { erreur: n.data.erreur_id }));
}

onMounted(() => {
    rafraichir();
    // Toutes les 8 s, même onglet en arrière-plan : le super administrateur doit être prévenu en premier
    timer = window.setInterval(rafraichir, 8000);
});
onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-[#0B0F19] transition hover:border-[#0B0F19]"
            :class="{ 'border-red-300 bg-red-50 text-red-700': notifications.length }"
            aria-label="Alertes du super administrateur"
            :aria-expanded="open"
            @click="basculer"
        >
            <i class="fa-solid fa-bug text-sm" :class="{ 'fa-shake': notifications.length }" aria-hidden="true"></i>
            <span v-if="notifications.length" class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">
                {{ notifications.length > 99 ? '99+' : notifications.length }}
            </span>
        </button>

        <div v-if="open" class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white text-left shadow-2xl">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <p class="text-sm font-black text-[#0B0F19]">Super administrateur</p>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-emerald-600">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500" aria-hidden="true"></span>{{ enLigne }} en ligne
                </span>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <button
                    v-for="n in notifications"
                    :key="n.id"
                    type="button"
                    class="flex w-full gap-3 border-b px-4 py-3 text-left hover:bg-red-50/60"
                    @click="ouvrir(n)"
                >
                    <i class="fa-solid mt-0.5 text-red-600" :class="n.data.source === 'client' ? 'fa-display' : 'fa-server'" aria-hidden="true"></i>
                    <span class="min-w-0">
                        <span class="block text-xs font-black text-red-700">{{ n.data.type }}</span>
                        <span class="mt-0.5 block break-words text-sm text-[#0B0F19]">{{ n.data.message }}</span>
                        <span class="mt-1 block text-[11px] text-gray-400">{{ new Date(n.created_at).toLocaleString('fr-FR') }}</span>
                    </span>
                </button>
                <p v-if="!notifications.length" class="px-4 py-6 text-center text-sm text-gray-500">
                    Aucune nouvelle erreur.
                    <span v-if="erreursOuvertes" class="block text-xs">{{ erreursOuvertes }} erreur(s) encore ouverte(s).</span>
                </p>
            </div>

            <div class="grid grid-cols-2 gap-px bg-gray-100">
                <Link :href="route('superadmin.erreurs.index')" class="bg-white px-4 py-3 text-center text-xs font-bold text-[#0B0F19] hover:bg-gray-50" @click="open = false">
                    <i class="fa-solid fa-bug mr-1.5 text-red-600" aria-hidden="true"></i>Erreurs ({{ erreursOuvertes }})
                </Link>
                <Link :href="route('superadmin.supervision.index')" class="bg-white px-4 py-3 text-center text-xs font-bold text-[#0B0F19] hover:bg-gray-50" @click="open = false">
                    <i class="fa-solid fa-satellite-dish mr-1.5 text-[#E11D48]" aria-hidden="true"></i>Supervision
                </Link>
            </div>
        </div>

        <Teleport to="body">
            <button
                v-if="toast"
                type="button"
                class="fixed bottom-4 right-4 z-[70] flex max-w-[calc(100vw-2rem)] items-start gap-3 rounded-2xl border border-red-200 bg-white p-4 text-left shadow-2xl sm:w-96"
                @click="ouvrir(toast); toast = null"
            >
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-red-600 text-white">
                    <i class="fa-solid fa-bug text-sm" aria-hidden="true"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-xs font-black uppercase tracking-wide text-red-700">Nouvelle erreur · {{ toast.data.type }}</span>
                    <span class="mt-0.5 block break-words text-sm font-medium text-[#0B0F19]">{{ toast.data.message }}</span>
                </span>
            </button>
        </Teleport>
    </div>
</template>
