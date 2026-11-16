<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Entete from '@/Components/SuperAdmin/Entete.vue';
import { compact, duree, fcfa, nombre, pourcent } from '@/utils/format.js';

const props = defineProps({
    periode: { type: Object, required: true },
    sieges: { type: Array, default: () => [] },
    evolution: { type: Object, default: () => ({ mois: [], series: [] }) },
    seuil_stock: { type: Number, default: 5 },
});

// Une couleur fixe par siège (lisible en daltonisme : bleu / vert / ambre)
const COULEURS = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777'];
const couleur = (code) => COULEURS[Math.max(0, props.sieges.findIndex((s) => s.code === code)) % COULEURS.length];

const changerPeriode = (cle) => router.get(route('superadmin.sieges'), { periode: cle }, { preserveScroll: true, preserveState: true });
const lienExport = computed(() => route('superadmin.sieges.export', { periode: props.periode.cle }));

// ─── Tableau comparatif : le meilleur siège de chaque ligne est mis en avant ───
const jours = (v) => (v === null || v === undefined ? '—' : `${String(v).replace('.', ',')} j`);
const GROUPES = [
    {
        titre: 'Argent',
        lignes: [
            { cle: 'ca', libelle: "Chiffre d'affaires facturé", format: fcfa, mieux: 'haut' },
            { cle: 'encaisse', libelle: 'Encaissé', format: fcfa, mieux: 'haut' },
            { cle: 'panier_moyen', libelle: 'Panier moyen par facture', format: fcfa, mieux: 'haut' },
            { cle: 'impayes', libelle: 'Impayés à ce jour', format: fcfa, mieux: 'bas', detail: (s) => `${s.nb_impayes} facture(s)` },
            { cle: 'taux_acceptation', libelle: 'Devis acceptés', format: pourcent, mieux: 'haut', detail: (s) => `${s.devis_acceptes} / ${s.devis}` },
        ],
    },
    {
        titre: 'Atelier',
        lignes: [
            { cle: 'interventions', libelle: 'Véhicules reçus', format: nombre, mieux: 'haut' },
            { cle: 'livrees', libelle: 'Véhicules livrés', format: nombre, mieux: 'haut' },
            { cle: 'en_cours', libelle: "En cours aujourd'hui", format: nombre, mieux: null },
            { cle: 'delai_moyen_jours', libelle: 'Délai moyen réception → facture', format: jours, mieux: 'bas' },
        ],
    },
    {
        titre: 'Stock',
        lignes: [
            { cle: 'ruptures', libelle: 'Pièces en rupture', format: nombre, mieux: 'bas' },
            { cle: 'sous_seuil', libelle: `Pièces sous le seuil (≤ ${props.seuil_stock})`, format: nombre, mieux: 'bas' },
            { cle: 'valeur_stock', libelle: 'Valeur du stock (prix d’achat)', format: fcfa, mieux: null },
        ],
    },
    {
        titre: 'Clients',
        lignes: [
            { cle: 'demandes_portail', libelle: 'Demandes du portail client', format: nombre, mieux: null, detail: (s) => `${s.demandes_terminees} terminée(s), ${s.demandes_refusees} refusée(s)` },
            { cle: 'delai_traitement_min', libelle: 'Délai de prise en charge', format: duree, mieux: 'bas' },
            { cle: 'nouveaux_clients', libelle: 'Nouveaux clients', format: nombre, mieux: 'haut' },
            { cle: 'effectif', libelle: 'Effectif', format: nombre, mieux: null },
        ],
    },
];

const meilleur = (ligne) => {
    if (!ligne.mieux) return null;
    const valeurs = props.sieges.map((s) => s[ligne.cle]).filter((v) => v !== null && v !== undefined);
    if (valeurs.length < 2 || new Set(valeurs).size === 1) return null;
    return ligne.mieux === 'haut' ? Math.max(...valeurs) : Math.min(...valeurs);
};

// ─── Totaux (tous sièges) ───
const total = (cle) => props.sieges.reduce((t, s) => t + (Number(s[cle]) || 0), 0);
const caTotal = computed(() => total('ca'));
const part = (s) => (caTotal.value > 0 ? Math.round((s.ca / caTotal.value) * 100) : 0);

