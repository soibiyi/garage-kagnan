<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { 
    faMagnifyingGlass, 
    faCar, 
    faArrowRight, 
    faCircleInfo, 
    faCheck, 
    faTimes,
    faArrowLeft
} from '@fortawesome/free-solid-svg-icons';

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

// Traduction et style des statuts (Palette personnalisée)
const getStatutBadge = (statut) => {
    const badges = {
        reception: { text: 'Sur le Parc (Réception)', class: 'bg-[#0B0F19]/10 text-[#0B0F19] border-[#0B0F19]/20' },
        atelier: { text: 'En Atelier', class: 'bg-[#E11D48]/10 text-[#E11D48] border-[#E11D48]/20' },
        en_cours: { text: 'En Réparation', class: 'bg-[#8A8D8F]/15 text-[#0B0F19] border-[#8A8D8F]/30' },
        attente_accord: { text: 'Attente Accord Devis', class: 'bg-[#E11D48]/15 text-[#E11D48] border-[#E11D48]/30' },
    };
    return badges[statut] || { text: statut, class: 'bg-[#8A8D8F]/10 text-[#8A8D8F] border-[#8A8D8F]/20' };
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
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19] flex items-center gap-3">
                        <span>Véhicules sur le Parc</span>
                        <span class="text-xs px-3 py-1 rounded-full bg-[#E11D48]/10 text-[#E11D48] font-bold border border-[#E11D48]/20">
                            {{ filteredInterventions.length }} actif(s)
                        </span>
                    </h2>
                    <p class="text-sm text-[#8A8D8F] mt-1">Suivi en temps réel des véhicules dans l'enceinte de l'atelier</p>
                </div>

                <!-- Bouton Retour au tableau de bord -->
                <div>
                    <Link 
                        :href="route('dashboard')" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-[#8A8D8F]/30 hover:bg-[#8A8D8F]/10 text-[#0B0F19] text-xs font-semibold rounded-xl transition-all shadow-xs"
                    >
                        <FontAwesomeIcon :icon="faArrowLeft" />
                        <span>Tableau de bord</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-[#0B0F19]/[0.02] min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- BARRE DE RECHERCHE -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-sm border border-[#8A8D8F]/20 flex items-center gap-3">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-[#8A8D8F]">
                            <FontAwesomeIcon :icon="faMagnifyingGlass" class="w-4 h-4" />
                        </span>
                        <input 
                            v-model="search"
                            type="text" 
                            placeholder="Rechercher par immatriculation, marque, modèle, client, n° OT..." 
                            class="w-full pl-10 pr-4 py-2.5 text-sm bg-white border border-[#8A8D8F]/30 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#E11D48]/20 focus:border-[#E11D48] transition-all placeholder:text-[#8A8D8F]/60 text-[#0B0F19]"
                        />
                    </div>
                </div>

                <!-- TABLEAU DES DONNÉES -->
                <div class="bg-white shadow-sm rounded-2xl border border-[#8A8D8F]/20 overflow-hidden">
                    <div v-if="filteredInterventions.length === 0" class="text-center py-16 px-4">
                        <div class="w-16 h-16 bg-[#8A8D8F]/10 text-[#8A8D8F] rounded-2xl flex items-center justify-center mx-auto mb-3 text-xl">
                            <FontAwesomeIcon :icon="faCar" class="w-6 h-6" />
                        </div>
                        <p class="text-[#0B0F19] font-medium">Aucun véhicule trouvé</p>
                        <p class="text-[#8A8D8F] text-xs mt-1">Modifiez vos critères de recherche pour afficher des résultats.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-[#8A8D8F]/15 text-left text-sm">
                            <thead class="bg-[#0B0F19]/5 text-[#8A8D8F] uppercase tracking-wider text-[11px] font-bold">
                                <tr>
                                    <th class="px-6 py-3.5">N° OT</th>
                                    <th class="px-6 py-3.5">Immatriculation</th>
                                    <th class="px-6 py-3.5">Véhicule</th>
                                    <th class="px-6 py-3.5">Client</th>
                                    <th class="px-6 py-3.5">Statut</th>
                                    <th class="px-6 py-3.5">Entrée</th>
                                    <th class="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#8A8D8F]/15">
                                <tr v-for="item in filteredInterventions" :key="item.id" class="hover:bg-[#0B0F19]/[0.01] transition-colors group">
                                    <td class="px-6 py-4 font-bold text-[#E11D48]">
                                        {{ item.numero_ot }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 bg-[#0B0F19] text-white font-mono font-bold text-xs rounded-lg tracking-wider uppercase shadow-xs">
                                            {{ item.vehicule?.immatriculation }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-[#0B0F19]">{{ item.vehicule?.marque }} {{ item.vehicule?.modele }}</div>
                                        <div class="text-xs text-[#8A8D8F] mt-0.5">Kilométrage : <span class="font-medium text-[#0B0F19]">{{ item.kilometrage }} km</span></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-[#0B0F19]">{{ item.vehicule?.client?.nom }} {{ item.vehicule?.client?.prenom }}</div>
                                        <div class="text-xs text-[#8A8D8F] mt-0.5">{{ item.vehicule?.client?.telephone || 'Aucun tèl' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="['inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full border', getStatutBadge(item.statut).class]">
                                            {{ getStatutBadge(item.statut).text }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-[#8A8D8F] text-xs">
                                        <div class="font-medium text-[#0B0F19]">{{ new Date(item.date_reception).toLocaleDateString() }}</div>
                                        <div class="text-[#8A8D8F] mt-0.5">{{ new Date(item.date_reception).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link 
                                                :href="route('parc.show', item.id)" 
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#8A8D8F]/30 hover:bg-[#8A8D8F]/10 text-[#0B0F19] text-xs font-semibold rounded-xl transition-all shadow-xs"
                                                title="Voir toutes les informations"
                                            >
                                                <FontAwesomeIcon :icon="faCircleInfo" class="text-[#8A8D8F]" /> Infos
                                            </Link>

                                            <button 
                                                type="button"
                                                @click="openNextModal(item)"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#E11D48] hover:bg-[#E11D48]/90 text-white text-xs font-semibold rounded-xl shadow-sm shadow-[#E11D48]/20 transition-all"
                                                title="Étape suivante"
                                            >
                                                Suivant <FontAwesomeIcon :icon="faArrowRight" class="w-3 h-3" />
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

        <!-- ================= MODALE "SUIVANT" ================= -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B0F19]/60 backdrop-blur-xs p-4 animate-fade-in">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-[#8A8D8F]/20">
                
                <div class="px-6 py-4 border-b border-[#8A8D8F]/15 flex justify-between items-center bg-[#0B0F19]/[0.03]">
                    <div>
                        <h3 class="font-bold text-[#0B0F19] text-base">Transmission du Dossier</h3>
                        <p class="text-xs text-[#8A8D8F]">Ordre de Travail : <span class="font-mono font-bold text-[#E11D48]">{{ activeIntervention?.numero_ot }}</span></p>
                    </div>
                    <button @click="closeModal" class="w-8 h-8 rounded-full bg-white border border-[#8A8D8F]/30 text-[#8A8D8F] hover:text-[#0B0F19] hover:bg-[#8A8D8F]/10 flex items-center justify-center transition-all font-bold">
                        <FontAwesomeIcon :icon="faTimes" />
                    </button>
                </div>

                <form @submit.prevent="submitProgress" class="p-6 space-y-5">
                    
                    <!-- Sélection du mécanicien -->
                    <div>
                        <label class="block text-xs font-bold text-[#0B0F19] uppercase tracking-wider mb-1.5">Mécanicien assigné *</label>
                        <select v-model="form.mecanicien_id" required class="w-full rounded-xl border-[#8A8D8F]/30 bg-white text-sm focus:border-[#E11D48] focus:ring-2 focus:ring-[#E11D48]/20 transition-all text-[#0B0F19]">
                            <option value="" disabled>-- Choisir un mécanicien --</option>
                            <option v-for="mec in mecaniciens" :key="mec.id" :value="mec.id">
                                {{ mec.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.mecanicien_id" class="text-[#E11D48] text-xs mt-1.5 font-medium">{{ form.errors.mecanicien_id }}</div>
                    </div>

                    <!-- Rapport / Pannes détectées -->
                    <div>
                        <label class="block text-xs font-bold text-[#0B0F19] uppercase tracking-wider mb-1.5">Rapport / Pannes détectées *</label>
                        <textarea 
                            v-model="form.rapport_mecanicien" 
                            required 
                            rows="4"
                            placeholder="Décrivez ici le rapport et les pannes constatées..." 
                            class="w-full rounded-xl border-[#8A8D8F]/30 bg-white text-sm focus:border-[#E11D48] focus:ring-2 focus:ring-[#E11D48]/20 transition-all placeholder:text-[#8A8D8F]/60 text-[#0B0F19]"
                        ></textarea>
                        <div v-if="form.errors.rapport_mecanicien" class="text-[#E11D48] text-xs mt-1.5 font-medium">{{ form.errors.rapport_mecanicien }}</div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-[#8A8D8F]/15">
                        <button 
                            type="button" 
                            @click="closeModal" 
                            class="px-4 py-2.5 bg-white border border-[#8A8D8F]/30 hover:bg-[#8A8D8F]/10 text-[#0B0F19] text-xs font-semibold rounded-xl transition-all shadow-xs"
                        >
                            Annuler
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-[#E11D48] hover:bg-[#E11D48]/90 text-white text-xs font-semibold rounded-xl shadow-md shadow-[#E11D48]/20 transition-all disabled:opacity-50"
                        >
                            Envoyer à l'administration <FontAwesomeIcon :icon="faCheck" />
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </AuthenticatedLayout>
</template>