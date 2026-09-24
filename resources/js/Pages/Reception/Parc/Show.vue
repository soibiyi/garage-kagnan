<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    intervention: Object,
});

const page = usePage();

// Propriété calculée pour déterminer si l'utilisateur est un mécanicien
// (Adapte 'mechanicien' ou la structure selon ton système de rôles Laravel/Spatie)
const isMecanicien = computed(() => {
    const user = page.props.auth.user;
    // Si tu utilises un champ 'role' simple sur l'user ou un tableau de rôles
    return user?.role === 'mecanicien' || user?.roles?.some(r => r.name === 'mecanicien');
});

// Lien de retour dynamique selon le rôle
const backUrl = computed(() => {
    if (isMecanicien.value) {
        // Remplace 'mecanicien.index' par le nom exact de la route de l'espace mécanicien
        return route('mecanicien.index'); 
    }
    return route('parc.index');
});

// Texte du bouton dynamique selon le rôle
const backText = computed(() => {
    return isMecanicien.value 
        ? '← Retour à mon atelier' 
        : '← Retour à la liste du parc';
});

// Traduction et style des statuts
const getStatutBadge = (statut) => {
    const badges = {
        reception: { text: 'Sur le Parc (Réception)', class: 'bg-blue-100 text-blue-800' },
        atelier: { text: 'En Atelier', class: 'bg-amber-100 text-amber-800' },
        en_cours: { text: 'En Réparation', class: 'bg-purple-100 text-purple-800' },
        attente_accord: { text: 'Attente Accord Devis', class: 'bg-rose-100 text-rose-800' },
    };
    return badges[statut] || { text: statut, class: 'bg-gray-100 text-gray-800' };
};

// Fonction pour générer l'URL correcte des images stockées dans Laravel
const imageUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('blob:')) {
        return path;
    }
    if (path.startsWith('/storage/')) return path;
    if (path.startsWith('storage/')) return '/' + path;
    
    return `/storage/${path.replace(/^\/+/, '')}`;
};

// Liste des équipements pour affichage dynamique propre
const equipmentsList = [
    { key: 'allume_cigare', label: 'Allume-cigare' },
    { key: 'rk7', label: 'RK7 / Radio' },
    { key: 'rcd', label: 'RCD / Lecteur CD' },
    { key: 'essuie_glace_av', label: 'Essuie-glace AV' },
    { key: 'essuie_glace_ar', label: 'Essuie-glace AR' },
    { key: 'retro_ext_gauche', label: 'Rétro ext. gauche' },
    { key: 'retro_ext_droit', label: 'Rétro ext. droit' },
    { key: 'retro_int', label: 'Rétro intérieur' },
    { key: 'cric', label: 'Cric' },
    { key: 'manivelle', label: 'Manivelle' },
    { key: 'roue_secours', label: 'Roue de secours' },
    { key: 'trousse', label: 'Trousse à pharmacie' },
];
</script>

