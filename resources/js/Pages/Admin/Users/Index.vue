<script setup>
import { Head } from '@inertiajs/vue3';
import TableauDeBordAdmin from '@/Components/Admin/TableauDeBordAdmin.vue';

defineProps({
    users: Array,
    stats: Object,
    siegeFiltre: { type: String, default: null },
});
</script>

<template>
  <Head title="Gestion des utilisateurs" />
  <TableauDeBordAdmin :users="users" :stats="stats" :siege-filtre="siegeFiltre" />
</template>
