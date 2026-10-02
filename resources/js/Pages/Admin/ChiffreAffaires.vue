<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import SiegeFilter from '@/Components/SiegeFilter.vue';

const props = defineProps({
    annees: { type: Array, default: () => [] },
    siegeFiltre: { type: String, default: null },
});

// Année sélectionnée (la plus récente par défaut)
const anneeSelectionnee = ref(props.annees[0]?.annee ?? null);

const donneesAnnee = computed(() => props.annees.find(a => a.annee === anneeSelectionnee.value) ?? null);
const donneesAnneePrecedente = computed(() => props.annees.find(a => a.annee === anneeSelectionnee.value - 1) ?? null);

// Si le siège filtré n'a pas de paiement pour l'année affichée, on revient sur la plus récente
watch(() => props.annees, (liste) => {
    if (!liste.some(a => a.annee === anneeSelectionnee.value)) {
        anneeSelectionnee.value = liste[0]?.annee ?? null;
    }
});

// Répartition du CA de l'année sélectionnée entre les sièges (toujours tous les sièges, pour comparer)
const repartitionSieges = computed(() => {
    const parSiege = donneesAnnee.value?.par_siege ?? {};
    const total = Object.values(parSiege).reduce((somme, n) => somme + n, 0);
    return Object.entries(parSiege).map(([code, montant]) => ({
        code,
        montant,
        part: total > 0 ? Math.round((montant * 100) / total) : 0,
    }));
});

const formatMontant = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n || 0) + ' FCFA';

// ---- Indicateurs de l'année sélectionnée ----
const moyenneMensuelle = computed(() => (donneesAnnee.value ? donneesAnnee.value.total / 12 : 0));

const meilleurMois = computed(() => {
    if (!donneesAnnee.value) return null;
    const best = [...donneesAnnee.value.mois].sort((a, b) => b.total - a.total)[0];
    return best && best.total > 0 ? best : null;
});

const variation = computed(() => {
    if (!donneesAnnee.value || !donneesAnneePrecedente.value || donneesAnneePrecedente.value.total === 0) return null;
    return ((donneesAnnee.value.total - donneesAnneePrecedente.value.total) / donneesAnneePrecedente.value.total) * 100;
});

// ---- Graphique 1 : CA par mois pour l'année sélectionnée ----
const barresMois = computed(() => {
    if (!donneesAnnee.value) return [];
    const max = Math.max(...donneesAnnee.value.mois.map(m => m.total), 1);
    return donneesAnnee.value.mois.map(m => ({
        ...m,
        court: m.label.slice(0, 3),
        hauteur: m.total > 0 ? Math.max((m.total / max) * 100, 2) : 0,
    }));
});

