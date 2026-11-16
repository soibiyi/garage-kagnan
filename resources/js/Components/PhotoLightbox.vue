<script setup>
// Visionneuse de photos en modal : flèches ←/→ pour naviguer, Échap pour fermer.
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    // [{ url, nom }]
    photos: { type: Array, default: () => [] },
    // Index de la photo affichée ; null = fermé
    index: { type: Number, default: null },
    legende: { type: String, default: '' },
});

const emit = defineEmits(['update:index']);

const ouvert = computed(() => props.index !== null && props.photos.length > 0);
const photo = computed(() => (ouvert.value ? props.photos[props.index] : null));
const plusieurs = computed(() => props.photos.length > 1);
const chargee = ref(false);
const bouton = ref(null);

const fermer = () => emit('update:index', null);
const aller = (pas) => {
    if (!plusieurs.value) return;
    emit('update:index', (props.index + pas + props.photos.length) % props.photos.length);
};

// Écouté en phase de capture et stoppé : Échap ne ferme que la visionneuse,
// pas une éventuelle modal située en dessous.
const onKeydown = (e) => {
    if (!ouvert.value || !['Escape', 'ArrowRight', 'ArrowLeft'].includes(e.key)) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    if (e.key === 'Escape') fermer();
    else aller(e.key === 'ArrowRight' ? 1 : -1);
};

// Balayage tactile
let debutX = null;
const onTouchStart = (e) => { debutX = e.touches[0].clientX; };
const onTouchEnd = (e) => {
    if (debutX === null) return;
    const dx = e.changedTouches[0].clientX - debutX;
    if (Math.abs(dx) > 50) aller(dx < 0 ? 1 : -1);
    debutX = null;
};

watch(() => photo.value?.url, () => { chargee.value = false; });

// On rétablit le défilement d'origine (une modal en dessous peut déjà l'avoir bloqué)
let overflowAvant = '';
watch(ouvert, (estOuvert) => {
    if (estOuvert) {
        overflowAvant = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeydown, true);
        setTimeout(() => bouton.value?.focus(), 0);
    } else {
        document.body.style.overflow = overflowAvant;
        window.removeEventListener('keydown', onKeydown, true);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown, true);
    if (ouvert.value) document.body.style.overflow = overflowAvant;
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="ouvert"
                class="fixed inset-0 z-[100] flex flex-col bg-[#0B0F19]/95"
                role="dialog"
                aria-modal="true"
                :aria-label="photo?.nom || 'Photo'"
                @click.self="fermer"
                @touchstart.passive="onTouchStart"
                @touchend="onTouchEnd"
            >
                <!-- Barre du haut -->
                <div class="flex items-center gap-3 px-4 py-3 text-white">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold">{{ photo?.nom || 'Photo' }}</p>
                        <p v-if="legende || plusieurs" class="truncate text-xs text-white/60">
                            <template v-if="plusieurs">{{ index + 1 }} / {{ photos.length }}</template>
                            <template v-if="legende && plusieurs"> · </template>{{ legende }}
                        </p>
                    </div>
                    <a
                        :href="photo?.url"
                        target="_blank"
                        rel="noopener"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-white/80 transition hover:bg-white/10 hover:text-white"
                        aria-label="Ouvrir dans un nouvel onglet"
                        title="Ouvrir dans un nouvel onglet"
                    >
                        <i class="fa-solid fa-up-right-from-square text-sm"></i>
                    </a>
                    <button
                        ref="bouton"
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                        aria-label="Fermer"
                        @click="fermer"
                    >
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- Photo -->
                <div class="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-6 sm:px-16" @click.self="fermer">
                    <i v-if="!chargee" class="fa-solid fa-spinner fa-spin absolute text-2xl text-white/60"></i>
                    <img
                        :key="photo?.url"
                        :src="photo?.url"
                        :alt="photo?.nom || 'Photo'"
                        class="max-h-full max-w-full select-none rounded-lg object-contain shadow-2xl transition-opacity duration-200"
                        :class="chargee ? 'opacity-100' : 'opacity-0'"
                        @load="chargee = true"
                    />

                    <template v-if="plusieurs">
                        <button
                            type="button"
                            class="absolute left-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:left-4"
                            aria-label="Photo précédente"
                            @click="aller(-1)"
                        >
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button
                            type="button"
                            class="absolute right-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:right-4"
                            aria-label="Photo suivante"
                            @click="aller(1)"
                        >
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
