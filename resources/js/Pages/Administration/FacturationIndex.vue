<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    dossiers: { type: Array, default: () => [] },
});

const page = usePage();

// ---------- Recherche (filtre côté navigateur) ----------
const search = ref('');

// Ignore la casse et les accents ("Séné" trouve "sene")
const normaliser = (v) =>
    String(v ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

const dossiersFiltres = computed(() => {
    const q = normaliser(search.value).trim();
    if (!q) return props.dossiers;

    return props.dossiers.filter((d) =>
        normaliser([
            d.numero_ot,
            d.vehicule?.marque,
            d.vehicule?.modele,
            d.vehicule?.immatriculation,
            d.vehicule?.client?.nom,
            d.vehicule?.client?.prenom,
            d.vehicule?.client?.name,
            d.statut,
        ].join(' ')).includes(q)
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
    <Head title="Facturation & Règlements" />

    <AuthenticatedLayout>
        <!-- En-tête de page -->
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Devis & Validation</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Gérez les devis et valider les choix clients.
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
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl overflow-hidden border border-gray-100 p-6">

                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-clock text-gray-400 text-xs"></i>
                            <span>Devis en attente de validation </span>
                        </h3>
                        <span v-if="dossiers.length > 0" class="text-[11px] font-semibold text-gray-400">
                            {{ dossiersFiltres.length }} / {{ dossiers.length }} dossier(s)
                        </span>
                    </div>

                    <!-- Barre de recherche -->
                    <div v-if="dossiers.length > 0" class="relative mb-6">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Rechercher par client, véhicule, immatriculation ou statut..."
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl text-xs py-2.5 pl-9 pr-9 focus:border-[#E11D48] focus:ring-[#E11D48] transition"
                        />
                        <button
                            v-if="search"
                            type="button"
                            @click="search = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition"
                            title="Effacer"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <!-- Message si aucun dossier -->
                    <div v-if="dossiers.length === 0" class="text-center py-16">
                        <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mx-auto mb-3">
                            <i class="fa-solid fa-folder-closed text-xl"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Aucun dossier</h3>
                        <p class="text-xs text-gray-500 mt-1">Il n'y a pas de dossier en attente pour le moment.</p>
                    </div>

                    <!-- Tableau des dossiers -->
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50/70 text-gray-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">N° Dossier / Véhicule</th>
                                    <th class="px-6 py-3 font-semibold">Client</th>
                                    <th class="px-6 py-3 font-semibold">Statut</th>
                                    <th class="px-6 py-3 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/60">
                                <tr v-for="dossier in dossiersFiltres" :key="dossier.id" class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-gray-900 flex items-center gap-2 uppercase">
                                            <i class="fa-solid fa-car text-gray-400 text-[11px]"></i>
                                            <span>{{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }}</span>
                                        </div>
                                        <span class="font-mono text-[11px] text-[#E11D48] mt-0.5 block">
                                            Immat: {{ dossier.vehicule?.immatriculation }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">
                                        {{ dossier.vehicule?.client?.nom || '' }} {{ dossier.vehicule?.client?.prenom || dossier.vehicule?.client?.name || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 inline-flex text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200">
                                            {{ dossier.statut }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <Link
                                                :href="route('administration.devis.edit', dossier.id)"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-[11px]"
                                            >
                                                <i class="fa-solid fa-pen text-[10px]"></i>
                                                <span>Modifier</span>
                                            </Link>
                                            <Link
                                                :href="route('administration.facturation.show', dossier.id)"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#E11D48] hover:bg-rose-700 text-white font-bold uppercase tracking-wider rounded-lg shadow-sm transition text-[11px]"
                                            >
                                                <span>Traiter</span>
                                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Aucun résultat pour la recherche -->
                                <tr v-if="dossiersFiltres.length === 0">
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-400">
                                        <i class="fa-solid fa-magnifying-glass text-xl mb-2 block"></i>
                                        <span class="text-xs">Aucun résultat pour « {{ search }} ».</span>
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