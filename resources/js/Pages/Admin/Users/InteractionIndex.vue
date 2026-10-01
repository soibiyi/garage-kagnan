<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    chargesClients: Array,
});

/* ------------------------------------------------------------------ */
/* Champ de recherche & filtrage dynamique                             */
/* ------------------------------------------------------------------ */
const searchQuery = ref('');

const chargesClientsFiltres = computed(() => {
    if (!props.chargesClients) return [];
    const query = searchQuery.value.trim().toLowerCase();
    
    if (!query) return props.chargesClients;

    return props.chargesClients
        .map((charge) => {
            const matchCharge = charge.name.toLowerCase().includes(query) || 
                                (charge.email && charge.email.toLowerCase().includes(query));

            // Si le nom du chargé client correspond, on conserve tous ses clients
            if (matchCharge) {
                return charge;
            }

            // Sinon, on filtre les groupes de clients pour ne garder que ceux dont le nom/téléphone correspond
            const clientsFiltres = charge.clients_groups.filter((groupe) => {
                const nomClient = (groupe.client.nom || '').toLowerCase();
                const telClient = (groupe.client.telephone || '').toLowerCase();
                return nomClient.includes(query) || telClient.includes(query);
            });

            if (clientsFiltres.length > 0) {
                return {
                    ...charge,
                    clients_groups: clientsFiltres,
                };
            }

            return null;
        })
        .filter(Boolean);
});

/* ------------------------------------------------------------------ */
/* Gestion de l'ouverture/fermeture des accordéons                   */
/* ------------------------------------------------------------------ */
const chargeOuvertId = ref(props.chargesClients?.[0]?.id || null);

const basculerCharge = (id) => {
    chargeOuvertId.value = chargeOuvertId.value === id ? null : id;
};

/* ------------------------------------------------------------------ */
/* Utilitaires                                                        */
/* ------------------------------------------------------------------ */
const lireDate = (valeur) => {
    if (!valeur) return null;
    const [a, m, j] = String(valeur).slice(0, 10).split('-').map(Number);
    if (!a || !m || !j) return null;
    return new Date(a, m - 1, j);
};

const formatDateLongue = (date) =>
    date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const formatHeure = (date) =>
    date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

const debutJour = (valeur) => {
    const d = new Date(valeur);
    d.setHours(0, 0, 0, 0);
    return d;
};

const ecartJours = (date) => Math.round((debutJour(new Date()) - debutJour(date)) / 86400000);

const quand = (date) => {
    const j = ecartJours(date);
    const jour = j <= 0 ? "aujourd'hui" : j === 1 ? 'hier' : formatDateLongue(date);
    return `${jour} à ${formatHeure(date)}`;
};

/* ------------------------------------------------------------------ */
/* Type d'interactions                                                */
/* ------------------------------------------------------------------ */
const TYPES = [
    { valeur: 'appel', label: 'Appel', icone: 'fa-solid fa-phone' },
    { valeur: 'whatsapp', label: 'WhatsApp', icone: 'fa-solid fa-comments' },
    { valeur: 'sms', label: 'SMS', icone: 'fa-solid fa-message' },
    { valeur: 'email', label: 'E-mail', icone: 'fa-solid fa-envelope' },
    { valeur: 'visite', label: 'Visite', icone: 'fa-solid fa-handshake' },
    { valeur: 'autre', label: 'Autre', icone: 'fa-solid fa-ellipsis' },
];

const iconeType = (valeur) => TYPES.find((t) => t.valeur === valeur)?.icone || 'fa-solid fa-comment';
</script>

