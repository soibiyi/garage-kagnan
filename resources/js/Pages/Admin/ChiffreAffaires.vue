<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    annees: { type: Array, default: () => [] },
    siegeFiltre: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

/* -------------------------------------------------------------------------- */
/* Constantes & formatage                                                      */
/* -------------------------------------------------------------------------- */

const MOIS_COURTS = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
const ROUGE = '#C8102E';
const NOIR = '#1A1A1A';

const formatNombre = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n || 0);
const formatMontant = (n) => formatNombre(n) + ' FCFA';

// 1 250 000 -> "1,3 M" | 48 000 -> "48 k"
const formatCompact = (n) => {
    const v = Math.abs(n || 0);
    if (v >= 1e6) return (n / 1e6).toFixed(1).replace('.0', '').replace('.', ',') + ' M';
    if (v >= 1e3) return Math.round(n / 1e3) + ' k';
    return String(Math.round(n || 0));
};

const formatEvol = (v) => (v >= 0 ? '+' : '') + v.toFixed(1).replace('.', ',') + ' %';
const formatPart = (v) => v.toFixed(1).replace('.', ',') + ' %';

/* -------------------------------------------------------------------------- */
/* Sélection de l'année                                                        */
/* -------------------------------------------------------------------------- */

const anneesDesc = computed(() => [...props.annees].sort((a, b) => b.annee - a.annee));
const anneesTriees = computed(() => [...props.annees].sort((a, b) => a.annee - b.annee));

const anneeSelectionnee = ref(anneesDesc.value[0]?.annee ?? null);
const pdfContainer = ref(null);
const isGeneratingPdf = ref(false);

watch(() => props.annees, () => {
    if (!props.annees.some(a => a.annee === anneeSelectionnee.value)) {
        anneeSelectionnee.value = anneesDesc.value[0]?.annee ?? null;
    }
});

const donneesAnnee = computed(() => props.annees.find(a => a.annee === anneeSelectionnee.value) ?? null);
const donneesAnneePrecedente = computed(() => props.annees.find(a => a.annee === anneeSelectionnee.value - 1) ?? null);

/* -------------------------------------------------------------------------- */
/* Indicateurs                                                                 */
/* -------------------------------------------------------------------------- */

const anneeCourante = new Date().getFullYear();

// Nombre de mois réellement écoulés (12 pour une année passée)
const moisEcoules = computed(() =>
    anneeSelectionnee.value < anneeCourante ? 12 : new Date().getMonth() + 1
);
const anneePartielle = computed(() => moisEcoules.value < 12);
const labelMoisCourant = computed(
    () => donneesAnnee.value?.mois.find(m => m.numero === moisEcoules.value)?.label ?? ''
);

const cumulJusqua = (annee, n) =>
    annee?.mois.filter(m => m.numero <= n).reduce((s, m) => s + m.total, 0) ?? 0;

const moyenneMensuelle = computed(() =>
    donneesAnnee.value ? donneesAnnee.value.total / moisEcoules.value : 0
);

const panierMoyen = computed(() => {
    if (!donneesAnnee.value || !donneesAnnee.value.nombre_paiements) return 0;
    return donneesAnnee.value.total / donneesAnnee.value.nombre_paiements;
});

const meilleurMois = computed(() => {
    if (!donneesAnnee.value) return null;
    const best = [...donneesAnnee.value.mois].sort((a, b) => b.total - a.total)[0];
    return best && best.total > 0 ? best : null;
});

// Comparaison à période égale (janvier -> mois courant), pas année partielle vs année complète
const variation = computed(() => {
    if (!donneesAnnee.value || !donneesAnneePrecedente.value) return null;
    const base = cumulJusqua(donneesAnneePrecedente.value, moisEcoules.value);
    if (!base) return null;
    return ((cumulJusqua(donneesAnnee.value, moisEcoules.value) - base) / base) * 100;
});

const moisEnrichis = computed(() => {
    if (!donneesAnnee.value) return [];
    const total = donneesAnnee.value.total || 0;
    const max = Math.max(...donneesAnnee.value.mois.map(m => m.total), 1);
    const precedents = donneesAnneePrecedente.value?.mois ?? [];

    return donneesAnnee.value.mois.map(m => {
        const prev = precedents.find(p => p.numero === m.numero)?.total ?? 0;
        return {
            ...m,
            part: total ? (m.total / total) * 100 : 0,
            largeur: (m.total / max) * 100, // barre relative au meilleur mois = lisible
            evol: prev ? ((m.total - prev) / prev) * 100 : null,
            futur: anneeSelectionnee.value === anneeCourante && m.numero > moisEcoules.value,
        };
    });
});

