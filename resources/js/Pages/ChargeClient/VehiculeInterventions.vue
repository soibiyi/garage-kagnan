<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VehiculeEntete from '@/Components/VehiculeEntete.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { FAMILLES, FAMILLE_PAR_DEFAUT } from '@/constants/familles.js';

const props = defineProps({
    vehicule: Object,
});

/* ------------------------------------------------------------------ */
/* Utilitaires de formatage                                            */
/* ------------------------------------------------------------------ */
const formatDateLongue = (date) =>
    date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const formatDateCourte = (valeur) => {
    if (!valeur) return 'date non renseignée';
    const date = new Date(valeur);
    return !isNaN(date) ? formatDateLongue(date) : 'date non renseignée';
};

const lireDate = (valeur) => {
    if (!valeur) return null;
    const [a, m, j] = String(valeur).slice(0, 10).split('-').map(Number);
    if (!a || !m || !j) return null;
    return new Date(a, m - 1, j);
};

const debutJour = (valeur) => {
    const d = new Date(valeur);
    d.setHours(0, 0, 0, 0);
    return d;
};

const normaliser = (texte) =>
    String(texte || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

const nombre = (n) => Number(n || 0);
const fmt = (n) => Math.round(nombre(n)).toLocaleString('fr-FR');
const fcfa = (n) => `${fmt(n)} FCFA`;

/* ------------------------------------------------------------------ */
/* Règles métier existantes (inchangées)                               */
/* ------------------------------------------------------------------ */
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

const STYLES_STATUT = {
    accepte: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    attente_accord: 'bg-amber-50 text-amber-700 ring-amber-200',
    atelier: 'bg-slate-100 text-slate-700 ring-slate-200',
};
const styleStatut = (cle) => STYLES_STATUT[cle] || 'bg-gray-100 text-gray-700 ring-gray-200';

const devisValide = (devis) => Boolean(devis && devis.statut && devis.statut !== 'en_attente');
const estAcceptee = (ligne) => ligne.is_accepted === true || Number(ligne.is_accepted) === 1;

const grouperLignesParFamille = (lignes) => {
    if (!lignes || lignes.length === 0) return [];

    const groupes = {};
    lignes.forEach((ligne) => {
        const familleNom = ligne.famille || FAMILLE_PAR_DEFAUT;
        if (!groupes[familleNom]) groupes[familleNom] = [];
        groupes[familleNom].push(ligne);
    });

    const ordreFamilles = [...Object.keys(FAMILLES), FAMILLE_PAR_DEFAUT];
    const resultat = [];

    const ajouterGroupe = (familleNom) => {
        if (groupes[familleNom] && groupes[familleNom].length > 0) {
            resultat.push({
                famille: familleNom,
                lignes: groupes[familleNom],
                sousTotalHt: groupes[familleNom].reduce((acc, l) => acc + nombre(l.montant_ht), 0),
            });
        }
    };

    ordreFamilles.forEach(ajouterGroupe);
    Object.keys(groupes).forEach((nom) => {
        if (!ordreFamilles.includes(nom)) ajouterGroupe(nom);
    });

    return resultat;
};

// Si devis validé, seules les lignes acceptées comptent pour le total à payer
const calculerTotaux = (devis) => {
    if (!devis || !devis.lignes) return { ht: 0, remises: 0, tva: 0, ttcBrut: 0, petiteFourniture: 0, ttcFinal: 0 };

    const lignesCibles = devisValide(devis) ? devis.lignes.filter(estAcceptee) : devis.lignes;

    const ht = lignesCibles.reduce((acc, l) => acc + nombre(l.montant_ht), 0);
    const remises = lignesCibles.reduce((acc, l) => acc + nombre(l.remise), 0);
    const ttcBrut = lignesCibles.reduce((acc, l) => acc + nombre(l.montant_ttc), 0);
    const tva = ttcBrut - ht;
    const petiteFourniture = Math.round(ttcBrut * 0.03);
    const ttcFinal = ttcBrut + petiteFourniture;

    return { ht, remises, tva, ttcBrut, petiteFourniture, ttcFinal };
};

const extrairePaiements = (intervention) => intervention?.facture?.paiements || [];
const totalPaye = (intervention) =>
    extrairePaiements(intervention).reduce((somme, p) => somme + nombre(p.montant), 0);

const numeroDevis = (devis) => {
    const d = new Date(devis.created_at);
    return `D/1/ADMI/${devis.id}/${isNaN(d) ? '' : d.getFullYear()}`;
};

/* ------------------------------------------------------------------ */
/* Règlement                                                           */
/* ------------------------------------------------------------------ */
const REGLEMENTS = {
    solde: { label: 'Soldé', icone: 'fa-solid fa-circle-check', classes: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    partiel: { label: 'Règlement partiel', icone: 'fa-solid fa-circle-half-stroke', classes: 'bg-amber-50 text-amber-700 ring-amber-200' },
    impaye: { label: 'Non réglé', icone: 'fa-solid fa-circle-exclamation', classes: 'bg-rose-50 text-rose-700 ring-rose-200' },
    attente: { label: 'Devis en attente', icone: 'fa-regular fa-clock', classes: 'bg-slate-100 text-slate-600 ring-slate-200' },
    sans_devis: { label: 'Sans devis', icone: 'fa-solid fa-file-circle-xmark', classes: 'bg-slate-100 text-slate-500 ring-slate-200' },
    sans_montant: { label: 'Aucun montant', icone: 'fa-solid fa-minus', classes: 'bg-slate-100 text-slate-500 ring-slate-200' },
};

/* ------------------------------------------------------------------ */
/* Interventions enrichies (calculées une seule fois)                  */
/* ------------------------------------------------------------------ */
const interventions = computed(() =>
    [...(props.vehicule.interventions || [])].sort((a, b) => {
        const da = new Date(a.date_reception || 0);
        const db = new Date(b.date_reception || 0);
        return db - da || b.id - a.id;
    }),
);

const enrichies = computed(() =>
    interventions.value.map((intervention) => {
        const devis = intervention.devis;
        const valide = devisValide(devis);
        const totaux = calculerTotaux(devis);
        const paye = totalPaye(intervention);
        const reste = valide ? Math.max(0, totaux.ttcFinal - paye) : 0;

        let reglement;
        if (!devis) reglement = 'sans_devis';
        else if (!valide) reglement = 'attente';
        else if (totaux.ttcFinal === 0) reglement = 'sans_montant';
        else if (reste === 0) reglement = 'solde';
        else if (paye > 0) reglement = 'partiel';
        else reglement = 'impaye';

        const date = intervention.date_reception ? new Date(intervention.date_reception) : null;
        const numero = intervention.numero_ot || `#${intervention.id}`;

        return {
            id: intervention.id,
            intervention,
            numero,
            date: date && !isNaN(date) ? date : null,
            cleStatut: intervention.statut || 'en_cours',
            valide,
            totaux,
            paye,
            reste,
            pct: totaux.ttcFinal > 0 ? Math.min(100, Math.round((paye * 100) / totaux.ttcFinal)) : 0,
            reglement,
            recherche: normaliser(
                [numero, intervention.numero_ot, intervention.id, devis ? numeroDevis(devis) : ''].join(' '),
            ),
        };
    }),
);

/* ------------------------------------------------------------------ */
/* Recherche et filtres                                                */
/* ------------------------------------------------------------------ */
const recherche = ref('');
const filtreStatut = ref('tous');
const filtreReglement = ref('tous');
const periode = ref('tout');
const dateDebut = ref('');
const dateFin = ref('');

const afficherFiltres = computed(() => enrichies.value.length >= 4);

const statutsPresents = computed(() => {
    const cles = [...new Set(enrichies.value.map((e) => e.cleStatut))];
    return cles.map((cle) => ({ valeur: cle, label: libelleStatut(cle === 'en_cours' ? null : cle) }));
});

const FILTRES_REGLEMENT = [
    { valeur: 'tous', label: 'Tous' },
    { valeur: 'solde', label: 'Soldés' },
    { valeur: 'a_regler', label: 'Reste à payer' },
];

const PERIODES = [
    { valeur: 'tout', label: 'Tout' },
    { valeur: '30j', label: '30 jours' },
    { valeur: '12m', label: '12 mois' },
    { valeur: 'perso', label: 'Personnalisé' },
];

const bornes = computed(() => {
    const auj = debutJour(new Date());
    if (periode.value === '30j') {
        const d = new Date(auj);
        d.setDate(d.getDate() - 29);
        return { debut: d, fin: null };
    }
    if (periode.value === '12m') {
        const d = new Date(auj);
        d.setFullYear(d.getFullYear() - 1);
        return { debut: d, fin: null };
    }
    if (periode.value === 'perso') {
        const fin = lireDate(dateFin.value);
        if (fin) fin.setHours(23, 59, 59, 999);
        return { debut: lireDate(dateDebut.value), fin };
    }
    return { debut: null, fin: null };
});

const periodeInvalide = computed(() => {
    const { debut, fin } = bornes.value;
    return periode.value === 'perso' && debut && fin && debut > fin;
});

const dansPeriode = (date) => {
    if (periode.value === 'tout') return true;
    if (!date) return false;
    const { debut, fin } = bornes.value;
    if (debut && date < debut) return false;
    if (fin && date > fin) return false;
    return true;
};

const filtresActifs = computed(
    () =>
        recherche.value.trim() !== '' ||
        filtreStatut.value !== 'tous' ||
        filtreReglement.value !== 'tous' ||
        periode.value !== 'tout',
);

const reinitialiser = () => {
    recherche.value = '';
    filtreStatut.value = 'tous';
    filtreReglement.value = 'tous';
    periode.value = 'tout';
    dateDebut.value = '';
    dateFin.value = '';
};

const filtrees = computed(() => {
    const q = normaliser(recherche.value).trim();

    return enrichies.value.filter((e) => {
        if (filtreStatut.value !== 'tous' && e.cleStatut !== filtreStatut.value) return false;
        if (filtreReglement.value === 'solde' && e.reglement !== 'solde') return false;
        if (filtreReglement.value === 'a_regler' && !['partiel', 'impaye'].includes(e.reglement)) return false;
        if (!dansPeriode(e.date)) return false;
        return !q || e.recherche.includes(q);
    });
});

/* ------------------------------------------------------------------ */
/* Bandeau de chiffres (sur la sélection filtrée)                      */
/* ------------------------------------------------------------------ */
const kpi = computed(() => {
    const liste = filtrees.value;
    const validees = liste.filter((e) => e.valide);

    return {
        total: liste.length,
        enAttente: liste.filter((e) => e.reglement === 'attente' || e.reglement === 'sans_devis').length,
        facture: validees.reduce((s, e) => s + e.totaux.ttcFinal, 0),
        regle: validees.reduce((s, e) => s + e.paye, 0),
        reste: validees.reduce((s, e) => s + e.reste, 0),
    };
});

/* ------------------------------------------------------------------ */
/* Modal de détail (fiche devis / facture)                             */
/* ------------------------------------------------------------------ */
const ouvertId = ref(null);
const boutonFermer = ref(null);

const modal = computed(() => enrichies.value.find((e) => e.id === ouvertId.value) || null);
const groupesModal = computed(() => grouperLignesParFamille(modal.value?.intervention.devis?.lignes));

const ouvrir = async (id) => {
    ouvertId.value = id;
    await nextTick();
    boutonFermer.value?.focus();
};

const fermer = () => {
    ouvertId.value = null;
};

watch(modal, (ouvert) => {
    document.body.style.overflow = ouvert ? 'hidden' : '';
    // Permet au CSS d'impression de ne garder que la fiche ouverte
    document.body.classList.toggle('impression-modal', Boolean(ouvert));
});

const surTouche = (e) => {
    if (e.key === 'Escape' && modal.value) fermer();
};

onMounted(() => window.addEventListener('keydown', surTouche));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', surTouche);
    document.body.style.overflow = '';
    document.body.classList.remove('impression-modal');
});

