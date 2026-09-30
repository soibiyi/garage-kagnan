<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VehiculeEntete from '@/Components/VehiculeEntete.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    vehicule: Object,
});

/* ------------------------------------------------------------------ */
/* Utilitaires                                                         */
/* ------------------------------------------------------------------ */
const lireDate = (valeur) => {
    if (!valeur) return null;
    const [a, m, j] = String(valeur).slice(0, 10).split('-').map(Number);
    if (!a || !m || !j) return null;
    return new Date(a, m - 1, j);
};

const formatDateLongue = (date) =>
    date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const formatHeure = (date) => date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

const debutJour = (valeur) => {
    const d = new Date(valeur);
    d.setHours(0, 0, 0, 0);
    return d;
};

// > 0 : dans le passé, < 0 : dans le futur
const ecartJours = (date) => Math.round((debutJour(new Date()) - debutJour(date)) / 86400000);

const ilYa = (date) => {
    const j = ecartJours(date);
    if (j <= 0) return "aujourd'hui";
    if (j === 1) return 'hier';
    return `il y a ${j} jours`;
};

// "hier à 14:30" pour le récent, date complète au-delà
const quand = (date) => {
    const j = ecartJours(date);
    const jour = j <= 0 ? "aujourd'hui" : j === 1 ? 'hier' : formatDateLongue(date);
    return `${jour} à ${formatHeure(date)}`;
};

const aujourdhuiISO = () => {
    const d = new Date();
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
};
const dateMin = aujourdhuiISO();

/* ------------------------------------------------------------------ */
/* Interactions avec le client (table interaction_clients)              */
/* ------------------------------------------------------------------ */
const TYPES = [
    { valeur: 'appel', label: 'Appel', icone: 'fa-solid fa-phone' },
    { valeur: 'whatsapp', label: 'WhatsApp', icone: 'fa-solid fa-comments' },
    { valeur: 'sms', label: 'SMS', icone: 'fa-solid fa-message' },
    { valeur: 'email', label: 'E-mail', icone: 'fa-solid fa-envelope' },
    { valeur: 'visite', label: 'Visite', icone: 'fa-solid fa-handshake' },
    { valeur: 'autre', label: 'Autre', icone: 'fa-solid fa-ellipsis' },
];

const OBJETS_RAPIDES = [
    "Renouvellement de l'assurance",
    'Visite technique (SICTA)',
    'Suite à un devis',
    'Prise de nouvelles',
];

const iconeType = (valeur) => TYPES.find((t) => t.valeur === valeur)?.icone || 'fa-solid fa-comment';
const libelleType = (valeur) => TYPES.find((t) => t.valeur === valeur)?.label || valeur;

// Plus récentes en premier
const interactions = computed(() =>
    [...(props.vehicule.interactions || [])].sort(
        (a, b) => new Date(b.created_at) - new Date(a.created_at) || b.id - a.id
    )
);

const derniereInteraction = computed(() => interactions.value[0] || null);

// La relance en cours est celle annoncée par la dernière interaction
const relancePrevue = computed(() => {
    const date = derniereInteraction.value ? lireDate(derniereInteraction.value.date_relance_prevue) : null;
    if (!date) return null;

    const jours = -ecartJours(date);

    if (jours < 0) {
        return { date, urgent: true, icone: 'fa-solid fa-triangle-exclamation', libelle: `En retard de ${pluriel(Math.abs(jours), 'jour')}` };
    }
    if (jours === 0) {
        return { date, urgent: true, icone: 'fa-solid fa-bell', libelle: "À faire aujourd'hui" };
    }
    return { date, urgent: false, icone: 'fa-regular fa-calendar', libelle: `Dans ${pluriel(jours, 'jour')}` };
});

/* ------------------------------------------------------------------ */
/* Formulaire repliable                                                 */
/* ------------------------------------------------------------------ */
const formInteraction = useForm({
    type: 'appel',
    objet: '',
    notes: '',
    accord_convenu: '',
    date_relance_prevue: '',
});

const panneauOuvert = ref(false);
const detailsOuverts = ref(false);
const confirmation = ref(false);

// Les détails s'ouvrent seuls si une erreur serveur les concerne
const detailsVisibles = computed(
    () => detailsOuverts.value || Boolean(formInteraction.errors.notes || formInteraction.errors.accord_convenu)
);

const basculerPanneau = async () => {
    panneauOuvert.value = !panneauOuvert.value;
    if (panneauOuvert.value) {
        confirmation.value = false;
        await nextTick();
        document.getElementById('objet-interaction')?.focus({ preventScroll: true });
    }
};

