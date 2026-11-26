<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { dateHeure, fcfa } from '@/utils/format.js';

const props = defineProps({
    enAttente: { type: Array, default: () => [] },
    traitees: { type: Array, default: () => [] },
});

const STATUTS = {
    approuvee: { libelle: 'Supprimé', classe: 'bg-red-100 text-red-800' },
    refusee: { libelle: 'Refusée', classe: 'bg-emerald-100 text-emerald-800' },
    caduque: { libelle: 'Déjà supprimé', classe: 'bg-gray-200 text-gray-700' },
};

const enCours = ref(null);
const reponses = ref({});
const selection = ref([]);
const reponseSelection = ref('');

const toutesSelectionnees = () => props.enAttente.length > 0
    && props.enAttente.every((demande) => selection.value.includes(demande.id));

function basculerTout() {
    selection.value = toutesSelectionnees()
        ? []
        : props.enAttente.map((demande) => demande.id);
}

function basculerSelection(id) {
    selection.value = selection.value.includes(id)
        ? selection.value.filter((selectionId) => selectionId !== id)
        : [...selection.value, id];
}

function traiterSelection(action) {
    if (!selection.value.length || enCours.value) return;

    if (action === 'approuver') {
        if (!window.confirm(`Supprimer définitivement ${selection.value.length} demande(s) sélectionnée(s) ?\n\nLe super administrateur pourra restaurer les éléments supprimés.`)) return;
    }

    enCours.value = 'selection';
    router.post(route(`admin.demandes-suppression.${action}-selection`), {
        ids: selection.value,
        reponse: reponseSelection.value.trim() || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            enCours.value = null;
            selection.value = [];
            reponseSelection.value = '';
        },
    });
}

const vehicule = (c) => [c?.vehicule, c?.immatriculation ? `(${c.immatriculation})` : null].filter(Boolean).join(' ');

function approuver(d) {
    const qui = d.contexte?.client ? ` du client ${d.contexte.client}` : '';
    if (!window.confirm(`Supprimer ${d.libelle}${qui} ?\n\nLe dossier, son devis, sa facture et ses versements seront supprimés. Le super administrateur pourra les restaurer.`)) return;
    envoyer(d, 'approuver');
}

function refuser(d) {
    envoyer(d, 'refuser');
}

function envoyer(d, action) {
    enCours.value = d.id;
    router.post(route(`admin.demandes-suppression.${action}`, d.id), { reponse: reponses.value[d.id] || null }, {
        preserveScroll: true,
        onFinish: () => { enCours.value = null; },
    });
}
</script>

