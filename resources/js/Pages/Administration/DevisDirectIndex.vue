<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    devisDirects: {
        type: Array,
        default: () => []
    }
});

const rechercheDevis = ref('');
const normaliser = (valeur) => String(valeur ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

const devisDirectsFiltres = computed(() => {
    const recherche = normaliser(rechercheDevis.value.trim());
    if (!recherche) return props.devisDirects;

    return props.devisDirects.filter((dossier) => {
        const client = dossier.vehicule?.client;
        const vehicule = dossier.vehicule;
        const contenuDevis = (dossier.devis?.lignes ?? [])
            .flatMap((ligne) => [ligne.designation, ligne.reference_piece]);
        const champs = [
            dossier.id,
            dossier.numero_ot,
            client?.nom,
            client?.prenom,
            client?.prenoms,
            client?.telephone,
            vehicule?.marque,
            vehicule?.modele,
            vehicule?.immatriculation,
            dossier.created_at,
            dossier.receptionniste?.name,
            getStatutLabel(dossier),
            ...contenuDevis,
        ];

        return normaliser(champs.join(' ')).includes(recherche);
    });
});

// ─────────────────────────────────────────────
// HELPERS POUR STATUT & PAIEMENT
// ─────────────────────────────────────────────
const estSolde = (dossier) => {
    return Boolean(dossier.resume_paiement?.soldee) || 
           dossier.statut?.toLowerCase() === 'solde' || 
           dossier.statut?.toLowerCase() === 'soldé' ||
           dossier.statut?.toLowerCase() === 'payé' ||
           dossier.statut?.toLowerCase() === 'paye';
};

const getStatutLabel = (dossier) => {
    if (estSolde(dossier)) {
        return 'Soldé';
    }
    return dossier.statut || 'En attente';
};

// ─────────────────────────────────────────────
// ACTUALISATION AUTOMATIQUE
// ─────────────────────────────────────────────
const REFRESH_INTERVAL = 4000;
let refreshTimer = null;
let rechargementEnCours = false;

const actualiser = () => {
    if (document.hidden || rechargementEnCours) return;

    rechargementEnCours = true;
    router.reload({
        only: ['devisDirects'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            rechargementEnCours = false;
        },
    });
};

const onVisibilityChange = () => {
    if (!document.hidden) actualiser();
};

onMounted(() => {
    refreshTimer = setInterval(actualiser, REFRESH_INTERVAL);
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('focus', actualiser);
});

onUnmounted(() => {
    clearInterval(refreshTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('focus', actualiser);
});
</script>

<template>
    <Head title="Gestion des Devis Directs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[#E11D48]"></i>
                        <span>Gestion des Devis Directs</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Consultez et créez vos devis rapides en atelier.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Bouton pour créer un nouveau devis direct -->
                    <Link 
                        :href="route('administration.devis.directs.create')" 
                        class="px-4 py-2 bg-[#E11D48] hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-rose-600/25 transition flex items-center gap-2"
                    >
                        <i class="fa-solid fa-plus"></i>
                        <span> Nouveau Devis Direct</span>
                    </Link>

                    <!-- Bouton de retour -->
                    <Link 
                        :href="route('dashboard')" 
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition"
                    >
                        ← Retour
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-white min-h-screen text-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Tableau des devis directs -->
                <div class="bg-white shadow-xl shadow-gray-200/50 rounded-2xl overflow-hidden border border-gray-100">
                    <div v-if="props.devisDirects.length === 0" class="p-12 text-center">
                        <div class="w-16 h-16 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-[#E11D48] mx-auto mb-4">
                            <i class="fa-solid fa-folder-open text-2xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">Aucun devis direct enregistré</h3>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                            Commencez par créer votre premier devis direct en cliquant sur le bouton en haut à droite.
                        </p>
                    </div>

                    <template v-else>
                        <div class="flex flex-col gap-3 border-b border-gray-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="relative w-full sm:max-w-md">
                                <label for="recherche-devis-direct" class="sr-only">Rechercher un client ou un devis direct</label>
                                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
                                <input
                                    id="recherche-devis-direct"
                                    v-model="rechercheDevis"
                                    type="search"
                                    placeholder="Client, téléphone, immatriculation, numéro ou pièce…"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-10 text-xs focus:border-[#E11D48] focus:ring-[#E11D48]"
                                />
                                <button
                                    v-if="rechercheDevis"
                                    type="button"
                                    @click="rechercheDevis = ''"
                                    class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-md text-gray-400 hover:bg-gray-200 hover:text-gray-700"
                                    aria-label="Effacer la recherche"
                                >
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </button>
                            </div>
                            <span class="text-xs font-semibold text-gray-500" aria-live="polite">
                                {{ devisDirectsFiltres.length }} / {{ props.devisDirects.length }} devis
                            </span>
                        </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs align-middle">
                            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-3">Client / Véhicule</th>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3">Créé par</th>
                                    <th class="px-6 py-3">Statut</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="dossier in devisDirectsFiltres" :key="dossier.id" class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-gray-900">
                                            {{ dossier.vehicule?.client?.nom || 'Client inconnu' }} {{ dossier.vehicule?.client?.prenoms || dossier.vehicule?.client?.prenom || '' }}
                                        </div>
                                        <div class="text-gray-500 text-[11px]">
                                            {{ dossier.vehicule?.marque }} {{ dossier.vehicule?.modele }} ({{ dossier.vehicule?.immatriculation }})
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        {{ new Date(dossier.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        {{ dossier.receptionniste?.name || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <!-- BADGE DYNAMIQUE -->
                                        <span
                                            v-if="estSolde(dossier)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200"
                                        >
                                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                                            <span>{{ getStatutLabel(dossier) }}</span>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200"
                                        >
                                            <i class="fa-solid fa-hourglass-half text-[10px]"></i>
                                            <span>{{ getStatutLabel(dossier) }}</span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                        <Link 
                                            :href="route('administration.facturation.show', dossier.id)" 
                                            class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition"
                                        >
                                            Voir le détail
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="devisDirectsFiltres.length === 0">
                                    <td colspan="5" class="p-10 text-center text-sm text-gray-500">
                                        Aucun client ou devis ne correspond à « {{ rechercheDevis }} ».
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    </template>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>