const imprimerFiche = () => window.print();
</script>

<template>
    <Head title="Interventions — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <VehiculeEntete
                :vehicule="vehicule"
                :href="route('charge_client.vehicules.show', vehicule.id)"
                libelle-retour="Retour au véhicule"
                section="Interventions"
            />
        </template>

        <div class="min-h-screen bg-white py-6 sm:py-10">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

                <h3 class="flex items-center gap-2.5 text-lg font-black text-[#0B0F19]">
                    <i class="fa-solid fa-clock-rotate-left text-[#E11D48]"></i>
                    <span>Historique des interventions</span>
                    <span
                        v-if="interventions.length"
                        class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-black tabular-nums text-[#0B0F19]"
                    >
                        {{ interventions.length }}
                    </span>
                </h3>

                <!-- ============ ÉTAT VIDE GLOBAL ============ -->
                <div
                    v-if="!interventions.length"
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

                <template v-else>

                    <!-- ============ BANDEAU DE CHIFFRES ============ -->
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4">
                            <p class="text-[11px] font-semibold text-[#8A8D8F]">Interventions</p>
                            <p class="mt-1 text-2xl font-black text-[#0B0F19]">{{ kpi.total }}</p>
                            <p class="mt-0.5 text-[11px] font-medium text-[#8A8D8F]">dont {{ kpi.enAttente }} sans devis validé</p>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4">
                            <p class="text-[11px] font-semibold text-[#8A8D8F]">Total à payer</p>
                            <p class="mt-1 text-lg font-black text-[#0B0F19] sm:text-xl">{{ fcfa(kpi.facture) }}</p>
                            <p class="mt-0.5 text-[11px] font-medium text-[#8A8D8F]">devis validés, TTC</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-[11px] font-semibold text-emerald-700">Déjà réglé</p>
                            <p class="mt-1 text-lg font-black text-emerald-800 sm:text-xl">{{ fcfa(kpi.regle) }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#0B0F19] bg-[#0B0F19] p-4">
                            <p class="text-[11px] font-semibold text-gray-400">Reste à payer</p>
                            <p class="mt-1 text-lg font-black text-white sm:text-xl">{{ fcfa(kpi.reste) }}</p>
                        </div>
                    </div>

                    <!-- ============ RECHERCHE + FILTRES ============ -->
                    <div v-if="afficherFiltres" class="space-y-3 rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input
                                v-model="recherche"
                                type="text"
                                placeholder="Rechercher par n° d'OT ou n° de devis..."
                                class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-10 text-xs font-medium text-[#0B0F19] transition focus:border-[#0B0F19] focus:outline-none focus:ring-1 focus:ring-[#0B0F19]"
                            />
                            <button
                                v-if="recherche"
                                type="button"
                                aria-label="Effacer la recherche"
                                @click="recherche = ''"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 transition hover:text-gray-600"
                            >
                                <i class="fa-solid fa-circle-xmark text-xs"></i>
                            </button>
                        </div>

                        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Statut de l'intervention">
                                <button
                                    type="button"
                                    :aria-pressed="filtreStatut === 'tous'"
                                    @click="filtreStatut = 'tous'"
                                    class="inline-flex min-h-[36px] shrink-0 items-center rounded-full border px-3.5 text-xs font-bold transition active:scale-95"
                                    :class="filtreStatut === 'tous' ? 'border-[#0B0F19] bg-[#0B0F19] text-white' : 'border-gray-200 bg-white text-[#0B0F19] hover:bg-gray-100'"
                                >
                                    Tous
                                </button>
                                <button
                                    v-for="s in statutsPresents"
                                    :key="s.valeur"
                                    type="button"
                                    :aria-pressed="filtreStatut === s.valeur"
                                    @click="filtreStatut = s.valeur"
                                    class="inline-flex min-h-[36px] shrink-0 items-center rounded-full border px-3.5 text-xs font-bold transition active:scale-95"
                                    :class="filtreStatut === s.valeur ? 'border-[#0B0F19] bg-[#0B0F19] text-white' : 'border-gray-200 bg-white text-[#0B0F19] hover:bg-gray-100'"
                                >
                                    {{ s.label }}
                                </button>
                            </div>

                            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Règlement">
                                <button
                                    v-for="r in FILTRES_REGLEMENT"
                                    :key="r.valeur"
                                    type="button"
                                    :aria-pressed="filtreReglement === r.valeur"
                                    @click="filtreReglement = r.valeur"
                                    class="inline-flex min-h-[36px] shrink-0 items-center rounded-full border px-3.5 text-xs font-bold transition active:scale-95"
                                    :class="filtreReglement === r.valeur ? 'border-[#0B0F19] bg-[#0B0F19] text-white' : 'border-gray-200 bg-white text-[#0B0F19] hover:bg-gray-100'"
                                >
                                    {{ r.label }}
                                </button>
                            </div>

                            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Période">
                                <button
                                    v-for="p in PERIODES"
                                    :key="p.valeur"
                                    type="button"
                                    :aria-pressed="periode === p.valeur"
                                    @click="periode = p.valeur"
                                    class="inline-flex min-h-[36px] shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-xs font-bold transition active:scale-95"
                                    :class="periode === p.valeur ? 'border-[#E11D48] bg-[#E11D48] text-white' : 'border-gray-200 bg-white text-[#0B0F19] hover:bg-gray-100'"
                                >
                                    <i v-if="p.valeur === 'perso'" class="fa-regular fa-calendar text-[10px]"></i>
                                    {{ p.label }}
                                </button>
                            </div>
                        </div>

                        <Transition name="fade">
                            <div v-if="periode === 'perso'" class="flex flex-wrap items-center gap-2">
                                <label class="flex items-center gap-2 text-[11px] font-semibold text-[#8A8D8F]">
                                    Du
                                    <input
                                        v-model="dateDebut"
                                        type="date"
                                        :max="dateFin || undefined"
                                        class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-[#0B0F19] focus:border-[#0B0F19] focus:outline-none focus:ring-1 focus:ring-[#0B0F19]"
                                    />
                                </label>
                                <label class="flex items-center gap-2 text-[11px] font-semibold text-[#8A8D8F]">
                                    au
                                    <input
                                        v-model="dateFin"
                                        type="date"
                                        :min="dateDebut || undefined"
                                        class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-[#0B0F19] focus:border-[#0B0F19] focus:outline-none focus:ring-1 focus:ring-[#0B0F19]"
                                    />
                                </label>
                            </div>
                        </Transition>

                        <p v-if="periodeInvalide" class="flex items-center gap-1.5 text-[11px] font-semibold text-[#E11D48]">
                            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                            La date de début doit précéder la date de fin.
                        </p>
                    </div>

                    <!-- ============ LISTE COMPACTE ============ -->
                    <div v-if="filtrees.length" class="space-y-3">
                        <p v-if="filtresActifs" class="text-xs font-semibold text-[#8A8D8F]">
                            {{ pluriel(filtrees.length, 'intervention') }} · filtres actifs
                        </p>

                        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                            <li
                                v-for="(e, index) in filtrees"
                                :key="e.id"
                                class="row-in"
                                :style="{ animationDelay: Math.min(index, 6) * 50 + 'ms' }"
                            >
                                <button
                                    type="button"
                                    @click="ouvrir(e.id)"
                                    class="group flex w-full flex-col gap-3 px-4 py-4 text-left transition hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none sm:flex-row sm:items-center sm:gap-4 sm:px-5"
                                >
                                    <!-- OT + date -->
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#0B0F19] text-white">
                                            <i class="fa-solid fa-screwdriver-wrench text-sm"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-extrabold text-[#0B0F19]">Ordre de travail {{ e.numero }}</p>
                                            <p class="mt-0.5 text-xs font-medium text-[#8A8D8F]">
                                                Réceptionné le {{ formatDateCourte(e.intervention.date_reception) }}
                                                <span v-if="e.intervention.kilometrage"> · {{ fmt(e.intervention.kilometrage) }} km</span>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Badges -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset"
                                            :class="styleStatut(e.cleStatut)"
                                        >
                                            {{ libelleStatut(e.intervention.statut) }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset"
                                            :class="REGLEMENTS[e.reglement].classes"
                                        >
                                            <i :class="REGLEMENTS[e.reglement].icone" class="text-[10px]"></i>
                                            {{ REGLEMENTS[e.reglement].label }}
                                        </span>
                                    </div>

                                    <!-- Montant -->
                                    <div class="flex items-center justify-between gap-4 sm:min-w-[170px] sm:justify-end">
                                        <div v-if="e.intervention.devis" class="sm:text-right">
                                            <p class="text-sm font-black tabular-nums text-[#0B0F19]">{{ fcfa(e.totaux.ttcFinal) }}</p>
                                            <p class="text-[11px] font-medium text-[#8A8D8F]">
                                                <template v-if="e.valide && e.reste > 0">Reste {{ fcfa(e.reste) }}</template>
                                                <template v-else>{{ e.valide ? 'Total accepté' : 'Total proposé' }}</template>
                                            </p>
                                        </div>
                                        <p v-else class="text-[11px] font-medium text-[#8A8D8F]">Aucun devis</p>
                                        <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-[#0B0F19]"></i>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- ============ AUCUN RÉSULTAT ============ -->
                    <div v-else class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-12 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucune intervention ne correspond</h4>
                        <p class="mx-auto mt-1 max-w-sm text-xs font-medium leading-relaxed text-[#8A8D8F]">
                            Essayez un autre numéro, un autre statut ou élargissez la période.
                        </p>
                        <button
                            type="button"
                            @click="reinitialiser"
                            class="mt-4 inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-xs font-bold text-[#0B0F19] transition hover:bg-gray-100 active:scale-95"
                        >
                            <i class="fa-solid fa-rotate-left text-[10px]"></i>
                            Réinitialiser les filtres
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- ============ MODAL : FICHE DEVIS / FACTURE ============ -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="modal"
                    class="print-root fixed inset-0 z-50 flex items-end justify-center bg-[#0B0F19]/60 sm:items-center sm:p-6 print:static print:block print:bg-white print:p-0"
                    @click.self="fermer"
                >
                    <div
                        class="modal-panel flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-h-[90vh] sm:rounded-2xl print:max-h-none print:max-w-none print:overflow-visible print:rounded-none print:shadow-none"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="modal-intervention-titre"
                    >
                        <!-- En-tête (masqué à l'impression) -->
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 bg-[#F8FAFC] p-5 print:hidden">
                            <div class="min-w-0">
                                <h3 id="modal-intervention-titre" class="truncate text-base font-black text-[#0B0F19]">
                                    Ordre de travail {{ modal.numero }}
                                </h3>
                                <p class="mt-0.5 text-xs font-medium text-[#8A8D8F]">
                                    Réceptionné le {{ formatDateCourte(modal.intervention.date_reception) }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset"
                                        :class="styleStatut(modal.cleStatut)"
                                    >
                                        {{ libelleStatut(modal.intervention.statut) }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset"
                                        :class="REGLEMENTS[modal.reglement].classes"
                                    >
                                        <i :class="REGLEMENTS[modal.reglement].icone" class="text-[10px]"></i>
                                        {{ REGLEMENTS[modal.reglement].label }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <button
                                    v-if="modal.intervention.devis"
                                    type="button"
                                    @click="imprimerFiche"
                                    class="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg bg-gray-800 px-3.5 text-xs font-bold text-white shadow-sm transition hover:bg-gray-900 active:scale-95"
                                >
                                    <i class="fa-solid fa-print text-[11px]"></i>
                                    <span class="hidden sm:inline">Imprimer</span>
                                </button>
                                <button
                                    ref="boutonFermer"
                                    type="button"
                                    aria-label="Fermer"
                                    @click="fermer"
                                    class="flex h-10 w-10 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-[#0B0F19] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0B0F19]"
                                >
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Corps -->
                        <div class="flex-1 overflow-y-auto p-3 sm:p-5 print:overflow-visible print:p-0">

                            <!-- FICHE FACTURE / DEVIS -->
                            <div v-if="modal.intervention.devis" class="invoice-sheet rounded-2xl border border-gray-200 bg-white p-4 text-gray-800 sm:p-6 print:border-none print:p-0">

                                <!-- En-tête facture -->
                                <div class="mb-3 flex flex-wrap items-start justify-between gap-3 border-b-2 border-gray-900 pb-3">
                                    <div>
                                        <img src="/images/logo-kagnan.png" alt="Garage Kagnan" class="h-14 w-auto object-contain" />
                                        <p class="mt-0.5 text-[11px] font-medium text-gray-500">Service Entretien & Réparation Automobile</p>
                                    </div>
                                    <div class="min-w-[200px] rounded-xl border-2 border-gray-900 bg-gray-50/50 p-2 text-right">
                                        <p
                                            class="mb-1 rounded px-2 py-0.5 text-center text-[11px] font-bold uppercase tracking-wider"
                                            :class="modal.valide ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                        >
                                            {{ modal.valide ? 'Facture / Devis Validé' : 'Devis Proposé' }}
                                        </p>
                                        <p class="mb-0.5 text-[11px] text-gray-700"><span class="font-semibold text-gray-900">N° :</span> {{ numeroDevis(modal.intervention.devis) }}</p>
                                        <p class="mb-0.5 text-[11px] text-gray-700"><span class="font-semibold text-gray-900">Date :</span> {{ formatDateCourte(modal.intervention.devis.created_at) }}</p>
                                        <p class="text-[11px] text-gray-700">
                                            <span class="font-semibold text-gray-900">Statut :</span>
                                            <span class="font-semibold capitalize text-[#E11D48]">{{ (modal.intervention.devis.statut || '').replace(/_/g, ' ') }}</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Client & véhicule -->
                                <div class="grid grid-cols-1 overflow-hidden rounded-lg border border-gray-900 text-xs sm:grid-cols-2 print:grid-cols-2">
                                    <div class="space-y-1 border-b border-gray-900 p-2.5 sm:border-b-0 sm:border-r print:border-b-0 print:border-r">
                                        <p class="mb-1 flex items-center gap-1.5 bg-gray-100 p-1 font-bold uppercase tracking-wider text-gray-900 underline">
                                            <i class="fa-solid fa-user text-[11px] text-gray-500"></i>
                                            <span>Client</span>
                                        </p>
                                        <p><span class="font-semibold">Nom client :</span> {{ vehicule.client?.nom }} {{ vehicule.client?.prenom || vehicule.client?.name }}</p>
                                        <p><span class="font-semibold">Adresse :</span> {{ vehicule.client?.adresse || 'N/A' }}</p>
                                        <p><span class="font-semibold">Téléphone :</span> {{ vehicule.client?.telephone || 'N/A' }}</p>
                                        <p><span class="font-semibold">Code Equipe :</span> {{ modal.intervention.mecanicien_id || 'N/A' }}</p>
                                    </div>
                                    <div class="space-y-1 p-2.5">
                                        <p class="mb-1 flex items-center gap-1.5 bg-gray-100 p-1 font-bold uppercase tracking-wider text-gray-900 underline">
                                            <i class="fa-solid fa-circle-info text-[11px] text-gray-500"></i>
                                            <span>Divers</span>
                                        </p>
                                        <p><span class="font-semibold">N° OT :</span> {{ modal.intervention.numero_ot || modal.intervention.id }}</p>
                                        <p><span class="font-semibold">Immatriculation :</span> {{ vehicule.immatriculation }}</p>
                                        <p><span class="font-semibold">Marque :</span> {{ vehicule.marque }}</p>
                                        <p><span class="font-semibold">Type :</span> {{ vehicule.modele }}</p>
                                        <p><span class="font-semibold">Kilométrage :</span> {{ modal.intervention.kilometrage || 'N/A' }} km</p>
                                    </div>
                                </div>

                                <div class="mb-3 rounded-b-lg border-x border-b border-gray-900 bg-gray-50/30 px-3 py-1 text-xs">
                                    <span class="font-semibold">N° Chassis :</span> <span class="font-mono">{{ vehicule.vin || 'N/A' }}</span>
                                </div>

                                <!-- Prestations regroupées par famille -->
                                <div class="mb-3">
                                    <div class="rounded-t-lg bg-gray-900 px-3 py-1 text-white">
                                        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider">
                                            <i class="fa-solid fa-list-check text-[11px] text-gray-400"></i>
                                            <span>Prestations & Pièces regroupées par famille</span>
                                        </p>
                                    </div>

                                    <div class="overflow-x-auto print:overflow-visible">
                                        <table class="min-w-full border-collapse border border-gray-900 text-xs">
                                            <thead>
                                                <tr class="border-b border-gray-900 bg-gray-100 text-center font-semibold">
                                                    <th class="w-12 border-r border-gray-900 p-1">Qté</th>
                                                    <th class="border-r border-gray-900 p-1 text-left">Désignation / Réf</th>
                                                    <th class="w-20 border-r border-gray-900 p-1">PU Net</th>
                                                    <th class="w-16 border-r border-gray-900 p-1">Remise</th>
                                                    <th class="w-16 border-r border-gray-900 p-1">TVA</th>
                                                    <th class="w-24 border-r border-gray-900 p-1">Montant HT</th>
                                                    <th class="w-28 p-1">Statut Client</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="!groupesModal.length">
                                                    <td colspan="7" class="p-3 text-center italic text-gray-500">Aucune ligne enregistrée pour ce devis.</td>
                                                </tr>
                                                <template v-for="groupe in groupesModal" :key="groupe.famille">
                                                    <tr class="border-y border-gray-900 bg-gray-200/90 font-bold">
                                                        <td colspan="7" class="px-3 py-1 text-[11px] uppercase tracking-wider text-gray-900">
                                                            <i class="fa-solid fa-layer-group mr-1.5 text-slate-600"></i>
                                                            <span>{{ groupe.famille }}</span>
                                                        </td>
                                                    </tr>

                                                    <tr
                                                        v-for="ligne in groupe.lignes"
                                                        :key="ligne.id"
                                                        :class="{ 'bg-rose-50/50 opacity-70': modal.valide && !estAcceptee(ligne) }"
                                                        class="border-b border-gray-300 text-center"
                                                    >
                                                        <td class="border-r border-gray-900 p-1 font-medium">{{ ligne.quantite }}</td>
                                                        <td class="border-r border-gray-900 p-1 text-left">
                                                            <span class="font-medium text-gray-900" :class="{ 'text-gray-500 line-through': modal.valide && !estAcceptee(ligne) }">
                                                                {{ ligne.designation }}
                                                            </span>
                                                            <span v-if="ligne.sous_famille" class="ml-1 text-[10px] font-semibold text-gray-500">({{ ligne.sous_famille }})</span>
                                                            <span v-if="ligne.reference_piece" class="block font-mono text-[10px] text-gray-500">Réf : {{ ligne.reference_piece }}</span>
                                                            <span v-if="modal.valide && !estAcceptee(ligne)" class="ml-1 text-[10px] font-bold italic text-rose-600">(Refusé par le client)</span>
                                                        </td>
                                                        <td class="whitespace-nowrap border-r border-gray-900 p-1 text-right">{{ fmt(ligne.pu_net) }} F</td>
                                                        <td class="whitespace-nowrap border-r border-gray-900 p-1 text-right">{{ fmt(ligne.remise) }} F</td>
                                                        <td class="border-r border-gray-900 p-1 text-center">
                                                            <span v-if="ligne.ne_pas_appliquer_tva" class="rounded border border-amber-200 bg-amber-50 px-1 py-0.5 text-[10px] font-semibold text-amber-600">Exonéré</span>
                                                            <span v-else class="text-gray-600">18%</span>
                                                        </td>
                                                        <td class="whitespace-nowrap border-r border-gray-900 p-1 text-right font-bold text-gray-900">{{ fmt(ligne.montant_ht) }} F</td>
                                                        <td class="p-1 text-center">
                                                            <span v-if="!modal.valide" class="whitespace-nowrap rounded bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600">En attente</span>
                                                            <span v-else-if="estAcceptee(ligne)" class="whitespace-nowrap rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                                                <i class="fa-solid fa-check"></i> Accepté
                                                            </span>
                                                            <span v-else class="whitespace-nowrap rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800">
                                                                <i class="fa-solid fa-xmark"></i> Refusé
                                                            </span>
                                                        </td>
                                                    </tr>

                                                    <tr class="border-b-2 border-gray-900 bg-gray-50 text-xs font-bold">
                                                        <td colspan="5" class="px-3 py-1 text-right italic text-gray-700">Sous-total HT {{ groupe.famille }} :</td>
                                                        <td colspan="2" class="whitespace-nowrap border-t border-gray-400 p-1 pl-4 text-left text-gray-900">{{ fmt(groupe.sousTotalHt) }} F</td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Totaux -->
                                <div v-if="groupesModal.length" class="mb-3 flex justify-end">
                                    <div class="w-full overflow-hidden rounded-lg border border-gray-900 text-xs sm:w-80 print:w-80">
                                        <div class="flex justify-between border-b border-gray-900 bg-gray-50 px-3 py-1">
                                            <span class="font-semibold text-gray-700">Total HT {{ modal.valide ? '(Accepté)' : '' }}</span>
                                            <span class="font-medium">{{ fmt(modal.totaux.ht) }} F</span>
                                        </div>
                                        <div class="flex justify-between border-b border-gray-900 bg-white px-3 py-1">
                                            <span class="font-semibold text-gray-700">Total Remises</span>
                                            <span class="font-medium">{{ fmt(modal.totaux.remises) }} F</span>
                                        </div>
                                        <div class="flex justify-between border-b border-gray-900 bg-white px-3 py-1">
                                            <span class="font-semibold text-gray-700">TVA Totale</span>
                                            <span class="font-medium">{{ fmt(modal.totaux.tva) }} F</span>
                                        </div>
                                        <div class="flex justify-between border-b border-gray-900 bg-gray-50 px-3 py-1">
                                            <span class="font-semibold text-gray-700">Petite fourniture (3%)</span>
                                            <span class="font-medium">{{ fmt(modal.totaux.petiteFourniture) }} F</span>
                                        </div>
                                        <div class="flex justify-between bg-gray-200 px-3 py-1.5 text-sm font-black text-gray-900">
                                            <span>Total TTC à Payer</span>
                                            <span class="text-[#E11D48]">{{ fmt(modal.totaux.ttcFinal) }} F</span>
                                        </div>
                                        <div v-if="modal.valide" class="flex justify-between border-t border-gray-900 bg-white px-3 py-1">
                                            <span class="font-semibold text-gray-700">Déjà réglé</span>
                                            <span class="font-medium text-emerald-700">{{ fmt(modal.paye) }} F</span>
                                        </div>
                                        <div v-if="modal.valide" class="flex justify-between border-t border-gray-900 bg-gray-50 px-3 py-1.5 text-sm font-black">
                                            <span>Reste à payer</span>
                                            <span :class="modal.reste === 0 ? 'text-emerald-700' : 'text-[#E11D48]'">{{ fmt(modal.reste) }} F</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Suivi des paiements (lecture seule) -->
                                <div v-if="modal.valide" class="mb-3 overflow-hidden rounded-lg border border-gray-900 text-xs">
                                    <div class="flex items-center justify-between bg-gray-900 px-3 py-1 text-white">
                                        <p class="flex items-center gap-2 font-bold uppercase tracking-wider">
                                            <i class="fa-solid fa-money-bill-wave text-[11px] text-gray-400"></i>
                                            <span>Suivi des paiements</span>
                                        </p>
                                        <span v-if="modal.reste === 0" class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-800">Facture soldée</span>
                                        <span v-else class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-800">En cours de règlement</span>
                                    </div>

                                    <div class="border-b border-gray-300 bg-gray-50/50 px-3 py-2">
                                        <div class="mb-1 flex justify-between font-semibold text-gray-700">
                                            <span>{{ fmt(modal.paye) }} F réglés sur {{ fmt(modal.totaux.ttcFinal) }} F</span>
                                            <span :class="modal.reste === 0 ? 'text-emerald-700' : 'text-[#E11D48]'">{{ modal.pct }} %</span>
                                        </div>
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                            <div
                                                class="h-full transition-all duration-500"
                                                :class="modal.reste === 0 ? 'bg-emerald-500' : 'bg-[#E11D48]'"
                                                :style="{ width: modal.pct + '%' }"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto print:overflow-visible">
                                        <table class="min-w-full border-collapse">
                                            <thead>
                                                <tr class="border-b border-gray-300 bg-gray-100 text-center text-[11px] font-semibold">
                                                    <th class="w-10 border-r border-gray-300 p-1">N°</th>
                                                    <th class="w-24 border-r border-gray-300 p-1">Date</th>
                                                    <th class="border-r border-gray-300 p-1 text-right">Montant</th>
                                                    <th class="border-r border-gray-300 p-1">Mode</th>
                                                    <th class="p-1">Note / Référence</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="extrairePaiements(modal.intervention).length === 0">
                                                    <td colspan="5" class="p-2.5 text-center italic text-gray-500">Aucun règlement enregistré.</td>
                                                </tr>
                                                <tr
                                                    v-for="(p, pIdx) in extrairePaiements(modal.intervention)"
                                                    :key="p.id"
                                                    class="border-b border-gray-200 text-center"
                                                >
                                                    <td class="border-r border-gray-300 p-1">{{ pIdx + 1 }}</td>
                                                    <td class="whitespace-nowrap border-r border-gray-300 p-1">{{ formatDateCourte(p.date_paiement || p.created_at) }}</td>
                                                    <td class="whitespace-nowrap border-r border-gray-300 p-1 text-right font-bold text-emerald-700">+{{ fmt(p.montant) }} F</td>
                                                    <td class="border-r border-gray-300 p-1 capitalize">{{ p.mode_paiement || 'Espèces' }}</td>
                                                    <td class="p-1 text-left font-mono text-[11px] text-gray-600">{{ p.notes || '—' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Conditions -->
                                <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50/30 p-2 text-xs">
                                    <p class="mb-0.5 font-bold uppercase tracking-wider text-[#E11D48] underline">NB :</p>
                                    <p class="font-bold tracking-wide text-gray-900">PAYER UNE AVANCE DE 70% AVANT TRAVAUX</p>
                                </div>

                                <!-- Signatures -->
                                <div class="mb-3 grid grid-cols-2 gap-8 pt-1 text-center text-xs font-bold">
                                    <div>
                                        <p class="mb-8 uppercase tracking-wider text-gray-700">CLIENT</p>
                                        <div class="mx-auto w-full max-w-[11rem] border-b border-gray-400"></div>
                                    </div>
                                    <div>
                                        <p class="mb-8 uppercase tracking-wider text-gray-700">PRESTATAIRE</p>
                                        <div class="mx-auto w-full max-w-[11rem] border-b border-gray-400"></div>
                                    </div>
                                </div>

                                <!-- Pied de page légal -->
                                <div class="mt-2 space-y-0.5 border-t border-gray-300 pt-2 text-center text-[9px] text-gray-500">
                                    <p>Siège : Yopougon Zone Industrielle & Terminus 27 NCC : 1113876 J - RCCM : CI- ABJ-2012-B-5126</p>
                                    <p>Régime d'imposition : Taxe d'Etat de l'Entreprenant (TEE)</p>
                                    <p>Tel : 25 23 01 90 86 / 07 08 38 83 25/ 05 44 10 00 78 E-mail : garagekagnan@gmail.com</p>
                                </div>
                            </div>

                            <!-- Aucun devis -->
                            <div v-else class="py-10 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F8FAFC] text-[#8A8D8F] ring-1 ring-gray-200">
                                    <i class="fa-solid fa-file-circle-xmark"></i>
                                </div>
                                <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucun devis rattaché</h4>
                                <p class="mx-auto mt-1 max-w-xs text-xs font-medium leading-relaxed text-[#8A8D8F]">
                                    Cette intervention n'a pas encore de devis.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 180ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 220ms cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-active .modal-panel,
.modal-leave-active .modal-panel {
    transition: transform 300ms cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-from .modal-panel,
.modal-leave-to .modal-panel {
    transform: translateY(2.5rem);
}
@media (min-width: 640px) {
    .modal-enter-from .modal-panel,
    .modal-leave-to .modal-panel {
        transform: translateY(0.5rem) scale(0.97);
    }
}

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

@media (prefers-reduced-motion: reduce) {
    .fade-enter-active,
    .fade-leave-active,
    .modal-enter-active,
    .modal-leave-active,
    .modal-enter-active .modal-panel,
    .modal-leave-active .modal-panel {
        transition: none;
    }
}
</style>

<!-- Impression : non scopé car le modal est téléporté dans <body> -->
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    body {
        background: white !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Quand une fiche est ouverte, on n'imprime qu'elle */
    body.impression-modal {
        overflow: visible !important;
    }
    body.impression-modal > *:not(.print-root) {
        display: none !important;
    }

    .invoice-sheet {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>