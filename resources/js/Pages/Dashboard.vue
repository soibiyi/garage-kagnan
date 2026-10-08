<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, Link, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';

const props = defineProps({
    interventionsAtelier: Object, // résultat paginé Laravel : { data, from, to, total, current_page, last_page, prev_page_url, next_page_url }
    filters: { type: Object, default: () => ({}) },
    statuts: { type: Array, default: () => [] },
});

// Recherche et filtre de statut du tableau « Dossiers en cours / Atelier »
const search = ref(props.filters.search || '');
const statut = ref(props.filters.statut || '');

const applyFilters = () => {
    router.get(
        route('dashboard'),
        {
            search: search.value.trim() || undefined,
            statut: statut.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

// Petit délai pour ne pas interroger le serveur à chaque lettre tapée
let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});
watch(statut, applyFilters);

// Libellés lisibles des statuts
const statutLabels = {
    reception: 'Sur le parc',
    atelier: 'En atelier',
    en_cours: 'En réparation',
    attente_accord: 'Attente accord devis',
};
const getStatutLabel = (st) => statutLabels[st] || (st || '').replace(/_/g, ' ');

const page = usePage();
const user = computed(() => page.props.auth.user);

// Libellés et styles des rôles
const roleInfo = computed(() => {
    const roles = {
        receptionniste: { label: 'Réceptionniste', badge: 'bg-[#0B0F19] text-white border border-[#0B0F19]' },
        mecanicien: { label: 'Mécanicien', badge: 'bg-[#8A8D8F]/20 text-[#0B0F19] border border-[#8A8D8F]/40' },
        administratif: { label: 'Administratif', badge: 'bg-[#E11D48]/10 text-[#E11D48] border border-[#E11D48]/30' },
        charge_client: { label: 'Chargé de Suivi Client', badge: 'bg-gray-100 text-gray-800 border border-gray-300' },
    };
    return roles[user.value?.role] || { label: user.value?.role || 'Espace', badge: 'bg-gray-100 text-gray-700 border border-gray-200' };
});

// Écran de chargement (affiché une seule fois par session d'utilisateur)
const isLoading = ref(false);

onMounted(() => {
    const hasLoaded = sessionStorage.getItem('dashboard_loaded');

    if (!hasLoaded) {
        isLoading.value = true;
        setTimeout(() => {
            isLoading.value = false;
            sessionStorage.setItem('dashboard_loaded', 'true');
        }, 1500);
    }
});

// Méthode de déconnexion avec nettoyage de session
const logout = () => {
    sessionStorage.removeItem('dashboard_loaded');
    sessionStorage.removeItem('admin_loaded');
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Tableau de bord — Garage Kagnan" />

    <!-- ÉCRAN DE CHARGEMENT UNE FOIS PAR SESSION -->
    <Transition name="fade">
        <div v-if="isLoading" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-white">
            <div class="text-center space-y-4 max-w-sm w-full px-6">
                <h1 class="text-2xl font-black uppercase tracking-wider text-[#0B0F19]">
                    Garage Kagnan <span class="text-[#E11D48]">— {{ roleInfo.label }}</span>
                </h1>
                <p class="text-xs font-semibold text-[#8A8D8F]">
                    Chargement de votre espace de travail...
                </p>
                
                <!-- Barre de chargement rouge -->
                <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full rounded-full bg-[#E11D48] animate-loader"></div>
                </div>
            </div>
        </div>
    </Transition>

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 py-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19]">
                        Espace de Travail
                    </h2>
                    <p class="text-sm text-[#8A8D8F] mt-0.5 font-medium">
                        Connecté en tant que <span class="font-bold text-[#0B0F19]">{{ user?.name }}</span>
                    </p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Badge du rôle -->
                    <span :class="['px-4 py-1.5 text-xs font-bold rounded-xl shadow-xs uppercase tracking-wider', roleInfo.badge]">
                        {{ roleInfo.label }}
                    </span>
                    
                    <!-- Bouton Déconnexion (si présent dans le header) -->
                    <button 
                        @click="logout" 
                        class="text-xs font-bold px-3 py-1.5 rounded-xl border border-gray-200 text-gray-600 hover:text-[#E11D48] hover:border-[#E11D48] transition bg-white shadow-xs"
                    >
                        Déconnexion
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12 bg-white min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

                <!-- ========================================== -->
                <!-- 1. VUE : RÉCEPTIONNISTE -->
                <!-- ========================================== -->
                <div v-if="user?.role === 'receptionniste'" class="space-y-8">
                    <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-8">
                        
                        <div class="flex items-center gap-5 pb-6 border-b border-gray-100">
                            <div class="w-14 h-14 rounded-2xl bg-[#0B0F19] flex items-center justify-center text-white shadow-md">
                                <i class="fa-solid fa-car-tunnel text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Réception Véhicule & Accueil Client</h3>
                                <p class="text-sm text-[#8A8D8F] font-medium">Enregistrez l'arrivée d'un véhicule et initiez le circuit de prise en charge.</p>
                            </div>
                        </div>

                        <!-- Actions rapides -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Link :href="route('reception.create')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-circle-plus text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Nouvelle Réception</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Fiche d'entrée, kilométrage et état initial.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#0B0F19] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <Link :href="route('parc.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-square-parking text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Véhicules sur le parc</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Consulter l'état des véhicules en cours d'accueil.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#0B0F19] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>
                        </div>

                        <!-- TABLEAU DES DOSSIERS EN COURS -->
                        <div class="pt-6 border-t border-gray-100 space-y-6">
                            <h4 class="text-lg font-black text-[#0B0F19] flex items-center gap-2.5">
                                <i class="fa-solid fa-clipboard-list text-[#E11D48] text-base"></i>
                                <span>Dossiers en cours / Atelier</span>
                            </h4>

                            <!-- Recherche + filtre par statut -->
                            <div class="flex flex-col md:flex-row gap-3">
                                <div class="w-full relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#8A8D8F]">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                                    <input
                                        v-model="search"
                                        type="text"
                                        placeholder="Rechercher par N° OT, nom du client, immatriculation, marque, modèle..."
                                        class="w-full pl-11 pr-4 py-3 text-sm bg-[#F8FAFC] border border-gray-200 rounded-2xl focus:ring-2 focus:ring-[#E11D48] focus:border-[#E11D48] transition text-[#0B0F19] placeholder:text-[#8A8D8F]"
                                    />
                                </div>
                                <select
                                    v-model="statut"
                                    class="w-full md:w-64 py-3 px-4 text-sm bg-[#F8FAFC] border border-gray-200 rounded-2xl focus:ring-2 focus:ring-[#E11D48] focus:border-[#E11D48] text-[#0B0F19]"
                                >
                                    <option value="">Tous les statuts</option>
                                    <option v-for="st in statuts" :key="st" :value="st">{{ getStatutLabel(st) }}</option>
                                </select>
                            </div>

                            <div v-if="interventionsAtelier?.data?.length > 0" class="overflow-x-auto rounded-2xl border border-gray-200 shadow-xs">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr class="bg-[#F8FAFC] text-left text-xs font-extrabold text-[#8A8D8F] uppercase tracking-wider">
                                            <th class="px-5 py-4">OT / Date</th>
                                            <th class="px-5 py-4">Client</th>
                                            <th class="px-5 py-4">Véhicule</th>
                                            <th class="px-5 py-4">Mécanicien</th>
                                            <th class="px-5 py-4">Statut / Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white text-sm">
                                        <tr v-for="item in interventionsAtelier.data" :key="item.id" class="hover:bg-gray-50/60 transition">
                                            <td class="px-5 py-4 whitespace-nowrap">
                                                <span class="font-extrabold text-[#0B0F19]">{{ item.numero_ot || 'N/A' }}</span>
                                                <div class="text-xs text-[#8A8D8F] font-medium">{{ item.date_reception }}</div>
                                            </td>
                                            <td class="px-5 py-4 whitespace-nowrap font-bold text-[#0B0F19]">
                                                {{ item.vehicule?.client?.nom }} {{ item.vehicule?.client?.prenom }}
                                            </td>
                                            <td class="px-5 py-4 whitespace-nowrap text-gray-600 font-medium">
                                                {{ item.vehicule?.marque }} {{ item.vehicule?.modele }} <span class="text-xs text-[#8A8D8F]">({{ item.vehicule?.immatriculation }})</span>
                                            </td>
                                            <td class="px-5 py-4 whitespace-nowrap text-[#0B0F19] font-bold">
                                                {{ item.mecanicien?.name || 'Non assigné' }}
                                            </td>
                                            <td class="px-5 py-4 whitespace-nowrap flex items-center gap-4">
                                                <span class="px-3 py-1 text-xs font-black rounded-lg bg-[#E11D48]/10 text-[#E11D48] border border-[#E11D48]/20 uppercase tracking-wide">
                                                    {{ getStatutLabel(item.statut) }}
                                                </span>
                                                <Link :href="route('parc.show', item.id)" class="text-[#0B0F19] font-extrabold hover:text-[#E11D48] transition inline-flex items-center gap-1.5 text-xs">
                                                    <span>Consulter</span>
                                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center py-10 text-[#8A8D8F] text-sm bg-[#F8FAFC] rounded-2xl border border-dashed border-gray-200 font-medium">
                                {{ (search || statut) ? 'Aucun dossier ne correspond à votre recherche.' : 'Aucun dossier pour le moment.' }}
                            </div>

                            <!-- Pagination -->
                            <div v-if="interventionsAtelier?.total > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3">
                                <p class="text-xs text-[#8A8D8F] font-medium">
                                    Affichage de {{ interventionsAtelier.from }} à {{ interventionsAtelier.to }} sur {{ interventionsAtelier.total }} dossier(s)
                                </p>
                                <div class="flex items-center gap-2">
                                    <Link
                                        v-if="interventionsAtelier.prev_page_url"
                                        :href="interventionsAtelier.prev_page_url"
                                        preserve-scroll
                                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-[#0B0F19] text-xs font-bold rounded-xl transition"
                                    >
                                        <i class="fa-solid fa-chevron-left text-[10px] mr-1"></i> Précédent
                                    </Link>
                                    <span class="px-3 text-xs font-bold text-[#0B0F19]">
                                        Page {{ interventionsAtelier.current_page }} / {{ interventionsAtelier.last_page }}
                                    </span>
                                    <Link
                                        v-if="interventionsAtelier.next_page_url"
                                        :href="interventionsAtelier.next_page_url"
                                        preserve-scroll
                                        class="px-4 py-2 bg-[#0B0F19] hover:bg-gray-800 text-white text-xs font-bold rounded-xl transition"
                                    >
                                        Suivant <i class="fa-solid fa-chevron-right text-[10px] ml-1"></i>
                                    </Link>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 2. VUE : MÉCANICIEN -->
                <!-- ========================================== -->
                <div v-else-if="user?.role === 'mecanicien'" class="space-y-8">
                    <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-8">
                        <div class="flex items-center gap-5 pb-6 border-b border-gray-100">
                            <div class="w-14 h-14 rounded-2xl bg-[#0B0F19] flex items-center justify-center text-white shadow-md">
                                <i class="fa-solid fa-wrench text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Atelier & Diagnostics</h3>
                                <p class="text-sm text-[#8A8D8F] font-medium">Consultez les véhicules assignés, réalisez les essais et diagnostics techniques.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Link :href="route('mecanicien.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#0B0F19] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#0B0F19]">
                                        <i class="fa-solid fa-screwdriver-wrench text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19]">Mes Interventions Assignées</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Liste des travaux en cours dans votre bay d'atelier.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#0B0F19] group-hover:bg-[#0B0F19] group-hover:text-white transition-all shadow-xs">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <div class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#0B0F19] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs cursor-pointer">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#0B0F19]">
                                        <i class="fa-solid fa-car-burst text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19]">Rapports d'Essai</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Saisir les observations suite aux essais routiers.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#0B0F19] group-hover:bg-[#0B0F19] group-hover:text-white transition-all shadow-xs">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 3. VUE : ADMINISTRATIF -->
                <!-- ========================================== -->
                <div v-else-if="user?.role === 'administratif'" class="space-y-8">
                    <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-8">
                        <div class="flex items-center gap-5 pb-6 border-b border-gray-100">
                            <div class="w-14 h-14 rounded-2xl bg-[#E11D48] flex items-center justify-center text-white shadow-md">
                                <i class="fa-solid fa-file-invoice text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Devis & Facturation</h3>
                                <p class="text-sm text-[#8A8D8F] font-medium">Gérez l'édition des devis, validez les coûts et éditez les factures clients.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- 1. En attente de devis -->
                            <Link :href="route('administration.dossiers.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-file-pen text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">En Attente de Devis</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Chiffrages pièces et main-d'œuvre à transformer.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- 2. Devis en attente de validation -->
                            <Link :href="route('administration.facturation.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-receipt text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Devis en Attente de Validation</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Saisir les choix du client et enregistrer.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- 3. Devis validés par le client -->
                            <Link :href="route('administration.devis.acceptes')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-clipboard-check text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Devis Validés</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Dossiers dont l'accord client a été enregistré.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- 4. Historique global des devis -->
                            <Link :href="route('administration.devis.historique')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-clock-rotate-left text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Historique Global des Devis</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Vue d'ensemble de tous les services (acceptés/refusés).</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- 5. Devis Direct -->
                            <Link :href="route('administration.devis.directs.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-file-invoice-dollar text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Devis Direct</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Création et gestion des devis directs (Comptoir).</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- 6. Facture et Encaissement -->
                            <Link :href="route('administration.factures.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-cash-register text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Facture & Encaissement</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Gérez la facturation finale et le suivi des règlements.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- 7. Gestion des Stocks -->
                            <Link :href="route('administration.stocks.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs md:col-span-2">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-boxes-stacked text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Gestion des Stocks de Pièces</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Consultez, ajoutez, modifiez ou supprimez les pièces et composants du stock.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 4. VUE : CHARGÉ DE SUIVI CLIENT -->
                <!-- ========================================== -->
                <div v-else-if="user?.role === 'charge_client'" class="space-y-8">
                    <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-8">
                        <div class="flex items-center gap-5 pb-6 border-b border-gray-100">
                            <div class="w-14 h-14 rounded-2xl bg-[#0B0F19] flex items-center justify-center text-white shadow-md">
                                <i class="fa-solid fa-headset text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Suivi Client & Relances</h3>
                                <p class="text-sm text-[#8A8D8F] font-medium">Consultez l'annuaire des clients, leurs véhicules, les alertes d'assurance/SICTA et les devis refusés.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Link :href="route('charge_client.clients.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-users text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Annuaire des Clients & Véhicules</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Accéder aux fiches clients, historique, assurances et lignes refusées.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- En attente de devis (mêmes routes que l'administration) -->
                            <Link :href="route('administration.dossiers.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-file-pen text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">En Attente de Devis</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Chiffrages pièces et main-d'œuvre à transformer.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- Devis en attente de validation (mêmes routes que l'administration) -->
                            <Link :href="route('administration.facturation.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-receipt text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Devis en Attente de Validation</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Modifier les devis, saisir les choix du client et enregistrer.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>

                            <!-- Devis Direct (mêmes routes que l'administration) -->
                            <Link :href="route('administration.devis.directs.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                                        <i class="fa-solid fa-file-invoice-dollar text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Devis Direct</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Création et gestion des devis directs (Comptoir).</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs shrink-0">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Disparition fondue du loader */
.fade-leave-active {
    transition: opacity 0.4s ease;
}
.fade-leave-to {
    opacity: 0;
}

/* Animation de la barre rouge */
@keyframes loadingBar {
    0% {
        width: 0%;
    }
    50% {
        width: 70%;
    }
    100% {
        width: 100%;
    }
}

.animate-loader {
    animation: loadingBar 1.5s ease-in-out infinite;
}
</style>