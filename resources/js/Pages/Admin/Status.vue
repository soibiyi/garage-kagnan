<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faCar,
    faUser,
    faSearch,
    faArrowLeft,
    faFilter,
    faClipboardList
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    vehicules: Array,
});

const search = ref('');
const filterStatut = ref('tous');

// Configuration des badges de statut
const getStatutBadge = (statut) => {
    const badges = {
        reception: { text: 'Sur le parc (réception)', classe: 'bg-blue-50 text-blue-700 ring-blue-200' },
        atelier: { text: 'En atelier', classe: 'bg-amber-50 text-amber-700 ring-amber-200' },
        en_cours: { text: 'En réparation', classe: 'bg-purple-50 text-purple-700 ring-purple-200' },
        attente_accord: { text: 'Attente accord devis', classe: 'bg-[#E11D48]/10 text-[#E11D48] ring-[#E11D48]/25' },
        accepte: { text: 'Devis accepté', classe: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
        livre: { text: 'Véhicule livré', classe: 'bg-gray-900 text-white ring-gray-900' },
    };
    return badges[statut] || { text: statut || 'Aucune intervention', classe: 'bg-gray-100 text-gray-700 ring-gray-200' };
};

// Filtrage dynamique des véhicules
const vehiculesFiltres = computed(() => {
    if (!props.vehicules) return [];

    return props.vehicules.filter((v) => {
        const derniereIntervention = v.interventions && v.interventions.length > 0 ? v.interventions[0] : null;
        const statutActuel = derniereIntervention ? derniereIntervention.statut : 'aucun';

        // Filtre par statut
        if (filterStatut.value !== 'tous' && statutActuel !== filterStatut.value) {
            return false;
        }

        // Filtre par recherche texte
        if (search.value.trim() !== '') {
            const query = search.value.toLowerCase();
            const immat = (v.immatriculation || '').toLowerCase();
            const marque = (v.marque || '').toLowerCase();
            const modele = (v.modele || '').toLowerCase();
            const clientNom = v.client ? `${v.client.nom} ${v.client.prenom}`.toLowerCase() : '';

            return immat.includes(query) || marque.includes(query) || modele.includes(query) || clientNom.includes(query);
        }

        return true;
    });
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- En-tête -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 transition shadow-sm"
                        >
                            <FontAwesomeIcon :icon="faArrowLeft" class="text-sm" />
                        </Link>
                        <h1 class="text-2xl font-bold text-[#1A1A1A]">Statut des Véhicules Enregistrés</h1>
                    </div>
                    <p class="text-xs text-[#8A8D8F] mt-1">Liste globale de tous les véhicules, propriétaires et état d'avancement</p>
                </div>

                <div class="text-xs font-bold bg-white px-4 py-2 rounded-xl border border-gray-200 shadow-sm text-gray-700">
                    Total : {{ vehiculesFiltres.length }} véhicule(s)
                </div>
            </div>

            <!-- Barre de Filtres et Recherche -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
                <!-- Champ de recherche -->
                <div class="relative w-full md:w-96">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <FontAwesomeIcon :icon="faSearch" class="text-xs" />
                    </span>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Rechercher par immatriculation, marque, propriétaire..."
                        class="w-full pl-9 pr-4 py-2 text-xs rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#C8102E]/20 focus:border-[#C8102E]"
                    />
                </div>

                <!-- Select de filtrage par statut -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <FontAwesomeIcon :icon="faFilter" class="text-xs text-gray-400" />
                    <select
                        v-model="filterStatut"
                        class="w-full md:w-56 py-2 px-3 text-xs rounded-lg border border-gray-200 bg-white font-medium focus:outline-none focus:ring-2 focus:ring-[#C8102E]/20 focus:border-[#C8102E]"
                    >
                        <option value="tous">Tous les statuts</option>
                        <option value="reception">Sur le parc (réception)</option>
                        <option value="atelier">En atelier</option>
                        <option value="en_cours">En réparation</option>
                        <option value="attente_accord">Attente accord devis</option>
                        <option value="accepte">Devis accepté</option>
                        <option value="livre">Véhicule livré</option>
                    </select>
                </div>
            </div>

            <!-- Tableau des Véhicules -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase font-extrabold tracking-wider">
                            <tr>
                                <th class="py-3.5 px-4">Véhicule</th>
                                <th class="py-3.5 px-4">Immatriculation</th>
                                <th class="py-3.5 px-4">Propriétaire</th>
                                <th class="py-3.5 px-4">Dernier OT</th>
                                <th class="py-3.5 px-4">Statut Actuel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="v in vehiculesFiltres"
                                :key="v.id"
                                class="hover:bg-gray-50/80 transition"
                            >
                                <!-- Marque & Modèle -->
                                <td class="py-4 px-4 font-bold text-gray-900">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600">
                                            <FontAwesomeIcon :icon="faCar" class="text-xs" />
                                        </span>
                                        <span>{{ v.marque }} {{ v.modele }}</span>
                                    </div>
                                </td>

                                <!-- Immatriculation -->
                                <td class="py-4 px-4 font-mono font-bold uppercase text-gray-800">
                                    {{ v.immatriculation || 'Non renseignée' }}
                                </td>

                                <!-- Propriétaire -->
                                <td class="py-4 px-4">
                                    <div v-if="v.client" class="flex flex-col">
                                        <span class="font-bold text-gray-900">{{ v.client.nom }} {{ v.client.prenom }}</span>
                                        <span class="text-[11px] text-gray-500">{{ v.client.telephone || 'Sans téléphone' }}</span>
                                    </div>
                                    <span v-else class="text-gray-400 italic">Aucun propriétaire</span>
                                </td>

                                <!-- Numéro OT -->
                                <td class="py-4 px-4 font-mono text-gray-600">
                                    <template v-if="v.interventions && v.interventions.length > 0">
                                        <span class="font-bold text-[#C8102E]">
                                            {{ v.interventions[0].numero_ot || '#' + v.interventions[0].id }}
                                        </span>
                                    </template>
                                    <span v-else class="text-gray-400">Aucun OT</span>
                                </td>

                                <!-- Statut -->
                                <td class="py-4 px-4">
                                    <template v-if="v.interventions && v.interventions.length > 0">
                                        <span
                                            class="inline-flex px-2.5 py-1 text-[11px] font-bold rounded-lg ring-1 ring-inset"
                                            :class="getStatutBadge(v.interventions[0].statut).classe"
                                        >
                                            {{ getStatutBadge(v.interventions[0].statut).text }}
                                        </span>
                                    </template>
                                    <span v-else class="inline-flex px-2.5 py-1 text-[11px] font-bold rounded-lg bg-gray-100 text-gray-500">
                                        Aucune intervention
                                    </span>
                                </td>
                            </tr>

                            <!-- Aucun résultat -->
                            <tr v-if="vehiculesFiltres.length === 0">
                                <td colspan="5" class="py-10 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <FontAwesomeIcon :icon="faClipboardList" class="text-2xl text-gray-300" />
                                        <p class="font-bold text-sm">Aucun véhicule trouvé</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</template>