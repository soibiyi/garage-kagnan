<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const toast = ref(null); // { type: 'success' | 'error' | 'warning' | 'info', message: string }
let timer = null;
const dureeAffichage = ref(5000);
const debutAffichage = ref(Date.now());
const tempsRestant = ref(5000);

const config = computed(() => ({
    success: {
        titre: 'Modification réussie',
        icone: 'fa-solid fa-circle-check',
        bordure: 'border-emerald-300',
        iconeFond: 'bg-emerald-100 text-emerald-700',
        barre: 'bg-emerald-500',
    },
    error: {
        titre: 'Impossible de continuer',
        icone: 'fa-solid fa-circle-exclamation',
        bordure: 'border-[#E11D48]/40',
        iconeFond: 'bg-[#E11D48]/10 text-[#E11D48]',
        barre: 'bg-[#E11D48]',
    },
    warning: {
        titre: 'Attention',
        icone: 'fa-solid fa-triangle-exclamation',
        bordure: 'border-amber-300',
        iconeFond: 'bg-amber-100 text-amber-700',
        barre: 'bg-amber-500',
    },
    info: {
        titre: 'Information',
        icone: 'fa-solid fa-circle-info',
        bordure: 'border-sky-300',
        iconeFond: 'bg-sky-100 text-sky-700',
        barre: 'bg-sky-500',
    },
}[toast.value?.type ?? 'info']));

const statusLisible = {
    'verification-link-sent': 'Un nouveau lien de vérification a été envoyé.',
};

const hide = () => {
    clearTimeout(timer);
    toast.value = null;
};

const demarrerTimer = () => {
    clearTimeout(timer);
    debutAffichage.value = Date.now();
    timer = setTimeout(hide, tempsRestant.value);
};

const suspendreTimer = () => {
    clearTimeout(timer);
    tempsRestant.value = Math.max(1000, tempsRestant.value - (Date.now() - debutAffichage.value));
};

const reprendreTimer = () => {
    demarrerTimer();
};

// Se déclenche à chaque nouvelle réponse Inertia (donc à chaque redirection avec ->with(...))
watch(
    () => page.props.flash,
    (flash) => {
        const messageBrute = flash?.error || flash?.success || flash?.warning || flash?.info || flash?.status;
        if (!messageBrute) return;
        const message = typeof messageBrute === 'string' && statusLisible[messageBrute]
            ? statusLisible[messageBrute]
            : messageBrute;

        const type = flash.error
            ? 'error'
            : flash.warning
                ? 'warning'
                : flash.info || flash.status
                    ? 'info'
                    : 'success';

        clearTimeout(timer);
        dureeAffichage.value = type === 'error' ? 8000 : type === 'warning' ? 7000 : 5000;
        tempsRestant.value = dureeAffichage.value;
        toast.value = { type, message };
        demarrerTimer();
    },
    { immediate: true }
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 top-32 z-50 flex justify-center px-4 sm:justify-end sm:px-6 lg:px-8 print:hidden"
    >
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast"
                :role="toast.type === 'error' ? 'alert' : 'status'"
                class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-white shadow-xl"
                :class="config.bordure"
                @mouseenter="suspendreTimer"
                @mouseleave="reprendreTimer"
            >
                <div class="flex items-start gap-3 p-4">
                    <span
                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm"
                        :class="config.iconeFond"
                    >
                        <i :class="config.icone"></i>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-extrabold uppercase tracking-wide text-[#6B6E70]">{{ config.titre }}</p>
                        <p class="mt-1 text-sm font-semibold leading-5 text-[#0B0F19]">{{ toast.message }}</p>
                    </div>

                    <button
                        type="button"
                        aria-label="Fermer la notification"
                        class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-[#8A8D8F] transition hover:bg-gray-100 hover:text-[#0B0F19]"
                        @click="hide"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="h-1 bg-gray-100">
                    <div class="h-full w-full origin-left" :class="config.barre"></div>
                </div>
            </div>
        </Transition>
    </div>
</template>