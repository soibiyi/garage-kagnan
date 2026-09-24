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

// Fonction utilitaire pour calculer le temps écoulé depuis l'entrée
const calculerTempsEcoule = (dateString) => {
    if (!dateString) return '';
    const dateEntree = new Date(dateString);
    const maintenant = new Date();
    const diffMs = maintenant - dateEntree;

    if (diffMs < 0) return "À l'instant";

    const diffMinutes = Math.floor(diffMs / (1000 * 60));
    const diffHeures = Math.floor(diffMinutes / 60);
    const jours = Math.floor(diffHeures / 24);
    const heuresRestantes = diffHeures % 24;

    if (jours > 0) {
        return `${jours}j ${heuresRestantes}h`;
    } else if (diffHeures > 0) {
        return `${diffHeures}h`;
    } else {
        return `${diffMinutes} min`;
    }
};

// Fonction utilitaire pour les badges de statut
const getStatutBadge = (statut) => {
    const badges = {
        reception: { text: 'Sur le Parc (Réception)', class: 'bg-blue-50 text-blue-700 border border-blue-200' },
        atelier: { text: 'En Atelier', class: 'bg-amber-50 text-amber-700 border border-amber-200' },
        en_cours: { text: 'En Réparation', class: 'bg-purple-50 text-purple-700 border border-purple-200' },
        attente_accord: { text: 'Attente Accord Devis', class: 'bg-rose-50 text-rose-700 border border-rose-200' },
    };
    return badges[statut] || { text: statut, class: 'bg-gray-100 text-gray-700 border border-gray-200' };
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
    <Head title="Véhicules sur le Parc — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 py-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19] flex items-center gap-2.5">
                        <i class="fa-solid fa-car-tunnel text-[#E11D48] text-xl"></i>
                        <span>Véhicules sur le Parc</span>
                    </h2>
                    <p class="text-sm text-[#8A8D8F] font-medium mt-0.5">Suivi en temps réel des véhicules actuellement dans l'enceinte de l'établissement</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Bouton Retour au tableau de bord -->
                    <Link 
                        :href="route('dashboard')" 
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-[#0B0F19] text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-xs"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Tableau de bord</span>
                    </Link>

                    <Link 
                        :href="route('reception.create')" 
                        class="px-5 py-2.5 bg-[#E11D48] text-white text-xs font-bold rounded-xl hover:bg-[#BE123C] transition shadow-md shadow-[#E11D48]/20 flex items-center gap-2"
                    >
                        <i class="fa-solid fa-plus"></i>
                        <span>Nouvelle Réception</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12 bg-white min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- SECTION RECHERCHE -->
                <div class="bg-white p-6 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100">
                    <div class="w-full relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#8A8D8F]">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input 
                            v-model="search"
                            type="text" 
                            placeholder="Rechercher par immatriculation, marque, modèle, client, N° OT..." 
                            class="w-full pl-11 pr-4 py-3 text-sm bg-[#F8FAFC] border border-gray-200 rounded-2xl focus:ring-2 focus:ring-[#E11D48] focus:border-[#E11D48] transition text-[#0B0F19] placeholder:text-[#8A8D8F]"
                        />
                    </div>
                </div>

                <!-- TABLEAU DES DONNÉES -->
                <div class="bg-white overflow-hidden shadow-xl shadow-gray-100 sm:rounded-3xl border border-gray-100">
                    <div class="p-6 sm:p-8">
                        
                        <div v-if="filteredInterventions.length === 0" class="text-center py-16 space-y-3">
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 text-[#8A8D8F] flex items-center justify-center mx-auto text-xl">
                                <i class="fa-solid fa-folder-open"></i>
                            </div>
                            <p class="text-[#8A8D8F] text-sm font-medium">Aucun véhicule ne correspond à vos critères de recherche.</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-left text-sm">
                                <thead class="bg-[#F8FAFC] text-[#8A8D8F] uppercase tracking-wider text-xs font-black">
                                    <tr>
                                        <th class="px-6 py-4">N° OT</th>
                                        <th class="px-6 py-4">Immatriculation</th>
                                        <th class="px-6 py-4">Véhicule</th>
                                        <th class="px-6 py-4">Client</th>
                                        <th class="px-6 py-4">Statut</th>
                                        <th class="px-6 py-4">Date d'entrée</th>
                                        <th class="px-6 py-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="item in filteredInterventions" :key="item.id" class="hover:bg-gray-50/60 transition">
                                        <td class="px-6 py-4 font-black text-[#E11D48]">
                                            {{ item.numero_ot }}
                                        </td>
                                        <td class="px-6 py-4 font-black text-[#0B0F19] uppercase tracking-wide">
                                            {{ item.vehicule?.immatriculation }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-700 font-medium">
                                            {{ item.vehicule?.marque }} {{ item.vehicule?.modele }}
                                            <span class="block text-xs text-[#8A8D8F] font-normal">Kilométrage : {{ item.kilometrage }} km</span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-700 font-medium">
                                            {{ item.vehicule?.client?.nom }} {{ item.vehicule?.client?.prenom }}
                                            <span class="block text-xs text-[#8A8D8F] font-normal">{{ item.vehicule?.client?.telephone }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span :class="['px-3 py-1 text-xs font-bold rounded-lg uppercase tracking-wide inline-block', getStatutBadge(item.statut).class]">
                                                {{ getStatutBadge(item.statut).text }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-[#8A8D8F] text-xs font-medium">
                                            <div>{{ new Date(item.date_reception).toLocaleDateString() }} à {{ new Date(item.date_reception).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</div>
                                            <!-- Affichage du décompte -->
                                            <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 bg-[#E11D48]/10 text-[#E11D48] font-bold rounded text-[11px]">
                                                <i class="fa-solid fa-clock text-[10px]"></i>
                                                <span>Il y a {{ calculerTempsEcoule(item.date_reception) }}</span>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <Link 
                                                    :href="route('parc.show', item.id)" 
                                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-[#0B0F19] text-xs font-bold rounded-xl transition shadow-xs"
                                                    title="Voir toutes les informations"
                                                >
                                                    <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                                                    <span>Infos</span>
                                                </Link>

                                                <button 
                                                    type="button"
                                                    @click="openNextModal(item)"
                                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#0B0F19] hover:bg-gray-800 text-white text-xs font-bold rounded-xl transition shadow-sm"
                                                    title="Étape suivante"
                                                >
                                                    <span>Suivant</span>
                                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
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
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden border border-gray-100 animate-in fade-in zoom-in duration-200">
                
                <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-[#F8FAFC]">
                    <div>
                        <h3 class="font-black text-[#0B0F19] text-base">
                            Transmission du Dossier
                        </h3>
                        <p class="text-xs text-[#8A8D8F] font-medium mt-0.5">Ordre de Travail : <span class="text-[#E11D48] font-bold">{{ activeIntervention?.numero_ot }}</span></p>
                    </div>
                    <button @click="closeModal" class="w-8 h-8 rounded-xl bg-white border border-gray-200 text-[#8A8D8F] hover:text-[#0B0F19] flex items-center justify-center font-bold text-lg transition">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <form @submit.prevent="submitProgress" class="p-8 space-y-6">
                    
                    <!-- Sélection du mécanicien -->
                    <div>
                        <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Mécanicien assigné *</label>
                        <select v-model="form.mecanicien_id" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm py-3 px-4 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]">
                            <option value="" disabled>-- Choisir un mécanicien --</option>
                            <option v-for="mec in mecaniciens" :key="mec.id" :value="mec.id">
                                {{ mec.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.mecanicien_id" class="text-[#E11D48] text-xs font-semibold mt-1">{{ form.errors.mecanicien_id }}</div>
                    </div>

                    <!-- Rapport / Pannes détectées -->
                    <div>
                        <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Rapport / Pannes détectées *</label>
                        <textarea 
                            v-model="form.rapport_mecanicien" 
                            required 
                            rows="4"
                            placeholder="Décrivez ici le rapport et les pannes constatées..." 
                            class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-4 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]"
                        ></textarea>
                        <div v-if="form.errors.rapport_mecanicien" class="text-[#E11D48] text-xs font-semibold mt-1">{{ form.errors.rapport_mecanicien }}</div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button 
                            type="button" 
                            @click="closeModal" 
                            class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-[#0B0F19] text-xs font-bold rounded-xl transition"
                        >
                            Annuler
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-[#E11D48] hover:bg-[#BE123C] text-white text-xs font-bold rounded-xl shadow-md shadow-[#E11D48]/20 transition disabled:opacity-50 flex items-center gap-2"
                        >
                            <span>Envoyer à l'administration</span>
                            <i class="fa-solid fa-check text-xs"></i>
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </AuthenticatedLayout>
</template>