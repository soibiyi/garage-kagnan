<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    clients: Array,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.search || '');
const statut = ref(props.filters?.statut || 'tous');

watch([search, statut], () => {
    router.get(route('admin.clients.gestion'), {
        search: search.value,
        statut: statut.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, { deep: true });

const totalActifs = computed(() => props.stats?.actifs ?? 0);
const totalBannis = computed(() => props.stats?.bannis ?? 0);

const clientsFiltres = computed(() => props.clients || []);

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const toggleStatus = (client) => {
    if (!window.confirm(client.status === 'banni' || client.banned_at ? 'Réactiver ce compte client ?' : 'Bannir ce compte client ?')) {
        return;
    }

    router.patch(route('admin.clients.gestion.status', client.id), {}, {
        preserveScroll: true,
    });
};

const deleteClient = (client) => {
    if (!window.confirm(`Supprimer définitivement le compte client ${client.nom} ${client.prenom} ?`)) {
        return;
    }

    router.delete(route('admin.clients.gestion.destroy', client.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Gestion des comptes clients" />

    <div class="min-h-screen bg-[#F5F5F6] py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#C8102E]">Administration</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-[#1A1A1A]">Comptes clients</h1>
                    <p class="mt-1 text-sm text-[#6B6E70]">Vue centralisée des comptes client, leur statut, et leurs droits d’accès au portail.</p>
                </div>
                <Link
                    :href="route('admin.users.index')"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-[#1A1A1A] transition hover:bg-gray-50"
                >
                    Retour au dashboard
                </Link>
            </div>

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#6B6E70]">Total</p>
                    <p class="mt-3 text-3xl font-black text-[#1A1A1A]">{{ props.stats?.total ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Actifs</p>
                    <p class="mt-3 text-3xl font-black text-emerald-900">{{ totalActifs }}</p>
                </div>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-red-700">Bannis</p>
                    <p class="mt-3 text-3xl font-black text-red-900">{{ totalBannis }}</p>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
                        <div class="relative md:w-80">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Rechercher nom, téléphone, email..."
                                class="min-h-[44px] w-full rounded-xl border border-gray-200 bg-[#F8FAFC] px-3.5 pr-10 text-sm text-[#1A1A1A] outline-none transition focus:border-[#C8102E] focus:ring-2 focus:ring-[#C8102E]/15"
                            />
                        </div>
                        <select
                            v-model="statut"
                            class="min-h-[44px] rounded-xl border border-gray-200 bg-[#F8FAFC] px-3.5 text-sm font-medium text-[#1A1A1A] outline-none transition focus:border-[#C8102E] focus:ring-2 focus:ring-[#C8102E]/15"
                        >
                            <option value="tous">Tous</option>
                            <option value="actifs">Actifs</option>
                            <option value="bannis">Bannis</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                        <thead class="bg-[#F8FAFC] text-[#6B6E70]">
                            <tr>
                                <th class="px-4 py-3 font-bold">Client</th>
                                <th class="px-4 py-3 font-bold">Contact</th>
                                <th class="px-4 py-3 font-bold">Compte portail</th>
                                <th class="px-4 py-3 font-bold">Statut</th>
                                <th class="px-4 py-3 font-bold">Créé le</th>
                                <th class="px-4 py-3 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-if="clientsFiltres.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-[#6B6E70]">
                                    Aucun client trouvé.
                                </td>
                            </tr>
                            <tr v-for="client in clientsFiltres" :key="client.id" class="align-top hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#1A1A1A] text-xs font-black text-white">
                                            {{ (client.nom || '?').charAt(0) }}{{ (client.prenom || '?').charAt(0) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-[#1A1A1A]">{{ client.nom }} {{ client.prenom }}</p>
                                            <p class="text-xs text-[#6B6E70]">{{ client.siege || 'Sans siège' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-medium text-[#1A1A1A]">{{ client.telephone || '—' }}</p>
                                    <p class="text-xs text-[#6B6E70]">{{ client.email || 'Email non renseigné' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-medium text-[#1A1A1A]">{{ client.portal_identifier || '—' }}</p>
                                    <p class="text-xs text-[#6B6E70]">
                                        {{ client.portal_registered ? 'Compte en ligne' : 'Compte hors ligne' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        :class="client.status === 'banni' || client.banned_at ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                    >
                                        {{ client.status === 'banni' || client.banned_at ? 'Banni' : 'Actif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-[#6B6E70]">
                                    {{ formatDate(client.created_at) }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="route('admin.clients.gestion.show', client.id)"
                                            class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-[#1A1A1A] hover:bg-gray-50"
                                        >
                                            Voir
                                        </Link>
                                        <button
                                            type="button"
                                            @click="toggleStatus(client)"
                                            class="rounded-lg px-3 py-2 text-xs font-bold text-white"
                                            :class="client.status === 'banni' || client.banned_at ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-[#C8102E] hover:bg-[#a90d26]'"
                                        >
                                            {{ client.status === 'banni' || client.banned_at ? 'Réactiver' : 'Bannir' }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteClient(client)"
                                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100"
                                        >
                                            Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
