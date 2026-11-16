<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Entete from '@/Components/SuperAdmin/Entete.vue';
import { duree, fcfa, nombre, pourcent } from '@/utils/format.js';

const props = defineProps({
    periode: { type: Object, required: true },
    siege: { type: String, default: null },
    metiers: { type: Object, required: true },
    stats: { type: Object, required: true },
});

const ICONES = {
    mecaniciens: 'fa-wrench',
    receptionnistes: 'fa-clipboard-user',
    administratifs: 'fa-file-invoice-dollar',
    chauffeurs: 'fa-truck-fast',
    charges_clients: 'fa-headset',
    secretaires: 'fa-list-check',
};

// Mise en forme selon la colonne
const formater = (cle, v) => {
    if (v === null || v === undefined) return '—';
    if (cle.startsWith('montant')) return fcfa(v);
    if (cle.startsWith('taux')) return pourcent(v);
    if (cle.endsWith('_min')) return duree(v);
    if (cle.endsWith('_jours')) return `${String(v).replace('.', ',')} j`;
    if (cle === 'km') return `${nombre(v)} km`;
    return nombre(v);
};

// Colonnes où une valeur haute est un signal d'alerte
const ALERTES = { taux_reprise: 20, reprises: 1, en_retard: 1, terminees_en_retard: 1, refus: 3, relances_echues: 1 };
const enAlerte = (cle, v) => ALERTES[cle] !== undefined && v !== null && v >= ALERTES[cle];

const onglet = ref(Object.keys(props.metiers).find((k) => props.stats[k]?.length) || 'mecaniciens');
const metier = computed(() => props.metiers[onglet.value]);
const lignes = computed(() => props.stats[onglet.value] || []);
const colonnes = computed(() => Object.entries(metier.value.colonnes));

// Colonne principale (la 1re) : barre de comparaison entre collaborateurs
const principale = computed(() => colonnes.value[0]?.[0]);
const maxPrincipal = computed(() => Math.max(1, ...lignes.value.map((l) => Number(l[principale.value]) || 0)));

const tri = ref(null);
const lignesTriees = computed(() => {
    if (!tri.value) return lignes.value;
    return [...lignes.value].sort((a, b) => (Number(b[tri.value]) || 0) - (Number(a[tri.value]) || 0));
});

const filtrer = (params) => router.get(route('superadmin.equipes'), { periode: props.periode.cle, siege: props.siege, ...params }, { preserveScroll: true, preserveState: true });
const lienExport = computed(() => route('superadmin.equipes.export', { periode: props.periode.cle, ...(props.siege ? { siege: props.siege } : {}) }));
</script>