<template>
    <Head title="Demandes de suppression" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <header class="sg-glass sg-glass-dark sg-rise overflow-hidden p-5 sm:p-7" style="--d: 0ms">
                    <span class="absolute left-0 top-0 h-1 w-20 rounded-br-full bg-[#E11D48]"></span>
                    <div class="flex items-center gap-4">
                        <span class="sg-tile sg-tile-red h-12 w-12 text-lg"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-widest text-white/50">Administration</p>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Demandes de suppression</h1>
                            <p class="mt-0.5 text-sm text-white/60">Les collaborateurs demandent la suppression d’un dossier, d’un devis direct ou d’une facturation ; vous décidez.</p>
                        </div>
                    </div>
                </header>

                <!-- ═══ EN ATTENTE ═══ -->
                <section aria-labelledby="titre-attente" class="space-y-3">
                    <h2 id="titre-attente" class="sg-section-title text-[#0B0F19]">
                        <i class="fa-solid fa-hourglass-half text-[#E11D48]" aria-hidden="true"></i>À valider ({{ enAttente.length }})
                    </h2>

                    <div v-if="enAttente.length" class="sg-glass sg-glass-strong flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
                        <label class="flex min-h-10 items-center gap-2 text-sm font-bold text-[#0B0F19]">
                            <input
                                type="checkbox"
                                :checked="toutesSelectionnees()"
                                class="h-4 w-4 rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]"
                                aria-label="Sélectionner toutes les demandes"
                                @change="basculerTout"
                            />
                            Tout sélectionner
                        </label>

                        <div v-if="selection.length" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                            <span class="shrink-0 rounded-full bg-[#0B0F19] px-3 py-1.5 text-xs font-bold text-white">
                                {{ selection.length }} sélectionnée{{ selection.length > 1 ? 's' : '' }}
                            </span>
                            <input
                                v-model="reponseSelection"
                                type="text"
                                maxlength="500"
                                class="sg-field min-w-0 flex-1"
                                placeholder="Réponse commune (facultatif)"
                                aria-label="Réponse commune pour les demandes sélectionnées"
                            />
                            <button type="button" class="sg-btn sg-btn-glass" :disabled="enCours !== null" @click="traiterSelection('refuser')">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>Refuser
                            </button>
                            <button type="button" class="sg-btn sg-btn-red" :disabled="enCours !== null" @click="traiterSelection('approuver')">
                                <i class="fa-solid" :class="enCours === 'selection' ? 'fa-spinner fa-spin' : 'fa-trash-can'" aria-hidden="true"></i>Supprimer
                            </button>
                        </div>
                    </div>

                    <div v-if="!enAttente.length" class="sg-empty p-10 text-center">
                        <i class="fa-solid fa-circle-check text-3xl text-emerald-500" aria-hidden="true"></i>
                        <p class="mt-3 font-bold text-[#0B0F19]">Aucune demande en attente.</p>
                    </div>

                    <article v-for="d in enAttente" :key="d.id" class="sg-glass sg-glass-strong space-y-3 p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <label class="flex items-start gap-3 min-w-0">
                                <input
                                    type="checkbox"
                                    :checked="selection.includes(d.id)"
                                    class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]"
                                    :aria-label="`Sélectionner la demande ${d.libelle}`"
                                    @change="basculerSelection(d.id)"
                                />
                                <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-black text-[#0B0F19]">
                                    <span class="sg-chip bg-red-50 text-red-700">{{ d.type }}</span>
                                    {{ d.libelle }}
                                    <span v-if="d.siege" class="sg-chip bg-white text-gray-600">{{ d.siege }}</span>
                                </p>
                                <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-gray-700">
                                    <span v-if="d.contexte?.client"><i class="fa-solid fa-user-tie mr-1 text-gray-400" aria-hidden="true"></i><b>{{ d.contexte.client }}</b><template v-if="d.contexte.telephone"> · {{ d.contexte.telephone }}</template></span>
                                    <span v-if="vehicule(d.contexte)"><i class="fa-solid fa-car mr-1 text-gray-400" aria-hidden="true"></i>{{ vehicule(d.contexte) }}</span>
                                    <span v-if="d.contexte?.facture"><i class="fa-solid fa-receipt mr-1 text-gray-400" aria-hidden="true"></i>{{ d.contexte.facture }} · {{ fcfa(d.contexte.montant_total) }}<template v-if="d.contexte.montant_paye"> (payé {{ fcfa(d.contexte.montant_paye) }})</template></span>
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    Demandé par <b>{{ d.demandeur?.name || '—' }}</b><template v-if="d.demandeur?.role"> ({{ d.demandeur.role }})</template> · {{ dateHeure(d.date) }}
                                </p>
                                </div>
                            </label>
                            <span v-if="!d.existe" class="sg-chip bg-gray-200 text-gray-700">Déjà supprimé</span>
                        </div>

                        <blockquote class="rounded-xl border-l-4 border-[#E11D48] bg-white/70 px-3 py-2 text-sm text-[#0B0F19]">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Motif</span><br />{{ d.motif }}
                        </blockquote>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <label class="sr-only" :for="`rep-${d.id}`">Réponse au demandeur</label>
                            <input :id="`rep-${d.id}`" v-model="reponses[d.id]" type="text" maxlength="500" class="sg-field flex-1" placeholder="Réponse (facultatif)" />
                            <div class="flex gap-2">
                                <button type="button" class="sg-btn sg-btn-glass" :disabled="enCours === d.id" @click="refuser(d)">
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>Refuser
                                </button>
                                <button type="button" class="sg-btn sg-btn-red" :disabled="enCours === d.id" @click="approuver(d)">
                                    <i class="fa-solid" :class="enCours === d.id ? 'fa-spinner fa-spin' : 'fa-trash-can'" aria-hidden="true"></i>Supprimer
                                </button>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- ═══ HISTORIQUE ═══ -->
                <section v-if="traitees.length" aria-labelledby="titre-traitees" class="space-y-3">
                    <h2 id="titre-traitees" class="sg-section-title text-[#0B0F19]">
                        <i class="fa-solid fa-clock-rotate-left text-[#E11D48]" aria-hidden="true"></i>Dernières décisions
                    </h2>
                    <ul class="space-y-2">
                        <li v-for="d in traitees" :key="d.id" class="sg-glass sg-glass-strong flex flex-wrap items-start justify-between gap-2 p-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-bold text-[#0B0F19]">
                                    {{ d.libelle }}<template v-if="d.contexte?.client"> · {{ d.contexte.client }}</template>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Demandé par {{ d.demandeur?.name || '—' }} · « {{ d.motif }} »
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ d.traite_par || '—' }} · {{ dateHeure(d.traite_le) }}<template v-if="d.reponse"> · « {{ d.reponse }} »</template>
                                </p>
                            </div>
                            <span class="sg-chip" :class="STATUTS[d.statut]?.classe">{{ STATUTS[d.statut]?.libelle || d.statut }}</span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