<template>
    <Head :title="`Fiche Véhicule - OT ${intervention.numero_ot}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold leading-tight text-gray-900">
                            Fiche de Réception — OT : <span class="text-indigo-600">{{ intervention.numero_ot }}</span>
                        </h2>
                        <span :class="['px-2.5 py-1 text-xs font-semibold rounded-full', getStatutBadge(intervention.statut).class]">
                            {{ getStatutBadge(intervention.statut).text }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Enregistré le {{ new Date(intervention.date_reception).toLocaleDateString() }} à {{ new Date(intervention.date_reception).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                    </p>
                </div>
                
                <!-- Lien de retour dynamique -->
                <Link 
                    :href="backUrl" 
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition"
                >
                    {{ backText }}
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- COLONNE GAUCHE & CENTRE : INFOS PRINCIPALES -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- 1. INFORMATIONS VÉHICULE -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                            <h3 class="text-base font-bold text-gray-900 border-b pb-3 flex items-center gap-2">
                                🚗 Véhicule Concerné
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-400 block text-xs">Immatriculation</span>
                                    <span class="font-extrabold text-gray-900 uppercase text-base">{{ intervention.vehicule?.immatriculation }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Marque & Modèle</span>
                                    <span class="font-bold text-gray-800">{{ intervention.vehicule?.marque }} {{ intervention.vehicule?.modele }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Numéro de Châssis (VIN)</span>
                                    <span class="font-mono font-medium text-gray-700">{{ intervention.vehicule?.vin || 'Non renseigné' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Kilométrage à l'entrée</span>
                                    <span class="font-bold text-indigo-600">{{ intervention.kilometrage }} km</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Expiration Assurance</span>
                                    <span class="font-medium text-gray-700">{{ intervention.vehicule?.expiration_assurance || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Expiration SICTA (Visite tech.)</span>
                                    <span class="font-medium text-gray-700">{{ intervention.vehicule?.expiration_sicta || 'N/A' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. INFORMATIONS CLIENT -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                            <h3 class="text-base font-bold text-gray-900 border-b pb-3 flex items-center gap-2">
                                👤 Client / Propriétaire
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-400 block text-xs">Nom & Prénom</span>
                                    <span class="font-bold text-gray-900">{{ intervention.vehicule?.client?.nom }} {{ intervention.vehicule?.client?.prenom }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Téléphone</span>
                                    <span class="font-bold text-gray-800">{{ intervention.vehicule?.client?.telephone }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">E-mail</span>
                                    <span class="text-gray-700">{{ intervention.vehicule?.client?.email || 'Aucun email' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Adresse</span>
                                    <span class="text-gray-700">{{ intervention.vehicule?.client?.adresse || 'Non renseignée' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. CARBURANT & TRAITEMENT -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                            <h3 class="text-base font-bold text-gray-900 border-b pb-3 flex items-center gap-2">
                                ⛽ Carburant & Traitement
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-400 block text-xs">Niveau de Carburant</span>
                                    <span class="font-bold text-gray-800">{{ intervention.niveau_carburant }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Précision / Jauge</span>
                                    <span class="font-medium text-gray-700">{{ intervention.intervalle_niveau_carburant || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-xs">Circuit</span>
                                    <span class="inline-block px-2 py-0.5 mt-1 text-xs font-semibold rounded-md bg-gray-100 text-gray-800 uppercase">
                                        {{ intervention.circuit }}
                                    </span>
                                </div>
                                <div class="sm:col-span-3">
                                    <span class="text-gray-400 block text-xs">Personne à contacter</span>
                                    <span class="font-semibold text-gray-800">{{ intervention.personne_a_contacter }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4. REMARQUES / OBSERVATIONS -->
                        <div v-if="intervention.remarques_eventuelles" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-2">
                            <h3 class="text-base font-bold text-gray-900 border-b pb-3 flex items-center gap-2">
                                📝 Remarques et Observations
                            </h3>
                            <p class="text-sm text-gray-700 bg-gray-50 p-4 rounded-xl border border-gray-100 whitespace-pre-line">
                                {{ intervention.remarques_eventuelles }}
                            </p>
                        </div>

                    </div>

                    <!-- COLONNE DROITE : ÉQUIPEMENTS & PHOTOS -->
                    <div class="space-y-6">

                        <!-- ÉQUIPEMENTS PRÉSENTS -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                            <h3 class="text-base font-bold text-gray-900 border-b pb-3 flex items-center gap-2">
                                🛠️ Équipements & Accessoires
                            </h3>
                            <div class="space-y-2">
                                <div v-for="eq in equipmentsList" :key="eq.key" class="flex justify-between items-center text-xs py-1 border-b border-gray-50 last:border-0">
                                    <span class="text-gray-700">{{ eq.label }}</span>
                                    <span :class="['font-bold px-2 py-0.5 rounded', intervention[eq.key] ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400']">
                                        {{ intervention[eq.key] ? 'Présent ✓' : 'Absent ✗' }}
                                    </span>
                                </div>

                                <!-- Pare-brise fissuré (alerte spécifique) -->
                                <div class="flex justify-between items-center text-xs py-2 mt-2 border-t pt-2">
                                    <span class="text-red-600 font-semibold">Pare-brise fissuré</span>
                                    <span :class="['font-bold px-2 py-0.5 rounded', intervention.pare_brise_fissure ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700']">
                                        {{ intervention.pare_brise_fissure ? 'OUI (Attention)' : 'Non' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- PHOTOS D'ÉTAT INITIAL -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                            <h3 class="text-base font-bold text-gray-900 border-b pb-3 flex items-center gap-2">
                                📸 Photos d'État Initial
                            </h3>
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div v-for="photoField in ['photo_avant', 'photo_arriere', 'photo_gauche', 'photo_droite']" :key="photoField" class="space-y-1">
                                    <span class="font-semibold text-gray-600 uppercase text-[10px] block">{{ photoField.replace('photo_', '') }}</span>
                                    <div v-if="intervention[photoField]" class="aspect-video bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
                                        <a :href="imageUrl(intervention[photoField])" target="_blank">
                                            <img :src="imageUrl(intervention[photoField])" alt="Photo état" class="w-full h-full object-cover hover:scale-105 transition" />
                                        </a>
                                    </div>
                                    <div v-else class="aspect-video bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 text-[10px] border border-dashed border-gray-200">
                                        Non disponible
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>