// ─── Graphique : CA des 6 derniers mois ───
const maxEvolution = computed(() => Math.max(1, ...props.evolution.series.flatMap((s) => s.valeurs)));
const hauteur = (v) => `${Math.max(v > 0 ? 2 : 0, (v / maxEvolution.value) * 100)}%`;
const survol = ref(null);
</script>

<template>
    <Head title="Comparatif des sièges" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <Entete titre="Comparatif des sièges" icone="fa-building" :texte="periode.libelle">
                    <label class="sr-only" for="periode">Période</label>
                    <select id="periode" class="rounded-full border-0 bg-white/10 py-2 pl-4 pr-9 text-xs font-bold text-white ring-1 ring-white/15 focus:ring-2 focus:ring-[#E11D48]" :value="periode.cle" @change="changerPeriode($event.target.value)">
                        <option v-for="(libelle, cle) in periode.choix" :key="cle" :value="cle" class="text-[#0B0F19]">{{ libelle }}</option>
                    </select>
                    <a :href="lienExport" class="sg-btn sg-btn-sm sg-btn-glass">
                        <i class="fa-solid fa-file-excel text-emerald-600" aria-hidden="true"></i>Excel
                    </a>
                </Entete>

                <!-- ═══ UNE CARTE PAR SIÈGE ═══ -->
                <section class="grid gap-4 md:grid-cols-3 sg-rise" style="--d: 60ms" aria-label="Résumé par siège">
                    <article v-for="s in sieges" :key="s.code" class="sg-glass sg-glass-strong p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 text-xs font-black uppercase tracking-wide" :style="{ color: couleur(s.code) }">
                                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: couleur(s.code) }"></span>{{ s.code }}
                                </p>
                                <p class="mt-0.5 truncate text-sm font-bold text-gray-600" :title="s.nom">{{ s.nom }}</p>
                            </div>
                            <span class="sg-chip bg-white/80 text-gray-600">{{ part(s) }} % du CA</span>
                        </div>
                        <p class="mt-3 text-2xl font-black tabular-nums tracking-tight text-[#0B0F19]">{{ fcfa(s.ca) }}</p>
                        <p class="text-xs text-gray-500">facturés · {{ fcfa(s.encaisse) }} encaissés</p>
                        <div class="sg-progress mt-3"><div :style="{ width: `${part(s)}%`, background: couleur(s.code) }"></div></div>
                        <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="sg-surface px-2 py-2">
                                <dt class="text-[10px] font-bold uppercase text-gray-500">Reçus</dt>
                                <dd class="text-lg font-black tabular-nums">{{ s.interventions }}</dd>
                            </div>
                            <div class="sg-surface px-2 py-2">
                                <dt class="text-[10px] font-bold uppercase text-gray-500">Impayés</dt>
                                <dd class="text-lg font-black tabular-nums" :class="s.nb_impayes ? 'text-amber-700' : ''">{{ s.nb_impayes }}</dd>
                            </div>
                            <div class="sg-surface px-2 py-2">
                                <dt class="text-[10px] font-bold uppercase text-gray-500">Ruptures</dt>
                                <dd class="text-lg font-black tabular-nums" :class="s.ruptures ? 'text-red-700' : ''">{{ s.ruptures }}</dd>
                            </div>
                        </dl>
                    </article>
                </section>

                <!-- ═══ ÉVOLUTION DU CA ═══ -->
                <section class="sg-glass sg-glass-strong p-5 sm:p-6 sg-rise" style="--d: 120ms" aria-labelledby="titre-evolution">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="titre-evolution" class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-chart-column text-[#E11D48]" aria-hidden="true"></i>CA facturé — 6 derniers mois</h2>
                        <ul class="flex flex-wrap gap-3 text-xs font-bold text-gray-600">
                            <li v-for="serie in evolution.series" :key="serie.code" class="flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-sm" :style="{ background: couleur(serie.code) }"></span>{{ serie.code }}
                            </li>
                        </ul>
                    </div>
                    <div class="mt-5 grid h-56 grid-cols-6 items-end gap-2 border-b border-[#0B0F19]/10 sm:gap-4">
                        <div v-for="(mois, i) in evolution.mois" :key="mois" class="relative flex h-full items-end justify-center gap-0.5 sm:gap-1">
                            <button
                                v-for="serie in evolution.series"
                                :key="serie.code"
                                type="button"
                                class="w-full max-w-[22px] rounded-t-md transition-opacity focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#E11D48]"
                                :style="{ height: hauteur(serie.valeurs[i]), background: couleur(serie.code), opacity: survol && survol !== `${serie.code}-${i}` ? 0.35 : 1 }"
                                :aria-label="`${serie.code}, ${mois} : ${fcfa(serie.valeurs[i])}`"
                                @mouseenter="survol = `${serie.code}-${i}`"
                                @mouseleave="survol = null"
                                @focus="survol = `${serie.code}-${i}`"
                                @blur="survol = null"
                            ></button>
                            <template v-for="serie in evolution.series" :key="`t-${serie.code}`">
                                <span
                                    v-if="survol === `${serie.code}-${i}`"
                                    class="pointer-events-none absolute -top-2 left-1/2 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-[#0B0F19] px-2 py-1 text-[11px] font-bold text-white shadow-lg"
                                >{{ serie.code }} · {{ fcfa(serie.valeurs[i]) }}</span>
                            </template>
                        </div>
                    </div>
                    <div class="mt-2 grid grid-cols-6 gap-2 text-center text-[11px] font-bold text-gray-500 sm:gap-4">
                        <span v-for="mois in evolution.mois" :key="mois">{{ mois }}</span>
                    </div>
                    <p class="mt-2 text-right text-[11px] text-gray-400">Échelle : 0 – {{ compact(maxEvolution) }} FCFA</p>
                </section>

                <!-- ═══ TABLEAU COMPARATIF ═══ -->
                <section class="sg-glass sg-glass-strong overflow-hidden sg-rise" style="--d: 180ms" aria-labelledby="titre-comparatif">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5 sm:px-6">
                        <h2 id="titre-comparatif" class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-table-columns text-[#E11D48]" aria-hidden="true"></i>Côte à côte</h2>
                        <p class="text-xs text-gray-500"><i class="fa-solid fa-trophy mr-1 text-amber-500" aria-hidden="true"></i>meilleur siège sur la ligne</p>
                    </div>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th class="px-5 py-2 font-bold sm:px-6">Indicateur</th>
                                    <th v-for="s in sieges" :key="s.code" class="px-4 py-2 text-right font-black" :style="{ color: couleur(s.code) }">{{ s.code }}</th>
                                </tr>
                            </thead>
                            <tbody v-for="groupe in GROUPES" :key="groupe.titre">
                                <tr>
                                    <th :colspan="sieges.length + 1" class="bg-[#0B0F19]/[0.04] px-5 py-1.5 text-left text-[11px] font-black uppercase tracking-widest text-gray-500 sm:px-6">{{ groupe.titre }}</th>
                                </tr>
                                <tr v-for="ligne in groupe.lignes" :key="ligne.cle" class="border-t border-[#0B0F19]/5">
                                    <td class="px-5 py-2.5 text-gray-700 sm:px-6">{{ ligne.libelle }}</td>
                                    <td v-for="s in sieges" :key="s.code" class="px-4 py-2.5 text-right tabular-nums">
                                        <span class="font-bold" :class="meilleur(ligne) !== null && s[ligne.cle] === meilleur(ligne) ? 'text-[#0B0F19]' : 'text-gray-600'">
                                            <i v-if="meilleur(ligne) !== null && s[ligne.cle] === meilleur(ligne)" class="fa-solid fa-trophy mr-1 text-[10px] text-amber-500" aria-label="Meilleur"></i>{{ ligne.format(s[ligne.cle]) }}
                                        </span>
                                        <span v-if="ligne.detail" class="block text-[11px] text-gray-400">{{ ligne.detail(s) }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="px-5 pb-4 pt-2 text-[11px] text-gray-400 sm:px-6">
                        Les impayés, les véhicules en cours et le stock sont l'état actuel ; les autres chiffres portent sur la période choisie.
                    </p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
