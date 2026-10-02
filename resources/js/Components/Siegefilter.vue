<script setup>
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    routeName: { type: String, required: true }, // ex : 'admin.chiffre-affaires'
    current: { type: String, default: null },    // siège actuellement filtré (null = tous)
});

const page = usePage();

const changer = (event) => {
    const siege = event.target.value;
    router.get(route(props.routeName), siege ? { siege } : {}, {
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <div class="inline-flex items-center gap-2 print:hidden">
        <label class="text-xs font-black uppercase tracking-wider text-[#8A8D8F]">Siège</label>
        <select
            :value="current ?? ''"
            class="rounded-xl border-gray-200 bg-white py-2 pl-3 pr-8 text-sm font-semibold text-[#0B0F19] shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]"
            @change="changer"
        >
            <option value="">Tous les sièges</option>
            <option v-for="(nom, code) in page.props.sieges" :key="code" :value="code">{{ code }} — {{ nom }}</option>
        </select>
    </div>
</template>