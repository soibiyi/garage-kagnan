<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    siege: { type: String, default: null },
    resume: { type: Object, required: true },
    parMois: { type: Array, default: () => [] },
    parSemaine: { type: Array, default: () => [] },
    dossiers: { type: Array, default: () => [] },
});

// Couleurs des deux séries (validées : séparation daltonisme + contraste)
const SERIES = {
    total: { label: 'Facturées (soldées ou non)', couleur: '#2563eb' },
    soldees: { label: 'Encaissées (factures soldées)', couleur: '#059669' },
};

const fcfa = (n) => `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(n || 0))} FCFA`;
const nombre = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(n || 0));
const date = (d) => (d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');

const changerSiege = (siege) => router.get(route('superadmin.petites-fournitures'), siege ? { siege } : {}, { preserveScroll: true });

// ─── Période : mois / semaines ──────────────────────────────────────
const vue = ref('mois');
const periodes = computed(() => [...(vue.value === 'mois' ? props.parMois : props.parSemaine)].reverse()); // chronologique
const maxPeriode = computed(() => Math.max(1, ...periodes.value.map((p) => Math.max(p.total, p.soldees))));
const hauteur = (v) => `${Math.max(v > 0 ? 2 : 0, (v / maxPeriode.value) * 100)}%`;

// Graduations de l'axe (4 lignes)
const graduations = computed(() => [1, 0.75, 0.5, 0.25, 0].map((f) => ({ f, v: maxPeriode.value * f })));
const compact = (v) => (v >= 1e6 ? `${(v / 1e6).toFixed(1).replace('.', ',')} M` : v >= 1e3 ? `${Math.round(v / 1e3)} k` : nombre(v));

const survol = ref(null);
const selection = ref(null); // période cliquée → filtre le détail

const choisirPeriode = (p) => { selection.value = selection.value?.cle === p.cle ? null : p; };

// ─── Détail des dossiers ────────────────────────────────────────────
const filtreStatut = ref('tous');
const dossiersFiltres = computed(() => props.dossiers.filter((d) => {
    if (filtreStatut.value === 'soldes' && !d.soldee) return false;
    if (filtreStatut.value === 'non_soldes' && d.soldee) return false;
    if (selection.value) {
        const debut = new Date(selection.value.debut).getTime();
        const fin = new Date(selection.value.fin).getTime();
        const ref_ = filtreStatut.value === 'soldes' ? d.date_solde : d.date_facturation;
        const t = ref_ ? new Date(ref_).getTime() : NaN;
        if (!(t >= debut && t <= fin)) return false;
    }
    return true;
}));
const totalDetail = computed(() => dossiersFiltres.value.reduce((t, d) => t + d.petite_fourniture, 0));
const MODES = { auto: 'Auto 3 %', saisie: 'Montant saisi', desactivee: 'Désactivée' };

const tauxEncaissement = computed(() => (props.resume.total > 0 ? Math.round((props.resume.soldees / props.resume.total) * 100) : 0));
</script>

<template>
    <Head title="Petites fournitures" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <!-- ═══ EN-TÊTE ═══ -->
                <header class="sg-glass sg-glass-dark sg-rise overflow-hidden p-5 sm:p-7">
                    <span class="absolute left-0 top-0 h-1 w-20 rounded-br-full bg-[#E11D48]"></span>
                    <Link :href="route('superadmin.accueil')" class="sg-retour">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Accueil super admin
                    </Link>
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="sg-tile sg-tile-red h-12 w-12 text-lg"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-white/50">Super administrateur · Récapitulatif</p>
                                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Petites fournitures</h1>
                                <p class="mt-0.5 text-sm text-white/60">Sur les dossiers facturés (devis acceptés) — encaissées et non encaissées.</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1.5 rounded-full bg-white/10 p-1 ring-1 ring-white/10" role="group" aria-label="Filtrer par siège">
                            <button
                                type="button"
                                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                                :class="!siege ? 'bg-white text-[#0B0F19]' : 'text-white/70 hover:text-white'"
                                :aria-pressed="!siege"
                                @click="changerSiege(null)"
                            >Tous les sièges</button>
                            <button
                                v-for="(nom, code) in $page.props.sieges"
                                :key="code"
                                type="button"
                                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                                :class="siege === code ? 'bg-white text-[#0B0F19]' : 'text-white/70 hover:text-white'"
                                :aria-pressed="siege === code"
                                :title="nom"
                                @click="changerSiege(code)"
                            >{{ code }}</button>
                        </div>
                    </div>
                </header>

                <!-- ═══ CHIFFRES CLÉS ═══ -->
                <section class="pf-kpis sg-rise" style="--d: 80ms" aria-label="Chiffres clés">
                    <div class="sg-glass sg-glass-strong p-5">
                        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                            <span class="pf-puce" :style="{ background: SERIES.total.couleur }"></span>Total facturé
                        </p>
                        <p class="mt-2 text-2xl font-black tabular-nums tracking-tight text-[#0B0F19]">{{ fcfa(resume.total) }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ resume.nb_factures }} facture{{ resume.nb_factures > 1 ? 's' : '' }}, soldées ou non</p>
                    </div>
                    <div class="sg-glass sg-glass-strong p-5">
                        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                            <span class="pf-puce" :style="{ background: SERIES.soldees.couleur }"></span>Sur factures soldées
                        </p>
                        <p class="mt-2 text-2xl font-black tabular-nums tracking-tight text-[#0B0F19]">{{ fcfa(resume.soldees) }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ resume.nb_soldees }} soldée{{ resume.nb_soldees > 1 ? 's' : '' }} · {{ tauxEncaissement }} % du total</p>
                        <div class="sg-progress mt-3"><div :style="{ width: `${tauxEncaissement}%` }"></div></div>
                    </div>
                    <div class="sg-glass sg-glass-strong p-5">
                        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                            <i class="fa-regular fa-hourglass-half text-amber-600" aria-hidden="true"></i>Non soldées
                        </p>
                        <p class="mt-2 text-2xl font-black tabular-nums tracking-tight text-[#0B0F19]">{{ fcfa(resume.non_soldees) }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ resume.nb_factures - resume.nb_soldees }} facture(s) en attente de paiement</p>
                    </div>
                    <div class="sg-glass sg-glass-strong p-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Ce mois · cette semaine</p>
                        <dl class="mt-2 space-y-1.5 text-sm">
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-gray-500">Mois</dt>
                                <dd class="text-right font-black tabular-nums text-[#0B0F19]">{{ fcfa(resume.mois_en_cours.total) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-2 text-xs">
                                <dt class="text-gray-400">dont encaissé</dt>
                                <dd class="tabular-nums text-gray-600">{{ fcfa(resume.mois_en_cours.soldees) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-2 border-t border-[#0B0F19]/5 pt-1.5">
                                <dt class="text-gray-500">Semaine</dt>
                                <dd class="text-right font-black tabular-nums text-[#0B0F19]">{{ fcfa(resume.semaine_en_cours.total) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-2 text-xs">
                                <dt class="text-gray-400">dont encaissé</dt>
                                <dd class="tabular-nums text-gray-600">{{ fcfa(resume.semaine_en_cours.soldees) }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <!-- ═══ ÉVOLUTION ═══ -->
                <section class="sg-glass sg-rise p-4 sm:p-6" style="--d: 140ms">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black tracking-tight text-[#0B0F19]">
                                Petites fournitures par {{ vue === 'mois' ? 'mois' : 'semaine' }}
                            </h2>
                            <p class="text-xs text-gray-500">
                                {{ vue === 'mois' ? '12 derniers mois' : '12 dernières semaines' }} · cliquez une période pour voir ses dossiers
                            </p>
                        </div>
                        <div class="sg-segment" role="group" aria-label="Regrouper par">
                            <button type="button" class="sg-pill" :class="{ 'sg-pill-active': vue === 'mois' }" :aria-pressed="vue === 'mois'" @click="vue = 'mois'; selection = null">Mois</button>
                            <button type="button" class="sg-pill" :class="{ 'sg-pill-active': vue === 'semaine' }" :aria-pressed="vue === 'semaine'" @click="vue = 'semaine'; selection = null">Semaines</button>
                        </div>
                    </div>

                    <!-- Légende -->
                    <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-600">
                        <li v-for="(s, cle) in SERIES" :key="cle" class="flex items-center gap-2">
                            <span class="pf-puce" :style="{ background: s.couleur }"></span>{{ s.label }}
                        </li>
                    </ul>

                    <!-- Graphique : barres groupées, un seul axe (FCFA) -->
                    <div class="pf-chart mt-4" role="img" :aria-label="`Petites fournitures facturées et encaissées par ${vue}`">
                        <div class="pf-axe" aria-hidden="true">
                            <span v-for="g in graduations" :key="g.f" :style="{ bottom: `${g.f * 100}%` }">{{ compact(g.v) }}</span>
                        </div>
                        <div class="pf-zone">
                            <div v-for="g in graduations" :key="g.f" class="pf-grille" :style="{ bottom: `${g.f * 100}%` }" aria-hidden="true"></div>
                            <div class="pf-groupes">
                                <button
                                    v-for="p in periodes"
                                    :key="p.cle"
                                    type="button"
                                    class="pf-groupe"
                                    :class="{ 'pf-groupe-actif': selection?.cle === p.cle, 'pf-groupe-attenue': selection && selection.cle !== p.cle }"
                                    :aria-label="`${p.label} : facturées ${fcfa(p.total)}, encaissées ${fcfa(p.soldees)}`"
                                    @mouseenter="survol = p"
                                    @mouseleave="survol = null"
                                    @focus="survol = p"
                                    @blur="survol = null"
                                    @click="choisirPeriode(p)"
                                >
                                    <span class="pf-barres">
                                        <span class="pf-barre" :style="{ height: hauteur(p.total), background: SERIES.total.couleur }"></span>
                                        <span class="pf-barre" :style="{ height: hauteur(p.soldees), background: SERIES.soldees.couleur }"></span>
                                    </span>
                                    <span class="pf-label">{{ p.court }}</span>

                                    <span v-if="survol?.cle === p.cle" class="pf-tooltip" role="tooltip">
                                        <strong class="block text-[11px] text-white">{{ p.label }}</strong>
                                        <span class="mt-1 flex items-center gap-1.5"><span class="pf-puce" :style="{ background: SERIES.total.couleur }"></span>Facturées : <b class="text-white">{{ fcfa(p.total) }}</b></span>
                                        <span class="flex items-center gap-1.5 pl-4 text-white/60">dont non soldées : {{ fcfa(p.non_soldees) }}</span>
                                        <span class="flex items-center gap-1.5"><span class="pf-puce" :style="{ background: SERIES.soldees.couleur }"></span>Encaissées : <b class="text-white">{{ fcfa(p.soldees) }}</b></span>
                                        <span class="mt-1 block text-white/50">{{ p.nb_factures }} facturé(s) · {{ p.nb_soldees }} soldé(s)</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Vue tableau (mêmes chiffres) -->
                    <div class="sg-scroll mt-6 overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pr-3">Période</th>
                                    <th class="py-2 pr-3 text-right">Facturées</th>
                                    <th class="py-2 pr-3 text-right">dont non soldées</th>
                                    <th class="py-2 pr-3 text-right">Encaissées (soldées)</th>
                                    <th class="py-2 text-right">Factures</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#0B0F19]/5">
                                <tr
                                    v-for="p in [...periodes].reverse()"
                                    :key="p.cle"
                                    class="cursor-pointer transition hover:bg-white/60"
                                    :class="{ 'bg-white/80': selection?.cle === p.cle }"
                                    @click="choisirPeriode(p)"
                                >
                                    <td class="py-2 pr-3 font-semibold text-[#0B0F19]">{{ p.label }}</td>
                                    <td class="py-2 pr-3 text-right font-bold tabular-nums text-[#0B0F19]">{{ fcfa(p.total) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums text-gray-600">{{ fcfa(p.non_soldees) }}</td>
                                    <td class="py-2 pr-3 text-right font-bold tabular-nums text-[#0B0F19]">{{ fcfa(p.soldees) }}</td>
                                    <td class="py-2 text-right tabular-nums text-gray-500">{{ p.nb_factures }} / {{ p.nb_soldees }} soldée(s)</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-[#0B0F19]/10 font-black text-[#0B0F19]">
                                    <td class="py-2 pr-3">Total {{ vue === 'mois' ? '12 mois' : '12 semaines' }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ fcfa(periodes.reduce((t, p) => t + p.total, 0)) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ fcfa(periodes.reduce((t, p) => t + p.non_soldees, 0)) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ fcfa(periodes.reduce((t, p) => t + p.soldees, 0)) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ periodes.reduce((t, p) => t + p.nb_factures, 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="mt-3 text-[11px] text-gray-400">
                        « Facturées » : date d'acceptation du devis. « Encaissées » : date du paiement qui a soldé la facture.
                        Montant : 3 % automatique du TTC des lignes acceptées, ou montant saisi sur le devis.
                    </p>
                </section>

                <!-- ═══ DÉTAIL DES DOSSIERS ═══ -->
                <section class="sg-glass sg-rise overflow-hidden" style="--d: 200ms">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                        <div>
                            <h2 class="text-lg font-black tracking-tight text-[#0B0F19]">Détail par dossier</h2>
                            <p class="text-xs text-gray-500">
                                <template v-if="selection">{{ selection.label }} · </template>
                                {{ dossiersFiltres.length }} dossier(s) · {{ fcfa(totalDetail) }}
                                <button v-if="selection" type="button" class="ml-2 font-bold text-[#E11D48]" @click="selection = null">Toutes les périodes</button>
                            </p>
                        </div>
                        <div class="sg-segment" role="group" aria-label="Filtrer par paiement">
                            <button type="button" class="sg-pill" :class="{ 'sg-pill-active': filtreStatut === 'tous' }" @click="filtreStatut = 'tous'">Tous</button>
                            <button type="button" class="sg-pill" :class="{ 'sg-pill-active': filtreStatut === 'soldes' }" @click="filtreStatut = 'soldes'">Soldés</button>
                            <button type="button" class="sg-pill" :class="{ 'sg-pill-active': filtreStatut === 'non_soldes' }" @click="filtreStatut = 'non_soldes'">Non soldés</button>
                        </div>
                    </div>

                    <div class="sg-scroll max-h-[34rem] overflow-auto border-t border-[#0B0F19]/5">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead class="sticky top-0 bg-white/90 backdrop-blur">
                                <tr class="text-left text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <th class="px-4 py-2.5">Dossier</th>
                                    <th class="px-3 py-2.5">Client · véhicule</th>
                                    <th class="px-3 py-2.5">Facturé le</th>
                                    <th class="px-3 py-2.5 text-right">Total TTC</th>
                                    <th class="px-3 py-2.5 text-right">Petite fourniture</th>
                                    <th class="px-4 py-2.5">Paiement</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#0B0F19]/5">
                                <tr v-for="d in dossiersFiltres" :key="d.id" class="hover:bg-white/60">
                                    <td class="px-4 py-2.5">
                                        <Link :href="route('administration.facturation.show', d.id)" class="font-mono text-xs font-bold text-[#0B0F19] underline decoration-dotted underline-offset-2 hover:text-[#E11D48]">
                                            {{ d.numero_ot || `Devis direct #${d.id}` }}
                                        </Link>
                                        <span class="block text-[11px] text-gray-400">{{ d.siege }}</span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="block font-semibold text-[#0B0F19]">{{ d.client || '—' }}</span>
                                        <span class="block text-[11px] text-gray-500">{{ d.vehicule || '—' }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-gray-600">{{ date(d.date_facturation) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ fcfa(d.total_ttc) }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        <span class="font-black tabular-nums text-[#0B0F19]">{{ fcfa(d.petite_fourniture) }}</span>
                                        <span class="block text-[10px] text-gray-400">{{ MODES[d.mode] }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span v-if="d.soldee" class="sg-chip bg-emerald-100 text-emerald-800">
                                            <i class="fa-solid fa-check" aria-hidden="true"></i>Soldé le {{ date(d.date_solde) }}
                                        </span>
                                        <span v-else class="sg-chip bg-amber-100 text-amber-800">
                                            <i class="fa-regular fa-hourglass-half" aria-hidden="true"></i>{{ fcfa(d.montant_paye) }} payé
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!dossiersFiltres.length">
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">Aucun dossier pour ce filtre.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>

<style scoped>
.pf-kpis {
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(0, 1fr);
}
@media (min-width: 640px) { .pf-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 1024px) { .pf-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

.pf-puce {
    display: inline-block;
    height: 10px;
    width: 10px;
    flex-shrink: 0;
    border-radius: 3px;
}

/* ─── Graphique ─── */
.pf-chart {
    display: grid;
    grid-template-columns: 3.25rem minmax(0, 1fr);
    height: 260px;
    padding-bottom: 1.6rem; /* place des libellés de période */
}
.pf-axe { position: relative; }
.pf-axe span {
    position: absolute;
    right: 0.5rem;
    transform: translateY(50%);
    font-size: 10px;
    font-variant-numeric: tabular-nums;
    color: #8a8d8f;
}
.pf-zone { position: relative; }
.pf-grille {
    position: absolute;
    left: 0;
    right: 0;
    height: 1px;
    background: rgba(11, 15, 25, 0.07);
}
.pf-groupes {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: stretch;
    gap: 2px;
}
.pf-groupe {
    position: relative;
    display: flex;
    flex: 1 1 0;
    min-width: 0;
    flex-direction: column;
    justify-content: flex-end;
    border-radius: 10px;
    transition: background 0.2s ease, opacity 0.2s ease;
}
.pf-groupe:hover,
.pf-groupe:focus-visible,
.pf-groupe-actif { background: rgba(255, 255, 255, 0.55); outline: none; }
.pf-groupe:focus-visible { box-shadow: 0 0 0 2px #e11d48; }
.pf-groupe-attenue { opacity: 0.45; }
.pf-barres {
    display: flex;
    height: 100%;
    align-items: flex-end;
    justify-content: center;
    gap: 2px; /* écart de 2 px entre barres adjacentes */
    padding: 0 12%;
}
.pf-barre {
    display: block;
    width: 100%;
    max-width: 18px;
    border-radius: 4px 4px 0 0; /* bout arrondi, ancré sur la ligne de base */
    transition: height 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.pf-label {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1.45rem;
    overflow: hidden;
    text-align: center;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 10px;
    font-weight: 700;
    color: #6b6e70;
}
.pf-tooltip {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 50%;
    z-index: 20;
    width: max-content;
    max-width: 15rem;
    transform: translateX(-50%);
    padding: 0.6rem 0.75rem;
    border-radius: 12px;
    text-align: left;
    font-size: 11px;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.8);
    background: rgba(11, 15, 25, 0.94);
    box-shadow: 0 14px 30px -12px rgba(11, 15, 25, 0.6);
    pointer-events: none;
}
.pf-groupes > .pf-groupe:first-child .pf-tooltip,
.pf-groupes > .pf-groupe:nth-child(2) .pf-tooltip { left: 0; transform: none; }
.pf-groupes > .pf-groupe:last-child .pf-tooltip,
.pf-groupes > .pf-groupe:nth-last-child(2) .pf-tooltip { left: auto; right: 0; transform: none; }
</style>