<template>
    <Head title="Performance des équipes" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <Entete titre="Performance des équipes" icone="fa-people-group" :texte="periode.libelle">
                    <label class="sr-only" for="periode">Période</label>
                    <select id="periode" class="rounded-full border-0 bg-white/10 py-2 pl-4 pr-9 text-xs font-bold text-white ring-1 ring-white/15 focus:ring-2 focus:ring-[#E11D48]" :value="periode.cle" @change="filtrer({ periode: $event.target.value })">
                        <option v-for="(libelle, cle) in periode.choix" :key="cle" :value="cle" class="text-[#0B0F19]">{{ libelle }}</option>
                    </select>
                    <label class="sr-only" for="siege">Siège</label>
                    <select id="siege" class="rounded-full border-0 bg-white/10 py-2 pl-4 pr-9 text-xs font-bold text-white ring-1 ring-white/15 focus:ring-2 focus:ring-[#E11D48]" :value="siege || ''" @change="filtrer({ siege: $event.target.value || null })">
                        <option value="" class="text-[#0B0F19]">Tous les sièges</option>
                        <option v-for="(nom, code) in $page.props.sieges" :key="code" :value="code" class="text-[#0B0F19]">{{ code }}</option>
                    </select>
                    <a :href="lienExport" class="sg-btn sg-btn-sm sg-btn-glass">
                        <i class="fa-solid fa-file-excel text-emerald-600" aria-hidden="true"></i>Excel
                    </a>
                </Entete>

                <!-- ═══ MÉTIERS ═══ -->
                <nav class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 sg-rise" style="--d: 60ms" aria-label="Métiers">
                    <button
                        v-for="(m, cle) in metiers"
                        :key="cle"
                        type="button"
                        class="sg-glass flex items-center gap-3 p-3 text-left transition"
                        :class="onglet === cle ? 'sg-glass-strong ring-2 ring-[#E11D48]' : 'opacity-80 hover:opacity-100'"
                        :aria-pressed="onglet === cle"
                        @click="onglet = cle; tri = null"
                    >
                        <span class="sg-tile h-9 w-9 text-sm" :class="onglet === cle ? 'sg-tile-red' : ''"><i class="fa-solid" :class="ICONES[cle]" aria-hidden="true"></i></span>
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-black text-[#0B0F19]">{{ m.titre }}</span>
                            <span class="block text-[11px] text-gray-500">{{ stats[cle]?.length || 0 }} personne(s)</span>
                        </span>
                    </button>
                </nav>

                <!-- ═══ TABLEAU DU MÉTIER ═══ -->
                <section class="sg-glass sg-glass-strong overflow-hidden sg-rise" style="--d: 120ms" :aria-label="metier.titre">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5 sm:px-6">
                        <h2 class="sg-section-title text-[#0B0F19]"><i class="fa-solid text-[#E11D48]" :class="ICONES[onglet]" aria-hidden="true"></i>{{ metier.titre }}</h2>
                        <p class="text-xs text-gray-500">Cliquez sur un en-tête pour trier.</p>
                    </div>

                    <div v-if="!lignes.length" class="sg-empty m-5 p-8 text-center text-sm text-gray-500">
                        Personne dans ce métier{{ siege ? ` au siège ${siege}` : '' }}.
                    </div>

                    <div v-else class="mt-3 overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead>
                                <tr class="border-b border-[#0B0F19]/10 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                    <th class="px-5 py-2 font-bold sm:px-6">Collaborateur</th>
                                    <th v-for="[cle, libelle] in colonnes" :key="cle" class="px-3 py-2 text-right font-bold">
                                        <button type="button" class="inline-flex items-center gap-1 uppercase hover:text-[#0B0F19]" :class="{ 'text-[#0B0F19]': tri === cle }" @click="tri = tri === cle ? null : cle">
                                            {{ libelle }}<i v-if="tri === cle" class="fa-solid fa-arrow-down-wide-short text-[9px]" aria-hidden="true"></i>
                                        </button>
                                    </th>
                                    <th class="px-3 py-2 text-right font-bold">
                                        <button type="button" class="uppercase hover:text-[#0B0F19]" :class="{ 'text-[#0B0F19]': tri === 'actions' }" @click="tri = tri === 'actions' ? null : 'actions'">Actions dans l'appli</button>
                                    </th>
                                    <th class="px-3 py-2"><span class="sr-only">Détail</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="l in lignesTriees" :key="l.id" class="border-b border-[#0B0F19]/5 hover:bg-white/60">
                                    <td class="px-5 py-3 sm:px-6">
                                        <p class="font-bold text-[#0B0F19]">
                                            {{ l.name }}
                                            <span v-if="l.suspendu" class="sg-chip ml-1 bg-red-100 text-red-700">Suspendu</span>
                                        </p>
                                        <p class="text-[11px] text-gray-500">{{ l.siege || 'Sans siège' }}<template v-if="l.role === 'admin'"> · administrateur</template></p>
                                        <div v-if="principale" class="sg-progress mt-1.5 max-w-[160px]"><div :style="{ width: `${(Number(l[principale]) || 0) / maxPrincipal * 100}%` }"></div></div>
                                    </td>
                                    <td v-for="[cle] in colonnes" :key="cle" class="px-3 py-3 text-right tabular-nums" :class="enAlerte(cle, l[cle]) ? 'font-black text-red-700' : 'text-gray-700'">
                                        {{ formater(cle, l[cle]) }}
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-500">{{ nombre(l.actions) }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <Link :href="route('superadmin.supervision.index', { user: l.id })" class="sg-icon-btn" :aria-label="`Voir l'activité de ${l.name}`" title="Voir son activité">
                                            <i class="fa-solid fa-satellite-dish text-xs" aria-hidden="true"></i>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <ul class="space-y-1 px-5 pb-5 pt-3 text-[11px] text-gray-400 sm:px-6">
                        <li v-if="onglet === 'mecaniciens'">Reprise : le même véhicule revient chez le même mécanicien dans les {{ stats.jours_reprise }} jours. Délai : de la réception à la facture.</li>
                        <li v-if="onglet === 'charges_clients'">Conversion : client contacté sur la période qui a ensuite déposé un véhicule au garage.</li>
                        <li v-if="onglet === 'chauffeurs'">Délai d'acceptation : entre la demande du client et l'acceptation de la mission.</li>
                        <li>« Actions dans l'appli » : enregistrements, modifications et validations faits sur la période (hors simples consultations).</li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
