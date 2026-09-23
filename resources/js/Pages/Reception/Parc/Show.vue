<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    intervention: Object,
});

// Helper pour générer l'URL correcte de l'image stockée
const getPhotoUrl = (path) => {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
};

// Traduction et style des statuts
const getStatutBadge = (statut) => {
    const badges = {
        reception: { text: 'Sur le Parc (Réception)', class: 'bg-blue-50 text-blue-700 border border-blue-200' },
        atelier: { text: 'En Atelier', class: 'bg-amber-50 text-amber-700 border border-amber-200' },
        en_cours: { text: 'En Réparation', class: 'bg-purple-50 text-purple-700 border border-purple-200' },
        attente_accord: { text: 'Attente Accord Devis', class: 'bg-rose-50 text-rose-700 border border-rose-200' },
    };
    return badges[statut] || { text: statut, class: 'bg-gray-100 text-gray-700 border border-gray-200' };
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
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 py-2">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-2xl font-black tracking-tight text-[#0B0F19]">
                            Fiche de Réception — OT : <span class="text-[#E11D48]">{{ intervention.numero_ot }}</span>
                        </h2>
                        <span :class="['px-3 py-1 text-xs font-bold rounded-lg uppercase tracking-wide', getStatutBadge(intervention.statut).class]">
                            {{ getStatutBadge(intervention.statut).text }}
                        </span>
                    </div>
                    <p class="text-sm text-[#8A8D8F] font-medium mt-1">
                        Enregistré le {{ new Date(intervention.date_reception).toLocaleDateString() }} à {{ new Date(intervention.date_reception).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                    </p>
                </div>
                <Link 
                    :href="route('parc.index')" 
                    class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-[#0B0F19] text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-xs"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Retour à la liste du parc</span>
                </Link>
            </div>
        </template>

        <div class="py-12 bg-white min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- COLONNE GAUCHE & CENTRE : INFOS PRINCIPALES -->
                    <div class="lg:col-span-2 space-y-8">

                        <!-- 1. INFORMATIONS VÉHICULE -->
                        <div class="bg-white p-8 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-6">
                            <h3 class="text-base font-black text-[#0B0F19] border-b border-gray-100 pb-4 flex items-center gap-2.5">
                                <i class="fa-solid fa-car text-[#E11D48]"></i>
                                <span>Véhicule Concerné</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Immatriculation</span>
                                    <span class="font-black text-[#0B0F19] uppercase text-base tracking-wide">{{ intervention.vehicule?.immatriculation }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Marque & Modèle</span>
                                    <span class="font-extrabold text-[#0B0F19]">{{ intervention.vehicule?.marque }} {{ intervention.vehicule?.modele }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Numéro de Châssis (VIN)</span>
                                    <span class="font-mono font-bold text-gray-700">{{ intervention.vehicule?.vin || 'Non renseigné' }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Kilométrage à l'entrée</span>
                                    <span class="font-black text-[#E11D48]">{{ intervention.kilometrage }} km</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Expiration Assurance</span>
                                    <span class="font-semibold text-gray-700">{{ intervention.vehicule?.expiration_assurance || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Expiration SICTA (Visite tech.)</span>
                                    <span class="font-semibold text-gray-700">{{ intervention.vehicule?.expiration_sicta || 'N/A' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. INFORMATIONS CLIENT -->
                        <div class="bg-white p-8 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-6">
                            <h3 class="text-base font-black text-[#0B0F19] border-b border-gray-100 pb-4 flex items-center gap-2.5">
                                <i class="fa-solid fa-user text-[#E11D48]"></i>
                                <span>Client / Propriétaire</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Nom & Prénom</span>
                                    <span class="font-black text-[#0B0F19]">{{ intervention.vehicule?.client?.nom }} {{ intervention.vehicule?.client?.prenom }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Téléphone</span>
                                    <span class="font-extrabold text-[#0B0F19]">{{ intervention.vehicule?.client?.telephone }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">E-mail</span>
                                    <span class="text-gray-700 font-medium">{{ intervention.vehicule?.client?.email || 'Aucun email' }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Adresse</span>
                                    <span class="text-gray-700 font-medium">{{ intervention.vehicule?.client?.adresse || 'Non renseignée' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. CARBURANT & TRAITEMENT -->
                        <div class="bg-white p-8 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-6">
                            <h3 class="text-base font-black text-[#0B0F19] border-b border-gray-100 pb-4 flex items-center gap-2.5">
                                <i class="fa-solid fa-gas-pump text-[#E11D48]"></i>
                                <span>Carburant & Traitement</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm">
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Niveau de Carburant</span>
                                    <span class="font-extrabold text-[#0B0F19]">{{ intervention.niveau_carburant }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Précision / Jauge</span>
                                    <span class="font-semibold text-gray-700">{{ intervention.intervalle_niveau_carburant || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Circuit</span>
                                    <span class="inline-block px-3 py-1 mt-1 text-xs font-bold rounded-lg bg-gray-100 text-[#0B0F19] uppercase tracking-wider">
                                        {{ intervention.circuit }}
                                    </span>
                                </div>
                                <div class="sm:col-span-3">
                                    <span class="text-[#8A8D8F] block text-xs font-bold uppercase tracking-wider mb-1">Personne à contacter</span>
                                    <span class="font-bold text-[#0B0F19]">{{ intervention.personne_a_contacter }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4. REMARQUES / OBSERVATIONS -->
                        <div v-if="intervention.remarques_eventuelles" class="bg-white p-8 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-4">
                            <h3 class="text-base font-black text-[#0B0F19] border-b border-gray-100 pb-4 flex items-center gap-2.5">
                                <i class="fa-solid fa-notes-medical text-[#E11D48]"></i>
                                <span>Remarques et Observations</span>
                            </h3>
                            <p class="text-sm text-gray-700 bg-[#F8FAFC] p-5 rounded-2xl border border-gray-200/60 whitespace-pre-line font-medium leading-relaxed">
                                {{ intervention.remarques_eventuelles }}
                            </p>
                        </div>

                    </div>

                    <!-- COLONNE DROITE : ÉQUIPEMENTS & PHOTOS -->
                    <div class="space-y-8">

                        <!-- ÉQUIPEMENTS PRÉSENTS -->
                        <div class="bg-white p-8 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-6">
                            <h3 class="text-base font-black text-[#0B0F19] border-b border-gray-100 pb-4 flex items-center gap-2.5">
                                <i class="fa-solid fa-toolbox text-[#E11D48]"></i>
                                <span>Équipements & Accessoires</span>
                            </h3>
                            <div class="space-y-3">
                                <div v-for="eq in equipmentsList" :key="eq.key" class="flex justify-between items-center text-xs py-1.5 border-b border-gray-50 last:border-0 font-medium">
                                    <span class="text-gray-700">{{ eq.label }}</span>
                                    <span :class="['font-bold px-2.5 py-1 rounded-lg text-[11px]', intervention[eq.key] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-[#8A8D8F]']">
                                        {{ intervention[eq.key] ? 'Présent ✓' : 'Absent ✗' }}
                                    </span>
                                </div>

                                <!-- Pare-brise fissuré (alerte spécifique) -->
                                <div class="flex justify-between items-center text-xs py-3 mt-3 border-t border-gray-100 font-bold">
                                    <span class="text-[#E11D48]">Pare-brise fissuré</span>
                                    <span :class="['px-2.5 py-1 rounded-lg text-[11px]', intervention.pare_brise_fissure ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200']">
                                        {{ intervention.pare_brise_fissure ? 'OUI (Attention)' : 'Non' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- PHOTOS D'ÉTAT INITIAL -->
                        <div class="bg-white p-8 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-6">
                            <h3 class="text-base font-black text-[#0B0F19] border-b border-gray-100 pb-4 flex items-center gap-2.5">
                                <i class="fa-solid fa-camera text-[#E11D48]"></i>
                                <span>Photos d'État Initial</span>
                            </h3>
                            <div class="grid grid-cols-2 gap-4 text-xs">
                                <div v-for="photoField in ['photo_avant', 'photo_arriere', 'photo_gauche', 'photo_droite']" :key="photoField" class="space-y-1.5">
                                    <span class="font-black text-[#0B0F19] uppercase text-[10px] tracking-wider block">{{ photoField.replace('photo_', '') }}</span>
                                    
                                    <div v-if="intervention[photoField]" class="aspect-video bg-[#F8FAFC] rounded-2xl overflow-hidden border border-gray-200 shadow-xs group relative">
                                        <a :href="getPhotoUrl(intervention[photoField])" target="_blank" class="block w-full h-full">
                                            <img :src="getPhotoUrl(intervention[photoField])" alt="Photo état initial" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white">
                                                <i class="fa-solid fa-expand text-sm"></i>
                                            </div>
                                        </a>
                                    </div>
                                    <div v-else class="aspect-video bg-gray-50 rounded-2xl flex items-center justify-center text-[#8A8D8F] text-[10px] font-medium border border-dashed border-gray-200">
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