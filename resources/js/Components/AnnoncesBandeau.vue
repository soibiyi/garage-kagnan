<script setup>
// Bandeaux d'annonce publiés par le super administrateur (Système → Annonces).
// Chaque utilisateur peut masquer une annonce ; elle réapparaît si elle est modifiée.
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const CLE = 'annonces_masquees';

const lire = () => {
    try { return JSON.parse(window.localStorage.getItem(CLE) || '[]'); } catch { return []; }
};
const masquees = ref(lire());

const cle = (a) => `${a.id}:${a.version}`;
const visibles = computed(() => (page.props.annonces || []).filter((a) => !masquees.value.includes(cle(a))));

function masquer(a) {
    masquees.value = [...masquees.value, cle(a)].slice(-50);
    try { window.localStorage.setItem(CLE, JSON.stringify(masquees.value)); } catch { /* stockage indisponible */ }
}

const STYLES = {
    info: { classe: 'border-sky-200 bg-sky-50 text-sky-900', icone: 'fa-circle-info text-sky-600' },
    attention: { classe: 'border-amber-200 bg-amber-50 text-amber-900', icone: 'fa-triangle-exclamation text-amber-600' },
    urgent: { classe: 'border-red-200 bg-red-50 text-red-900', icone: 'fa-bullhorn text-red-600' },
};
</script>

<template>
    <div v-if="visibles.length" class="print:hidden">
        <div
            v-for="a in visibles"
            :key="a.id"
            class="border-b"
            :class="(STYLES[a.niveau] || STYLES.info).classe"
            role="status"
        >
            <div class="mx-auto flex max-w-7xl items-start gap-3 px-4 py-2.5 sm:px-6 lg:px-8">
                <i class="fa-solid mt-0.5 shrink-0" :class="(STYLES[a.niveau] || STYLES.info).icone" aria-hidden="true"></i>
                <p class="min-w-0 flex-1 text-sm">
                    <strong class="font-black">{{ a.titre }}</strong>
                    <span v-if="a.message" class="whitespace-pre-line"> — {{ a.message }}</span>
                </p>
                <button
                    type="button"
                    class="-mr-1 grid h-7 w-7 shrink-0 place-items-center rounded-lg opacity-60 transition hover:bg-black/5 hover:opacity-100"
                    aria-label="Masquer cette annonce"
                    @click="masquer(a)"
                >
                    <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</template>