// ---- Graphique 2 : CA par année (ordre chronologique) ----
const barresAnnees = computed(() => {
    const liste = [...props.annees].sort((a, b) => a.annee - b.annee);
    const max = Math.max(...liste.map(a => a.total), 1);
    return liste.map(a => ({
        ...a,
        hauteur: a.total > 0 ? Math.max((a.total / max) * 100, 2) : 0,
    }));
});
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">

      <!-- En-tête -->
      <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
          <h1 class="text-3xl font-bold" style="color: #1A1A1A;">Historique du Chiffre d'affaires</h1>
          <p class="text-sm mt-1" style="color: #8A8D8F;">Évolution des paiements encaissés, année par année<span v-if="siegeFiltre" class="font-bold"> — siège {{ siegeFiltre }}</span></p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <SiegeFilter route-name="admin.chiffre-affaires" :current="siegeFiltre" />
          <select
            v-if="annees.length"
            v-model="anneeSelectionnee"
            class="text-sm font-semibold border border-gray-300 rounded-lg py-2 pl-3 pr-8 bg-white shadow-sm focus:border-[#C8102E] focus:ring-0"
          >
            <option v-for="a in annees" :key="a.annee" :value="a.annee">{{ a.annee }}</option>
          </select>
          <Link
            :href="route('admin.users.index')"
            class="text-sm font-semibold px-4 py-2.5 rounded-lg border border-gray-300 hover:bg-gray-100 transition shadow-sm bg-white"
            style="color: #1A1A1A;"
          >
            ← Retour au tableau de bord
          </Link>
        </div>
      </div>

      <!-- Répartition par siège (année sélectionnée) : cliquer sur un siège pour le filtrer -->
      <div v-if="repartitionSieges.length" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Link
          v-for="s in repartitionSieges"
          :key="s.code"
          :href="route('admin.chiffre-affaires', { siege: s.code })"
          class="bg-white p-5 rounded-xl border shadow-sm transition hover:shadow-md"
          :class="siegeFiltre === s.code ? 'border-[#C8102E] ring-1 ring-[#C8102E]' : 'border-gray-200'"
        >
          <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">{{ s.code }} — {{ $page.props.sieges[s.code] }}</p>
          <p class="text-xl font-black mt-2" style="color: #1A1A1A;">{{ formatMontant(s.montant) }}</p>
          <div class="mt-3 h-1.5 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full rounded-full" style="background-color: #C8102E;" :style="{ width: s.part + '%' }"></div>
          </div>
          <p class="text-[11px] mt-1" style="color: #8A8D8F;">{{ s.part }} % du total {{ anneeSelectionnee }}</p>
        </Link>
      </div>

      <!-- Aucun paiement -->
      <div v-if="!annees.length" class="bg-white p-10 rounded-xl border border-gray-200 text-center text-sm" style="color: #8A8D8F;">
        Aucun paiement enregistré pour le moment.
      </div>

      <template v-else>
        <!-- Indicateurs -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Total {{ anneeSelectionnee }}</p>
            <p class="text-2xl font-black mt-2" style="color: #1A1A1A;">{{ formatMontant(donneesAnnee?.total) }}</p>
            <p class="text-xs mt-1" style="color: #8A8D8F;">{{ donneesAnnee?.nombre_paiements }} paiement(s)</p>
          </div>

          <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Moyenne mensuelle</p>
            <p class="text-2xl font-black mt-2" style="color: #1A1A1A;">{{ formatMontant(moyenneMensuelle) }}</p>
          </div>

          <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Meilleur mois</p>
            <template v-if="meilleurMois">
              <p class="text-2xl font-black mt-2" style="color: #1A1A1A;">{{ meilleurMois.label }}</p>
              <p class="text-xs mt-1" style="color: #8A8D8F;">{{ formatMontant(meilleurMois.total) }}</p>
            </template>
            <p v-else class="text-2xl font-black mt-2" style="color: #1A1A1A;">—</p>
          </div>

          <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
            <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Évolution vs {{ anneeSelectionnee - 1 }}</p>
            <p v-if="variation !== null" class="text-2xl font-black mt-2" :class="variation >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ variation >= 0 ? '+' : '' }}{{ variation.toFixed(1) }} %
            </p>
            <p v-else class="text-2xl font-black mt-2" style="color: #1A1A1A;">—</p>
          </div>
        </div>

        <!-- Graphique 1 : par mois -->
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
          <h2 class="text-lg font-extrabold mb-1" style="color: #1A1A1A;">Chiffre d'affaires mensuel — {{ anneeSelectionnee }}</h2>
          <p class="text-xs mb-6" style="color: #8A8D8F;">Survole une barre pour voir le montant exact</p>

          <div class="flex items-end gap-2 h-64 border-b border-gray-200">
            <div
              v-for="m in barresMois"
              :key="m.numero"
              class="flex-1 h-full flex flex-col justify-end items-center group"
              :title="m.label + ' : ' + formatMontant(m.total)"
            >
              <span class="text-[10px] font-semibold mb-1 opacity-0 group-hover:opacity-100 transition whitespace-nowrap" style="color: #1A1A1A;">
                {{ formatMontant(m.total) }}
              </span>
              <div
                class="w-full rounded-t-md transition-all duration-300 group-hover:opacity-80"
                :style="{ height: m.hauteur + '%', backgroundColor: '#C8102E' }"
              ></div>
            </div>
          </div>
          <div class="flex gap-2 mt-2">
            <span v-for="m in barresMois" :key="m.numero" class="flex-1 text-center text-[11px] font-medium" style="color: #8A8D8F;">
              {{ m.court }}
            </span>
          </div>
        </div>

        <!-- Graphique 2 : par année -->
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
          <h2 class="text-lg font-extrabold mb-1" style="color: #1A1A1A;">Comparaison par année</h2>
          <p class="text-xs mb-6" style="color: #8A8D8F;">Clique sur une année pour afficher son détail mensuel</p>

          <div class="flex items-end justify-center gap-6 h-64 border-b border-gray-200">
            <button
              v-for="a in barresAnnees"
              :key="a.annee"
              type="button"
              @click="anneeSelectionnee = a.annee"
              class="w-full max-w-[110px] h-full flex flex-col justify-end items-center group"
              :title="a.annee + ' : ' + formatMontant(a.total)"
            >
              <span class="text-[10px] font-semibold mb-1 whitespace-nowrap" style="color: #1A1A1A;">
                {{ formatMontant(a.total) }}
              </span>
              <div
                class="w-full rounded-t-md transition-all duration-300 group-hover:opacity-80"
                :style="{ height: a.hauteur + '%', backgroundColor: a.annee === anneeSelectionnee ? '#C8102E' : '#1A1A1A' }"
              ></div>
            </button>
          </div>
          <div class="flex justify-center gap-6 mt-2">
            <span
              v-for="a in barresAnnees"
              :key="a.annee"
              class="w-full max-w-[110px] text-center text-xs font-bold"
              :style="{ color: a.annee === anneeSelectionnee ? '#C8102E' : '#8A8D8F' }"
            >
              {{ a.annee }}
            </span>
          </div>
        </div>

        <!-- Tableau détaillé -->
        <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
          <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-extrabold" style="color: #1A1A1A;">Détail {{ anneeSelectionnee }}</h2>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider" style="color: #8A8D8F;">
              <tr>
                <th class="text-left px-6 py-3">Mois</th>
                <th class="text-right px-6 py-3">Chiffre d'affaires</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in donneesAnnee?.mois" :key="m.numero" class="border-t border-gray-100">
                <td class="px-6 py-3 font-medium" style="color: #1A1A1A;">{{ m.label }}</td>
                <td class="px-6 py-3 text-right" :class="m.total === 0 ? 'text-gray-400' : 'font-semibold'">
                  {{ formatMontant(m.total) }}
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 font-extrabold">
              <tr class="border-t border-gray-200">
                <td class="px-6 py-3" style="color: #1A1A1A;">Total</td>
                <td class="px-6 py-3 text-right" style="color: #C8102E;">{{ formatMontant(donneesAnnee?.total) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </template>

    </div>
  </div>
</template>