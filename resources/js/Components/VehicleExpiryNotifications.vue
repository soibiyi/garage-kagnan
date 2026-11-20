<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const notifications = ref([]);
const open = ref(false);
const error = ref('');
const root = ref(null);
let timer;

const libelleDelai = (notification) => {
    if (notification.jours_restants < 0) {
        const jours = Math.abs(notification.jours_restants);
        return `Expirée depuis ${jours} jour${jours > 1 ? 's' : ''}`;
    }
    if (notification.jours_restants === 0) return 'Expire aujourd’hui';
    return `Expire dans ${notification.jours_restants} jour${notification.jours_restants > 1 ? 's' : ''}`;
};

const dateLisible = (date) => {
    const [annee, mois, jour] = date.split('-').map(Number);
    return new Date(annee, mois - 1, jour).toLocaleDateString('fr-FR');
};

async function actualiser() {
    if (document.hidden) return;

    try {
        const { data } = await axios.get(route('charge_client.vehicules.echeances.notifications'), {
            headers: { Accept: 'application/json' },
        });
        notifications.value = data.notifications;
        error.value = '';
    } catch {
        error.value = 'Les notifications d’échéances sont momentanément indisponibles.';
    }
}

function ouvrir(notification) {
    if (!notification.vehicule_id) {
        open.value = false;
        router.visit(route('charge_client.vehicules.echeances.index'));
        return;
    }

    open.value = false;
    router.visit(route('charge_client.vehicules.echeances.index', {
        vehicule: notification.vehicule_id,
        type: notification.type,
    }));
}

function fermerSiExterieur(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => {
    actualiser();
    timer = window.setInterval(actualiser, 300000);
    document.addEventListener('visibilitychange', actualiser);
    document.addEventListener('click', fermerSiExterieur);
});

onBeforeUnmount(() => {
    window.clearInterval(timer);
    document.removeEventListener('visibilitychange', actualiser);
    document.removeEventListener('click', fermerSiExterieur);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative grid h-10 w-10 place-items-center rounded-xl border border-gray-200 text-gray-700 transition hover:border-amber-300 hover:bg-amber-50"
            :class="{ 'border-red-300 text-red-700': error }"
            :aria-label="`Échéances véhicules${notifications.length ? `, ${notifications.length} à suivre` : ''}${error ? `, ${error}` : ''}`"
            :title="error || 'Échéances des véhicules'"
            aria-haspopup="dialog"
            :aria-expanded="open"
            @click="open = !open"
        >
            <i class="fa-solid fa-bell text-sm" aria-hidden="true"></i>
            <span
                v-if="notifications.length"
                class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-amber-500 px-1 text-[10px] font-bold text-white"
            >
                {{ notifications.length > 99 ? '99+' : notifications.length }}
            </span>
            <span v-else-if="error" class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-red-600"></span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl"
            role="dialog"
            aria-label="Échéances des véhicules"
        >
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <h2 class="text-sm font-extrabold text-[#0B0F19]">Échéances à suivre</h2>
                <button
                    type="button"
                    class="text-xs font-bold text-[#E11D48] hover:underline"
                @click="ouvrir({ vehicule_id: null })"
                >
                    Voir tous
                </button>
            </div>
            <p v-if="error" class="px-4 py-3 text-xs font-semibold text-red-700" role="alert">{{ error }}</p>
            <p v-else-if="!notifications.length" class="px-4 py-6 text-center text-sm text-gray-500">
                Aucune assurance ou visite technique n’expire dans les 5 jours.
            </p>
            <div v-else class="max-h-[min(70vh,28rem)] overflow-y-auto">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="block w-full border-b border-gray-100 px-4 py-3 text-left transition last:border-0 hover:bg-amber-50"
                    @click="ouvrir(notification)"
                >
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-extrabold text-[#0B0F19]">{{ notification.libelle }}</span>
                            <span class="mt-1 block font-mono text-[11px] font-bold text-[#E11D48]">{{ notification.immatriculation }}</span>
                            <span class="mt-0.5 block truncate text-[11px] text-gray-500">{{ notification.client }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block text-[11px] font-bold" :class="notification.jours_restants <= 0 ? 'text-red-700' : 'text-amber-700'">
                                {{ libelleDelai(notification) }}
                            </span>
                            <span class="mt-1 block text-[10px] text-gray-500">{{ dateLisible(notification.date) }}</span>
                        </span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
