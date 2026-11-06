<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientEntete from '@/Components/ClientEntete.vue';
import MissionTracking from '@/Components/MissionTracking.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    client: Object,
});

const TYPES = {
    depannage: { label: 'Dépannage', icone: 'fa-solid fa-truck-medical' },
    convoyage: { label: 'Convoyage', icone: 'fa-solid fa-truck-pickup' },
    message: { label: 'Message', icone: 'fa-solid fa-envelope' },
};

const STATUTS = {
    en_attente: { label: 'En attente', classe: 'bg-amber-100 text-amber-800' },
    accepte: { label: 'Acceptée', classe: 'bg-emerald-100 text-emerald-800' },
    en_cours: { label: 'En cours', classe: 'bg-purple-100 text-purple-800' },
    refuse: { label: 'Refusée', classe: 'bg-rose-100 text-rose-800' },
};

const statut = (s) => STATUTS[s] || { label: String(s || '').replaceAll('_', ' '), classe: 'bg-gray-100 text-gray-700' };
const type = (t) => TYPES[t] || { label: t, icone: 'fa-solid fa-circle-question' };

// Les rendez-vous ont leur propre vue
const demandes = computed(() =>
    (props.client.portal_requests || []).filter((r) => r.type !== 'rendez_vous')
);

const filtre = ref('toutes');
const filtres = computed(() => [
    { cle: 'toutes', label: 'Toutes', nb: demandes.value.length },
    { cle: 'en_attente', label: 'En attente', nb: demandes.value.filter((d) => d.statut === 'en_attente').length },
    { cle: 'en_cours', label: 'En cours', nb: demandes.value.filter((d) => ['accepte', 'en_cours'].includes(d.statut)).length },
    { cle: 'refuse', label: 'Refusées', nb: demandes.value.filter((d) => d.statut === 'refuse').length },
]);

const affichees = computed(() => {
    switch (filtre.value) {
        case 'en_attente': return demandes.value.filter((d) => d.statut === 'en_attente');
        case 'en_cours': return demandes.value.filter((d) => ['accepte', 'en_cours'].includes(d.statut));
        case 'refuse': return demandes.value.filter((d) => d.statut === 'refuse');
        default: return demandes.value;
    }
});

const suiviActif = (d) =>
    ['depannage', 'convoyage'].includes(d.type) && ['accepte', 'en_cours'].includes(d.statut);

const position = (lat, lng) =>
    lat !== null && lat !== undefined ? { lat: Number(lat), lng: Number(lng) } : null;

const dateFr = (valeur) => new Date(valeur).toLocaleString('fr-FR');
</script>

<template>
    <Head :title="`Demandes : ${client.nom} — Garage Kagnan`" />

    <AuthenticatedLayout>
        <template #header>
            <ClientEntete
                :client="client"
                :href="route('charge_client.clients.show', client.id)"
                libelle-retour="Retour au client"
                section="Demandes"
            />
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filtrer les demandes">
                    <button
                        v-for="f in filtres"
                        :key="f.cle"
                        type="button"
                        role="tab"
                        :aria-selected="filtre === f.cle"
                        class="inline-flex min-h-10 items-center gap-2 rounded-xl border px-3.5 text-xs font-bold transition duration-200 active:scale-[0.97]"
                        :class="filtre === f.cle
                            ? 'border-[#0B0F19] bg-[#0B0F19] text-white'
                            : 'border-gray-200 bg-white text-[#0B0F19] hover:border-[#0B0F19]'"
                        @click="filtre = f.cle"
                    >
                        {{ f.label }}
                        <span
                            class="rounded-md px-1.5 py-0.5 text-[10px]"
                            :class="filtre === f.cle ? 'bg-white/20' : 'bg-[#F8FAFC] text-[#8A8D8F]'"
                        >{{ f.nb }}</span>
                    </button>
                </div>

                <article
                    v-for="demande in affichees"
                    :key="demande.id"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#E11D48]">
                                <i :class="type(demande.type).icone"></i>
                            </div>
                            <div>
                                <p class="text-sm font-extrabold text-[#0B0F19]">{{ type(demande.type).label }}</p>
                                <p class="text-xs font-medium text-[#8A8D8F]">{{ dateFr(demande.created_at) }}</p>
                            </div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statut(demande.statut).classe">
                            {{ statut(demande.statut).label }}
                        </span>
                    </div>

                    <p v-if="demande.vehicule" class="mt-3 flex items-center gap-2 text-xs font-semibold text-gray-600">
                        <i class="fa-solid fa-car text-[10px] text-[#8A8D8F]"></i>
                        {{ demande.vehicule.marque }} {{ demande.vehicule.modele }} — {{ demande.vehicule.immatriculation }}
                    </p>

                    <dl v-if="demande.details && Object.keys(demande.details).length" class="mt-3 space-y-1 rounded-xl bg-[#F8FAFC] p-3 text-sm">
                        <div v-for="(valeur, cle) in demande.details" :key="cle" class="flex flex-wrap gap-x-2">
                            <dt class="font-bold capitalize text-[#0B0F19]">{{ cle.replaceAll('_', ' ') }} :</dt>
                            <dd class="text-gray-700">{{ valeur }}</dd>
                        </div>
                    </dl>

                    <MissionTracking
                        v-if="suiviActif(demande)"
                        class="mt-4"
                        audience="staff"
                        :request-id="Number(demande.id)"
                        :client-id="Number(client.id)"
                        :initial-client-position="position(demande.client_lat, demande.client_lng)"
                        :initial-destination-position="position(demande.destination_lat, demande.destination_lng)"
                    />
                </article>

                <div
                    v-if="!affichees.length"
                    class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-12 text-center"
                >
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                        <i class="fa-solid fa-truck-medical"></i>
                    </div>
                    <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucune demande</h4>
                    <p class="mx-auto mt-1 max-w-xs text-xs font-medium leading-relaxed text-[#8A8D8F]">
                        Les demandes de dépannage et de convoyage envoyées depuis l’espace client apparaîtront ici.
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
