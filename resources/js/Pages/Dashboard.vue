<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// Récupération de l'utilisateur connecté via les props partagées d'Inertia
const page = usePage();
const user = computed(() => page.props.auth.user);

// Libellés et styles des rôles (hors admin)
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
                    <h2 class="text-2xl font-bold tracking-tight" style="color: #1A1A1A;">
                        Espace de Travail — Garage
                    </h2>
                    <p class="text-sm mt-0.5" style="color: #8A8D8F;">
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
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center text-2xl shadow-inner">🚗</div>
                            <div>
                                <h3 class="text-xl font-bold" style="color: #1A1A1A;">Réception Véhicule & Accueil Client</h3>
                                <p class="text-sm text-gray-500">Enregistrez l'arrivée d'un véhicule et initiez le circuit de prise en charge.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                            <!-- Lien cliquable vers le formulaire de nouvelle réception -->
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
                                <h3 class="text-xl font-bold" style="color: #1A1A1A;">Atelier & Diagnostics</h3>
                                <p class="text-sm text-gray-500">Consultez les véhicules assignés, réalisez les essais et diagnostics techniques.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                            <div class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-gray-400 transition cursor-pointer flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-900">Mes Interventions Assignées</h4>
                                    <p class="text-xs text-gray-500 mt-1">Liste des travaux en cours dans votre bay d'atelier.</p>
                                </div>
                                <span class="text-gray-700 font-bold text-lg">→</span>
                            </div>
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
                                <h3 class="text-xl font-bold" style="color: #1A1A1A;">Devis & Facturation</h3>
                                <p class="text-sm text-gray-500">Gérez l'édition des devis, validez les coûts et éditez les factures clients.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                            <div class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 transition cursor-pointer flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-900">Devis en attente de validation</h4>
                                    <p class="text-xs text-gray-500 mt-1">Chiffrages pièces et main-d'œuvre à transformer.</p>
                                </div>
                                <span class="text-blue-600 font-bold text-lg">→</span>
                            </div>
                            <div class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 transition cursor-pointer flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-900">Facturation & Règlements</h4>
                                    <p class="text-xs text-gray-500 mt-1">Suivi des encaissements et factures acquittées.</p>
                                </div>
                                <span class="text-gray-400 font-bold text-lg">→</span>
                            </div>
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
                                <h3 class="text-xl font-bold" style="color: #1A1A1A;">Suivi Client & Relances</h3>
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