const enregistrerInteraction = () => {
    formInteraction.post(route('charge_client.vehicules.interactions.store', props.vehicule.id), {
        preserveScroll: true,
        onSuccess: () => {
            formInteraction.reset();
            panneauOuvert.value = false;
            detailsOuverts.value = false;
            confirmation.value = true;
            setTimeout(() => (confirmation.value = false), 4000);
        },
    });
};
</script>

<template>
    <Head title="Suivi des relances — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <VehiculeEntete
                :vehicule="vehicule"
                :href="route('charge_client.vehicules.show', vehicule.id)"
                libelle-retour="Retour au véhicule"
                section="Suivi des relances"
            />
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">

                <!-- OÙ EN EST-ON ? Un seul bandeau -->
                <section
                    class="grid grid-cols-1 divide-y divide-gray-200 overflow-hidden rounded-2xl border border-gray-200 bg-[#F8FAFC] sm:grid-cols-2 sm:divide-x sm:divide-y-0"
                    aria-label="Où en est le suivi"
                >
                    <div class="p-5">
                        <p class="text-xs font-semibold text-[#8A8D8F]">Prochaine relance</p>
                        <template v-if="relancePrevue">
                            <p class="mt-1 text-base font-black text-[#0B0F19]">{{ formatDateLongue(relancePrevue.date) }}</p>
                            <p
                                class="mt-1 flex items-center gap-1.5 text-xs font-bold"
                                :class="relancePrevue.urgent ? 'text-[#E11D48]' : 'text-[#8A8D8F]'"
                            >
                                <i :class="relancePrevue.icone" class="text-[11px]"></i>
                                {{ relancePrevue.libelle }}
                            </p>
                        </template>
                        <p v-else class="mt-1 text-sm font-medium text-[#8A8D8F]">Aucune relance planifiée.</p>
                    </div>

                    <div class="p-5">
                        <p class="text-xs font-semibold text-[#8A8D8F]">Dernière interaction</p>
                        <template v-if="derniereInteraction">
                            <p class="mt-1 text-base font-black text-[#0B0F19]">
                                {{ libelleType(derniereInteraction.type) }}, {{ ilYa(new Date(derniereInteraction.created_at)) }}
                            </p>
                            <p class="mt-1 truncate text-xs font-medium text-[#8A8D8F]">
                                {{ derniereInteraction.objet || 'Sans objet' }}
                            </p>
                        </template>
                        <p v-else class="mt-1 text-sm font-medium text-[#8A8D8F]">Client pas encore contacté.</p>
                    </div>
                </section>

                <!-- ÉCHANGES -->
                <section class="space-y-6" aria-labelledby="titre-interactions">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 id="titre-interactions" class="text-lg font-black tracking-tight text-[#0B0F19]">
                                Échanges avec le client
                            </h3>
                            <p class="mt-0.5 text-sm font-medium text-[#8A8D8F]">
                                {{ interactions.length ? pluriel(interactions.length, 'interaction') : 'Aucun échange enregistré' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            :aria-expanded="panneauOuvert"
                            aria-controls="panneau-interaction"
                            @click="basculerPanneau"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-xs font-extrabold transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95"
                            :class="panneauOuvert
                                ? 'border border-gray-200 bg-white text-[#0B0F19] hover:border-[#0B0F19]'
                                : 'bg-[#0B0F19] text-white hover:bg-[#E11D48]'"
                        >
                            <i :class="panneauOuvert ? 'fa-solid fa-xmark' : 'fa-solid fa-plus'" class="text-[11px]"></i>
                            {{ panneauOuvert ? 'Fermer' : 'Noter une interaction' }}
                        </button>
                    </div>

                    <div role="status" aria-live="polite">
                        <p v-if="confirmation" class="flex items-center gap-2 text-sm font-semibold text-emerald-700">
                            <i class="fa-solid fa-circle-check"></i>
                            Interaction enregistrée.
                        </p>
                    </div>

                    <!-- Formulaire : replié tant qu'on n'en a pas besoin -->
                    <div
                        id="panneau-interaction"
                        class="grid transition-[grid-template-rows] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] motion-reduce:transition-none"
                        :class="panneauOuvert ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                        :inert="panneauOuvert ? undefined : ''"
                    >
                        <div class="min-h-0 overflow-hidden">
                            <form
                                @submit.prevent="enregistrerInteraction"
                                class="space-y-6 rounded-2xl border border-gray-200 bg-[#F8FAFC] p-5 sm:p-6"
                            >
                                <fieldset>
                                    <legend class="mb-2.5 text-xs font-semibold text-[#8A8D8F]">Type d'échange</legend>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="t in TYPES"
                                            :key="t.valeur"
                                            type="button"
                                            :aria-pressed="formInteraction.type === t.valeur"
                                            @click="formInteraction.type = t.valeur"
                                            class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-xs font-bold ring-1 ring-inset transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/50 active:scale-95"
                                            :class="formInteraction.type === t.valeur
                                                ? 'bg-[#0B0F19] text-white ring-[#0B0F19]'
                                                : 'bg-white text-[#0B0F19] ring-gray-200 hover:ring-[#0B0F19]'"
                                        >
                                            <i :class="t.icone" class="text-[11px]"></i>
                                            {{ t.label }}
                                        </button>
                                    </div>
                                    <p v-if="formInteraction.errors.type" role="alert" class="mt-1.5 text-xs font-semibold text-[#E11D48]">
                                        {{ formInteraction.errors.type }}
                                    </p>
                                </fieldset>

                                <div>
                                    <label for="objet-interaction" class="mb-2 block text-xs font-semibold text-[#8A8D8F]">
                                        Objet
                                    </label>
                                    <input
                                        id="objet-interaction"
                                        v-model="formInteraction.objet"
                                        type="text"
                                        maxlength="255"
                                        required
                                        placeholder="Ex. : rappel du renouvellement de l'assurance"
                                        class="min-h-11 w-full rounded-xl border border-gray-200 bg-white px-3.5 text-sm font-medium text-[#0B0F19] placeholder:text-gray-400 transition duration-200 focus:border-[#E11D48] focus:outline-none focus:ring-4 focus:ring-[#E11D48]/10"
                                    />
                                    <!-- Suggestions : seulement tant que le champ est vide -->
                                    <div v-if="!formInteraction.objet" class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1.5">
                                        <button
                                            v-for="objet in OBJETS_RAPIDES"
                                            :key="objet"
                                            type="button"
                                            @click="formInteraction.objet = objet"
                                            class="text-xs font-semibold text-[#8A8D8F] underline decoration-gray-300 underline-offset-4 transition duration-200 hover:text-[#0B0F19] hover:decoration-[#0B0F19] focus-visible:outline-none focus-visible:text-[#0B0F19]"
                                        >
                                            {{ objet }}
                                        </button>
                                    </div>
                                    <p v-if="formInteraction.errors.objet" role="alert" class="mt-1.5 text-xs font-semibold text-[#E11D48]">
                                        {{ formInteraction.errors.objet }}
                                    </p>
                                </div>

                                <div>
                                    <label for="date-relance-prevue" class="mb-2 block text-xs font-semibold text-[#8A8D8F]">
                                        Prochaine relance le <span class="font-medium">(facultatif)</span>
                                    </label>
                                    <input
                                        id="date-relance-prevue"
                                        v-model="formInteraction.date_relance_prevue"
                                        type="date"
                                        :min="dateMin"
                                        class="min-h-11 w-full rounded-xl border border-gray-200 bg-white px-3.5 text-sm font-medium text-[#0B0F19] transition duration-200 focus:border-[#E11D48] focus:outline-none focus:ring-4 focus:ring-[#E11D48]/10 sm:w-64"
                                    />
                                    <p v-if="formInteraction.errors.date_relance_prevue" role="alert" class="mt-1.5 text-xs font-semibold text-[#E11D48]">
                                        {{ formInteraction.errors.date_relance_prevue }}
                                    </p>
                                </div>

                                <!-- Détails : notes et accord, à la demande -->
                                <div>
                                    <button
                                        v-if="!detailsVisibles"
                                        type="button"
                                        @click="detailsOuverts = true"
                                        class="inline-flex min-h-11 items-center gap-2 text-xs font-bold text-[#0B0F19] transition duration-200 hover:text-[#E11D48] focus-visible:outline-none focus-visible:text-[#E11D48]"
                                    >
                                        <i class="fa-solid fa-plus text-[10px]"></i>
                                        Ajouter des détails (ce qui a été dit, accord convenu)
                                    </button>

                                    <div v-else class="space-y-5">
                                        <div>
                                            <label for="notes-interaction" class="mb-2 block text-xs font-semibold text-[#8A8D8F]">
                                                Ce qui a été dit
                                            </label>
                                            <textarea
                                                id="notes-interaction"
                                                v-model="formInteraction.notes"
                                                rows="3"
                                                placeholder="Ex. : le client n'avait pas vu l'échéance, il vérifie avec son assureur."
                                                class="w-full resize-none rounded-xl border border-gray-200 bg-white px-3.5 py-3 text-sm font-medium text-[#0B0F19] placeholder:text-gray-400 transition duration-200 focus:border-[#E11D48] focus:outline-none focus:ring-4 focus:ring-[#E11D48]/10"
                                            ></textarea>
                                            <p v-if="formInteraction.errors.notes" role="alert" class="mt-1.5 text-xs font-semibold text-[#E11D48]">
                                                {{ formInteraction.errors.notes }}
                                            </p>
                                        </div>

                                        <div>
                                            <label for="accord-interaction" class="mb-2 block text-xs font-semibold text-[#8A8D8F]">
                                                Accord convenu
                                            </label>
                                            <textarea
                                                id="accord-interaction"
                                                v-model="formInteraction.accord_convenu"
                                                rows="2"
                                                placeholder="Ex. : il apporte l'attestation vendredi."
                                                class="w-full resize-none rounded-xl border border-gray-200 bg-white px-3.5 py-3 text-sm font-medium text-[#0B0F19] placeholder:text-gray-400 transition duration-200 focus:border-[#E11D48] focus:outline-none focus:ring-4 focus:ring-[#E11D48]/10"
                                            ></textarea>
                                            <p v-if="formInteraction.errors.accord_convenu" role="alert" class="mt-1.5 text-xs font-semibold text-[#E11D48]">
                                                {{ formInteraction.errors.accord_convenu }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <button
                                        type="button"
                                        @click="basculerPanneau"
                                        class="inline-flex min-h-11 items-center justify-center rounded-xl px-5 text-xs font-bold text-[#8A8D8F] transition duration-200 hover:text-[#0B0F19] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40"
                                    >
                                        Annuler
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="formInteraction.processing || !formInteraction.objet.trim()"
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#0B0F19] px-6 text-xs font-extrabold text-white transition duration-200 hover:bg-[#E11D48] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <i v-if="formInteraction.processing" class="fa-solid fa-spinner fa-spin text-[11px]"></i>
                                        <i v-else class="fa-solid fa-check text-[11px]"></i>
                                        Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Journal : une frise, sans cartes -->
                    <ol v-if="interactions.length" class="relative ml-4 space-y-8 border-l border-gray-200">
                        <li v-for="(interaction, index) in interactions" :key="interaction.id" class="relative pl-8">
                            <span
                                class="absolute -left-4 top-0 flex h-8 w-8 items-center justify-center rounded-full text-xs ring-1"
                                :class="index === 0 ? 'bg-[#0B0F19] text-white ring-[#0B0F19]' : 'bg-white text-[#0B0F19] ring-gray-200'"
                            >
                                <i :class="iconeType(interaction.type)"></i>
                            </span>

                            <p class="text-sm font-extrabold leading-snug text-[#0B0F19]">
                                {{ interaction.objet || 'Sans objet' }}
                            </p>

                            <p class="mt-0.5 text-xs font-medium text-[#8A8D8F]">
                                {{ libelleType(interaction.type) }},
                                {{ quand(new Date(interaction.created_at)) }}
                                <template v-if="interaction.user?.name">
                                    par <span class="font-bold text-[#0B0F19]">{{ interaction.user.name }}</span>
                                </template>
                            </p>

                            <p v-if="interaction.notes" class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-700">
                                {{ interaction.notes }}
                            </p>

                            <div v-if="interaction.accord_convenu" class="mt-3 border-l-2 border-[#E11D48] pl-3">
                                <p class="text-xs font-bold text-[#0B0F19]">Accord convenu</p>
                                <p class="mt-0.5 whitespace-pre-line text-sm text-gray-700">{{ interaction.accord_convenu }}</p>
                            </div>

                            <p
                                v-if="interaction.date_relance_prevue && lireDate(interaction.date_relance_prevue)"
                                class="mt-3 flex items-center gap-1.5 text-xs font-semibold"
                                :class="index === 0 && relancePrevue?.urgent ? 'text-[#E11D48]' : 'text-[#8A8D8F]'"
                            >
                                <i class="fa-regular fa-calendar text-[11px]"></i>
                                Relance prévue le {{ formatDateLongue(lireDate(interaction.date_relance_prevue)) }}
                            </p>
                        </li>
                    </ol>

                    <!-- État vide : une invitation à agir -->
                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-12 text-center"
                    >
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                            <i class="fa-regular fa-comments"></i>
                        </div>
                        <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucun échange pour l'instant</h4>
                        <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                            Notez chaque appel, message ou visite avec l'accord convenu, pour que toute l'équipe sache où en est le suivi.
                        </p>
                        <button
                            v-if="!panneauOuvert"
                            type="button"
                            @click="basculerPanneau"
                            class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#0B0F19] px-5 text-xs font-extrabold text-white transition duration-200 hover:bg-[#E11D48] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95"
                        >
                            <i class="fa-solid fa-plus text-[11px]"></i>
                            Noter la première interaction
                        </button>
                    </div>
                </section>

            </div>
        </div>
    </AuthenticatedLayout>
</template>