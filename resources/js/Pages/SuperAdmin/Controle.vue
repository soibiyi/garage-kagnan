<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Entete from '@/Components/SuperAdmin/Entete.vue';
import Pagination from '@/Components/SuperAdmin/Pagination.vue';
import { dateHeure, fcfa, nombre } from '@/utils/format.js';

const props = defineProps({
    onglet: { type: String, default: 'alertes' },
    filtres: { type: Object, default: () => ({}) },
    journal: { type: Object, default: null },
    compteurs: { type: Object, default: () => ({}) },
    types: { type: Object, default: () => ({}) },
    utilisateurs: { type: Array, default: () => [] },
    inventaire: { type: Object, default: null },
});

const ONGLETS = [
    { cle: 'alertes', libelle: 'Actions sensibles', icone: 'fa-triangle-exclamation' },
    { cle: 'historique', libelle: 'Historique avant / après', icone: 'fa-clock-rotate-left' },
    { cle: 'inventaire', libelle: "Écarts d'inventaire", icone: 'fa-boxes-stacked' },
];

const EVENEMENTS = {
    cree: { libelle: 'Création', classe: 'bg-emerald-100 text-emerald-800', icone: 'fa-plus' },
    modifie: { libelle: 'Modification', classe: 'bg-sky-100 text-sky-800', icone: 'fa-pen' },
    supprime: { libelle: 'Suppression', classe: 'bg-red-100 text-red-800', icone: 'fa-trash' },
};

const aller = (params) => router.get(route('superadmin.controle'), params, { preserveScroll: true, preserveState: true });
const changerOnglet = (cle) => aller({ onglet: cle });

// ─── Filtres du journal ───
const filtres = reactive({
    type: props.filtres.type || '',
    siege: props.filtres.siege || '',
    user: props.filtres.user || '',
    evenement: props.filtres.evenement || '',
    q: props.filtres.q || '',
});
const nettoyer = (o) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '' && v !== null && v !== undefined));
const appliquer = () => aller(nettoyer({ onglet: props.onglet, ...filtres }));
let delai;
watch(() => filtres.q, () => { window.clearTimeout(delai); delai = window.setTimeout(appliquer, 400); });
const reinitialiser = () => { Object.assign(filtres, { type: '', siege: '', user: '', evenement: '', q: '' }); appliquer(); };
const filtresActifs = computed(() => Object.values(filtres).some((v) => v));
const lienExport = computed(() => route('superadmin.controle.export', nettoyer({ onglet: props.onglet, ...filtres })));

const ouverts = ref(new Set());
const basculer = (id) => {
    const s = new Set(ouverts.value);
    s.has(id) ? s.delete(id) : s.add(id);
    ouverts.value = s;
};

const CHAMPS = {
    montant_total: 'Montant total', montant_paye: 'Montant payé', montant: 'Montant', statut: 'Statut', remise: 'Remise (%)',
    quantite: 'Quantité', pu_net: 'Prix unitaire', montant_ht: 'Montant HT', montant_ttc: 'Montant TTC', montant_total_ttc: 'Total TTC',
    montant_total_ht: 'Total HT', designation: 'Désignation', mode_paiement: 'Mode de paiement', sortie: 'Sortie', quantite_attribuee: 'Quantité attribuée',
    prix_achat: "Prix d'achat", prix_ttc_kagnan: 'Prix TTC Kagnan', mecanicien_id: 'Mécanicien', siege: 'Siège', type: 'Type', motif: 'Motif',
};
const nomChamp = (c) => CHAMPS[c] || c.replace(/_/g, ' ');
const valeur = (v) => {
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'object') return JSON.stringify(v);
    const s = String(v);
    return s.length > 120 ? `${s.slice(0, 120)}…` : s;
};

