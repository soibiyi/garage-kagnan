<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    interventions: Array,
    mecaniciens: Array,
});

// État pour la recherche
const search = ref('');

// État pour la modale "Suivant"
const showModal = ref(false);
const activeIntervention = ref(null);

// Formulaire Inertia pour la modale
const form = useForm({
    mecanicien_id: '',
    rapport_mecanicien: '',
});

// Ouvrir la modale pour une intervention donnée
const openNextModal = (item) => {
    activeIntervention.value = item;
    form.mecanicien_id = item.mecanicien_id || '';
    form.rapport_mecanicien = item.rapport_mecanicien || '';
    form.clearErrors();
    showModal.value = true;
};

// Fermer la modale
const closeModal = () => {
    showModal.value = false;
    activeIntervention.value = null;
    form.reset();
};

// Soumettre le formulaire vers la route de progression
const submitProgress = () => {
    form.patch(route('parc.progress', activeIntervention.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
    });
};

// Traduction et style des statuts (homogénéisé avec la vue show)
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
        
        const immatriculation = item.vehicule?.immatriculation?.toLowerCase() || '';
        const marque = item.vehicule?.marque?.toLowerCase() || '';
        const modele = item.vehicule?.modele?.toLowerCase() || '';
        const clientNom = item.vehicule?.client?.nom?.toLowerCase() || '';
        const clientPrenom = item.vehicule?.client?.prenom?.toLowerCase() || '';
        const numeroOt = item.numero_ot?.toLowerCase() || '';

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
            <div>
                <h2 class="text-xl font-bold leading-tight text-gray-900">
                    Véhicules sur le Parc 🚗
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">Suivi des véhicules actuellement dans l'enceinte de l'établissement</p>
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
                        
                        <div v-if="filteredInterventions.length === 0" class="text-center py-12">
                            <p class="text-gray-400 text-sm">Aucun véhicule ne correspond à vos critères de recherche.</p>
                        </div>

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
                                            <div class="flex items-center justify-end gap-2">
                                                <Link 
                                                    :href="route('parc.show', item.id)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition"
                                                    title="Voir toutes les informations"
                                                >
                                                    🔍 Infos
                                                </Link>

                                                <button 
                                                    type="button"
                                                    @click="openNextModal(item)"
                                                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition shadow-sm"
                                                    title="Étape suivante"
                                                >
                                                    Suivant →
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MODALE "SUIVANT" ================= -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden border border-gray-100">
                
                <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                    <h3 class="font-bold text-gray-900 text-base">
                        Transmission Dossier (OT : <span class="text-indigo-600">{{ activeIntervention?.numero_ot }}</span>)
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 font-bold text-lg">×</button>
                </div>

                <form @submit.prevent="submitProgress" class="p-6 space-y-5">
                    
                    <!-- Sélection du mécanicien -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Mécanicien assigné *</label>
                        <select v-model="form.mecanicien_id" required class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="" disabled>-- Choisir un mécanicien --</option>
                            <option v-for="mec in mecaniciens" :key="mec.id" :value="mec.id">
                                {{ mec.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.mecanicien_id" class="text-red-600 text-xs mt-1">{{ form.errors.mecanicien_id }}</div>
                    </div>

                    <!-- Rapport / Pannes détectées -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Rapport / Pannes détectées *</label>
                        <textarea 
                            v-model="form.rapport_mecanicien" 
                            required 
                            rows="4"
                            placeholder="Décrivez ici le rapport et les pannes constatées..." 
                            class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                        <div v-if="form.errors.rapport_mecanicien" class="text-red-600 text-xs mt-1">{{ form.errors.rapport_mecanicien }}</div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button 
                            type="button" 
                            @click="closeModal" 
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition"
                        >
                            Annuler
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition disabled:opacity-50"
                        >
                            Envoyer à l'administration ✓
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </AuthenticatedLayout>
</template>