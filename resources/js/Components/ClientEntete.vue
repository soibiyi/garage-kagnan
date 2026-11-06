<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    client: { type: Object, required: true },
    href: { type: String, required: true },
    libelleRetour: { type: String, default: 'Retour' },
    section: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col gap-3 py-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p v-if="section" class="text-xs font-bold text-[#E11D48]">{{ section }}</p>
            <h2 class="truncate text-2xl font-black tracking-tight text-[#0B0F19]">
                {{ client.nom }} <span class="text-[#E11D48]">{{ client.prenom }}</span>
            </h2>
            <p class="mt-0.5 text-sm font-medium text-[#8A8D8F]">
                Téléphone : <span class="font-bold text-[#0B0F19]">{{ client.telephone || 'N/A' }}</span>
            </p>
        </div>
        <Link
            :href="href"
            class="inline-flex min-h-10 items-center text-xs font-bold text-[#0B0F19] transition hover:text-[#E11D48]"
        >
            <i class="fa-solid fa-arrow-left mr-1.5"></i> {{ libelleRetour }}
        </Link>
    </div>
</template>
