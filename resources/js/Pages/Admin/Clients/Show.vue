<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    client: Object,
});

const showPassword = ref(false);

const statusLabel = computed(() => props.client?.status === 'banni' || props.client?.banned_at ? 'Banni' : 'Actif');
const statusClass = computed(() => props.client?.status === 'banni' || props.client?.banned_at ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700');

const toggleStatus = () => {
    if (!window.confirm(props.client?.status === 'banni' || props.client?.banned_at ? 'Réactiver ce compte client ?' : 'Bannir ce compte client ?')) {
        return;
    }

    router.patch(route('admin.clients.gestion.status', props.client.id), {}, {
        preserveScroll: true,
    });
};

const deleteClient = () => {
    if (!window.confirm(`Supprimer définitivement ${props.client?.nom} ${props.client?.prenom} ?`)) {
        return;
    }

    router.delete(route('admin.clients.gestion.destroy', props.client.id), {
        preserveScroll: true,
    });
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
</script>

<template>
    <Head :title="`Compte client - ${client?.nom || ''} ${client?.prenom || ''}`" />

    <div class="min-h-screen bg-[#F5F5F6] py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#C8102E]">Compte client</p>
                    <h1 class="mt-2 text-2xl font-black text-[#1A1A1A]">
                        {{ client?.nom }} {{ client?.prenom }}
                    </h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('admin.clients.gestion')"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-[#1A1A1A] hover:bg-gray-50"
                    >
                        Retour
                    </Link>
                    <button
                        type="button"
                        @click="toggleStatus"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-xl px-4 text-sm font-semibold text-white"
                        :class="client?.status === 'banni' || client?.banned_at ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-[#C8102E] hover:bg-[#a90d26]'"
                    >
                        {{ client?.status === 'banni' || client?.banned_at ? 'Réactiver' : 'Bannir' }}
                    </button>
                    <button
                        type="button"
                        @click="deleteClient"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 text-sm font-semibold text-red-700 hover:bg-red-100"
                    >
                        Supprimer
                    </button>
                </div>
            </div>

            <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-black text-[#1A1A1A]">Informations du compte</h2>
                            <span :class="statusClass" class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold">
                                {{ statusLabel }}
                            </span>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Nom</p>
                                <p class="mt-1 text-sm font-semibold text-[#1A1A1A]">{{ client?.nom }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Prénom</p>
                                <p class="mt-1 text-sm font-semibold text-[#1A1A1A]">{{ client?.prenom }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Téléphone</p>
                                <p class="mt-1 text-sm font-semibold text-[#1A1A1A]">{{ client?.telephone || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Email</p>
                                <p class="mt-1 text-sm font-semibold text-[#1A1A1A]">{{ client?.email || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Siège</p>
                                <p class="mt-1 text-sm font-semibold text-[#1A1A1A]">{{ client?.siege || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Type</p>
                                <p class="mt-1 text-sm font-semibold text-[#1A1A1A]">{{ client?.type_client || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Créé le</p>
                                <p class="mt-1 text-sm font-semibold text-[#1A1A1A]">{{ formatDate(client?.created_at) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Banni le</p>
                                <p class="mt-1 text-sm font-semibold text-[#1A1A1A]">{{ formatDate(client?.banned_at) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-black text-[#1A1A1A]">Accès portail client</h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Identifiant</p>
                                <p class="mt-1 select-all font-mono text-sm font-bold text-[#1A1A1A]">{{ client?.portal_identifier || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#6B6E70]">Mot de passe</p>
                                <div v-if="client?.portal_temp_password" class="mt-1 flex items-center gap-3">
                                    <p class="select-all font-mono text-sm font-bold text-[#1A1A1A]">
                                        {{ showPassword ? client.portal_temp_password : '••••••••••••••••' }}
                                    </p>
                                    <button type="button" @click="showPassword = !showPassword" class="text-xs font-bold text-[#C8102E]">
                                        {{ showPassword ? 'Masquer' : 'Afficher' }}
                                    </button>
                                </div>
                                <p v-else class="mt-1 text-sm text-[#6B6E70]">Pas de mot de passe temporaire.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-black text-[#1A1A1A]">Véhicules</h2>
                        <div v-if="client?.vehicules?.length" class="mt-4 space-y-3">
                            <div v-for="vehicule in client.vehicules" :key="vehicule.id" class="rounded-2xl border border-gray-200 bg-[#F8FAFC] p-3">
                                <p class="font-bold text-[#1A1A1A]">{{ vehicule.marque }} {{ vehicule.modele }}</p>
                                <p class="mt-1 text-xs text-[#6B6E70]">{{ vehicule.immatriculation }}</p>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-[#6B6E70]">Aucun véhicule.</p>
                    </div>

                    <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-black text-[#1A1A1A]">Demandes portail</h2>
                        <div v-if="client?.portal_requests?.length" class="mt-4 space-y-3">
                            <div v-for="request in client.portal_requests" :key="request.id" class="rounded-2xl border border-gray-200 bg-[#F8FAFC] p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-bold text-[#1A1A1A]">{{ request.type }}</p>
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-bold uppercase text-[#1A1A1A]">{{ request.statut }}</span>
                                </div>
                                <p class="mt-1 text-xs text-[#6B6E70]">{{ formatDate(request.created_at) }}</p>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-[#6B6E70]">Aucune demande.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
