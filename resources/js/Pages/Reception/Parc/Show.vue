<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faCar,
    faUser,
    faGasPump,
    faNoteSticky,
    faScrewdriverWrench,
    faCamera,
    faCheck,
    faXmark,
    faPen,
} from '@fortawesome/free-solid-svg-icons';

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

// ====== MODIFICATION DES INFOS VÉHICULE / PROPRIÉTAIRE ======
// Seuls la réception et l'admin peuvent modifier (le serveur revérifie aussi)
const canEdit = computed(() => ['admin', 'receptionniste'].includes(page.props.auth.user?.role));

const inputClass = 'w-full rounded-xl border-gray-200 bg-gray-50 text-sm py-2 px-3 focus:border-indigo-500 focus:ring-indigo-500';

// Les champs date doivent être au format AAAA-MM-JJ pour <input type="date">
const dateOnly = (v) => (v ? String(v).substring(0, 10) : '');

const vehiculeFields = [
    { key: 'immatriculation', label: 'Immatriculation *', type: 'text', required: true },
    { key: 'marque', label: 'Marque', type: 'text' },
    { key: 'modele', label: 'Modèle', type: 'text' },
    { key: 'vin', label: 'Numéro de châssis (VIN)', type: 'text' },
    { key: 'kilometrage', label: "Kilométrage à l'entrée (km) *", type: 'number', required: true },
    { key: 'expiration_assurance', label: 'Expiration assurance', type: 'date' },
    { key: 'expiration_sicta', label: 'Expiration SICTA', type: 'date' },
];

const clientFields = [
    { key: 'nom', label: 'Nom *', type: 'text', required: true },
    { key: 'prenom', label: 'Prénom', type: 'text' },
    { key: 'telephone', label: 'Téléphone *', type: 'text', required: true },
    { key: 'email', label: 'E-mail', type: 'email' },
    { key: 'adresse', label: 'Adresse', type: 'text' },
];

// --- Véhicule ---
const editVehicule = ref(false);
const vehiculeForm = useForm({
    immatriculation: '', marque: '', modele: '', vin: '',
    kilometrage: '', expiration_assurance: '', expiration_sicta: '',
});

const startEditVehicule = () => {
    const v = props.intervention.vehicule || {};
    vehiculeForm.immatriculation = v.immatriculation || '';
    vehiculeForm.marque = v.marque || '';
    vehiculeForm.modele = v.modele || '';
    vehiculeForm.vin = v.vin || '';
    vehiculeForm.kilometrage = props.intervention.kilometrage ?? '';
    vehiculeForm.expiration_assurance = dateOnly(v.expiration_assurance);
    vehiculeForm.expiration_sicta = dateOnly(v.expiration_sicta);
    vehiculeForm.clearErrors();
    editVehicule.value = true;
};

const saveVehicule = () => {
    vehiculeForm.patch(route('parc.vehicule.update', props.intervention.id), {
        preserveScroll: true,
        onSuccess: () => { editVehicule.value = false; },
    });
};

// --- Client / propriétaire ---
const editClient = ref(false);
const clientForm = useForm({ nom: '', prenom: '', telephone: '', email: '', adresse: '' });

const startEditClient = () => {
    const c = props.intervention.vehicule?.client || {};
    clientForm.nom = c.nom || '';
    clientForm.prenom = c.prenom || '';
    clientForm.telephone = c.telephone || '';
    clientForm.email = c.email || '';
    clientForm.adresse = c.adresse || '';
    clientForm.clearErrors();
    editClient.value = true;
};

const saveClient = () => {
    clientForm.patch(route('parc.client.update', props.intervention.id), {
        preserveScroll: true,
        onSuccess: () => { editClient.value = false; },
    });
};

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

