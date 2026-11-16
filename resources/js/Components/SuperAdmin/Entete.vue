<script setup>
// En-tête commun des pages du super administrateur (verre sombre, retour à l'accueil).
import { Link } from '@inertiajs/vue3';

defineProps({
    titre: { type: String, required: true },
    surtitre: { type: String, default: 'Super administrateur' },
    texte: { type: String, default: null },
    icone: { type: String, required: true },
});
</script>

<template>
    <header class="sg-glass sg-glass-dark sg-rise overflow-hidden p-5 sm:p-7">
        <span class="absolute left-0 top-0 h-1 w-20 rounded-br-full bg-[#E11D48]"></span>
        <Link :href="route('superadmin.accueil')" class="sg-retour">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Accueil super admin
        </Link>
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <span class="sg-tile sg-tile-red h-12 w-12 text-lg"><i class="fa-solid" :class="icone" aria-hidden="true"></i></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-white/50">{{ surtitre }}</p>
                    <h1 class="text-2xl font-black tracking-tight sm:text-3xl">{{ titre }}</h1>
                    <p v-if="texte" class="mt-0.5 text-sm text-white/60">{{ texte }}</p>
                </div>
            </div>
            <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
                <slot />
            </div>
        </div>
    </header>
</template>
