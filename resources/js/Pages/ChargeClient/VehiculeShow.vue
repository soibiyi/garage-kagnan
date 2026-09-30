<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VehiculeEntete from '@/Components/VehiculeEntete.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    vehicule: Object,
});

/* ------------------------------------------------------------------ */
/* Utilitaires                                                         */
/* ------------------------------------------------------------------ */
const JOURS_ALERTE = 10;

const lireDate = (valeur) => {
    if (!valeur) return null;
    const [a, m, j] = String(valeur).slice(0, 10).split('-').map(Number);
    if (!a || !m || !j) return null;
    return new Date(a, m - 1, j);
};

const formatDateLongue = (date) =>
    date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const nombre = (n) => Number(n || 0);
const fmt = (n) => Math.round(nombre(n)).toLocaleString('fr-FR');
const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

/* ------------------------------------------------------------------ */
/* Échéances administratives                                            */
/* ------------------------------------------------------------------ */
const STYLES_ECHEANCE = {
    expiree: {
        cadre: 'border-[#E11D48]/40 bg-[#E11D48]/5',
        pastille: 'bg-[#E11D48] text-white',
        badge: 'bg-[#E11D48]/10 text-[#E11D48] ring-[#E11D48]/25',
        icone: 'fa-solid fa-circle-xmark',
    },
    bientot: {
        cadre: 'border-[#E11D48]/30 bg-white',
        pastille: 'bg-[#E11D48]/10 text-[#E11D48]',
        badge: 'bg-[#E11D48]/10 text-[#E11D48] ring-[#E11D48]/25',
        icone: 'fa-solid fa-clock',
    },
    valide: {
        cadre: 'border-gray-200 bg-white',
        pastille: 'bg-emerald-50 text-emerald-700',
        badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        icone: 'fa-solid fa-circle-check',
    },
    inconnu: {
        cadre: 'border-dashed border-gray-300 bg-[#F8FAFC]',
        pastille: 'bg-white text-[#8A8D8F] ring-1 ring-gray-200',
        badge: 'bg-gray-100 text-gray-600 ring-gray-200',
        icone: 'fa-solid fa-circle-question',
    },
};

const statutEcheance = (valeur) => {
    const date = lireDate(valeur);

    if (!date) {
        return {
            style: STYLES_ECHEANCE.inconnu,
            date: 'Non renseignée',
            libelle: 'À renseigner',
            detail: null,
        };
    }

    const aujourdhui = new Date();
    aujourdhui.setHours(0, 0, 0, 0);
    const jours = Math.round((date - aujourdhui) / 86400000);

    if (jours < 0) {
        return {
            style: STYLES_ECHEANCE.expiree,
            date: formatDateLongue(date),
            libelle: 'Expirée',
            detail: `Depuis ${pluriel(Math.abs(jours), 'jour')}`,
        };
    }

    if (jours <= JOURS_ALERTE) {
        return {
            style: STYLES_ECHEANCE.bientot,
            date: formatDateLongue(date),
            libelle: 'Expire bientôt',
            detail: jours === 0 ? "C'est aujourd'hui" : `Dans ${pluriel(jours, 'jour')}`,
        };
    }

    return {
        style: STYLES_ECHEANCE.valide,
        date: formatDateLongue(date),
        libelle: 'Valide',
        detail: `Encore ${pluriel(jours, 'jour')}`,
    };
};

const echeances = computed(() => [
    {
        cle: 'assurance',
        label: 'Assurance',
        icone: 'fa-solid fa-shield-halved',
        statut: statutEcheance(props.vehicule.expiration_assurance),
    },
    {
        cle: 'sicta',
        label: 'Visite technique (SICTA)',
        icone: 'fa-solid fa-clipboard-check',
        statut: statutEcheance(props.vehicule.expiration_sicta),
    },
].map((doc) => ({
    ...doc,
    urgent: doc.statut.style === STYLES_ECHEANCE.expiree || doc.statut.style === STYLES_ECHEANCE.bientot,
})));

/* ------------------------------------------------------------------ */
/* Données servant aux résumés des cartes                               */
/* ------------------------------------------------------------------ */
const debutJour = (valeur) => {
    const d = new Date(valeur);
    d.setHours(0, 0, 0, 0);
    return d;
};

// > 0 : dans le passé, < 0 : dans le futur
const ecartJours = (date) => Math.round((debutJour(new Date()) - debutJour(date)) / 86400000);

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
    return { date, enRetard: -ecartJours(date) < 0 };
});

const interventions = computed(() => props.vehicule.interventions || []);

const devisValide = (devis) => Boolean(devis && devis.statut && devis.statut !== 'en_attente');
const estAcceptee = (ligne) => ligne.is_accepted === true || Number(ligne.is_accepted) === 1;

const totalAccepte = (devis) => {
    if (!devis) return 0;
    return (devis.lignes || []).filter(estAcceptee).reduce((somme, l) => somme + nombre(l.montant_ttc), 0);
};

const totalPaye = (intervention) =>
    (intervention.facture?.paiements || []).reduce((somme, p) => somme + nombre(p.montant), 0);

const soldeRestant = (intervention) => {
    const du = intervention.devis && devisValide(intervention.devis) ? totalAccepte(intervention.devis) : 0;
    return Math.max(0, du - totalPaye(intervention));
};

const nbUrgents = computed(() => echeances.value.filter((e) => e.urgent).length);
const totalImpaye = computed(() => interventions.value.reduce((s, i) => s + soldeRestant(i), 0));

