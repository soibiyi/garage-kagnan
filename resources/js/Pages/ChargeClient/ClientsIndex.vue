<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    clients: Array,
    filters: Object,
});

// Champ de recherche initialisé avec la valeur précédente
const search = ref(props.filters?.search || '');

// Synchronisation de la recherche avec Inertia (rechargement automatique)
watch(search, (value) => {
    router.get(
        route('charge_client.clients.index'),
        { search: value },
        { preserveState: true, replace: true }
    );
});

// Fonction pour filtrer et compter uniquement les véhicules au circuit "normal"
const getNormalVehicles = (vehicules) => {
    if (!vehicules) return [];
    return vehicules.filter(v => 
        v.interventions && v.interventions.some(i => i.circuit && i.circuit.toLowerCase() === 'normal')
    );
};

// Fonction pour vérifier si une date est expirée ou arrive à échéance dans les 10 prochains jours
const isExpiringSoonOrExpired = (dateStr) => {
    if (!dateStr) return false;
    const date = new Date(dateStr);
    const today = new Date();
    
    const tenDaysLimit = new Date();
    tenDaysLimit.setDate(today.getDate() + 10);
    
    return date <= tenDaysLimit;
};

const hasAlertes = (vehicules) => {
    const normalVehicles = getNormalVehicles(vehicules);
    return normalVehicles.some(v => 
        isExpiringSoonOrExpired(v.expiration_assurance) || isExpiringSoonOrExpired(v.expiration_sicta)
    );
};
</script>

<template>
    <Head title="Liste des Clients — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center py-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19]">
                        Annuaire des Clients (Circuit Normal)
                    </h2>
                    <p class="text-sm text-[#8A8D8F] mt-0.5 font-medium">
                        Sélectionnez un client pour voir ses véhicules et l'historique.
                    </p>
                </div>
                <Link :href="route('dashboard')" class="text-xs font-bold text-[#0B0F19] hover:text-[#E11D48] transition">
                    <i class="fa-solid fa-arrow-left mr-1.5"></i> Retour au tableau de bord
                </Link>
            </div>
        </template>

        <div class="py-12 bg-white min-h-screen">
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
                            class="w-full pl-10 pr-4 py-3 bg-[#F8FAFC] border border-gray-200 rounded-2xl text-sm font-medium text-[#0B0F19] focus:outline-none focus:border-[#E11D48] transition shadow-xs"
                        />
                    </div>
                </div>

                <div v-if="clients && clients.length > 0" class="overflow-x-auto rounded-3xl border border-gray-200 shadow-xs bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-[#F8FAFC] text-left text-xs font-extrabold text-[#8A8D8F] uppercase tracking-wider">
                                <th class="px-6 py-4">Client</th>
                                <th class="px-6 py-4">Téléphone</th>
                                <th class="px-6 py-4">Véhicules (Circuit Normal)</th>
                                <th class="px-6 py-4">Alerte Administrative</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-for="client in clients" :key="client.id" class="hover:bg-gray-50/60 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-extrabold text-[#0B0F19]">{{ client.nom }} {{ client.prenom }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-medium">
                                    {{ client.telephone || 'Non renseigné' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold text-[#0B0F19]">
                                    <span class="px-2.5 py-1 rounded-lg bg-gray-100 text-gray-800 text-xs">
                                        {{ getNormalVehicles(client.vehicules).length }} véhicule(s)
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="hasAlertes(client.vehicules)" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-[#E11D48]/10 text-[#E11D48] text-xs font-bold border border-[#E11D48]/30">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        Attention (Assurance ou SICTA)
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200">
                                        <i class="fa-solid fa-shield-check"></i>
                                        À jour
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <Link :href="route('charge_client.clients.show', client.id)" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0B0F19] text-white text-xs font-extrabold hover:bg-[#E11D48] transition shadow-xs">
                                        <span>Détails & Véhicules</span>
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-12 text-[#8A8D8F] text-sm bg-[#F8FAFC] rounded-3xl border border-dashed border-gray-200 font-medium">
                    Aucun résultat trouvé pour votre recherche.
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>