<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
/* 1. Échéances administratives                                         */
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
]);

/* ------------------------------------------------------------------ */
/* 2. Historique des interventions, devis et paiements                  */
/* ------------------------------------------------------------------ */
const interventions = computed(() =>
    [...(props.vehicule.interventions || [])].sort((a, b) => {
        const da = new Date(a.date_reception || 0);
        const db = new Date(b.date_reception || 0);
        return db - da || b.id - a.id;
    })
);

const ouverts = ref(interventions.value.length ? [interventions.value[0].id] : []);

const estOuvert = (id) => ouverts.value.includes(id);
const basculer = (id) => {
    ouverts.value = estOuvert(id) ? ouverts.value.filter((x) => x !== id) : [...ouverts.value, id];
};

const formatDateCourte = (valeur) => {
    const date = valeur ? new Date(valeur) : null;
    return date && !isNaN(date) ? formatDateLongue(date) : 'date non renseignée';
};

const LIBELLES_STATUT = {
    atelier: 'En atelier',
    attente_accord: "En attente d'accord client",
    accepte: 'Devis accepté',
};

const libelleStatut = (statut) => {
    if (!statut) return 'En cours';
    const texte = LIBELLES_STATUT[statut] || statut.replace(/_/g, ' ');
    return texte.charAt(0).toUpperCase() + texte.slice(1);
};

const devisValide = (devis) => Boolean(devis && devis.statut && devis.statut !== 'en_attente');
const estAcceptee = (ligne) => ligne.is_accepted === true || Number(ligne.is_accepted) === 1;

const statutLigne = (devis, ligne) => {
    if (!devisValide(devis)) return 'attente';
    return estAcceptee(ligne) ? 'accepte' : 'refuse';
};

const BADGES_LIGNE = {
    accepte: { classe: 'bg-emerald-50 text-emerald-700 ring-emerald-200', icone: 'fa-solid fa-check', texte: 'Accepté' },
    refuse: { classe: 'bg-[#E11D48]/10 text-[#E11D48] ring-[#E11D48]/25', icone: 'fa-solid fa-xmark', texte: 'Refusé par le client' },
    attente: { classe: 'bg-gray-100 text-gray-600 ring-gray-200', icone: 'fa-solid fa-hourglass-half', texte: 'En attente' },
};

const totalDevis = (devis) => {
    if (!devis) return 0;
    const lignes = devis.lignes || [];
    if (!lignes.length) return nombre(devis.montant_total || devis.total || devis.montant);
    return lignes.reduce((somme, l) => somme + nombre(l.montant_ttc), 0);
};

const totalAccepte = (devis) => {
    if (!devis) return 0;
    return (devis.lignes || []).filter(estAcceptee).reduce((somme, l) => somme + nombre(l.montant_ttc), 0);
};

const resumeMontant = (devis) =>
    devisValide(devis)
        ? { valeur: totalAccepte(devis), legende: 'Total accepté' }
        : { valeur: totalDevis(devis), legende: 'Devis proposé' };

/* --- Utilitaires Paiements (via Facture) --- */
const extrairePaiements = (intervention) => {
    if (!intervention) return [];
    return intervention.facture?.paiements || [];
};

const totalPaye = (intervention) => {
    const paiements = extrairePaiements(intervention);
    return paiements.reduce((somme, p) => somme + nombre(p.montant), 0);
};

const soldeRestant = (intervention) => {
    const du = intervention.devis && devisValide(intervention.devis)
        ? totalAccepte(intervention.devis)
        : 0;
    return Math.max(0, du - totalPaye(intervention));
};

const statutPaiement = (intervention) => {
    if (!intervention.devis || !devisValide(intervention.devis)) {
        return { label: 'En attente devis', classe: 'bg-gray-100 text-gray-600 ring-gray-200' };
    }
    const du = totalAccepte(intervention.devis);
    const paye = totalPaye(intervention);

    if (du === 0) return { label: 'Sans frais', classe: 'bg-gray-100 text-gray-600 ring-gray-200' };
    if (paye >= du) return { label: 'Réglé', classe: 'bg-emerald-50 text-emerald-700 ring-emerald-200' };
    if (paye > 0) return { label: 'Partiellement réglé', classe: 'bg-amber-50 text-amber-700 ring-amber-200' };
    return { label: 'Non réglé', classe: 'bg-[#E11D48]/10 text-[#E11D48] ring-[#E11D48]/25' };
};
</script>

