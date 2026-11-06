<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientEntete from '@/Components/ClientEntete.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    client: Object,
});

const acceptingRequestId = ref(null);

const acceptAppointment = (requestId) => {
    if (acceptingRequestId.value) return;
    acceptingRequestId.value = requestId;
    router.post(route('charge_client.clients.portal-requests.accept', {
        client: props.client.id,
        portalRequest: requestId,
    }), {}, {
        preserveScroll: true,
        onFinish: () => { acceptingRequestId.value = null; },
    });
};

const refusingRequestId = ref(null);

const refuseAppointment = (requestId) => {
    if (acceptingRequestId.value || refusingRequestId.value) return;
    if (!window.confirm('Refuser ce rendez-vous ? Le client verra que sa demande a été refusée.')) return;
    refusingRequestId.value = requestId;
    router.post(route('charge_client.clients.portal-requests.refuse', {
        client: props.client.id,
        portalRequest: requestId,
    }), {}, {
        preserveScroll: true,
        onFinish: () => { refusingRequestId.value = null; },
    });
};

const STATUTS = {
    en_attente: { label: 'En attente', classe: 'bg-amber-100 text-amber-800' },
    accepte: { label: 'Accepté', classe: 'bg-emerald-100 text-emerald-800' },
    refuse: { label: 'Refusé', classe: 'bg-rose-100 text-rose-800' },
};
const statut = (s) => STATUTS[s] || { label: String(s || '').replaceAll('_', ' '), classe: 'bg-gray-100 text-gray-700' };

const rendezVous = computed(() =>
    (props.client.portal_requests || []).filter((r) => r.type === 'rendez_vous')
);

const filtre = ref('toutes');
const filtres = computed(() => [
    { cle: 'toutes', label: 'Tous', nb: rendezVous.value.length },
    { cle: 'en_attente', label: 'À accepter', nb: rendezVous.value.filter((r) => r.statut === 'en_attente').length },
    { cle: 'accepte', label: 'Acceptés', nb: rendezVous.value.filter((r) => r.statut === 'accepte').length },
    { cle: 'refuse', label: 'Refusés', nb: rendezVous.value.filter((r) => r.statut === 'refuse').length },
]);

const affiches = computed(() =>
    filtre.value === 'toutes' ? rendezVous.value : rendezVous.value.filter((r) => r.statut === filtre.value)
);

const dateFr = (valeur) => new Date(valeur).toLocaleString('fr-FR');
</script>

<template>
    <Head :title="`Rendez-vous : ${client.nom} — Garage Kagnan`" />

    <AuthenticatedLayout>
        <template #header>
            <ClientEntete
                :client="client"
                :href="route('charge_client.clients.show', client.id)"
                libelle-retour="Retour au client"
                section="Rendez-vous"
            />
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filtrer les rendez-vous">
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
                    v-for="rdv in affiches"
                    :key="rdv.id"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#E11D48]">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div>
                                <p class="text-sm font-extrabold text-[#0B0F19]">Demande de rendez-vous</p>
                                <p class="text-xs font-medium text-[#8A8D8F]">Envoyée le {{ dateFr(rdv.created_at) }}</p>
                            </div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statut(rdv.statut).classe">
                            {{ statut(rdv.statut).label }}
                        </span>
                    </div>

                    <p v-if="rdv.vehicule" class="mt-3 flex items-center gap-2 text-xs font-semibold text-gray-600">
                        <i class="fa-solid fa-car text-[10px] text-[#8A8D8F]"></i>
                        {{ rdv.vehicule.marque }} {{ rdv.vehicule.modele }} — {{ rdv.vehicule.immatriculation }}
                    </p>

                    <dl v-if="rdv.details && Object.keys(rdv.details).length" class="mt-3 space-y-1 rounded-xl bg-[#F8FAFC] p-3 text-sm">
                        <div v-for="(valeur, cle) in rdv.details" :key="cle" class="flex flex-wrap gap-x-2">
                            <dt class="font-bold capitalize text-[#0B0F19]">{{ cle.replaceAll('_', ' ') }} :</dt>
                            <dd class="text-gray-700">{{ valeur }}</dd>
                        </div>
                    </dl>

                    <div v-if="rdv.statut === 'en_attente'" class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            :disabled="acceptingRequestId !== null || refusingRequestId !== null"
                            class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-[#E11D48] px-4 text-xs font-bold text-white transition hover:bg-[#BE123C] active:scale-[0.97] disabled:cursor-wait disabled:opacity-60"
                            @click="acceptAppointment(rdv.id)"
                        >
                            <i
                                :class="acceptingRequestId === rdv.id ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-check'"
                                class="text-[11px]"
                            ></i>
                            {{ acceptingRequestId === rdv.id ? 'Acceptation…' : 'Accepter' }}
                        </button>
                        <button
                            type="button"
                            :disabled="acceptingRequestId !== null || refusingRequestId !== null"
                            class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-xs font-bold text-[#0B0F19] transition hover:border-[#0B0F19] active:scale-[0.97] disabled:cursor-wait disabled:opacity-60"
                            @click="refuseAppointment(rdv.id)"
                        >
                            <i
                                :class="refusingRequestId === rdv.id ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-xmark'"
                                class="text-[11px]"
                            ></i>
                            {{ refusingRequestId === rdv.id ? 'Refus…' : 'Refuser' }}
                        </button>
                    </div>
                </article>

                <div
                    v-if="!affiches.length"
                    class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-12 text-center"
                >
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucun rendez-vous</h4>
                    <p class="mx-auto mt-1 max-w-xs text-xs font-medium leading-relaxed text-[#8A8D8F]">
                        Les rendez-vous demandés depuis l’espace client apparaîtront ici, prêts à être acceptés.
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>