<template>
    <Head title="Suivi des interactions par chargé client — Administration" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-black text-[#0B0F19]">
                        Suivi des interactions
                    </h2>
                    <p class="text-xs font-medium text-[#8A8D8F]">
                        Vue d'ensemble de l'historique des échanges par chargé client et par client
                    </p>
                </div>

                <!-- BARRE DE RECHERCHE -->
                <div class="relative w-full sm:w-80">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Rechercher par chargé client ou client..."
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/50 pl-9 pr-8 py-2 text-xs font-medium text-[#0B0F19] transition focus:border-[#0B0F19] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0B0F19]"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                    >
                        <i class="fa-solid fa-circle-xmark text-xs"></i>
                    </button>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">

                <!-- LISTE DES CHARGÉS CLIENTS -->
                <div v-if="chargesClientsFiltres && chargesClientsFiltres.length" class="space-y-6">
                    <div
                        v-for="charge in chargesClientsFiltres"
                        :key="charge.id"
                        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition"
                    >
                        <!-- ENTÊTE CHARGÉ CLIENT -->
                        <button
                            type="button"
                            @click="basculerCharge(charge.id)"
                            class="flex w-full items-center justify-between bg-[#F8FAFC] p-5 text-left transition hover:bg-gray-100"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#0B0F19] text-white font-black text-sm">
                                    {{ charge.name ? charge.name.charAt(0).toUpperCase() : 'C' }}
                                </div>
                                <div>
                                    <h3 class="text-base font-black text-[#0B0F19]">{{ charge.name }}</h3>
                                    <p class="text-xs font-medium text-[#8A8D8F]">{{ charge.email }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-bold text-[#0B0F19]">
                                    {{ pluriel(charge.total_interactions, 'interaction') }}
                                </span>
                                <i
                                    class="fa-solid fa-chevron-down text-gray-400 transition-transform duration-200"
                                    :class="{ 'rotate-180': chargeOuvertId === charge.id || searchQuery !== '' }"
                                ></i>
                            </div>
                        </button>

                        <!-- CONTENU : GROUPES PAR CLIENT -->
                        <div v-if="chargeOuvertId === charge.id || searchQuery !== ''" class="divide-y divide-gray-100 p-6 space-y-6">
                            <div
                                v-for="(groupe, idx) in charge.clients_groups"
                                :key="idx"
                                class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 space-y-4"
                            >
                                <!-- ENTÊTE CLIENT -->
                                <div class="flex items-center justify-between border-b border-gray-200/60 pb-3">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-user-circle text-gray-400 text-lg"></i>
                                        <h4 class="text-sm font-extrabold text-[#0B0F19]">
                                            {{ groupe.client.nom }}
                                        </h4>
                                        <span v-if="groupe.client.telephone" class="text-xs text-[#8A8D8F] font-medium">
                                            ({{ groupe.client.telephone }})
                                        </span>
                                    </div>
                                    <span class="text-xs font-semibold text-[#8A8D8F]">
                                        {{ pluriel(groupe.interactions.length, 'échange') }}
                                    </span>
                                </div>

                                <!-- FRISE TEMPORELLE DES INTERACTIONS DU CLIENT -->
                                <ol class="relative ml-4 space-y-6 border-l border-gray-200">
                                    <li
                                        v-for="(interaction, iIdx) in groupe.interactions"
                                        :key="interaction.id"
                                        class="relative pl-6"
                                    >
                                        <span
                                            class="absolute -left-3 top-0 flex h-6 w-6 items-center justify-center rounded-full text-[10px] ring-1"
                                            :class="iIdx === 0 ? 'bg-[#0B0F19] text-white ring-[#0B0F19]' : 'bg-white text-[#0B0F19] ring-gray-200'"
                                        >
                                            <i :class="iconeType(interaction.type)"></i>
                                        </span>

                                        <div class="flex items-baseline justify-between">
                                            <p class="text-xs font-bold text-[#0B0F19]">
                                                {{ interaction.objet || 'Sans objet' }}
                                            </p>
                                            <span class="text-[11px] font-medium text-[#8A8D8F]">
                                                {{ quand(new Date(interaction.created_at)) }}
                                            </span>
                                        </div>

                                        <p v-if="interaction.vehicule" class="mt-0.5 text-[11px] font-semibold text-gray-500">
                                            <i class="fa-solid fa-car text-[10px] mr-1"></i>
                                            {{ interaction.vehicule.marque }} {{ interaction.vehicule.modele }} ({{ interaction.vehicule.immatriculation }})
                                        </p>

                                        <p v-if="interaction.notes" class="mt-1.5 whitespace-pre-line text-xs text-gray-700 leading-relaxed">
                                            {{ interaction.notes }}
                                        </p>

                                        <div v-if="interaction.accord_convenu" class="mt-2 border-l-2 border-[#E11D48] pl-2">
                                            <p class="text-[11px] font-bold text-[#0B0F19]">Accord convenu</p>
                                            <p class="text-xs text-gray-700 whitespace-pre-line">{{ interaction.accord_convenu }}</p>
                                        </div>

                                        <p
                                            v-if="interaction.date_relance_prevue && lireDate(interaction.date_relance_prevue)"
                                            class="mt-2 flex items-center gap-1.5 text-[11px] font-semibold text-gray-500"
                                        >
                                            <i class="fa-regular fa-calendar text-[10px]"></i>
                                            Relance prévue le {{ formatDateLongue(lireDate(interaction.date_relance_prevue)) }}
                                        </p>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AUCUN RÉSULTAT DE RECHERCHE -->
                <div
                    v-else
                    class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-12 text-center"
                >
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucun résultat trouvé</h4>
                    <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                        Aucun chargé client ou client ne correspond à votre recherche "{{ searchQuery }}".
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>