// Photos supplémentaires (jusqu'à 6, en plus des 4 photos obligatoires)
const photosSupplementaires = computed(() => {
    const brut = props.intervention?.photos_supplementaires;
    if (!brut) return [];
    if (Array.isArray(brut)) return brut.filter(Boolean);
    try {
        const decode = JSON.parse(brut);
        return Array.isArray(decode) ? decode.filter(Boolean) : [];
    } catch (e) {
        return [];
    }
});

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
                                <font-awesome-icon :icon="faCar" class="text-indigo-500 text-sm" />
                                Véhicule Concerné
                                <button
                                    v-if="canEdit && !editVehicule"
                                    type="button"
                                    @click="startEditVehicule"
                                    class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition"
                                >
                                    <font-awesome-icon :icon="faPen" class="text-[10px]" />
                                    Modifier
                                </button>
                            </h3>
                            <div v-if="!editVehicule" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
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
                            <!-- Formulaire de modification -->
                            <form v-else @submit.prevent="saveVehicule" class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                    <div v-for="f in vehiculeFields" :key="f.key">
                                        <label class="text-gray-500 block text-xs mb-1">{{ f.label }}</label>
                                        <input
                                            v-model="vehiculeForm[f.key]"
                                            :type="f.type"
                                            :required="f.required"
                                            :class="inputClass"
                                        />
                                        <div v-if="vehiculeForm.errors[f.key]" class="text-red-600 text-xs font-semibold mt-1">{{ vehiculeForm.errors[f.key] }}</div>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="editVehicule = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition">
                                        Annuler
                                    </button>
                                    <button type="submit" :disabled="vehiculeForm.processing" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">
                                        Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 2. INFORMATIONS CLIENT -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                            <h3 class="text-base font-bold text-gray-900 border-b pb-3 flex items-center gap-2">
                                <font-awesome-icon :icon="faUser" class="text-indigo-500 text-sm" />
                                Client / Propriétaire
                                <button
                                    v-if="canEdit && !editClient"
                                    type="button"
                                    @click="startEditClient"
                                    class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition"
                                >
                                    <font-awesome-icon :icon="faPen" class="text-[10px]" />
                                    Modifier
                                </button>
                            </h3>
                            <div v-if="!editClient" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
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
                            <!-- Formulaire de modification -->
                            <form v-else @submit.prevent="saveClient" class="space-y-4">
                                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                    Ces informations sont partagées : elles seront modifiées pour tous les véhicules et dossiers de ce client.
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                    <div v-for="f in clientFields" :key="f.key">
                                        <label class="text-gray-500 block text-xs mb-1">{{ f.label }}</label>
                                        <input
                                            v-model="clientForm[f.key]"
                                            :type="f.type"
                                            :required="f.required"
                                            :class="inputClass"
                                        />
                                        <div v-if="clientForm.errors[f.key]" class="text-red-600 text-xs font-semibold mt-1">{{ clientForm.errors[f.key] }}</div>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="editClient = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition">
                                        Annuler
                                    </button>
                                    <button type="submit" :disabled="clientForm.processing" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">
                                        Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 3. CARBURANT & TRAITEMENT -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
                            <h3 class="text-base font-bold text-gray-900 border-b pb-3 flex items-center gap-2">
                                <font-awesome-icon :icon="faGasPump" class="text-indigo-500 text-sm" />
                                Carburant & Traitement
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
                                <font-awesome-icon :icon="faNoteSticky" class="text-indigo-500 text-sm" />
                                Remarques et Observations
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
                                <font-awesome-icon :icon="faScrewdriverWrench" class="text-indigo-500 text-sm" />
                                Équipements & Accessoires
                            </h3>
                            <div class="space-y-2">
                                <div v-for="eq in equipmentsList" :key="eq.key" class="flex justify-between items-center text-xs py-1 border-b border-gray-50 last:border-0">
                                    <span class="text-gray-700">{{ eq.label }}</span>
                                    <span :class="['font-bold px-2 py-0.5 rounded inline-flex items-center gap-1', intervention[eq.key] ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400']">
                                        <font-awesome-icon :icon="intervention[eq.key] ? faCheck : faXmark" class="text-[10px]" />
                                        {{ intervention[eq.key] ? 'Présent' : 'Absent' }}
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
                                <font-awesome-icon :icon="faCamera" class="text-indigo-500 text-sm" />
                                Photos d'État Initial
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
                            <!-- Photos supplémentaires (facultatives) -->
                            <div v-if="photosSupplementaires.length" class="pt-3 border-t border-gray-100 space-y-2">
                                <span class="font-semibold text-gray-600 uppercase text-[10px] block">
                                    Photos supplémentaires ({{ photosSupplementaires.length }})
                                </span>
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div
                                        v-for="photo in photosSupplementaires"
                                        :key="photo"
                                        class="aspect-video bg-gray-100 rounded-lg overflow-hidden border border-gray-200"
                                    >
                                        <a :href="imageUrl(photo)" target="_blank">
                                            <img :src="imageUrl(photo)" alt="Photo supplémentaire" class="w-full h-full object-cover hover:scale-105 transition" />
                                        </a>
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