/* -------------------------------------------------------------------------- */
/* Animation du total (un seul moment animé sur la page)                       */
/* -------------------------------------------------------------------------- */

const totalAffiche = ref(donneesAnnee.value?.total ?? 0);
let rafId = null;

const animerTotal = (cible) => {
    if (typeof window === 'undefined') return;
    cancelAnimationFrame(rafId);

    const reduit = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (reduit) {
        totalAffiche.value = cible;
        return;
    }

    const depart = totalAffiche.value;
    const debut = performance.now();
    const duree = 600;

    const pas = (t) => {
        const p = Math.min((t - debut) / duree, 1);
        const ease = 1 - Math.pow(1 - p, 3);
        totalAffiche.value = depart + (cible - depart) * ease;
        if (p < 1) rafId = requestAnimationFrame(pas);
    };
    rafId = requestAnimationFrame(pas);
};

watch(() => donneesAnnee.value?.total ?? 0, animerTotal);
onBeforeUnmount(() => cancelAnimationFrame(rafId));

// Pendant l'export, on affiche la valeur exacte (pas une valeur intermédiaire d'animation)
const totalHero = computed(() => (isGeneratingPdf.value ? donneesAnnee.value?.total ?? 0 : totalAffiche.value));

/* -------------------------------------------------------------------------- */
/* Graphique mensuel : année N (barres) vs année N-1 (courbe pointillée)       */
/* -------------------------------------------------------------------------- */

const serieAnneeN = computed(() =>
    MOIS_COURTS.map((_, i) => {
        const m = moisEnrichis.value.find(x => x.numero === i + 1);
        return !m || m.futur ? null : m.total;
    })
);

const serieAnneePrecedente = computed(() =>
    MOIS_COURTS.map((_, i) => donneesAnneePrecedente.value?.mois.find(m => m.numero === i + 1)?.total ?? 0)
);

const chartMensuelSeries = computed(() => {
    const series = [{ name: String(anneeSelectionnee.value), type: 'column', data: serieAnneeN.value }];
    if (donneesAnneePrecedente.value) {
        series.push({ name: String(anneeSelectionnee.value - 1), type: 'line', data: serieAnneePrecedente.value });
    }
    return series;
});

const chartMensuelOptions = computed(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        fontFamily: 'Inter, system-ui, sans-serif',
        zoom: { enabled: false },
        animations: { enabled: !isGeneratingPdf.value, easing: 'easeout', speed: 400 },
    },
    stroke: { width: [0, 2], curve: 'smooth', dashArray: [0, 5] },
    colors: [ROUGE, '#6B7280'],
    plotOptions: {
        bar: { borderRadius: 6, columnWidth: '50%', dataLabels: { position: 'top' } },
    },
    dataLabels: {
        enabled: true,
        enabledOnSeries: [0],
        formatter: (val) => (val > 0 ? formatCompact(val) : ''),
        style: { fontSize: '10px', colors: ['#4B5563'] },
        offsetY: -20,
    },
    markers: { size: [0, 3] },
    xaxis: {
        categories: MOIS_COURTS,
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: { labels: { formatter: (val) => formatCompact(val) } },
    tooltip: {
        shared: true,
        intersect: false,
        y: { formatter: (val) => (val == null ? '—' : formatMontant(val)) },
    },
    legend: { position: 'top', horizontalAlign: 'right' },
    grid: { borderColor: '#F3F4F6' },
    responsive: [
        {
            breakpoint: 640,
            options: {
                dataLabels: { enabled: false },
                plotOptions: { bar: { columnWidth: '65%' } },
            },
        },
    ],
}));

/* -------------------------------------------------------------------------- */
/* Graphique annuel (cliquable)                                                */
/* -------------------------------------------------------------------------- */

const chartAnnuelSeries = computed(() => [
    { name: 'Total encaissé', data: anneesTriees.value.map(a => a.total) },
]);

const chartAnnuelOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        fontFamily: 'Inter, system-ui, sans-serif',
        animations: { enabled: !isGeneratingPdf.value, speed: 400 },
        events: {
            dataPointSelection: (event, chartContext, config) => {
                const annee = anneesTriees.value[config.dataPointIndex]?.annee;
                if (annee) anneeSelectionnee.value = annee;
            },
        },
    },
    colors: [
        function ({ dataPointIndex }) {
            return anneesTriees.value[dataPointIndex]?.annee === anneeSelectionnee.value ? ROUGE : NOIR;
        },
    ],
    plotOptions: {
        bar: {
            borderRadius: 8,
            columnWidth: '35%',
            distributed: true,
            dataLabels: { position: 'top' },
        },
    },
    states: { active: { filter: { type: 'none' } } },
    dataLabels: {
        enabled: true,
        formatter: (val) => formatCompact(val),
        style: { fontSize: '11px', fontWeight: 'bold' },
        offsetY: -20,
    },
    xaxis: {
        categories: anneesTriees.value.map(a => String(a.annee)),
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: { show: false },
    tooltip: { y: { formatter: (val) => formatMontant(val) } },
    legend: { show: false },
    grid: { show: false },
}));

/* -------------------------------------------------------------------------- */
/* Export PDF                                                                  */
/* -------------------------------------------------------------------------- */

