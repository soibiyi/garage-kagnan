<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';
import VueApexCharts from 'vue3-apexcharts';
import html2pdf from 'html2pdf.js';

const props = defineProps({
    annees: { type: Array, default: () => [] },
    siegeFiltre: { type: String, default: null },
});

// Année sélectionnée
const anneeSelectionnee = ref(props.annees[0]?.annee ?? null);
const pdfContainer = ref(null);
const isGeneratingPdf = ref(false);

const donneesAnnee = computed(() => props.annees.find(a => a.annee === anneeSelectionnee.value) ?? null);
const donneesAnneePrecedente = computed(() => props.annees.find(a => a.annee === anneeSelectionnee.value - 1) ?? null);

watch(() => props.annees, (liste) => {
    if (!liste.some(a => a.annee === anneeSelectionnee.value)) {
        anneeSelectionnee.value = liste[0]?.annee ?? null;
    }
});

const formatMontant = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n || 0) + ' FCFA';

// ---- KPIs ----
const moyenneMensuelle = computed(() => (donneesAnnee.value ? donneesAnnee.value.total / 12 : 0));

const panierMoyen = computed(() => {
    if (!donneesAnnee.value || !donneesAnnee.value.nombre_paiements) return 0;
    return donneesAnnee.value.total / donneesAnnee.value.nombre_paiements;
});

const meilleurMois = computed(() => {
    if (!donneesAnnee.value) return null;
    const best = [...donneesAnnee.value.mois].sort((a, b) => b.total - a.total)[0];
    return best && best.total > 0 ? best : null;
});

const variation = computed(() => {
    if (!donneesAnnee.value || !donneesAnneePrecedente.value || donneesAnneePrecedente.value.total === 0) return null;
    return ((donneesAnnee.value.total - donneesAnneePrecedente.value.total) / donneesAnneePrecedente.value.total) * 100;
});

// ---- Tableau avec Poids (%) ----
const moisAvecPourcentage = computed(() => {
    if (!donneesAnnee.value) return [];
    const totalAn = donneesAnnee.value.total || 1;
    return donneesAnnee.value.mois.map(m => ({
        ...m,
        pourcentage: ((m.total / totalAn) * 100).toFixed(1)
    }));
});

// ---- Configuration ApexCharts : Graphique Mensuel (Barres & Courbe de Tendance) ----
const chartMensuelOptions = computed(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        fontFamily: 'Inter, system-ui, sans-serif',
        zoom: { enabled: false }
    },
    stroke: {
        width: [0, 3],
        curve: 'smooth'
    },
    colors: ['#C8102E', '#1A1A1A'],
    plotOptions: {
        bar: {
            borderRadius: 6,
            columnWidth: '45%',
            dataLabels: { position: 'top' }
        }
    },
    dataLabels: {
        enabled: true,
        enabledOnSeries: [0],
        formatter: (val) => val > 0 ? (val / 1000000).toFixed(1) + 'M' : '',
        style: { fontSize: '10px', colors: ['#4B5563'] },
        offsetY: -20
    },
    xaxis: {
        categories: donneesAnnee.value?.mois.map(m => m.label.slice(0, 3)) || [],
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: {
        labels: {
            formatter: (val) => (val / 1000000).toFixed(0) + ' M FCFA'
        }
    },
    tooltip: {
        y: {
            formatter: (val) => formatMontant(val)
        }
    },
    legend: { position: 'top', horizontalAlign: 'right' },
    grid: { borderColor: '#F3F4F6' }
}));

const chartMensuelSeries = computed(() => {
    const dataCurrent = donneesAnnee.value?.mois.map(m => m.total) || [];
    return [
        { name: `CA ${anneeSelectionnee.value}`, type: 'column', data: dataCurrent },
        { name: 'Tendance', type: 'line', data: dataCurrent }
    ];
});

// ---- Configuration ApexCharts : Comparaison Pluri-Annuelle ----
const anneesTriees = computed(() => [...props.annees].sort((a, b) => a.annee - b.annee));

const chartAnnuelOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        events: {
            click: (event, chartContext, config) => {
                if (config.dataPointIndex !== undefined && config.dataPointIndex !== -1) {
                    const anneeCliquee = anneesTriees.value[config.dataPointIndex]?.annee;
                    if (anneeCliquee) anneeSelectionnee.value = anneeCliquee;
                }
            }
        }
    },
    colors: [
        function({ dataPointIndex }) {
            return anneesTriees.value[dataPointIndex]?.annee === anneeSelectionnee.value ? '#C8102E' : '#1A1A1A';
        }
    ],
    plotOptions: {
        bar: {
            borderRadius: 8,
            columnWidth: '35%',
            distributed: true,
            dataLabels: { position: 'top' }
        }
    },
    dataLabels: {
        enabled: true,
        formatter: (val) => (val / 1000000).toFixed(1) + 'M',
        style: { fontSize: '11px', fontWeight: 'bold' },
        offsetY: -20
    },
    xaxis: {
        categories: anneesTriees.value.map(a => a.annee),
        axisBorder: { show: false }
    },
    yaxis: { show: false },
    legend: { show: false },
    grid: { show: false }
}));

const chartAnnuelSeries = computed(() => [
    { name: 'Total Encaissement', data: anneesTriees.value.map(a => a.total) }
]);

