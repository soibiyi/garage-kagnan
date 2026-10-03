<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    current: { type: String, default: null },
});

// Mémorise le siège choisi en session, puis recharge la page courante avec les données de ce siège
const changerSiege = (event) => {
    const siege = event.target.value;
    if (siege === props.current) return;

    router.post(route('admin.siege.switch'), { siege }, { preserveScroll: true });
};
</script>

<template>
  <div class="flex items-center gap-2">
    <label class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Siège</label>
    <select
      :value="current"
      @change="changerSiege"
      class="text-sm font-semibold border border-gray-300 rounded-lg py-2 pl-3 pr-8 bg-white shadow-sm focus:border-[#C8102E] focus:ring-0"
    >
      <option v-for="(nom, code) in $page.props.sieges" :key="code" :value="code">{{ code }} — {{ nom }}</option>
    </select>
  </div>
</template>