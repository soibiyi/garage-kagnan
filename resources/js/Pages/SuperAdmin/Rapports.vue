<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Entete from '@/Components/SuperAdmin/Entete.vue';
import Pagination from '@/Components/SuperAdmin/Pagination.vue';
import { date, dateHeure, fcfa, nombre } from '@/utils/format.js';

const props = defineProps({
    rapports: { type: Object, required: true },
    reglages: { type: Object, default: () => ({}) },
    selection: { type: Object, default: null },
});

const form = useForm({ periode: 'jour' });
const generer = (periode) => { form.periode = periode; form.post(route('superadmin.rapports.generer')); };

const titre = (r) => (r.periode === 'semaine' ? `Semaine du ${date(r.debut)} au ${date(r.fin)}` : `Journée du ${date(r.debut)}`);
const d = computed(() => props.selection?.donnees);
const sieges = computed(() => d.value?.sieges || []);
const maxSiege = computed(() => Math.max(1, ...sieges.value.map((s) => s.ca)));
</script>

<template>
    <Head title="Rapports" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <Entete titre="Rapports automatiques" icone="fa-file-lines" texte="CA, actions sensibles, erreurs, sécurité et stocks bas, chaque soir et chaque lundi.">
                    <button type="button" class="sg-btn sg-btn-sm sg-btn-glass" :disabled="form.processing" @click="generer('jour')">
                        <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>Rapport du jour
                    </button>
                    <button type="button" class="sg-btn sg-btn-sm sg-btn-red" :disabled="form.processing" @click="generer('semaine')">
                        <i class="fa-solid fa-calendar-week" aria-hidden="true"></i>Rapport de la semaine
                    </button>
                </Entete>

                <!-- Réglages -->
                <section class="sg-glass sg-glass-strong flex flex-col gap-3 p-4 text-sm sm:flex-row sm:items-center sm:justify-between sm:p-5 sg-rise" style="--d: 60ms">
                    <div class="flex flex-wrap gap-2">
                        <span class="sg-chip" :class="reglages.quotidien ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                            <i class="fa-solid text-[9px]" :class="reglages.quotidien ? 'fa-check' : 'fa-xmark'" aria-hidden="true"></i>Quotidien · 20 h
                        </span>
                        <span class="sg-chip" :class="reglages.hebdomadaire ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                            <i class="fa-solid text-[9px]" :class="reglages.hebdomadaire ? 'fa-check' : 'fa-xmark'" aria-hidden="true"></i>Hebdomadaire · lundi 7 h
                        </span>
                        <span class="sg-chip" :class="reglages.email ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'">
                            <i class="fa-solid text-[9px]" :class="reglages.email ? 'fa-envelope' : 'fa-xmark'" aria-hidden="true"></i>Par e-mail
                        </span>
                    </div>
                    <p class="text-xs text-gray-500">
                        Envoyés dans votre cloche<template v-if="reglages.email"> et par e-mail</template>.
                        <Link :href="route('superadmin.systeme')" class="font-bold text-[#E11D48] hover:underline">Modifier</Link>
                    </p>
                </section>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Liste -->
                    <section class="space-y-3 lg:col-span-1" aria-label="Rapports générés">
                        <p v-if="!rapports.data.length" class="sg-empty p-8 text-center text-sm text-gray-500">Aucun rapport pour l'instant. Le premier arrivera ce soir à 20 h, ou générez-en un maintenant.</p>
                        <Link
                            v-for="r in rapports.data"
                            :key="r.id"
                            :href="route('superadmin.rapports.show', r.id)"
                            preserve-scroll
                            class="sg-glass block p-4 transition"
                            :class="selection?.id === r.id ? 'sg-glass-strong ring-2 ring-[#E11D48]' : 'hover:bg-white/80'"
                        >
                            <p class="flex items-center justify-between gap-2">
                                <span class="text-sm font-black text-[#0B0F19]">{{ titre(r) }}</span>
                                <span class="sg-chip" :class="r.periode === 'semaine' ? 'bg-[#0B0F19] text-white' : 'bg-white text-gray-600'">{{ r.periode === 'semaine' ? 'Semaine' : 'Jour' }}</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">{{ fcfa(r.ca) }} facturés · {{ fcfa(r.encaisse) }} encaissés</p>
                            <p class="mt-1 flex flex-wrap gap-1.5 text-[11px]">
                                <span v-if="r.actions_sensibles" class="sg-chip bg-amber-100 text-amber-800">{{ r.actions_sensibles }} action(s) sensible(s)</span>
                                <span v-if="r.erreurs" class="sg-chip bg-red-100 text-red-800">{{ r.erreurs }} erreur(s)</span>
                                <span class="text-gray-400">{{ r.auteur ? `par ${r.auteur}` : 'automatique' }} · {{ dateHeure(r.created_at) }}</span>
                            </p>
                        </Link>
                        <Pagination :page="rapports" />
                    </section>

                    <!-- Détail -->
                    <section class="lg:col-span-2">
                        <div v-if="!selection" class="sg-empty flex h-full min-h-[240px] flex-col items-center justify-center p-8 text-center">
                            <i class="fa-solid fa-file-lines text-3xl text-gray-300" aria-hidden="true"></i>
                            <p class="mt-3 text-sm text-gray-500">Choisissez un rapport pour le consulter.</p>
                        </div>

                        <article v-else class="space-y-4">
                            <div class="sg-glass sg-glass-strong flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-xl font-black text-[#0B0F19]">{{ titre(selection) }}</h2>
                                    <p class="text-xs text-gray-500">Généré {{ dateHeure(selection.created_at) }} · {{ selection.auteur ? `par ${selection.auteur}` : 'automatiquement' }}</p>
                                </div>
                                <a :href="route('superadmin.rapports.pdf', selection.id)" class="sg-btn sg-btn-sm sg-btn-ink"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i>Télécharger en PDF</a>
                            </div>

                            <!-- Argent -->
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="sg-glass sg-glass-strong p-4">
                                    <p class="text-[11px] font-bold uppercase text-gray-500">CA facturé</p>
                                    <p class="mt-1 text-lg font-black tabular-nums">{{ fcfa(d.argent.ca) }}</p>
                                    <p class="text-[11px] text-gray-400">{{ d.argent.nb_factures }} facture(s)</p>
                                </div>
                                <div class="sg-glass sg-glass-strong p-4">
                                    <p class="text-[11px] font-bold uppercase text-gray-500">Encaissé</p>
                                    <p class="mt-1 text-lg font-black tabular-nums text-emerald-700">{{ fcfa(d.argent.encaisse) }}</p>
                                </div>
                                <div class="sg-glass sg-glass-strong p-4">
                                    <p class="text-[11px] font-bold uppercase text-gray-500">Impayés à ce jour</p>
                                    <p class="mt-1 text-lg font-black tabular-nums text-amber-700">{{ fcfa(d.argent.impayes) }}</p>
                                </div>
                                <div class="sg-glass sg-glass-strong p-4">
                                    <p class="text-[11px] font-bold uppercase text-gray-500">Véhicules reçus</p>
                                    <p class="mt-1 text-lg font-black tabular-nums">{{ nombre(d.activite.vehicules_recus) }}</p>
                                    <p class="text-[11px] text-gray-400">{{ d.activite.en_cours }} en cours</p>
                                </div>
                            </div>

                            <!-- Sièges -->
                            <div class="sg-glass sg-glass-strong p-5">
                                <h3 class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-building text-[#E11D48]" aria-hidden="true"></i>Par siège</h3>
                                <ul class="mt-3 space-y-3">
                                    <li v-for="s in sieges" :key="s.code">
                                        <div class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="font-bold">{{ s.code }} <span class="font-normal text-gray-500">· {{ s.interventions }} véhicule(s)<template v-if="s.ruptures"> · <span class="text-red-700">{{ s.ruptures }} rupture(s)</span></template></span></span>
                                            <span class="tabular-nums">{{ fcfa(s.ca) }}</span>
                                        </div>
                                        <div class="sg-progress mt-1"><div :style="{ width: `${(s.ca / maxSiege) * 100}%` }"></div></div>
                                    </li>
                                </ul>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-2">
                                <!-- Actions sensibles -->
                                <div class="sg-glass sg-glass-strong p-5">
                                    <h3 class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-shield-halved text-amber-600" aria-hidden="true"></i>Actions sensibles ({{ d.actions_sensibles.total }})</h3>
                                    <p v-if="!d.actions_sensibles.liste.length" class="mt-3 text-sm text-gray-500">Aucune.</p>
                                    <ul v-else class="mt-3 space-y-2">
                                        <li v-for="(a, i) in d.actions_sensibles.liste" :key="i" class="sg-surface p-2.5 text-sm">
                                            <p class="font-bold text-red-700">{{ a.alerte }}</p>
                                            <p class="text-[11px] text-gray-500">{{ a.auteur }}<template v-if="a.siege"> · {{ a.siege }}</template> · {{ dateHeure(a.date) }}</p>
                                        </li>
                                    </ul>
                                    <Link :href="route('superadmin.controle')" class="mt-3 inline-block text-xs font-bold text-[#E11D48] hover:underline">Ouvrir le contrôle →</Link>
                                </div>

                                <!-- Sécurité + erreurs -->
                                <div class="space-y-4">
                                    <div class="sg-glass sg-glass-strong p-5">
                                        <h3 class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-user-lock text-violet-600" aria-hidden="true"></i>Sécurité</h3>
                                        <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                            <div class="sg-surface p-2.5"><dt class="text-[11px] text-gray-500">Connexions</dt><dd class="font-black">{{ d.securite.connexions }}</dd></div>
                                            <div class="sg-surface p-2.5"><dt class="text-[11px] text-gray-500">Échecs</dt><dd class="font-black" :class="d.securite.echecs ? 'text-red-700' : ''">{{ d.securite.echecs }}</dd></div>
                                            <div class="sg-surface p-2.5"><dt class="text-[11px] text-gray-500">Hors horaires</dt><dd class="font-black">{{ d.securite.hors_horaires }}</dd></div>
                                            <div class="sg-surface p-2.5"><dt class="text-[11px] text-gray-500">Comptes suspendus</dt><dd class="font-black">{{ d.securite.comptes_suspendus }}</dd></div>
                                        </dl>
                                    </div>
                                    <div class="sg-glass sg-glass-strong p-5">
                                        <h3 class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-bug text-red-600" aria-hidden="true"></i>Erreurs</h3>
                                        <p class="mt-2 text-sm text-gray-600">{{ d.erreurs.nouvelles }} nouvelle(s) · {{ d.erreurs.ouvertes }} ouverte(s)</p>
                                        <ul v-if="d.erreurs.plus_frequentes.length" class="mt-2 space-y-1 text-xs text-gray-600">
                                            <li v-for="(e, i) in d.erreurs.plus_frequentes" :key="i"><strong>{{ e.type }}</strong> ×{{ e.occurrences }} — {{ e.message }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Stocks bas -->
                            <div class="sg-glass sg-glass-strong p-5">
                                <h3 class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-boxes-stacked text-[#E11D48]" aria-hidden="true"></i>Stocks bas (seuil {{ d.stocks_bas.seuil }})</h3>
                                <p v-if="!d.stocks_bas.liste.length" class="mt-3 text-sm text-gray-500">Aucune pièce sous le seuil.</p>
                                <div v-else class="mt-3 overflow-x-auto">
                                    <table class="w-full min-w-[420px] text-sm">
                                        <tbody>
                                            <tr v-for="(s, i) in d.stocks_bas.liste" :key="i" class="border-b border-[#0B0F19]/5">
                                                <td class="py-1.5 pr-3 font-bold">{{ s.piece }}</td>
                                                <td class="py-1.5 pr-3 text-gray-500">{{ s.reference || '—' }}</td>
                                                <td class="py-1.5 pr-3">{{ s.siege }}</td>
                                                <td class="py-1.5 text-right font-black tabular-nums" :class="s.reste === 0 ? 'text-red-700' : 'text-amber-700'">{{ s.reste }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </article>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