// ─── Annulation d'une action ───
const ANNULATION = {
    cree: "l'élément créé sera supprimé",
    modifie: 'les anciennes valeurs seront remises',
    supprime: "l'élément supprimé sera restauré à l'identique",
};
const enCours = ref(null);
const vehiculeTexte = (c) => [c?.vehicule, c?.immatriculation ? `(${c.immatriculation})` : null].filter(Boolean).join(' ');
function annuler(a, forcer = false) {
    if (!forcer) {
        const portee = a.operation > 1
            ? `Toute l'opération sera défaite (${a.operation} actions faites en même temps, ex. dossier + devis + lignes + facture + paiements).`
            : `Effet : ${ANNULATION[a.evenement] || 'action défaite'}.`;
        const qui = a.contexte ? `\nClient : ${a.contexte.client || '—'} · ${vehiculeTexte(a.contexte) || '—'}` : '';
        if (!window.confirm(`Annuler « ${a.libelle} » (${a.auteur}, ${dateHeure(a.date)}) ?${qui}\n\n${portee}`)) return;
    }
    enCours.value = a.id;
    router.post(route('superadmin.controle.annuler', a.id), { forcer }, {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.conflit && window.confirm(`Attention, ces éléments ont été modifiés depuis :\n\n${errors.conflit}\n\nAnnuler quand même ? Ces modifications plus récentes seront écrasées.`)) {
                annuler(a, true);
            }
        },
        onFinish: () => { if (enCours.value === a.id) enCours.value = null; },
    });
}

// ─── Inventaire ───
const recherche = ref(props.filtres.q || '');
let delaiInv;
watch(recherche, () => {
    window.clearTimeout(delaiInv);
    delaiInv = window.setTimeout(() => aller(nettoyer({ onglet: 'inventaire', siege: props.inventaire?.siege, q: recherche.value })), 400);
});
const saisies = reactive({});
const commentaires = reactive({});
watch(() => props.inventaire?.pieces?.current_page, () => {
    Object.keys(saisies).forEach((k) => delete saisies[k]);
    Object.keys(commentaires).forEach((k) => delete commentaires[k]);
});
const ecartSaisi = (p) => (saisies[p.stock_id] === '' || saisies[p.stock_id] === undefined ? null : Number(saisies[p.stock_id]) - p.theorique);
const nbSaisies = computed(() => Object.values(saisies).filter((v) => v !== '' && v !== undefined && v !== null).length);

const form = useForm({ siege: '', comptages: [] });
function enregistrerComptages() {
    form.siege = props.inventaire.siege;
    form.comptages = Object.entries(saisies)
        .filter(([, v]) => v !== '' && v !== undefined && v !== null)
        .map(([id, v]) => ({ stock_id: Number(id), quantite_comptee: Number(v), commentaire: commentaires[id] || null }));
    form.post(route('superadmin.controle.inventaire'), {
        preserveScroll: true,
        onSuccess: () => {
            Object.keys(saisies).forEach((k) => delete saisies[k]);
            Object.keys(commentaires).forEach((k) => delete commentaires[k]);
        },
    });
}
</script>

