<script setup>
// Cloche du secrétariat :
//  - secrétaire : nouvelles tâches / tâches modifiées + messages de l'admin
//  - admin      : tâches terminées par les secrétaires + messages des secrétaires
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const estAdmin = computed(() => page.props.auth.user?.role === 'admin');

const notifications = ref([]);
const messagesNonLus = ref(0);
const parSecretaire = ref({});
const open = ref(false);
const toast = ref(null);
let timer;
let dernierNotif = null;
let derniersMessages = null;

const total = computed(() => notifications.value.length + messagesNonLus.value);

const montrerToast = (texte) => {
    toast.value = texte;
    window.setTimeout(() => { if (toast.value === texte) toast.value = null; }, 7000);
};

async function rafraichir() {
    if (document.hidden) return;
    try {
        const { data } = await axios.get(route('secretariat.notifications'));
        notifications.value = data.notifications || [];
        parSecretaire.value = data.messages_par_secretaire || {};

        const plusRecente = notifications.value[0];
        if (plusRecente && dernierNotif !== null && plusRecente.id !== dernierNotif) montrerToast(plusRecente.data.message);
        dernierNotif = plusRecente?.id ?? dernierNotif ?? '';

        if (derniersMessages !== null && data.messages_non_lus > derniersMessages) montrerToast('Nouveau message dans la messagerie du secrétariat.');
        derniersMessages = data.messages_non_lus;
        messagesNonLus.value = data.messages_non_lus;
    } catch {
        // Nouvel essai au prochain passage
    }
}

const urlSecretariat = (secretaireId = null) =>
    estAdmin.value
        ? route('admin.secretariat.index', secretaireId ? { secretaire: secretaireId } : {})
        : route('secretaire.index');

async function ouvrir(notification) {
    try {
        await axios.patch(route('secretariat.notifications.read', notification.id));
    } catch {
        // L'ouverture de la page reste prioritaire
    }
    notifications.value = notifications.value.filter((n) => n.id !== notification.id);
    open.value = false;
    router.visit(urlSecretariat(notification.data.secretaire_id));
}

function ouvrirMessages() {
    open.value = false;
    const premier = Object.keys(parSecretaire.value)[0];
    router.visit(urlSecretariat(premier ? Number(premier) : null));
}

onMounted(() => {
    rafraichir();
    timer = window.setInterval(rafraichir, 10000);
    document.addEventListener('visibilitychange', rafraichir);
});
onBeforeUnmount(() => {
    window.clearInterval(timer);
    document.removeEventListener('visibilitychange', rafraichir);
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-[#0B0F19]"
            aria-label="Notifications du secrétariat"
            :aria-expanded="open"
            @click="open = !open"
        >
            <i class="fa-solid fa-list-check text-sm" aria-hidden="true"></i>
            <span v-if="total" class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-[#E11D48] px-1 text-[10px] font-bold text-white">
                {{ total > 99 ? '99+' : total }}
            </span>
        </button>

        <div v-if="open" class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
            <p class="border-b px-4 py-3 text-sm font-black">Secrétariat</p>

            <button
                v-if="messagesNonLus"
                type="button"
                class="flex w-full items-center gap-3 border-b bg-rose-50/60 px-4 py-3 text-left hover:bg-rose-50"
                @click="ouvrirMessages"
            >
                <i class="fa-solid fa-comments text-[#E11D48]" aria-hidden="true"></i>
                <span class="text-sm font-bold">
                    {{ messagesNonLus }} message{{ messagesNonLus > 1 ? 's' : '' }} non lu{{ messagesNonLus > 1 ? 's' : '' }}
                </span>
            </button>

            <div class="max-h-80 overflow-y-auto">
                <button
                    v-for="n in notifications"
                    :key="n.id"
                    type="button"
                    class="block w-full border-b px-4 py-3 text-left hover:bg-gray-50"
                    @click="ouvrir(n)"
                >
                    <span class="block text-sm font-bold">{{ n.data.message }}</span>
                    <span v-if="n.data.echeance" class="mt-1 block text-xs text-gray-500">
                        À rendre le {{ new Date(n.data.echeance).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                    </span>
                </button>
            </div>

            <p v-if="!total" class="px-4 py-6 text-center text-sm text-gray-500">Rien de nouveau.</p>
        </div>

        <Teleport to="body">
            <button
                v-if="toast"
                type="button"
                class="fixed bottom-4 right-4 z-[60] max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white p-4 text-left text-sm font-bold text-[#0B0F19] shadow-xl"
                @click="toast = null; open = true"
            >
                <i class="fa-solid fa-list-check mr-2 text-[#E11D48]" aria-hidden="true"></i>{{ toast }}
            </button>
        </Teleport>
    </div>
</template>
