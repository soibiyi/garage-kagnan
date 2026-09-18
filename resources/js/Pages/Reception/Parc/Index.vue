<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    interventions: Array,
});

// État pour la recherche
const search = ref('');

// Fonction utilitaire pour traduire ou styliser les statuts
const getStatutBadge = (statut) => {
    const badges = {
        reception: { text: 'Sur le Parc (Réception)', class: 'bg-blue-100 text-blue-800' },
        atelier: { text: 'En Atelier', class: 'bg-amber-100 text-amber-800' },
        en_cours: { text: 'En Réparation', class: 'bg-purple-100 text-purple-800' },
        attente_accord: { text: 'Attente Accord Devis', class: 'bg-rose-100 text-rose-800' },
    };
    return badges[statut] || { text: statut, class: 'bg-gray-100 text-gray-800' };
};

// Filtrage dynamique des interventions
const filteredInterventions = computed(() => {
    return props.interventions.filter((item) => {
        const searchTerm = search.value.toLowerCase().trim();
        
        // Récupération sécurisée des champs imbriqués
        const immatriculation = item.vehicule?.immatriculation?.toLowerCase() || '';
        const marque = item.vehicule?.marque?.toLowerCase() || '';
        const modele = item.vehicule?.modele?.toLowerCase() || '';
        const clientNom = item.vehicule?.client?.nom?.toLowerCase() || '';
        const clientPrenom = item.vehicule?.client?.prenom?.toLowerCase() || '';
        const numeroOt = item.numero_ot?.toLowerCase() || '';

        // Condition de recherche textuelle (Immatriculation, marque, modèle, client, OT)
        return (
            immatriculation.includes(searchTerm) ||
            marque.includes(searchTerm) ||
            modele.includes(searchTerm) ||
            clientNom.includes(searchTerm) ||
            clientPrenom.includes(searchTerm) ||
            numeroOt.includes(searchTerm)
        );
    });
});
</script>

<template>
    <Head title="Véhicules sur le Parc" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Véhicules sur le Parc 🚗
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">Suivi des véhicules actuellement dans l'enceinte de l'établissement</p>
                </div>
                <Link 
                    :href="route('reception.create')" 
                    class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition shadow-sm"
                >
                    + Nouvelle Réception
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- SECTION RECHERCHE -->
                <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-200">
                    <div class="w-full relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
                        <input 
                            v-model="search"
                            type="text" 
                            placeholder="Rechercher par immat, marque, modèle, client, OT..." 
                            class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                </div>

                <!-- TABLEAU DES DONNÉES -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-200">
                    <div class="p-6 text-gray-900">
                        
                        <!-- Message si aucun résultat après recherche ou vide -->
                        <div v-if="filteredInterventions.length === 0" class="text-center py-12">
                            <p class="text-gray-400 text-sm">Aucun véhicule ne correspond à vos critères de recherche.</p>
                        </div>

                        <!-- Tableau des véhicules -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-xs">
                                    <tr>
                                        <th class="px-6 py-3 font-semibold">N° OT</th>
                                        <th class="px-6 py-3 font-semibold">Immatriculation</th>
                                        <th class="px-6 py-3 font-semibold">Véhicule</th>
                                        <th class="px-6 py-3 font-semibold">Client</th>
                                        <th class="px-6 py-3 font-semibold">Statut</th>
                                        <th class="px-6 py-3 font-semibold">Date d'entrée</th>
                                        <th class="px-6 py-3 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="item in filteredInterventions" :key="item.id" class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4 font-bold text-indigo-600">
                                            {{ item.numero_ot }}
                                        </td>
                                        <td class="px-6 py-4 font-extrabold text-gray-900 uppercase">
                                            {{ item.vehicule?.immatriculation }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-700">
                                            {{ item.vehicule?.marque }} {{ item.vehicule?.modele }}
                                            <span class="block text-xs text-gray-400">Kilométrage : {{ item.kilometrage }} km</span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-700">
                                            {{ item.vehicule?.client?.nom }} {{ item.vehicule?.client?.prenom }}
                                            <span class="block text-xs text-gray-400">{{ item.vehicule?.client?.telephone }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span :class="['px-2.5 py-1 text-xs font-semibold rounded-full', getStatutBadge(item.statut).class]">
                                                {{ getStatutBadge(item.statut).text }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 text-xs">
                                            {{ new Date(item.date_reception).toLocaleDateString() }} à {{ new Date(item.date_reception).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <Link :href="route('parc.show', item.id)" class="text-xs font-bold text-indigo-600 hover:text-indigo-900 hover:underline">
                                                Voir Fiche →
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>