/* ------------------------------------------------------------------ */
/* Les 3 cartes : chacune mène à sa page                                */
/* ------------------------------------------------------------------ */
const cartes = computed(() => [
    {
        cle: 'infos',
        titre: 'Infos du véhicule',
        icone: 'fa-solid fa-car',
        href: route('charge_client.vehicules.infos', props.vehicule.id),
        resume: props.vehicule.immatriculation || 'Sans immatriculation',
        alerte: null,
    },
    {
        cle: 'relances',
        titre: 'Suivi des relances',
        icone: 'fa-solid fa-comments',
        href: route('charge_client.vehicules.relances', props.vehicule.id),
        resume: interactions.value.length
            ? pluriel(interactions.value.length, 'interaction')
            : 'Aucune interaction',
        alerte: nbUrgents.value
            ? `${pluriel(nbUrgents.value, 'échéance')} à traiter`
            : relancePrevue.value?.enRetard
                ? 'Relance en retard'
                : null,
    },
    {
        cle: 'interventions',
        titre: 'Interventions',
        icone: 'fa-solid fa-screwdriver-wrench',
        href: route('charge_client.vehicules.interventions', props.vehicule.id),
        resume: interventions.value.length
            ? pluriel(interventions.value.length, 'intervention')
            : 'Aucune intervention',
        alerte: totalImpaye.value > 0 ? `${fmt(totalImpaye.value)} FCFA à encaisser` : null,
    },
]);
</script>

<template>
    <Head title="Détail Véhicule — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <VehiculeEntete
                :vehicule="vehicule"
                :href="route('charge_client.clients.show', vehicule.client_id)"
                libelle-retour="Retour au client"
            />
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8">

                <!-- LES 3 CARTES -->
                <nav aria-label="Sections du véhicule">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <Link
                            v-for="carte in cartes"
                            :key="carte.cle"
                            :href="carte.href"
                            class="group flex min-h-24 items-start gap-4 rounded-2xl border border-gray-200 bg-white p-4 text-left text-[#0B0F19] transition duration-200 hover:border-[#0B0F19] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-[0.98] sm:p-5"
                        >
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#E11D48]">
                                <i :class="carte.icone"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-extrabold">{{ carte.titre }}</p>
                                <p class="mt-0.5 truncate text-xs font-medium text-[#8A8D8F]">{{ carte.resume }}</p>
                                <span
                                    v-if="carte.alerte"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-[#E11D48] px-2 py-0.5 text-[11px] font-bold text-white"
                                >
                                    <i class="fa-solid fa-circle-exclamation text-[10px]"></i>
                                    {{ carte.alerte }}
                                </span>
                            </div>

                            <i class="fa-solid fa-arrow-right mt-1 text-xs text-[#8A8D8F] transition-transform duration-200 group-hover:translate-x-0.5"></i>
                        </Link>
                    </div>
                </nav>

                <!-- ÉCHÉANCES ADMINISTRATIVES -->
                <section class="space-y-4" aria-labelledby="titre-echeances">
                    <h3 id="titre-echeances" class="flex items-center gap-2.5 text-lg font-black text-[#0B0F19]">
                        <i class="fa-solid fa-shield-halved text-[#E11D48]"></i>
                        <span>Assurance et visite technique</span>
                    </h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div
                            v-for="doc in echeances"
                            :key="doc.cle"
                            class="flex items-start gap-4 rounded-2xl border p-5 transition-shadow duration-200 hover:shadow-md"
                            :class="doc.statut.style.cadre"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                                :class="doc.statut.style.pastille"
                            >
                                <i :class="doc.icone"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-[#8A8D8F]">{{ doc.label }}</p>
                                <p
                                    class="mt-0.5 text-base font-black"
                                    :class="doc.statut.libelle === 'À renseigner' ? 'text-[#8A8D8F]' : 'text-[#0B0F19]'"
                                >
                                    {{ doc.statut.date }}
                                </p>

                                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                        :class="doc.statut.style.badge"
                                    >
                                        <i :class="doc.statut.style.icone" class="text-[11px]"></i>
                                        {{ doc.statut.libelle }}
                                    </span>
                                    <span v-if="doc.statut.detail" class="text-xs font-medium text-[#8A8D8F]">
                                        {{ doc.statut.detail }}
                                    </span>
                                </div>

                                <!-- Où en est-on ? Relance prévue -->
                                <div class="mt-4 border-t border-gray-200/70 pt-3">
                                    <p v-if="relancePrevue" class="text-xs font-medium text-[#8A8D8F]">
                                        <i class="fa-solid fa-calendar-check mr-1 text-[10px]"></i>
                                        Relance prévue le
                                        <span class="font-bold text-[#0B0F19]">{{ formatDateLongue(relancePrevue.date) }}</span>
                                    </p>
                                    <p
                                        v-else
                                        class="text-xs font-semibold"
                                        :class="doc.urgent ? 'text-[#E11D48]' : 'text-[#8A8D8F]'"
                                    >
                                        <i
                                            :class="doc.urgent ? 'fa-solid fa-bell' : 'fa-regular fa-bell'"
                                            class="mr-1 text-[10px]"
                                        ></i>
                                        {{ doc.urgent ? 'À relancer : aucune relance prévue' : 'Aucune relance prévue' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </AuthenticatedLayout>
</template>