<template>
    <Head title="Contrôle et anti-fraude" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <Entete titre="Contrôle et anti-fraude" icone="fa-shield-halved" texte="Actions sensibles, qui a changé quoi (avant / après), écarts d'inventaire.">
                    <a v-if="onglet !== 'inventaire'" :href="lienExport" class="sg-btn sg-btn-sm sg-btn-glass">
                        <i class="fa-solid fa-file-excel text-emerald-600" aria-hidden="true"></i>Excel
                    </a>
                </Entete>

                <!-- ═══ COMPTEURS ═══ -->
                <section class="grid gap-4 sm:grid-cols-3 sg-rise" style="--d: 60ms" aria-label="Compteurs">
                    <div class="sg-glass sg-glass-strong sg-stat">
                        <span class="sg-tile h-11 w-11 text-amber-600"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                        <div><p class="sg-stat-value">{{ compteurs.alertes_aujourdhui }}</p><p class="text-xs text-gray-500">action(s) sensible(s) aujourd'hui</p></div>
                    </div>
                    <div class="sg-glass sg-glass-strong sg-stat">
                        <span class="sg-tile h-11 w-11 text-red-600"><i class="fa-solid fa-calendar-week" aria-hidden="true"></i></span>
                        <div><p class="sg-stat-value">{{ compteurs.alertes_7j }}</p><p class="text-xs text-gray-500">sur les 7 derniers jours</p></div>
                    </div>
                    <div class="sg-glass sg-glass-strong sg-stat">
                        <span class="sg-tile h-11 w-11 text-sky-600"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></span>
                        <div><p class="sg-stat-value">{{ compteurs.modifications_aujourdhui }}</p><p class="text-xs text-gray-500">modification(s) tracée(s) aujourd'hui</p></div>
                    </div>
                </section>

                <!-- ═══ ONGLETS ═══ -->
                <div class="overflow-x-auto sg-rise" style="--d: 100ms">
                    <div class="sg-segment" role="tablist">
                        <button
                            v-for="o in ONGLETS"
                            :key="o.cle"
                            type="button"
                            role="tab"
                            class="sg-pill inline-flex items-center gap-1.5 whitespace-nowrap"
                            :class="{ 'sg-pill-active': onglet === o.cle }"
                            :aria-selected="onglet === o.cle"
                            @click="changerOnglet(o.cle)"
                        ><i class="fa-solid" :class="o.icone" aria-hidden="true"></i>{{ o.libelle }}</button>
                    </div>
                </div>

                <!-- ═══ JOURNAL (alertes / historique) ═══ -->
                <template v-if="onglet !== 'inventaire' && journal">
                    <section class="sg-glass sg-glass-strong p-4 sm:p-5" aria-label="Filtres">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <div class="lg:col-span-2">
                                <label class="sg-label" for="f-q">Recherche</label>
                                <input id="f-q" v-model="filtres.q" type="search" class="sg-field" placeholder="Client, immatriculation, n° de facture, pièce…" />
                            </div>
                            <div>
                                <label class="sg-label" for="f-type">Élément</label>
                                <select id="f-type" v-model="filtres.type" class="sg-field" @change="appliquer">
                                    <option value="">Tous</option>
                                    <option v-for="(libelle, cle) in types" :key="cle" :value="cle">{{ libelle }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="sg-label" for="f-user">Auteur</label>
                                <select id="f-user" v-model="filtres.user" class="sg-field" @change="appliquer">
                                    <option value="">Tous</option>
                                    <option v-for="u in utilisateurs" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label class="sg-label" for="f-siege">Siège</label>
                                    <select id="f-siege" v-model="filtres.siege" class="sg-field" @change="appliquer">
                                        <option value="">Tous</option>
                                        <option v-for="(nom, code) in $page.props.sieges" :key="code" :value="code">{{ code }}</option>
                                    </select>
                                </div>
                                <div v-if="onglet === 'historique'" class="flex-1">
                                    <label class="sg-label" for="f-evt">Événement</label>
                                    <select id="f-evt" v-model="filtres.evenement" class="sg-field" @change="appliquer">
                                        <option value="">Tous</option>
                                        <option v-for="(e, cle) in EVENEMENTS" :key="cle" :value="cle">{{ e.libelle }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button v-if="filtresActifs" type="button" class="mt-3 text-xs font-bold text-[#E11D48] hover:underline" @click="reinitialiser">
                            <i class="fa-solid fa-xmark mr-1" aria-hidden="true"></i>Effacer les filtres
                        </button>
                    </section>

                    <div v-if="!journal.data.length" class="sg-empty p-10 text-center">
                        <i class="fa-solid fa-circle-check text-3xl text-emerald-500" aria-hidden="true"></i>
                        <p class="mt-3 font-bold text-[#0B0F19]">{{ onglet === 'alertes' ? 'Aucune action sensible' : 'Aucune modification' }}{{ filtresActifs ? ' pour ces filtres' : '' }}.</p>
                        <p class="mt-1 text-sm text-gray-500">Les factures, paiements, devis, dossiers et stocks sont tracés à chaque modification.</p>
                    </div>

                    <ol v-else class="space-y-3">
                        <li v-for="a in journal.data" :key="a.id" class="sg-glass sg-glass-strong sg-task" :class="a.alerte ? 'sg-accent-retard' : 'sg-accent-ok'">
                            <button type="button" class="flex w-full flex-col gap-2 p-4 pl-6 text-left sm:flex-row sm:items-center sm:gap-4" :aria-expanded="ouverts.has(a.id)" @click="basculer(a.id)">
                                <div class="min-w-0 flex-1">
                                    <p v-if="a.alerte" class="flex items-start gap-2 font-black text-red-700">
                                        <i class="fa-solid fa-triangle-exclamation mt-1 text-xs" aria-hidden="true"></i><span>{{ a.alerte }}</span>
                                    </p>
                                    <p class="flex flex-wrap items-center gap-2" :class="a.alerte ? 'mt-1 text-sm text-gray-600' : 'font-bold text-[#0B0F19]'">
                                        <span class="sg-chip" :class="EVENEMENTS[a.evenement]?.classe">
                                            <i class="fa-solid text-[9px]" :class="EVENEMENTS[a.evenement]?.icone" aria-hidden="true"></i>{{ EVENEMENTS[a.evenement]?.libelle }}
                                        </span>
                                        <span>{{ a.libelle }}</span>
                                        <span v-if="a.siege" class="sg-chip bg-white text-gray-600">{{ a.siege }}</span>
                                        <span v-if="a.annulation" class="sg-chip bg-gray-200 text-gray-700">
                                            <i class="fa-solid fa-rotate-left text-[9px]" aria-hidden="true"></i>Annulée
                                        </span>
                                    </p>
                                    <p v-if="a.contexte" class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-700">
                                        <span v-if="a.contexte.client"><i class="fa-solid fa-user-tie mr-1 text-gray-400" aria-hidden="true"></i><span class="font-bold">{{ a.contexte.client }}</span><template v-if="a.contexte.telephone"> · {{ a.contexte.telephone }}</template></span>
                                        <span v-if="vehiculeTexte(a.contexte)"><i class="fa-solid fa-car mr-1 text-gray-400" aria-hidden="true"></i>{{ vehiculeTexte(a.contexte) }}</span>
                                        <span v-if="a.contexte.numero_ot"><i class="fa-solid fa-folder-open mr-1 text-gray-400" aria-hidden="true"></i>OT {{ a.contexte.numero_ot }}</span>
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        <i class="fa-solid fa-user mr-1" aria-hidden="true"></i>{{ a.auteur }}<template v-if="a.role"> ({{ a.role }})</template>
                                        <template v-if="a.par_superadmin"> · via « voir en tant que » par {{ a.par_superadmin }}</template>
                                        · {{ dateHeure(a.date) }}<template v-if="a.ip"> · IP {{ a.ip }}</template>
                                    </p>
                                </div>
                                <span class="shrink-0 text-xs font-bold text-gray-500">
                                    {{ a.changements.length }} champ(s)
                                    <i class="fa-solid ml-1" :class="ouverts.has(a.id) ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true"></i>
                                </span>
                            </button>
                            <div v-if="ouverts.has(a.id)" class="border-t border-[#0B0F19]/5 px-4 pb-4 pl-6 pt-3">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                    <p v-if="a.annulation" class="text-xs font-bold text-gray-600">
                                        <i class="fa-solid fa-rotate-left mr-1" aria-hidden="true"></i>Annulée le {{ dateHeure(a.annulation.date) }}<template v-if="a.annulation.par"> par {{ a.annulation.par }}</template>
                                    </p>
                                    <p v-else-if="a.operation > 1" class="text-xs text-gray-500">
                                        Fait partie d'une opération de {{ a.operation }} actions : elles seront annulées ensemble.
                                    </p>
                                    <span v-else></span>
                                    <button
                                        v-if="a.annulable"
                                        type="button"
                                        class="sg-btn sg-btn-sm sg-btn-red"
                                        :disabled="enCours === a.id"
                                        @click="annuler(a)"
                                    >
                                        <i class="fa-solid" :class="enCours === a.id ? 'fa-spinner fa-spin' : 'fa-rotate-left'" aria-hidden="true"></i>
                                        {{ a.operation > 1 ? `Annuler l'opération (${a.operation})` : 'Annuler cette action' }}
                                    </button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[480px] text-sm">
                                        <thead>
                                            <tr class="text-left text-[11px] uppercase tracking-wide text-gray-500">
                                                <th class="py-1 pr-3 font-bold">Champ</th>
                                                <th class="py-1 pr-3 font-bold">Avant</th>
                                                <th class="py-1 font-bold">Après</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="c in a.changements" :key="c.champ" class="border-t border-[#0B0F19]/5 align-top">
                                                <td class="py-1.5 pr-3 font-bold text-gray-700">{{ nomChamp(c.champ) }}</td>
                                                <td class="break-all py-1.5 pr-3" :class="a.evenement === 'modifie' ? 'text-red-700 line-through decoration-red-300' : 'text-gray-600'">{{ valeur(c.avant) }}</td>
                                                <td class="break-all py-1.5" :class="a.evenement === 'modifie' ? 'font-bold text-emerald-700' : 'text-gray-600'">{{ valeur(c.apres) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </li>
                    </ol>
                    <Pagination :page="journal" />
                </template>

                <!-- ═══ INVENTAIRE ═══ -->
                <template v-if="onglet === 'inventaire' && inventaire">
                    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Résumé des 30 derniers jours">
                        <div class="sg-glass sg-glass-strong p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Comptages (30 j)</p>
                            <p class="sg-stat-value mt-1">{{ nombre(inventaire.resume.comptages_30j) }}</p>
                        </div>
                        <div class="sg-glass sg-glass-strong p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Pièces manquantes (30 j)</p>
                            <p class="sg-stat-value mt-1" :class="inventaire.resume.manquants_30j ? 'text-red-700' : ''">{{ nombre(inventaire.resume.manquants_30j) }}</p>
                        </div>
                        <div class="sg-glass sg-glass-strong p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Valeur manquante (30 j)</p>
                            <p class="mt-1 text-xl font-black tabular-nums" :class="inventaire.resume.valeur_manquante_30j ? 'text-red-700' : ''">{{ fcfa(inventaire.resume.valeur_manquante_30j) }}</p>
                            <p class="text-[11px] text-gray-400">au prix d'achat</p>
                        </div>
                        <div class="sg-glass sg-glass-strong p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Pièces en surplus (30 j)</p>
                            <p class="sg-stat-value mt-1">{{ nombre(inventaire.resume.surplus_30j) }}</p>
                        </div>
                    </section>

                    <div class="grid gap-6 lg:grid-cols-5">
                        <!-- Comptage -->
                        <section class="sg-glass sg-glass-strong overflow-hidden lg:col-span-3" aria-labelledby="titre-comptage">
                            <div class="space-y-3 p-4 sm:p-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <h2 id="titre-comptage" class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-clipboard-check text-[#E11D48]" aria-hidden="true"></i>Saisir un comptage</h2>
                                    <div class="sg-segment" role="group" aria-label="Siège">
                                        <button
                                            v-for="(nom, code) in $page.props.sieges"
                                            :key="code"
                                            type="button"
                                            class="sg-pill"
                                            :class="{ 'sg-pill-active': inventaire.siege === code }"
                                            :aria-pressed="inventaire.siege === code"
                                            :title="nom"
                                            @click="aller({ onglet: 'inventaire', siege: code })"
                                        >{{ code }}</button>
                                    </div>
                                </div>
                                <label class="sr-only" for="inv-q">Rechercher une pièce</label>
                                <input id="inv-q" v-model="recherche" type="search" class="sg-field" placeholder="Rechercher une pièce, une référence, une marque…" />
                                <p class="text-xs text-gray-500">Saisissez la quantité réellement comptée en rayon. Seules les lignes remplies sont enregistrées. Le stock n'est pas modifié : l'écart est seulement tracé.</p>
                            </div>

                            <div v-if="!inventaire.pieces.data.length" class="sg-empty mx-4 mb-4 p-8 text-center text-sm text-gray-500 sm:mx-5">
                                Aucune pièce attribuée à ce siège{{ recherche ? ' pour cette recherche' : '' }}.
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full min-w-[560px] text-sm">
                                    <thead>
                                        <tr class="border-y border-[#0B0F19]/10 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                            <th class="px-4 py-2 font-bold sm:px-5">Pièce</th>
                                            <th class="px-2 py-2 text-right font-bold">Théorique</th>
                                            <th class="px-2 py-2 text-right font-bold">Comptée</th>
                                            <th class="px-2 py-2 text-right font-bold">Écart</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="p in inventaire.pieces.data" :key="p.stock_id" class="border-b border-[#0B0F19]/5 align-top">
                                            <td class="px-4 py-2.5 sm:px-5">
                                                <p class="font-bold text-[#0B0F19]">{{ p.piece }}</p>
                                                <p class="text-[11px] text-gray-500">
                                                    <template v-if="p.reference">{{ p.reference }} · </template>{{ p.vehicule || '—' }}
                                                </p>
                                                <p v-if="p.dernier_comptage" class="text-[11px]" :class="p.dernier_comptage.ecart ? 'text-amber-700' : 'text-gray-400'">
                                                    Dernier comptage {{ dateHeure(p.dernier_comptage.date) }} : {{ p.dernier_comptage.comptee }}
                                                    ({{ p.dernier_comptage.ecart > 0 ? '+' : '' }}{{ p.dernier_comptage.ecart }})
                                                </p>
                                                <input
                                                    v-if="saisies[p.stock_id] !== undefined && saisies[p.stock_id] !== '' && ecartSaisi(p) !== 0"
                                                    v-model="commentaires[p.stock_id]"
                                                    type="text"
                                                    maxlength="250"
                                                    class="sg-field mt-1.5 py-1 text-xs"
                                                    placeholder="Commentaire sur l'écart (facultatif)"
                                                    :aria-label="`Commentaire pour ${p.piece}`"
                                                />
                                            </td>
                                            <td class="px-2 py-2.5 text-right font-bold tabular-nums">{{ p.theorique }}</td>
                                            <td class="px-2 py-2.5 text-right">
                                                <input
                                                    v-model="saisies[p.stock_id]"
                                                    type="number"
                                                    min="0"
                                                    inputmode="numeric"
                                                    class="sg-field w-20 py-1 text-right tabular-nums"
                                                    :aria-label="`Quantité comptée pour ${p.piece}`"
                                                />
                                            </td>
                                            <td class="px-2 py-2.5 text-right font-black tabular-nums" :class="ecartSaisi(p) === null ? 'text-gray-300' : ecartSaisi(p) < 0 ? 'text-red-700' : ecartSaisi(p) > 0 ? 'text-amber-700' : 'text-emerald-700'">
                                                <template v-if="ecartSaisi(p) === null">—</template>
                                                <template v-else>{{ ecartSaisi(p) > 0 ? '+' : '' }}{{ ecartSaisi(p) }}</template>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                                <Pagination :page="inventaire.pieces" />
                                <button type="button" class="sg-btn sg-btn-red ml-auto" :disabled="!nbSaisies || form.processing" @click="enregistrerComptages">
                                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Enregistrer {{ nbSaisies || '' }} comptage(s)
                                </button>
                            </div>
                            <p v-if="form.errors && Object.keys(form.errors).length" class="px-5 pb-4 text-sm text-red-700">Vérifiez les quantités saisies.</p>
                        </section>

                        <!-- Écarts récents -->
                        <section class="sg-glass sg-glass-strong p-4 sm:p-5 lg:col-span-2" aria-labelledby="titre-ecarts">
                            <h2 id="titre-ecarts" class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-scale-unbalanced text-[#E11D48]" aria-hidden="true"></i>Derniers écarts constatés</h2>
                            <p v-if="!inventaire.ecarts.length" class="sg-empty mt-4 p-6 text-center text-sm text-gray-500">Aucun écart pour l'instant.</p>
                            <ul v-else class="mt-3 space-y-2">
                                <li v-for="e in inventaire.ecarts" :key="e.id" class="sg-surface p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-[#0B0F19]">{{ e.piece }}</p>
                                            <p class="text-[11px] text-gray-500">{{ e.siege }} · {{ dateHeure(e.date) }}<template v-if="e.par"> · {{ e.par }}</template></p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="font-black tabular-nums" :class="e.ecart < 0 ? 'text-red-700' : 'text-amber-700'">{{ e.ecart > 0 ? '+' : '' }}{{ e.ecart }}</p>
                                            <p class="text-[11px] tabular-nums text-gray-500">{{ fcfa(e.valeur) }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-gray-500">Théorique {{ e.theorique }} · compté {{ e.comptee }}<template v-if="e.commentaire"> — « {{ e.commentaire }} »</template></p>
                                </li>
                            </ul>
                        </section>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