<template>
    <Head title="Détail Véhicule — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 py-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <h2 class="text-xl font-black tracking-tight text-[#0B0F19] sm:text-2xl">
                            {{ vehicule.marque }} {{ vehicule.modele }}
                        </h2>
                        <span class="rounded-lg bg-[#0B0F19] px-2.5 py-1 font-mono text-xs font-bold text-white">
                            {{ vehicule.immatriculation || 'Sans immat.' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm font-medium text-[#8A8D8F]">
                        Propriétaire :
                        <span class="font-bold text-[#0B0F19]">
                            {{ vehicule.client?.nom }} {{ vehicule.client?.prenom }}
                        </span>
                    </p>
                </div>

                <Link
                    :href="route('charge_client.clients.show', vehicule.client_id)"
                    class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl border border-gray-200 bg-white px-4 text-xs font-bold text-[#0B0F19] transition duration-200 hover:border-[#0B0F19] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-95 sm:self-auto"
                >
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                    <span>Retour au client</span>
                </Link>
            </div>
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8">

                <!-- IDENTITÉ DU VÉHICULE -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-label="Identité du véhicule">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-5 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs font-semibold text-[#8A8D8F]">Immatriculation</dt>
                            <dd class="mt-1 font-mono text-sm font-bold text-[#0B0F19]">
                                {{ vehicule.immatriculation || 'Non renseignée' }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold text-[#8A8D8F]">N° de châssis (VIN)</dt>
                            <dd
                                class="mt-1 break-all font-mono text-sm font-bold"
                                :class="vehicule.vin ? 'text-[#0B0F19]' : 'text-[#8A8D8F]'"
                            >
                                {{ vehicule.vin || 'Non renseigné' }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold text-[#8A8D8F]">Propriétaire</dt>
                            <dd class="mt-1 truncate text-sm font-bold text-[#0B0F19]">
                                {{ vehicule.client?.nom }} {{ vehicule.client?.prenom }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-[#8A8D8F]">Téléphone</dt>
                            <dd class="mt-1 text-sm font-bold">
                                <a
                                    v-if="vehicule.client?.telephone"
                                    :href="`tel:${vehicule.client.telephone}`"
                                    class="inline-flex items-center gap-1.5 text-[#0B0F19] transition hover:text-[#E11D48] focus-visible:outline-none focus-visible:text-[#E11D48]"
                                >
                                    <i class="fa-solid fa-phone text-[10px]"></i>
                                    {{ vehicule.client.telephone }}
                                </a>
                                <span v-else class="text-[#8A8D8F]">Non renseigné</span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- 1. ÉCHÉANCES ADMINISTRATIVES -->
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
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. HISTORIQUE DES INTERVENTIONS ET DEVIS -->
                <section class="space-y-4" aria-labelledby="titre-historique">
                    <h3 id="titre-historique" class="flex items-center gap-2.5 text-lg font-black text-[#0B0F19]">
                        <i class="fa-solid fa-clock-rotate-left text-[#E11D48]"></i>
                        <span>Historique des interventions</span>
                        <span
                            v-if="interventions.length"
                            class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-black tabular-nums text-[#0B0F19]"
                        >
                            {{ interventions.length }}
                        </span>
                    </h3>

                    <div v-if="interventions.length" class="space-y-4">
                        <article
                            v-for="(intervention, index) in interventions"
                            :key="intervention.id"
                            class="row-in overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                            :style="{ animationDelay: Math.min(index, 6) * 60 + 'ms' }"
                        >
                            <!-- En-tête cliquable -->
                            <button
                                type="button"
                                :aria-expanded="estOuvert(intervention.id)"
                                :aria-controls="`intervention-${intervention.id}`"
                                @click="basculer(intervention.id)"
                                class="flex w-full items-center gap-4 px-4 py-4 text-left transition-colors duration-200 hover:bg-[#F8FAFC] focus-visible:bg-[#F8FAFC] focus-visible:outline-none sm:px-6"
                            >
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#0B0F19] text-white">
                                    <i class="fa-solid fa-screwdriver-wrench text-sm"></i>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-extrabold text-[#0B0F19]">
                                        Ordre de travail {{ intervention.numero_ot || '#' + intervention.id }}
                                    </p>
                                    <p class="mt-0.5 text-xs font-medium text-[#8A8D8F]">
                                        Réceptionné le {{ formatDateCourte(intervention.date_reception) }}
                                    </p>
                                    <span
                                        class="mt-2 inline-flex rounded-md px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset sm:hidden"
                                        :class="intervention.statut === 'accepte' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-gray-100 text-gray-700 ring-gray-200'"
                                    >
                                        {{ libelleStatut(intervention.statut) }}
                                    </span>
                                </div>

                                <span
                                    class="hidden rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset sm:inline-flex"
                                    :class="intervention.statut === 'accepte' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-gray-100 text-gray-700 ring-gray-200'"
                                >
                                    {{ libelleStatut(intervention.statut) }}
                                </span>

                                <div v-if="intervention.devis" class="hidden text-right md:block">
                                    <p class="text-sm font-black tabular-nums text-[#0B0F19]">
                                        {{ fmt(resumeMontant(intervention.devis).valeur) }} FCFA
                                    </p>
                                    <p class="text-[11px] font-medium text-[#8A8D8F]">
                                        {{ resumeMontant(intervention.devis).legende }}
                                    </p>
                                </div>

                                <i
                                    class="fa-solid fa-chevron-down text-xs text-[#8A8D8F] transition-transform duration-300"
                                    :class="{ 'rotate-180': estOuvert(intervention.id) }"
                                ></i>
                            </button>

                            <!-- Contenu repliable -->
                            <div
                                :id="`intervention-${intervention.id}`"
                                class="grid transition-[grid-template-rows] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] motion-reduce:transition-none"
                                :class="estOuvert(intervention.id) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                                :inert="estOuvert(intervention.id) ? undefined : ''"
                            >
                                <div class="min-h-0 overflow-hidden">
                                    <div class="space-y-6 border-t border-gray-100 px-4 py-5 sm:px-6">

                                        <!-- DEVIS ET DETAILS -->
                                        <template v-if="intervention.devis">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="text-sm font-extrabold text-[#0B0F19]">
                                                    Devis n° {{ intervention.devis.id }}
                                                </p>
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset"
                                                    :class="devisValide(intervention.devis) ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-gray-100 text-gray-600 ring-gray-200'"
                                                >
                                                    <i
                                                        :class="devisValide(intervention.devis) ? 'fa-solid fa-check' : 'fa-solid fa-hourglass-half'"
                                                        class="text-[10px]"
                                                    ></i>
                                                    {{ devisValide(intervention.devis) ? 'Réponse du client enregistrée' : "En attente de la réponse du client" }}
                                                </span>
                                            </div>

                                            <div
                                                v-if="intervention.devis.lignes && intervention.devis.lignes.length"
                                                class="overflow-hidden rounded-xl border border-gray-200"
                                            >
                                                <!-- En-têtes (desktop) -->
                                                <div
                                                    class="hidden grid-cols-[minmax(0,3fr)_56px_minmax(0,1fr)_minmax(0,1.1fr)_minmax(0,1.5fr)] gap-x-4 border-b border-gray-200 bg-[#F8FAFC] px-4 py-2.5 text-xs font-bold text-[#8A8D8F] md:grid"
                                                >
                                                    <span>Désignation</span>
                                                    <span class="text-center">Qté</span>
                                                    <span>P.U. net</span>
                                                    <span class="text-right">Montant TTC</span>
                                                    <span>Réponse du client</span>
                                                </div>

                                                <ul class="divide-y divide-gray-100">
                                                    <li
                                                        v-for="ligne in intervention.devis.lignes"
                                                        :key="ligne.id"
                                                        class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-4 py-3 md:grid-cols-[minmax(0,3fr)_56px_minmax(0,1fr)_minmax(0,1.1fr)_minmax(0,1.5fr)]"
                                                    >
                                                        <!-- Désignation -->
                                                        <div class="order-1 flex min-w-0 items-start gap-2.5">
                                                            <i
                                                                :class="ligne.type === 'piece' ? 'fa-solid fa-gears' : 'fa-solid fa-user-gear'"
                                                                class="mt-0.5 w-4 shrink-0 text-center text-xs text-[#8A8D8F]"
                                                                :title="ligne.type === 'piece' ? 'Pièce' : 'Main d\'œuvre'"
                                                            ></i>
                                                            <div class="min-w-0">
                                                                <p
                                                                    class="text-sm font-bold text-[#0B0F19]"
                                                                    :class="{ 'opacity-60': statutLigne(intervention.devis, ligne) === 'refuse' }"
                                                                >
                                                                    {{ ligne.designation }}
                                                                </p>
                                                                <p v-if="ligne.reference_piece" class="mt-0.5 font-mono text-[11px] text-[#8A8D8F]">
                                                                    Réf. {{ ligne.reference_piece }}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <!-- Montant -->
                                                        <p
                                                            class="order-2 text-right text-sm font-black tabular-nums md:order-4"
                                                            :class="statutLigne(intervention.devis, ligne) === 'refuse' ? 'text-[#8A8D8F] line-through' : 'text-[#0B0F19]'"
                                                        >
                                                            {{ fmt(ligne.montant_ttc) }} FCFA
                                                        </p>

                                                        <!-- Détail quantité (mobile) -->
                                                        <p class="order-3 col-span-2 text-xs font-medium text-[#8A8D8F] md:hidden">
                                                            {{ ligne.quantite }} × {{ fmt(ligne.pu_net) }} FCFA net
                                                        </p>

                                                        <!-- Quantité et prix unitaire (desktop) -->
                                                        <p class="hidden text-center text-sm text-gray-600 md:order-2 md:block">
                                                            {{ ligne.quantite }}
                                                        </p>
                                                        <p class="hidden text-sm tabular-nums text-gray-600 md:order-3 md:block">
                                                            {{ fmt(ligne.pu_net) }} FCFA
                                                        </p>

                                                        <!-- Réponse du client -->
                                                        <div class="order-4 col-span-2 md:order-5 md:col-span-1">
                                                            <span
                                                                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-bold ring-1 ring-inset"
                                                                :class="BADGES_LIGNE[statutLigne(intervention.devis, ligne)].classe"
                                                            >
                                                                <i
                                                                    :class="BADGES_LIGNE[statutLigne(intervention.devis, ligne)].icone"
                                                                    class="text-[10px]"
                                                                ></i>
                                                                {{ BADGES_LIGNE[statutLigne(intervention.devis, ligne)].texte }}
                                                            </span>
                                                        </div>
                                                    </li>
                                                </ul>

                                                <!-- Totaux -->
                                                <div class="space-y-1 border-t border-gray-200 bg-[#F8FAFC] px-4 py-3 text-right">
                                                    <p class="text-xs font-medium text-[#8A8D8F]">
                                                        Total proposé :
                                                        <span class="font-bold tabular-nums text-[#0B0F19]">
                                                            {{ fmt(totalDevis(intervention.devis)) }} FCFA
                                                        </span>
                                                    </p>
                                                    <p v-if="devisValide(intervention.devis)" class="text-sm font-extrabold text-[#0B0F19]">
                                                        Total accepté :
                                                        <span class="tabular-nums text-[#E11D48]">
                                                            {{ fmt(totalAccepte(intervention.devis)) }} FCFA
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>

                                            <p v-else class="flex items-center gap-2 rounded-xl bg-[#F8FAFC] px-4 py-3 text-xs font-medium text-[#8A8D8F]">
                                                <i class="fa-solid fa-file-circle-xmark"></i>
                                                Aucune ligne enregistrée pour ce devis.
                                            </p>
                                        </template>

                                        <p v-else class="flex items-center gap-2 rounded-xl bg-[#F8FAFC] px-4 py-3 text-xs font-medium text-[#8A8D8F]">
                                            <i class="fa-solid fa-file-circle-xmark"></i>
                                            Aucun devis rattaché à cette intervention.
                                        </p>

                                        <!-- SUIVI DES PAIEMENTS -->
                                        <div v-if="devisValide(intervention.devis)" class="space-y-3 pt-2">
                                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-4">
                                                <h4 class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-[#0B0F19]">
                                                    <i class="fa-solid fa-receipt text-[#E11D48]"></i>
                                                    <span>Suivi des règlements</span>
                                                </h4>
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset"
                                                    :class="statutPaiement(intervention).classe"
                                                >
                                                    {{ statutPaiement(intervention).label }}
                                                </span>
                                            </div>

                                            <!-- Synthèse encaissement -->
                                            <div class="grid grid-cols-1 gap-3 rounded-xl bg-[#F8FAFC] p-3.5 sm:grid-cols-3">
                                                <div>
                                                    <p class="text-[11px] font-semibold text-[#8A8D8F]">Total réglé</p>
                                                    <p class="mt-0.5 text-sm font-black tabular-nums text-emerald-600">
                                                        {{ fmt(totalPaye(intervention)) }} FCFA
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] font-semibold text-[#8A8D8F]">Reste à payer</p>
                                                    <p
                                                        class="mt-0.5 text-sm font-black tabular-nums"
                                                        :class="soldeRestant(intervention) > 0 ? 'text-[#E11D48]' : 'text-gray-700'"
                                                    >
                                                        {{ fmt(soldeRestant(intervention)) }} FCFA
                                                    </p>
                                                </div>
                                                <div class="flex flex-col justify-center">
                                                    <div class="flex items-center justify-between text-[11px] font-semibold text-[#8A8D8F]">
                                                        <span>Progression</span>
                                                        <span>
                                                            {{ Math.min(100, Math.round((totalPaye(intervention) / (totalAccepte(intervention.devis) || 1)) * 100)) }}%
                                                        </span>
                                                    </div>
                                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                                        <div
                                                            class="h-full bg-emerald-500 transition-all duration-300"
                                                            :style="{ width: Math.min(100, (totalPaye(intervention) / (totalAccepte(intervention.devis) || 1)) * 100) + '%' }"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Liste des paiements -->
                                            <div v-if="extrairePaiements(intervention).length" class="overflow-hidden rounded-xl border border-gray-200">
                                                <table class="w-full text-left text-xs">
                                                    <thead class="border-b border-gray-200 bg-[#F8FAFC] font-bold text-[#8A8D8F]">
                                                        <tr>
                                                            <th class="px-3.5 py-2">Date</th>
                                                            <th class="px-3.5 py-2">Mode</th>
                                                            <th class="hidden px-3.5 py-2 sm:table-cell">Notes</th>
                                                            <th class="px-3.5 py-2 text-right">Montant</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 font-medium text-[#0B0F19]">
                                                        <tr v-for="p in extrairePaiements(intervention)" :key="p.id" class="hover:bg-gray-50/50">
                                                            <td class="px-3.5 py-2.5">
                                                                {{ formatDateCourte(p.date_paiement || p.created_at) }}
                                                            </td>
                                                            <td class="px-3.5 py-2.5 capitalize">
                                                                {{ p.mode_paiement || 'Espèces' }}
                                                            </td>
                                                            <td class="hidden px-3.5 py-2.5 text-[11px] text-[#8A8D8F] sm:table-cell">
                                                                {{ p.notes || '—' }}
                                                            </td>
                                                            <td class="px-3.5 py-2.5 text-right font-bold tabular-nums text-emerald-600">
                                                                +{{ fmt(p.montant) }} FCFA
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <p v-else class="text-xs italic font-medium text-[#8A8D8F]">
                                                Aucun règlement enregistré sur la facture de cette intervention.
                                            </p>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- État vide -->
                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-14 text-center"
                    >
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                        <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucune intervention</h4>
                        <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                            Les interventions et devis de ce véhicule apparaîtront ici dès sa première réception à l'atelier.
                        </p>
                    </div>
                </section>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .row-in {
        animation: row-in 420ms cubic-bezier(0.16, 1, 0.3, 1) both;
    }
}

@keyframes row-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
</style>