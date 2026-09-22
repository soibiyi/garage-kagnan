<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    interventionsAtelier: Array, // Reçoit les dossiers en cours/atelier depuis le contrôleur
});

// Récupération de l'utilisateur connecté via les props partagées d'Inertia
const page = usePage();
const user = computed(() => page.props.auth.user);

// Libellés et styles des rôles
const roleInfo = computed(() => {
    const roles = {
        receptionniste: { label: 'Réceptionniste', badge: 'bg-red-50 text-[#C8102E] border border-[#C8102E]' },
        mecanicien: { label: 'Mécanicien', badge: 'bg-gray-100 text-[#1A1A1A] border border-gray-300' },
        administratif: { label: 'Administratif', badge: 'bg-blue-50 text-blue-700 border border-blue-200' },
        charge_client: { label: 'Chargé de Suivi Client', badge: 'bg-amber-50 text-amber-700 border border-amber-200' },
    };
    return roles[user.value?.role] || { label: user.value?.role, badge: 'bg-gray-100 text-gray-700' };
});
</script>

<template>
    <Head title="Tableau de bord — Garage" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900">
                        Espace de Travail — Garage
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Bienvenue, <span class="font-semibold text-gray-900">{{ user?.name }}</span>
                    </p>
                </div>
                <!-- Badge du rôle -->
                <span :class="['px-3 py-1.5 text-xs font-bold rounded-full shadow-sm', roleInfo.badge]">
                    {{ roleInfo.label }}
                </span>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- ========================================== -->
                <!-- 1. VUE : RÉCEPTIONNISTE -->
                <!-- ========================================== -->
                <div v-if="user?.role === 'receptionniste'" class="space-y-6">
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 space-y-6">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center text-2xl shadow-inner">🚗</div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Réception Véhicule & Accueil Client</h3>
                                <p class="text-sm text-gray-500">Enregistrez l'arrivée d'un véhicule et initiez le circuit de prise en charge.</p>
                            </div>
                        </div>

                        <!-- Les 2 liens de la réception -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Link :href="route('reception.create')" class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-[#C8102E] transition flex items-center justify-between group">
                                <div>
                                    <h4 class="font-bold text-gray-900 group-hover:text-[#C8102E] transition">+ Nouvelle Réception</h4>
                                    <p class="text-xs text-gray-500 mt-1">Fiche d'entrée, kilométrage et état initial.</p>
                                </div>
                                <span class="text-[#C8102E] font-bold text-lg transform group-hover:translate-x-1 transition">→</span>
                            </Link>

                            <Link :href="route('parc.index')" class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-[#C8102E] transition flex items-center justify-between group">
                                <div>
                                    <h4 class="font-bold text-gray-900 group-hover:text-[#C8102E] transition">Véhicules sur le parc</h4>
                                    <p class="text-xs text-gray-500 mt-1">Consulter l'état des véhicules en cours d'accueil.</p>
                                </div>
                                <span class="text-[#C8102E] font-bold text-lg transform group-hover:translate-x-1 transition">→</span>
                            </Link>
                        </div>

                        <!-- ========================================== -->
                        <!-- TABLEAU EN BAS DE LA SECTION RÉCEPTIONNISTE -->
                        <!-- ========================================== -->
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="text-lg font-bold text-gray-900 mb-4">Dossiers en cours / Atelier</h4>

                            <div v-if="interventionsAtelier && interventionsAtelier.length > 0" class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <th class="px-4 py-3">OT / Date</th>
                                            <th class="px-4 py-3">Client</th>
                                            <th class="px-4 py-3">Véhicule</th>
                                            <th class="px-4 py-3">Mécanicien</th>
                                            <th class="px-4 py-3">Statut / Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white text-sm">
                                        <tr v-for="item in interventionsAtelier" :key="item.id" class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="font-bold text-gray-900">{{ item.numero_ot || 'N/A' }}</span>
                                                <div class="text-xs text-gray-400">{{ item.date_reception }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-800">
                                                {{ item.vehicule?.client?.nom }} {{ item.vehicule?.client?.prenom }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                                {{ item.vehicule?.marque }} {{ item.vehicule?.modele }} ({{ item.vehicule?.immatriculation }})
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                                {{ item.mecanicien?.name || 'Non assigné' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap flex items-center gap-3">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-lg bg-red-50 text-[#C8102E] border border-red-200">
                                                    {{ item.statut }}
                                                </span>
                                                <Link :href="route('parc.show', item.id)" class="text-gray-700 font-semibold hover:underline">
                                                    Consulter →
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center py-6 text-gray-400 text-sm bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                Aucun dossier en cours pour le moment.
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ========================================== -->
<!-- 2. VUE : MÉCANICIEN -->
<!-- ========================================== -->
<div v-else-if="user?.role === 'mecanicien'" class="space-y-6">
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center text-2xl shadow-inner">🔧</div>
            <div>
                <h3 class="text-xl font-bold text-gray-900">Atelier & Diagnostics</h3>
                <p class="text-sm text-gray-500">Consultez les véhicules assignés, réalisez les essais et diagnostics techniques.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
            <Link :href="route('mecanicien.index')" class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-gray-400 transition cursor-pointer flex items-center justify-between">
                <div>
                    <h4 class="font-bold text-gray-900">Mes Interventions Assignées</h4>
                    <p class="text-xs text-gray-500 mt-1">Liste des travaux en cours dans votre bay d'atelier.</p>
                </div>
                <span class="text-gray-700 font-bold text-lg">→</span>
            </Link>
            <div class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-gray-400 transition cursor-pointer flex items-center justify-between">
                <div>
                    <h4 class="font-bold text-gray-900">Rapports d'Essai</h4>
                    <p class="text-xs text-gray-500 mt-1">Saisir les observations suite aux essais routiers.</p>
                </div>
                <span class="text-gray-400 font-bold text-lg">→</span>
            </div>
        </div>
    </div>
</div>
            <!-- ========================================== -->
<!-- 3. VUE : ADMINISTRATIF -->
<!-- ========================================== -->
<div v-else-if="user?.role === 'administratif'" class="space-y-6">
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-2xl shadow-inner">📄</div>
            <div>
                <h3 class="text-xl font-bold text-gray-900">Devis & Facturation</h3>
                <p class="text-sm text-gray-500">Gérez l'édition des devis, validez les coûts et éditez les factures clients.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
            <!-- Lien vers Devis en attente -->
            <Link :href="route('administration.dossiers.index')" class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 transition flex items-center justify-between group">
                <div>
                    <h4 class="font-bold text-gray-900 group-hover:text-blue-600 transition">Devis en attente de validation</h4>
                    <p class="text-xs text-gray-500 mt-1">Chiffrages pièces et main-d'œuvre à transformer.</p>
                </div>
                <span class="text-blue-600 font-bold text-lg transform group-hover:translate-x-1 transition">→</span>
            </Link>

            <!-- Lien cliquable vers Facturation & Règlements -->
            <Link :href="route('administration.facturation.index')" class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 transition flex items-center justify-between group">
                <div>
                    <h4 class="font-bold text-gray-900 group-hover:text-blue-600 transition">Facturation & Règlements</h4>
                    <p class="text-xs text-gray-500 mt-1">Suivi des encaissements et factures acquittées.</p>
                </div>
                <span class="text-blue-600 font-bold text-lg transform group-hover:translate-x-1 transition">→</span>
            </Link>
        </div>
    </div>
</div>

                <!-- ========================================== -->
                <!-- 4. VUE : CHARGÉ DE SUIVI CLIENT -->
                <!-- ========================================== -->
                <div v-else-if="user?.role === 'charge_client'" class="space-y-6">
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-2xl shadow-inner">📞</div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Suivi Client & Relances</h3>
                                <p class="text-sm text-gray-500">Suivez l'état d'avancement des réparations et gérez la communication client.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                            <div class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-amber-300 transition cursor-pointer flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-900">Suivi des Réparations</h4>
                                    <p class="text-xs text-gray-500 mt-1">État d'avancement en temps réel pour information client.</p>
                                </div>
                                <span class="text-amber-600 font-bold text-lg">→</span>
                            </div>
                            <div class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-amber-300 transition cursor-pointer flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-900">Journal des Relances</h4>
                                    <p class="text-xs text-gray-500 mt-1">Appels de restitution et enquêtes de satisfaction.</p>
                                </div>
                                <span class="text-gray-400 font-bold text-lg">→</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>