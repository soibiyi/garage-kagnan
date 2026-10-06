<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    vehicule: { type: Object, required: true },
    href: { type: String, required: true },
    libelleRetour: { type: String, required: true },
    section: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col gap-4 py-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p v-if="section" class="mb-1 text-xs font-bold uppercase tracking-wider text-[#E11D48]">
                {{ section }}
            </p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <h2 class="text-xl font-black tracking-tight text-[#0B0F19] sm:text-2xl">
                    {{ vehicule.marque }} {{ vehicule.modele }}
                </h2>
                <span class="rounded-lg bg-[#0B0F19] px-2.5 py-1 font-mono text-xs font-bold text-white">
                    {{ vehicule.immatriculation || 'Sans immat.' }}
                </span>
            </div>
            <p class="mt-1 text-sm font-medium text-[#8A8D8F]">
                Propriétaire :
                <span class="font-bold text-[#0B0F19]">
                    {{ vehicule.client?.nom }} {{ vehicule.client?.prenom }}
                </span>
            </p>
        </div>

        <Link
            :href="href"
            class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl border border-gray-200 bg-white px-4 text-xs font-bold text-[#0B0F19] transition duration-200 hover:border-[#0B0F19] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95 sm:self-auto"
        >
            <i class="fa-solid fa-arrow-left text-[11px]"></i>
            <span>{{ libelleRetour }}</span>
        </Link>
    </div>
</template>