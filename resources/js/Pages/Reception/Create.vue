<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    clients: Array,
    mecaniciens: Array,
});

const currentStep = ref(1);
const totalSteps = 4;

// Recherche dynamique du client
const searchQuery = ref('');
const showDropdown = ref(false);
const isNewClientMode = ref(false);

// Mode de gestion véhicule si client existant ('new' ou 'edit_existing')
const clientVehicleMode = ref('new'); 
const selectedExistingVehiculeId = ref('');

const filteredClients = computed(() => {
    if (!searchQuery.value || searchQuery.value.length < 2) return [];
    const query = searchQuery.value.toLowerCase();
    return props.clients.filter(c => 
        c.nom.toLowerCase().includes(query) || 
        (c.prenom && c.prenom.toLowerCase().includes(query)) ||
        (c.telephone && c.telephone.includes(query))
    );
});

const selectClient = (client) => {
    form.client_id = client.id;
    searchQuery.value = `${client.nom} ${client.prenom || ''} (${client.telephone})`;
    showDropdown.value = false;
    isNewClientMode.value = false;
    clientVehicleMode.value = 'new';
    selectedExistingVehiculeId.value = '';
    form.clearErrors();
};

const enableNewClientForm = () => {
    isNewClientMode.value = true;
    form.client_id = '';
    form.nom = searchQuery.value;
    showDropdown.value = false;
    clientVehicleMode.value = 'new';
    form.clearErrors();
};

// Récupérer le client actuellement sélectionné pour lister ses véhicules existants
const selectedClientObj = computed(() => {
    if (!form.client_id) return null;
    return props.clients.find(c => c.id === form.client_id);
});

