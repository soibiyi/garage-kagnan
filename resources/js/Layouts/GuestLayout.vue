<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0"
        style="background-color: #8A8D8F;"
    >
        <div
            class="w-full overflow-hidden px-6 py-4 sm:max-w-md sm:rounded-lg"
            style="background-color: #8A8D8F;"
        >
            <slot />
        </div>
    </div>
</template>