const dateEdition = new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const exporterPDF = async () => {
    if (!pdfContainer.value || !donneesAnnee.value) return;
    isGeneratingPdf.value = true;

    // Laisse le temps d'afficher l'en-tête du rapport et de couper les animations des graphiques
    await nextTick();
    await new Promise(resolve => setTimeout(resolve, 400));

    try {
        const { default: html2pdf } = await import('html2pdf.js');
        await html2pdf()
            .set({
                margin: 10,
                filename: `Rapport_CA_${anneeSelectionnee.value}${props.siegeFiltre ? '_' + props.siegeFiltre : ''}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'], avoid: ['.pdf-card'] },
            })
            .from(pdfContainer.value)
            .save();
    } catch (e) {
        console.error('Erreur génération PDF:', e);
    } finally {
        isGeneratingPdf.value = false;
    }
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-8 sm:py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">

      <!-- En-tête -->
      <div class="flex flex-wrap justify-between items-end gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">Historique du chiffre d'affaires</h1>
          <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <p class="text-sm text-gray-500">Évolution des encaissements, mois par mois et année par année.</p>
            <span
              v-if="siegeFiltre"
              class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-gray-900 text-white"
            >
              <i class="fa-solid fa-location-dot text-[#C8102E]"></i>
              Siège {{ siegeFiltre }}
            </span>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
          <SiegeSwitcher v-if="user?.role === 'admin'" :current="siegeFiltre" />

          <div v-if="annees.length">
            <label for="select-annee" class="sr-only">Année</label>
            <select
              id="select-annee"
              v-model="anneeSelectionnee"
              class="min-h-[44px] text-sm font-semibold border border-gray-300 rounded-xl py-2.5 pl-3 pr-8 bg-white shadow-sm focus:border-[#C8102E] focus:ring-2 focus:ring-[#C8102E]/30 cursor-pointer transition"
            >
              <option v-for="a in anneesDesc" :key="a.annee" :value="a.annee">{{ a.annee }}</option>
            </select>
          </div>

          <button
            v-if="annees.length"
            type="button"
            @click="exporterPDF"
            :disabled="isGeneratingPdf || !donneesAnnee"
            :aria-busy="isGeneratingPdf"
            class="inline-flex items-center justify-center gap-2 min-h-[44px] text-sm font-extrabold px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-800 shadow-sm transition hover:bg-gray-100 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i
              class="fa-solid w-4 text-[#C8102E]"
              :class="isGeneratingPdf ? 'fa-circle-notch fa-spin' : 'fa-file-pdf'"
            ></i>
            <span>{{ isGeneratingPdf ? 'Génération en cours…' : 'Télécharger le PDF' }}</span>
          </button>

          <Link
            :href="user?.role === 'admin' ? route('admin.users.index') : route('dashboard')"
            class="inline-flex items-center gap-2 min-h-[44px] text-sm font-semibold px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-900 shadow-sm transition hover:bg-gray-100 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C8102E]/40"
          >
            <i class="fa-solid fa-arrow-left text-xs"></i>
            Retour
          </Link>
        </div>
      </div>

      <!-- Contenu (capturé pour le PDF) -->
      <div ref="pdfContainer" class="space-y-6 bg-gray-50 p-2 rounded-2xl">

        <!-- En-tête du rapport : visible uniquement dans le PDF -->
        <div
          v-if="isGeneratingPdf"
          class="pdf-card flex items-end justify-between gap-4 border-b-2 border-[#C8102E] pb-3"
        >
          <div>
            <h1 class="text-xl font-black text-gray-900">Rapport du chiffre d'affaires {{ anneeSelectionnee }}</h1>
            <p v-if="siegeFiltre" class="text-sm text-gray-600 mt-0.5">Siège {{ siegeFiltre }}</p>
          </div>
          <p class="text-xs text-gray-500 shrink-0">Édité le {{ dateEdition }}</p>
        </div>

        <!-- État vide -->
        <div
          v-if="!annees.length"
          class="bg-white px-6 py-16 rounded-2xl border border-gray-200 text-center shadow-sm"
        >
          <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
            <i class="fa-solid fa-chart-line text-xl text-gray-400"></i>
          </div>
          <p class="text-base font-extrabold text-gray-900">Aucun paiement enregistré</p>
          <p class="mt-1 text-sm text-gray-500">Les statistiques apparaîtront dès le premier encaissement.</p>
        </div>

        <template v-else>
          <!-- Indicateurs -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Carte principale -->
            <div class="pdf-card sm:col-span-2 lg:row-span-2 rounded-2xl bg-[#1A1A1A] p-6 sm:p-8 text-white flex flex-col justify-between min-h-[200px]">
              <p class="text-sm font-semibold text-gray-400">Total encaissé en {{ anneeSelectionnee }}</p>
              <div class="my-6">
                <p class="font-black tracking-tight tabular-nums leading-none text-4xl sm:text-5xl">
                  {{ formatNombre(totalHero) }}
                  <span class="text-lg sm:text-xl font-bold text-[#C8102E]">FCFA</span>
                </p>
              </div>
              <p class="text-sm text-gray-400">
                {{ donneesAnnee?.nombre_paiements || 0 }} encaissement(s)
                <template v-if="anneePartielle"> depuis janvier</template>
              </p>
            </div>

            <div class="pdf-card bg-white p-5 rounded-xl border border-gray-200 flex flex-col justify-between">
              <p class="text-xs font-semibold text-gray-500">Moyenne mensuelle</p>
              <p class="mt-2 text-xl xl:text-2xl font-black text-gray-900 tabular-nums">
                {{ formatNombre(moyenneMensuelle) }}
                <span class="text-xs font-semibold text-gray-400">FCFA</span>
              </p>
              <p class="mt-1 text-xs text-gray-400">Sur {{ moisEcoules }} mois écoulé(s)</p>
            </div>

            <div class="pdf-card bg-white p-5 rounded-xl border border-gray-200 flex flex-col justify-between">
              <p class="text-xs font-semibold text-gray-500">Panier moyen</p>
              <p class="mt-2 text-xl xl:text-2xl font-black text-gray-900 tabular-nums">
                {{ formatNombre(panierMoyen) }}
                <span class="text-xs font-semibold text-gray-400">FCFA</span>
              </p>
              <p class="mt-1 text-xs text-gray-400">Par transaction</p>
            </div>

            <div class="pdf-card bg-white p-5 rounded-xl border border-gray-200 flex flex-col justify-between">
              <p class="text-xs font-semibold text-gray-500">Meilleur mois</p>
              <template v-if="meilleurMois">
                <p class="mt-2 text-xl xl:text-2xl font-black text-gray-900 truncate">{{ meilleurMois.label }}</p>
                <p class="mt-1 text-xs font-bold text-[#C8102E] tabular-nums">{{ formatMontant(meilleurMois.total) }}</p>
              </template>
              <p v-else class="mt-2 text-xl xl:text-2xl font-black text-gray-300">—</p>
            </div>

            <div class="pdf-card bg-white p-5 rounded-xl border border-gray-200 flex flex-col justify-between">
              <p class="text-xs font-semibold text-gray-500">Évolution vs {{ anneeSelectionnee - 1 }}</p>
              <p
                v-if="variation !== null"
                class="mt-2 flex items-center gap-2 text-xl xl:text-2xl font-black tabular-nums"
                :class="variation >= 0 ? 'text-emerald-600' : 'text-rose-600'"
              >
                <i class="fa-solid text-base" :class="variation >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'"></i>
                {{ formatEvol(variation) }}
              </p>
              <p v-else class="mt-2 text-xl xl:text-2xl font-black text-gray-300">—</p>
              <p class="mt-1 text-xs text-gray-400">
                <template v-if="anneePartielle">À période égale, jusqu'à {{ labelMoisCourant }}</template>
                <template v-else>Année complète</template>
              </p>
            </div>

          </div>

          <!-- Graphique mensuel -->
          <div class="pdf-card bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-200">
            <div class="mb-4">
              <h2 class="text-base font-extrabold text-gray-900">Encaissements de {{ anneeSelectionnee }}, mois par mois</h2>
              <p class="text-xs text-gray-500">
                <template v-if="donneesAnneePrecedente">La courbe pointillée correspond à {{ anneeSelectionnee - 1 }}.</template>
                <template v-else>Aucune donnée disponible pour {{ anneeSelectionnee - 1 }}.</template>
              </p>
            </div>

            <VueApexCharts
              type="line"
              height="300"
              :options="chartMensuelOptions"
              :series="chartMensuelSeries"
            />
          </div>

          <!-- Graphique annuel -->
          <div class="pdf-card bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-200">
            <h2 class="text-base font-extrabold text-gray-900 mb-1">Comparatif annuel</h2>
            <p class="text-xs text-gray-500 mb-4">Touchez une barre pour afficher l'année correspondante.</p>

            <div class="[&_.apexcharts-bar-area]:cursor-pointer">
              <VueApexCharts
                type="bar"
                height="220"
                :options="chartAnnuelOptions"
                :series="chartAnnuelSeries"
              />
            </div>
          </div>

          <!-- Tableau détaillé -->
          <div class="pdf-card bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-5 sm:p-6 border-b border-gray-100">
              <h2 class="text-base font-extrabold text-gray-900">Détail mensuel de {{ anneeSelectionnee }}</h2>
              <p class="text-xs text-gray-500 mt-0.5">Montant, poids dans l'année et écart avec {{ anneeSelectionnee - 1 }}.</p>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-xs font-bold text-gray-500 border-b border-gray-100">
                  <tr>
                    <th class="px-4 sm:px-6 py-3.5">Mois</th>
                    <th class="hidden sm:table-cell px-6 py-3.5 w-1/3">Poids dans l'année</th>
                    <th class="hidden sm:table-cell px-6 py-3.5 text-right">Part</th>
                    <th class="px-4 sm:px-6 py-3.5 text-right">vs {{ anneeSelectionnee - 1 }}</th>
                    <th class="px-4 sm:px-6 py-3.5 text-right">Chiffre d'affaires</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                  <tr v-for="m in moisEnrichis" :key="m.numero" class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 sm:px-6 py-3.5 font-bold" :class="m.futur ? 'text-gray-300' : 'text-gray-900'">
                      {{ m.label }}
                    </td>

                    <td class="hidden sm:table-cell px-6 py-3.5">
                      <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden" aria-hidden="true">
                        <div
                          class="h-2 rounded-full transition-[width] duration-500 ease-out motion-reduce:transition-none"
                          :class="meilleurMois?.numero === m.numero ? 'bg-[#C8102E]' : 'bg-gray-400'"
                          :style="{ width: m.largeur + '%' }"
                        ></div>
                      </div>
                    </td>

                    <td class="hidden sm:table-cell px-6 py-3.5 text-right font-bold text-xs tabular-nums"
                        :class="m.futur ? 'text-gray-300' : 'text-gray-500'">
                      {{ m.futur ? '—' : formatPart(m.part) }}
                    </td>

                    <td class="px-4 sm:px-6 py-3.5 text-right text-xs font-bold tabular-nums">
                      <span v-if="m.futur || m.evol === null" class="text-gray-300">—</span>
                      <span v-else :class="m.evol >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                        <i class="fa-solid mr-1" :class="m.evol >= 0 ? 'fa-caret-up' : 'fa-caret-down'"></i>{{ formatEvol(m.evol) }}
                      </span>
                    </td>

                    <td
                      class="px-4 sm:px-6 py-3.5 text-right tabular-nums"
                      :class="m.futur || m.total === 0 ? 'text-gray-300 font-normal' : 'text-gray-900 font-black'"
                    >
                      {{ m.futur ? '—' : formatMontant(m.total) }}
                    </td>
                  </tr>
                </tbody>

                <tfoot class="bg-gray-50 border-t border-gray-200 font-black">
                  <tr>
                    <td class="px-4 sm:px-6 py-4 text-gray-900 text-sm">Total {{ anneePartielle ? 'à date' : 'annuel' }}</td>
                    <td class="hidden sm:table-cell px-6 py-4"></td>
                    <td class="hidden sm:table-cell px-6 py-4 text-right text-gray-900 tabular-nums">100 %</td>
                    <td class="px-4 sm:px-6 py-4 text-right text-xs tabular-nums"
                        :class="variation === null ? 'text-gray-300' : variation >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                      {{ variation === null ? '—' : formatEvol(variation) }}
                    </td>
                    <td class="px-4 sm:px-6 py-4 text-right text-base sm:text-lg text-[#C8102E] tabular-nums">
                      {{ formatMontant(donneesAnnee?.total) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

        </template>
      </div>

    </div>
  </div>
</template>