// Quand on choisit de modifier un véhicule existant
const selectExistingVehicule = (vehicule) => {
    selectedExistingVehiculeId.value = vehicule.id;
    form.vehicule_id = vehicule.id; // Pour indiquer au backend qu'on met à jour un véhicule existant
    form.immatriculation = vehicule.immatriculation || '';
    form.marque = vehicule.marque || '';
    form.modele = vehicule.modele || '';
    form.vin = vehicule.vin || '';
    form.expiration_assurance = vehicule.expiration_assurance || '';
    form.expiration_sicta = vehicule.expiration_sicta || '';
    
    // Passage direct à l'étape 3 (OT & Équipements) car client et véhicule sont déjà connus
    currentStep.value = 3;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Formulaire complet
const form = useForm({
    // Client (si nouveau)
    client_id: '',
    nom: '',
    prenom: '',
    telephone: '',
    email: '',
    adresse: '',
    type_client: 'particulier',
    
    // Véhicule (table vehicules)
    vehicule_id: '', // Utilisé si modification d'un véhicule existant
    immatriculation: '',
    marque: '',
    modele: '',
    vin: '',
    expiration_assurance: '',
    expiration_sicta: '',

    // Intervention - Infos administratives & traçabilité
    numero_ot: '',
    date_reception: new Date().toISOString().split('T')[0],
    kilometrage: '',
    personne_a_contacter: '',
    circuit: 'normal',
    mecanicien_id: '',

    // Intervention - Équipements (Booleans)
    allume_cigare: false,
    rk7: false,
    rcd: false,
    essuie_glace_av: false,
    essuie_glace_ar: false,
    retro_ext_gauche: false,
    retro_ext_droit: false,
    retro_int: false,
    cric: false,
    manivelle: false,
    roue_secours: false,
    trousse: false,
    pare_brise_fissure: false,

    // Intervention - Carburant & remarques
    enjoliveurs: [],
    niveau_carburant: '1/2',
    intervalle_niveau_carburant: '',
    remarques_eventuelles: '',

    // Photos d'état initial
    photo_avant: null,
    photo_arriere: null,
    photo_gauche: null,
    photo_droite: null,
});

const nextStep = () => {
    if (currentStep.value < totalSteps) {
        currentStep.value++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const handleFileUpload = (event, field) => {
    form[field] = event.target.files[0];
};

const submit = () => {
    form.post(route('reception.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nouvelle Réception Véhicule & Fiche d'État" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Fiche de Réception — Étape {{ currentStep }} sur {{ totalSteps }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">Enregistrement complet de l'accueil, du véhicule et des équipements</p>
                </div>
                <Link :href="route('dashboard')" class="text-sm font-semibold text-gray-600 hover:text-gray-900">
                    ← Retour au tableau de bord
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- BARRE DE PROGRESSION -->
                <div class="mb-8 bg-white p-4 rounded-2xl shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between text-xs font-bold text-gray-400 mb-2">
                        <span :class="{'text-[#C8102E]': currentStep >= 1}">1. Client</span>
                        <span :class="{'text-[#C8102E]': currentStep >= 2}">2. Véhicule</span>
                        <span :class="{'text-[#C8102E]': currentStep >= 3}">3. OT & Équipements</span>
                        <span :class="{'text-[#C8102E]': currentStep >= 4}">4. Photos & État</span>
                    </div>
                    <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-[#C8102E] h-full transition-all duration-300" :style="{ width: (currentStep / totalSteps) * 100 + '%' }"></div>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">

                    <!-- ========================================== -->
                    <!-- ÉTAPE 1 : CLIENT -->
                    <!-- ========================================== -->
                    <div v-if="currentStep === 1" class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 space-y-6">
                        <div class="flex items-center gap-3 border-b pb-4">
                            <span class="w-8 h-8 rounded-lg bg-red-50 text-[#C8102E] flex items-center justify-center font-bold text-sm">1</span>
                            <h3 class="text-lg font-bold text-gray-900">Recherche ou Enregistrement Client</h3>
                        </div>

                        <div v-if="!isNewClientMode" class="relative space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Rechercher un client (Nom, Prénom, Tél)</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        v-model="searchQuery" 
                                        @input="showDropdown = true"
                                        placeholder="Tapez le nom du client..." 
                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E] pl-10" 
                                    />
                                    <span class="absolute left-3 top-3 text-gray-400">🔍</span>
                                </div>
                            </div>

                            <div v-if="showDropdown && searchQuery.length >= 2" class="absolute z-10 w-full bg-white border border-gray-200 rounded-xl shadow-lg mt-1 max-h-60 overflow-y-auto">
                                <template v-if="filteredClients.length > 0">
                                    <div 
                                        v-for="client in filteredClients" 
                                        :key="client.id" 
                                        @click="selectClient(client)"
                                        class="p-3 hover:bg-red-50 cursor-pointer border-b border-gray-100 transition flex justify-between items-center"
                                    >
                                        <div>
                                            <p class="font-bold text-gray-900 text-sm">{{ client.nom }} {{ client.prenom }}</p>
                                            <p class="text-xs text-gray-500">Tél: {{ client.telephone }}</p>
                                        </div>
                                        <span class="text-xs font-semibold text-[#C8102E] bg-red-50 px-2 py-1 rounded-md">Sélectionner ✓</span>
                                    </div>
                                </template>

                                <div v-else class="p-4 text-center">
                                    <p class="text-sm text-gray-500 mb-3">Aucun client trouvé pour "<span class="font-semibold">{{ searchQuery }}</span>"</p>
                                    <button type="button" @click="enableNewClientForm" class="px-4 py-2 bg-[#C8102E] text-white text-xs font-bold rounded-xl hover:bg-[#a60d25] shadow-sm transition">
                                        + Enregistrer le client
                                    </button>
                                </div>
                            </div>

                            <div class="flex justify-between items-center pt-2">
                                <span class="text-xs text-gray-500">Le client n'est pas dans la liste ?</span>
                                <button type="button" @click="enableNewClientForm" class="text-xs font-bold text-[#C8102E] hover:underline">
                                    Créer un nouveau client manuellement →
                                </button>
                            </div>
                        </div>

                        <!-- Si client sélectionné : Choix entre ajouter un nouveau véhicule ou modifier un véhicule existant -->
                        <div v-if="form.client_id && !isNewClientMode" class="space-y-4 pt-4 border-t">
                            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider block">Client sélectionné</span>
                                    <p class="text-sm font-semibold text-gray-900">{{ searchQuery }}</p>
                                </div>
                                <button type="button" @click="form.client_id = ''; searchQuery = ''; selectedExistingVehiculeId = ''" class="text-xs text-red-600 font-bold hover:underline">
                                    Changer
                                </button>
                            </div>

                            <!-- Si le client a déjà des véhicules -->
                            <div v-if="selectedClientObj?.vehicules && selectedClientObj.vehicules.length > 0" class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-3">
                                <h4 class="text-sm font-bold text-gray-900">Ce client possède déjà des véhicules enregistrés :</h4>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                                        <input type="radio" value="new" v-model="clientVehicleMode" class="text-[#C8102E]" />
                                        Ajouter une nouvelle voiture
                                    </label>
                                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                                        <input type="radio" value="edit_existing" v-model="clientVehicleMode" class="text-[#C8102E]" />
                                        Modifier un véhicule existant
                                    </label>
                                </div>

                                <!-- Liste des véhicules existants si "Modifier" est choisi -->
                                <div v-if="clientVehicleMode === 'edit_existing'" class="space-y-2 pt-2">
                                    <p class="text-xs text-gray-500">Cliquez sur le véhicule à modifier (vous passerez directement à l'étape OT & Équipements) :</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div 
                                            v-for="vehicule in selectedClientObj.vehicules" 
                                            :key="vehicule.id"
                                            @click="selectExistingVehicule(vehicule)"
                                            class="p-3 bg-white border border-gray-200 rounded-xl hover:border-[#C8102E] cursor-pointer transition flex justify-between items-center"
                                        >
                                            <div>
                                                <p class="text-xs font-extrabold uppercase text-gray-900">{{ vehicule.immatriculation }}</p>
                                                <p class="text-xs text-gray-500">{{ vehicule.marque }} {{ vehicule.modele }}</p>
                                            </div>
                                            <span class="text-xs font-bold text-[#C8102E]">Sélectionner →</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formulaire Nouveau Client -->
                        <div v-if="isNewClientMode" class="space-y-4 border-t pt-4">
                            <div class="flex justify-between items-center">
                                <h4 class="font-bold text-gray-900 text-sm">Nouveau Client à la volée</h4>
                                <button type="button" @click="isNewClientMode = false" class="text-xs text-gray-500 hover:underline">← Retour à la recherche</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nom *</label>
                                    <input type="text" v-model="form.nom" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="Nom" />
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Prénom *</label>
                                    <input type="text" v-model="form.prenom" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="Prénom" />
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Téléphone *</label>
                                    <input type="text" v-model="form.telephone" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="0700000000" />
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">E-mail *</label>
                                    <input type="email" v-model="form.email" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="email@example.com" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Adresse *</label>
                                    <textarea v-model="form.adresse" rows="2" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- ÉTAPE 2 : VÉHICULE -->
                    <!-- ========================================== -->
                    <div v-if="currentStep === 2" class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 space-y-6">
                        <div class="flex items-center gap-3 border-b pb-4">
                            <span class="w-8 h-8 rounded-lg bg-red-50 text-[#C8102E] flex items-center justify-center font-bold text-sm">2</span>
                            <h3 class="text-lg font-bold text-gray-900">Informations du Véhicule</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Immatriculation *</label>
                                <input type="text" v-model="form.immatriculation" required class="w-full rounded-xl border-gray-300 shadow-sm uppercase focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="AB-123-CD" />
                                <div v-if="form.errors.immatriculation" class="text-red-600 text-xs mt-1">{{ form.errors.immatriculation }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Marque *</label>
                                <input type="text" v-model="form.marque" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="Toyota" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Modèle *</label>
                                <input type="text" v-model="form.modele" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="Corolla" />
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Numéro de Châssis (VIN) *</label>
                                <input type="text" v-model="form.vin" required class="w-full rounded-xl border-gray-300 shadow-sm uppercase focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="17 caractères" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Expiration Assurance *</label>
                                <input type="date" v-model="form.expiration_assurance" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Expiration SICTA (Visite tech.) *</label>
                                <input type="date" v-model="form.expiration_sicta" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" />
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- ÉTAPE 3 : OT, CIRCUIT & ÉQUIPEMENTS -->
                    <!-- ========================================== -->
                    <div v-if="currentStep === 3" class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 space-y-6">
                        <div class="flex items-center gap-3 border-b pb-4">
                            <span class="w-8 h-8 rounded-lg bg-red-50 text-[#C8102E] flex items-center justify-center font-bold text-sm">3</span>
                            <h3 class="text-lg font-bold text-gray-900">Ordre de Réparation, Carburant & Équipements</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Numéro OT *</label>
                                <input type="text" v-model="form.numero_ot" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="ex: OT-2026-001" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Date de réception *</label>
                                <input type="date" v-model="form.date_reception" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Kilométrage actuel (km) *</label>
                                <input type="number" v-model="form.kilometrage" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="45000" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Personne à contacter *</label>
                                <input type="text" v-model="form.personne_a_contacter" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="Nom ou téléphone" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Niveau de Carburant *</label>
                                <select v-model="form.niveau_carburant" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]">
                                    <option value="Vide">Vide (Réserve)</option>
                                    <option value="1/4">1/4</option>
                                    <option value="1/2">1/2</option>
                                    <option value="3/4">3/4</option>
                                    <option value="Plein">Plein</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Précision niveau / Jauge *</label>
                                <input type="text" v-model="form.intervalle_niveau_carburant" required class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="ex: Exactement la moitié" />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Circuit de traitement *</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-1">
                                    <label :class="['border p-3 rounded-xl cursor-pointer flex items-center gap-3', form.circuit === 'normal' ? 'border-[#C8102E] bg-red-50/30' : 'border-gray-200']">
                                        <input type="radio" value="normal" v-model="form.circuit" class="text-[#C8102E]" />
                                        <div>
                                            <span class="font-bold text-sm text-gray-900 block">Circuit Normal</span>
                                            <span class="text-xs text-gray-500">Avec diagnostic & essai technique</span>
                                        </div>
                                    </label>
                                    <label :class="['border p-3 rounded-xl cursor-pointer flex items-center gap-3', form.circuit === 'devis_direct' ? 'border-[#C8102E] bg-red-50/30' : 'border-gray-200']">
                                        <input type="radio" value="devis_direct" v-model="form.circuit" class="text-[#C8102E]" />
                                        <div>
                                            <span class="font-bold text-sm text-gray-900 block">Devis Direct</span>
                                            <span class="text-xs text-gray-500">Panne visible / Chiffrage immédiat</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Équipements et accessoires -->
                        <div class="border-t pt-4 space-y-3">
                            <h4 class="font-bold text-sm text-gray-900">Équipements et accessoires à bord</h4>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-gray-700">
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.allume_cigare" class="rounded text-[#C8102E]" /> Allume-cigare</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.rk7" class="rounded text-[#C8102E]" /> RK7 / Radio</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.rcd" class="rounded text-[#C8102E]" /> RCD / Lecteur CD</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.essuie_glace_av" class="rounded text-[#C8102E]" /> Essuie-glace AV</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.essuie_glace_ar" class="rounded text-[#C8102E]" /> Essuie-glace AR</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.retro_ext_gauche" class="rounded text-[#C8102E]" /> Rétro ext. gauche</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.retro_ext_droit" class="rounded text-[#C8102E]" /> Rétro ext. droit</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.retro_int" class="rounded text-[#C8102E]" /> Rétro intérieur</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.cric" class="rounded text-[#C8102E]" /> Cric</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.manivelle" class="rounded text-[#C8102E]" /> Manivelle</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.roue_secours" class="rounded text-[#C8102E]" /> Roue de secours</label>
                                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.trousse" class="rounded text-[#C8102E]" /> Trousse à pharmacie</label>
                                <label class="flex items-center gap-2 sm:col-span-4 text-red-600 font-semibold"><input type="checkbox" v-model="form.pare_brise_fissure" class="rounded text-red-600" /> Pare-brise fissuré</label>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- ÉTAPE 4 : PHOTOS & REMARQUES -->
                    <!-- ========================================== -->
                    <div v-if="currentStep === 4" class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-200 space-y-6">
                        <div class="flex items-center gap-3 border-b pb-4">
                            <span class="w-8 h-8 rounded-lg bg-red-50 text-[#C8102E] flex items-center justify-center font-bold text-sm">4</span>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Photos réglementaires & Remarques</h3>
                                <p class="text-xs text-gray-500">Joignez les photos sous tous les angles de l'état initial.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="border-2 border-dashed border-gray-300 rounded-2xl p-4 text-center hover:border-[#C8102E] transition">
                                <label class="cursor-pointer block">
                                    <span class="text-sm font-bold text-gray-800 block mb-1">📸 Face Avant *</span>
                                    <input type="file" @change="e => handleFileUpload(e, 'photo_avant')" required accept="image/*" class="text-xs text-gray-500 w-full file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-[#C8102E]" />
                                </label>
                            </div>

                            <div class="border-2 border-dashed border-gray-300 rounded-2xl p-4 text-center hover:border-[#C8102E] transition">
                                <label class="cursor-pointer block">
                                    <span class="text-sm font-bold text-gray-800 block mb-1">📸 Face Arrière *</span>
                                    <input type="file" @change="e => handleFileUpload(e, 'photo_arriere')" required accept="image/*" class="text-xs text-gray-500 w-full file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-[#C8102E]" />
                                </label>
                            </div>

                            <div class="border-2 border-dashed border-gray-300 rounded-2xl p-4 text-center hover:border-[#C8102E] transition">
                                <label class="cursor-pointer block">
                                    <span class="text-sm font-bold text-gray-800 block mb-1">📸 Côté Gauche *</span>
                                    <input type="file" @change="e => handleFileUpload(e, 'photo_gauche')" required accept="image/*" class="text-xs text-gray-500 w-full file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-[#C8102E]" />
                                </label>
                            </div>

                            <div class="border-2 border-dashed border-gray-300 rounded-2xl p-4 text-center hover:border-[#C8102E] transition">
                                <label class="cursor-pointer block">
                                    <span class="text-sm font-bold text-gray-800 block mb-1">📸 Côté Droit *</span>
                                    <input type="file" @change="e => handleFileUpload(e, 'photo_droite')" required accept="image/*" class="text-xs text-gray-500 w-full file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-[#C8102E]" />
                                </label>
                            </div>
                        </div>

                        <div class="pt-4 border-t">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Remarques éventuelles / Observations</label>
                            <textarea v-model="form.remarques_eventuelles" rows="3" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-[#C8102E] focus:ring-[#C8102E]" placeholder="État de la carrosserie, rayures constatées, objets de valeur à bord..."></textarea>
                        </div>
                    </div>

                    <!-- BOUTONS DE NAVIGATION -->
                    <div class="flex justify-between pt-4">
                        <button 
                            v-if="currentStep > 1" 
                            type="button" 
                            @click="prevStep" 
                            class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold rounded-xl transition"
                        >
                            ← Précédent
                        </button>
                        <div v-else></div>

                        <button 
                            v-if="currentStep < totalSteps" 
                            type="button" 
                            @click="nextStep" 
                            class="px-6 py-2.5 bg-[#C8102E] hover:bg-[#a60d25] text-white text-sm font-bold rounded-xl shadow-sm transition ml-auto"
                        >
                            Suivant →
                        </button>

                        <button 
                            v-if="currentStep === totalSteps" 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition ml-auto disabled:opacity-50"
                        >
                            Enregistrer la réception ✓
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>