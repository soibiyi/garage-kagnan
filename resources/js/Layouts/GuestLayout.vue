<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="kg-internal-theme flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0"
    >
        <div
            class="w-full overflow-hidden rounded-2xl border border-white/70 bg-white/70 px-6 py-4 shadow-xl sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>