// ---- Génération Directe du PDF avec html2pdf.js ----
const exporterPDF = async () => {
    if (!pdfContainer.value) return;
    isGeneratingPdf.value = true;

    const opt = {
        margin: [10, 10, 10, 10],
        filename: `Rapport_CA_${anneeSelectionnee.value}${props.siegeFiltre ? '_' + props.siegeFiltre : ''}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    try {
        await html2pdf().set(opt).from(pdfContainer.value).save();
    } catch (e) {
        console.error('Erreur génération PDF:', e);
    } finally {
        isGeneratingPdf.value = false;
    }
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">

      <!-- En-tête -->
      <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
          <h1 class="text-3xl font-black text-gray-900 tracking-tight">Historique du Chiffre d'affaires</h1>
          <p class="text-sm text-gray-500 mt-1">
            Analyse dynamique et évolution des encaissements
            <span v-if="siegeFiltre" class="font-bold text-gray-800"> — Siège {{ siegeFiltre }}</span>
          </p>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center gap-3">
          <SiegeSwitcher :current="siegeFiltre" />
          
          <select
            v-if="annees.length"
            v-model="anneeSelectionnee"
            class="text-sm font-semibold border border-gray-300 rounded-xl py-2.5 pl-3 pr-8 bg-white shadow-sm focus:border-[#C8102E] focus:ring-0 cursor-pointer"
          >
            <option v-for="a in annees" :key="a.annee" :value="a.annee">{{ a.annee }}</option>
          </select>

          <!-- Bouton Exportation PDF -->
          <button
            v-if="annees.length"
            type="button"
            @click="exporterPDF"
            :disabled="isGeneratingPdf"
            class="inline-flex items-center gap-2 text-sm font-extrabold px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-800 hover:bg-gray-100 shadow-sm transition disabled:opacity-50"
          >
            <svg v-if="!isGeneratingPdf" class="w-4 h-4 text-[#C8102E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span v-else class="w-4 h-4 border-2 border-[#C8102E] border-t-transparent rounded-full animate-spin"></span>
            <span>{{ isGeneratingPdf ? 'Génération...' : 'Télécharger PDF' }}</span>
          </button>

          <Link
            :href="route('admin.users.index')"
            class="text-sm font-semibold px-4 py-2.5 rounded-xl border border-gray-300 hover:bg-gray-100 transition shadow-sm bg-white text-gray-900"
          >
            ← Retour
          </Link>
        </div>
      </div>

      <!-- Contenu imprimable/exportable -->
      <div ref="pdfContainer" class="space-y-8 bg-gray-50 p-2 rounded-2xl">

        <div v-if="!annees.length" class="bg-white p-12 rounded-2xl border border-gray-200 text-center text-sm text-gray-500 shadow-sm">
          Aucun paiement enregistré pour le moment.
        </div>

        <template v-else>
          <!-- Cartes KPIs -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total {{ anneeSelectionnee }}</p>
              <p class="text-2xl font-black text-gray-900 mt-2">{{ formatMontant(donneesAnnee?.total) }}</p>
              <p class="text-xs text-gray-500 font-medium mt-1">{{ donneesAnnee?.nombre_paiements || 0 }} encaissement(s)</p>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Moyenne Mensuelle</p>
              <p class="text-2xl font-black text-gray-900 mt-2">{{ formatMontant(moyenneMensuelle) }}</p>
              <p class="text-xs text-gray-400 mt-1">Sur 12 mois</p>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Panier Moyen</p>
              <p class="text-2xl font-black text-gray-900 mt-2">{{ formatMontant(panierMoyen) }}</p>
              <p class="text-xs text-gray-400 mt-1">Par transaction</p>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Meilleur Mois</p>
              <template v-if="meilleurMois">
                <p class="text-2xl font-black text-gray-900 mt-2 truncate">{{ meilleurMois.label }}</p>
                <p class="text-xs font-bold text-[#C8102E] mt-1">{{ formatMontant(meilleurMois.total) }}</p>
              </template>
              <p v-else class="text-2xl font-black text-gray-900 mt-2">—</p>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between sm:col-span-2 lg:col-span-1">
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Évol. vs {{ anneeSelectionnee - 1 }}</p>
              <div class="mt-2 flex items-baseline gap-1">
                <p v-if="variation !== null" class="text-2xl font-black" :class="variation >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                  {{ variation >= 0 ? '+' : '' }}{{ variation.toFixed(1) }} %
                </p>
                <p v-else class="text-2xl font-black text-gray-900">—</p>
              </div>
              <p class="text-xs text-gray-400 mt-1">Variation annuelle</p>
            </div>

          </div>

          <!-- Graphique ApexCharts 1 : Mensuel -->
          <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h2 class="text-base font-extrabold text-gray-900">Évolution du Chiffre d'affaires — {{ anneeSelectionnee }}</h2>
                <p class="text-xs text-gray-500">Paiements mensuels et courbe d'orientation</p>
              </div>
              <span class="text-xs font-extrabold px-3 py-1 bg-gray-100 text-gray-700 rounded-full border border-gray-200">
                Total : {{ formatMontant(donneesAnnee?.total) }}
              </span>
            </div>

            <VueApexCharts
              type="line"
              height="280"
              :options="chartMensuelOptions"
              :series="chartMensuelSeries"
            />
          </div>

          <!-- Graphique ApexCharts 2 : Historique Annuel -->
          <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <h2 class="text-base font-extrabold text-gray-900 mb-1">Comparatif Annuel</h2>
            <p class="text-xs text-gray-500 mb-4">Cliquez sur une barre pour basculer vers l'année correspondante</p>

            <VueApexCharts
              type="bar"
              height="220"
              :options="chartAnnuelOptions"
              :series="chartAnnuelSeries"
            />
          </div>

          <!-- Tableau Détaillé avec Barres de Poids (%) -->
          <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
              <h2 class="text-base font-extrabold text-gray-900">Rapport Financier Mensuel — {{ anneeSelectionnee }}</h2>
              <span class="text-xs text-gray-400 font-medium">Répartition exacte et poids contributif</span>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-[11px] font-extrabold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                  <tr>
                    <th class="px-6 py-3.5">Mois</th>
                    <th class="px-6 py-3.5 text-center w-1/3">Poids sur l'Année</th>
                    <th class="px-6 py-3.5 text-right">Part (%)</th>
                    <th class="px-6 py-3.5 text-right">Chiffre d'Affaires</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="m in moisAvecPourcentage" :key="m.numero" class="hover:bg-gray-50 transition">
                    <td class="px-6 py-3.5 font-bold text-gray-900">{{ m.label }}</td>
                    <td class="px-6 py-3.5">
                      <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div 
                          class="bg-[#C8102E] h-2 rounded-full transition-all duration-500" 
                          :style="{ width: m.pourcentage + '%' }"
                        ></div>
                      </div>
                    </td>
                    <td class="px-6 py-3.5 text-right font-extrabold text-xs text-gray-500">{{ m.pourcentage }} %</td>
                    <td class="px-6 py-3.5 text-right font-black" :class="m.total === 0 ? 'text-gray-300 font-normal' : 'text-gray-900'">
                      {{ formatMontant(m.total) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-gray-50 border-t border-gray-200 font-black">
                  <tr>
                    <td class="px-6 py-4 text-gray-900 uppercase text-xs tracking-wider">Total Annuel</td>
                    <td class="px-6 py-4"></td>
                    <td class="px-6 py-4 text-right text-gray-900">100 %</td>
                    <td class="px-6 py-4 text-right text-lg text-[#C8102E]">{{ formatMontant(donneesAnnee?.total) }}</td>
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