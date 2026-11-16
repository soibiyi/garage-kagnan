<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ page: { type: Object, required: true } });
</script>

<template>
    <nav v-if="page.last_page > 1" class="flex flex-wrap justify-center gap-1.5" aria-label="Pagination">
        <template v-for="lien in page.links" :key="lien.label">
            <Link
                v-if="lien.url"
                :href="lien.url"
                preserve-scroll
                preserve-state
                class="sg-pill inline-flex items-center"
                :class="{ 'sg-pill-active': lien.active }"
            ><span v-html="lien.label"></span></Link>
            <span v-else class="sg-pill inline-flex items-center opacity-40"><span v-html="lien.label"></span></span>
        </template>
    </nav>
</template>
