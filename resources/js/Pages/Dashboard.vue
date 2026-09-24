<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    interventionsAtelier: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Libellés et styles des rôles basés sur la nouvelle palette (Noir profond, Gris métallique, Rouge passion)
const roleInfo = computed(() => {
    const roles = {
        receptionniste: { label: 'Réceptionniste', badge: 'bg-[#0B0F19] text-white border border-[#0B0F19]' },
        mecanicien: { label: 'Mécanicien', badge: 'bg-[#8A8D8F]/20 text-[#0B0F19] border border-[#8A8D8F]/40' },
        administratif: { label: 'Administratif', badge: 'bg-[#E11D48]/10 text-[#E11D48] border border-[#E11D48]/30' },
        charge_client: { label: 'Chargé de Suivi Client', badge: 'bg-gray-100 text-gray-800 border border-gray-300' },
    };
    return roles[user.value?.role] || { label: user.value?.role, badge: 'bg-gray-100 text-gray-700 border border-gray-200' };
});
</script>

<template>
    <Head title="Tableau de bord — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 py-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19]">
                        Espace de Travail — <span class="text-[#E11D48]">Garage Kagnan</span>
                    </h2>
                    <p class="text-sm text-[#8A8D8F] mt-0.5 font-medium">
                        Connecté en tant que <span class="font-bold text-[#0B0F19]">{{ user?.name }}</span>
                    </p>
                </div>
                <!-- Badge du rôle -->
                <span :class="['px-4 py-1.5 text-xs font-bold rounded-xl shadow-xs uppercase tracking-wider', roleInfo.badge]">
                    {{ roleInfo.label }}
                </span>
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

                            <div v-if="interventionsAtelier && interventionsAtelier.length > 0" class="overflow-x-auto rounded-2xl border border-gray-200 shadow-xs">
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
                                        <tr v-for="item in interventionsAtelier" :key="item.id" class="hover:bg-gray-50/60 transition">
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
                                                    {{ item.statut }}
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
                                Aucun dossier en cours pour le moment.
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
                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs">
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
                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs">
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
                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs">
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
                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs">
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </span>
            </Link>

            <!-- 5. Devis Direct (Actif) -->
            <Link :href="route('administration.devis.directs.index')" class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#E11D48] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs md:col-span-2">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5 text-[#E11D48]">
                        <i class="fa-solid fa-file-invoice-dollar text-base"></i>
                        <h4 class="font-extrabold text-[#0B0F19] group-hover:text-[#E11D48] transition">Devis Direct</h4>
                    </div>
                    <p class="text-xs text-[#8A8D8F] font-medium">Création et gestion des devis directs (Comptoir).</p>
                </div>
                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#E11D48] group-hover:bg-[#E11D48] group-hover:text-white group-hover:border-[#E11D48] transition-all shadow-xs">
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
                            <div class="w-14 h-14 rounded-2xl bg-[#8A8D8F] flex items-center justify-center text-white shadow-md">
                                <i class="fa-solid fa-headset text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Suivi Client & Relances</h3>
                                <p class="text-sm text-[#8A8D8F] font-medium">Suivez l'état d'avancement des réparations et gérez la communication client.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#0B0F19] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs cursor-pointer">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#0B0F19]">
                                        <i class="fa-solid fa-timeline text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19]">Suivi des Réparations</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">État d'avancement en temps réel pour information client.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#0B0F19] group-hover:bg-[#0B0F19] group-hover:text-white transition-all shadow-xs">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </div>

                            <div class="p-6 bg-[#F8FAFC] rounded-2xl border border-gray-200/80 hover:border-[#0B0F19] hover:bg-white transition-all duration-300 flex items-center justify-between group shadow-xs cursor-pointer">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5 text-[#0B0F19]">
                                        <i class="fa-solid fa-phone-volume text-base"></i>
                                        <h4 class="font-extrabold text-[#0B0F19]">Journal des Relances</h4>
                                    </div>
                                    <p class="text-xs text-[#8A8D8F] font-medium">Appels de restitution et enquêtes de satisfaction.</p>
                                </div>
                                <span class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#0B0F19] group-hover:bg-[#0B0F19] group-hover:text-white transition-all shadow-xs">
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>