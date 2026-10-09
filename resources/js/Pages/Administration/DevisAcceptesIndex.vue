<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    dossiers: Array,
    filters: Object,
});

const page = usePage();

// Gestion de la recherche
const search = ref(props.filters?.search || '');

watch(search, (value) => {
    router.get(
        route('administration.devis.acceptes'),
        { search: value },
        { preserveState: true, replace: true }
    );
});

// Propriété calculée pour déterminer si l'utilisateur est un mécanicien
const isMecanicien = computed(() => {
    const user = page.props.auth.user;
    return user?.role === 'mecanicien' || user?.roles?.some(r => r.name === 'mecanicien');
});

// Lien de retour dynamique vers le tableau de bord ou l'atelier
const backUrl = computed(() => {
    if (isMecanicien.value) {
        return route('mecanicien.index'); 
    }
    return route('dashboard'); 
});

// Texte du bouton dynamique
const backText = computed(() => {
    return isMecanicien.value 
        ? '← Retour à mon atelier' 
        : '← Retour au tableau de bord';
});
</script>

<template>
    <Head title="Devis Validés" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-clipboard-check text-[#E11D48]"></i>
                        <span>Devis Validés par le Client</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Liste des dossiers (circuit normal et devis directs) dont l'accord client a été enregistré et prêts pour la suite.
                    </p>
                </div>

                <!-- Bouton de retour dynamique -->
                <Link 
                    :href="backUrl" 
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition"
                >
                    {{ backText }}
                </Link>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- BARRE DE RECHERCHE -->
                <div class="flex justify-between items-center">
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input 
                            type="text" 
                            v-model="search" 
                            placeholder="Rechercher par nom ou numéro d'OT..." 
                            class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-900 focus:outline-none focus:border-[#E11D48] transition shadow-xs"
                        />
                    </div>
                </div>

                <div class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl overflow-hidden border border-gray-100 p-6">
                    
                    <div v-if="dossiers.length === 0" class="text-center py-16">
                        <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                            <i class="fa-solid fa-folder-closed text-xl"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun devis validé</h3>
                        <p class="text-xs text-gray-500 mt-1">Aucun devis validé ne correspond à votre recherche.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50/70 text-gray-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">N° Dossier / Véhicule</th>
                                    <th class="px-6 py-3 font-semibold">Client</th>
                                    <th class="px-6 py-3 font-semibold">Statut Devis</th>
                                    <th class="px-6 py-3 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/60">
                                <tr v-for="dossier in dossiers" :key="dossier.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-gray-900 flex items-center gap-2 uppercase">
                                            <i class="fa-solid fa-car text-gray-400 text-[11px]"></i>
                                            <span>{{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }}</span>
                                        </div>
                                        <span class="font-mono text-[11px] text-[#E11D48] mt-0.5 block">
                                            Immat: {{ dossier.vehicule?.immatriculation }} | OT: {{ dossier.numero_ot || 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">
                                        {{ dossier.vehicule?.client?.nom || '' }} {{ dossier.vehicule?.client?.prenom || dossier.vehicule?.client?.name || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 inline-flex text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Accepté
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <!-- Modifiable uniquement tant qu'aucun paiement n'a été enregistré -->
                                            <template v-if="!dossier.a_paiement">
                                                <Link
                                                    :href="route('administration.devis.edit', dossier.id)"
                                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-[11px]"
                                                    title="Ajouter ou supprimer des lignes, cocher ou décocher les choix du client"
                                                >
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                    <span>Modifier</span>
                                                </Link>
                                            </template>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-100 text-gray-500 border border-gray-200 font-bold uppercase tracking-wider rounded-lg text-[10px]"
                                                title="Un paiement a déjà été enregistré : ce devis ne peut plus être modifié"
                                            >
                                                <i class="fa-solid fa-lock text-[10px]"></i>
                                                <span>Paiement enregistré</span>
                                            </span>

                                            <Link
                                                :href="route('administration.facturation.show', dossier.id)"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#E11D48] hover:bg-rose-700 text-white font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-[11px]"
                                            >
                                                <span>Voir Infos</span>
                                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>