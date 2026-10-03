<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted } from 'vue';

const props = defineProps({
    clients: Array,
    mecaniciens: Array,
    defaultNumerosOt: Object,
    siege: String,
});

const currentStep = ref(1);
const totalSteps = 4;

// Recherche dynamique client
const searchQuery = ref('');
const showDropdown = ref(false);
const isNewClientMode = ref(false);

const clientVehicleMode = ref('new'); 
const selectedExistingVehiculeId = ref('');

// Gestion Galerie & Aperçus
const fileInputs = ref({});
const photoPreviews = ref({
    photo_avant: null,
    photo_arriere: null,
    photo_gauche: null,
    photo_droite: null,
});

// Modale & Flux Caméra en Direct
const showCameraModal = ref(false);
const currentCameraField = ref(null);
const videoRef = ref(null);
const mediaStream = ref(null);

// Clients du siège concerné uniquement (siège de l'utilisateur connecté,
// ou siège choisi dans le formulaire si l'utilisateur n'est rattaché à aucun siège)
const clientsDuSiege = computed(() => {
    const siegeActif = props.siege || form.siege;
    if (!siegeActif) return [];
    return props.clients.filter(c => c.siege === siegeActif);
});

const filteredClients = computed(() => {
    if (!searchQuery.value || searchQuery.value.length < 2) return [];
    const query = searchQuery.value.toLowerCase();
    return clientsDuSiege.value.filter(c => 
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

const selectedClientObj = computed(() => {
    if (!form.client_id) return null;
    return props.clients.find(c => c.id === form.client_id);
});

const selectExistingVehicule = (vehicule) => {
    selectedExistingVehiculeId.value = vehicule.id;
    form.vehicule_id = vehicule.id;
    form.immatriculation = vehicule.immatriculation || '';
    form.marque = vehicule.marque || '';
    form.modele = vehicule.modele || '';
    form.vin = vehicule.vin || '';
    form.expiration_assurance = vehicule.expiration_assurance || '';
    form.expiration_sicta = vehicule.expiration_sicta || '';
    
    currentStep.value = 3;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const form = useForm({
    client_id: '',
    nom: '',
    prenom: '',
    telephone: '',
    email: '',
    adresse: '',
    type_client: 'particulier',
    
    vehicule_id: '',
    immatriculation: '',
    marque: '',
    modele: '',
    vin: '',
    expiration_assurance: '',
    expiration_sicta: '',

    siege: props.siege || '',
    numero_ot: props.siege ? (props.defaultNumerosOt?.[props.siege] ?? '') : '',
    date_reception: new Date().toISOString().split('T')[0],
    kilometrage: '',
    personne_a_contacter: '',
    circuit: 'normal',
    mecanicien_id: '',

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

    enjoliveurs: [],
    niveau_carburant: '1/2',
    intervalle_niveau_carburant: '',
    remarques_eventuelles: '',

    photo_avant: null,
    photo_arriere: null,
    photo_gauche: null,
    photo_droite: null,
});

watch(() => form.siege, (code) => {
    form.numero_ot = props.defaultNumerosOt?.[code] ?? '';

    // Si le siège change (cas sans siège imposé), on annule le client déjà choisi
    // s'il n'appartient pas au nouveau siège
    if (form.client_id && !props.siege) {
        const client = props.clients.find(c => c.id === form.client_id);
        if (!client || client.siege !== code) {
            form.client_id = '';
            searchQuery.value = '';
            selectedExistingVehiculeId.value = '';
            form.vehicule_id = '';
        }
    }
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

/* ------------------------------------------------------------------ */
/* Gestion de la Caméra en Direct (Live WebCam / Mobile)               */
/* ------------------------------------------------------------------ */
const openLiveCamera = async (field) => {
    currentCameraField.value = field;
    showCameraModal.value = true;

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } }, // Priorité caméra arrière
            audio: false
        });
        mediaStream.value = stream;
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
        }
    } catch (err) {
        alert("Impossible d'accéder à la caméra. Vérifiez les autorisations de votre navigateur.");
        closeCameraModal();
    }
};

const capturePhotoFromLive = () => {
    if (!videoRef.value) return;

    const video = videoRef.value;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob((blob) => {
        if (blob) {
            const field = currentCameraField.value;
            const file = new File([blob], `${field}_${Date.now()}.jpg`, { type: 'image/jpeg' });
            
            form[field] = file;
            photoPreviews.value[field] = URL.createObjectURL(file);
            closeCameraModal();
        }
    }, 'image/jpeg', 0.85);
};

const closeCameraModal = () => {
    if (mediaStream.value) {
        mediaStream.value.getTracks().forEach(track => track.stop());
        mediaStream.value = null;
    }
    showCameraModal.value = false;
    currentCameraField.value = null;
};

// Nettoyage automatique au démontage
onUnmounted(() => {
    closeCameraModal();
});

/* ------------------------------------------------------------------ */
/* Import depuis Galerie                                              */
/* ------------------------------------------------------------------ */
const triggerFileInput = (field) => {
    if (fileInputs.value[field]) {
        fileInputs.value[field].click();
    }
};

const handleFileUpload = (event, field) => {
    const file = event.target.files[0];
    if (file) {
        form[field] = file;
        photoPreviews.value[field] = URL.createObjectURL(file);
    }
};

const removePhoto = (field) => {
    form[field] = null;
    photoPreviews.value[field] = null;
};

const submit = () => {
    form.post(route('reception.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nouvelle Réception Véhicule — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 py-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-[#0B0F19] flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-plus text-[#E11D48] text-xl"></i>
                        <span>Fiche de Réception — Étape {{ currentStep }} sur {{ totalSteps }}</span>
                    </h2>
                    <p class="text-sm text-[#8A8D8F] font-medium mt-0.5">Enregistrement complet de l'accueil, du véhicule et des équipements</p>
                </div>
                <Link :href="route('dashboard')" class="text-xs font-bold text-[#0B0F19] hover:text-[#E11D48] transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Retour au tableau de bord</span>
                </Link>
            </div>
        </template>

        <div class="py-12 bg-white min-h-screen">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- BARRE DE PROGRESSION -->
                <div class="bg-white p-6 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-4">
                    <div class="flex items-center justify-between text-xs font-black uppercase tracking-wider text-[#8A8D8F]">
                        <span :class="{'text-[#E11D48] font-black': currentStep >= 1}">1. Client</span>
                        <span :class="{'text-[#E11D48] font-black': currentStep >= 2}">2. Véhicule</span>
                        <span :class="{'text-[#E11D48] font-black': currentStep >= 3}">3. OT & Équipements</span>
                        <span :class="{'text-[#E11D48] font-black': currentStep >= 4}">4. Photos & État</span>
                    </div>
                    <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                        <div class="bg-[#E11D48] h-full transition-all duration-300" :style="{ width: (currentStep / totalSteps) * 100 + '%' }"></div>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">

                    <!-- ========================================== -->
                    <!-- ÉTAPE 1 : CLIENT -->
                    <!-- ========================================== -->
                    <div v-if="currentStep === 1" class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-8">
                        <div class="flex items-center gap-4 border-b border-gray-100 pb-6">
                            <div class="w-12 h-12 rounded-2xl bg-[#E11D48]/10 text-[#E11D48] flex items-center justify-center font-black text-base border border-[#E11D48]/30">1</div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Recherche ou Enregistrement Client</h3>
                                <p class="text-xs text-[#8A8D8F] font-medium mt-0.5">Associez un propriétaire au dossier d'intervention.</p>
                            </div>
                        </div>

                        <div v-if="!isNewClientMode" class="relative space-y-5">
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Rechercher un client (Nom, Prénom, Tél)</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        v-model="searchQuery" 
                                        @input="showDropdown = true"
                                        placeholder="Tapez le nom du client..." 
                                        class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm py-3.5 pl-11 pr-4 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" 
                                    />
                                    <span class="absolute left-4 top-4 text-[#8A8D8F]">
                                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                                    </span>
                                </div>
                            </div>

                            <div v-if="showDropdown && searchQuery.length >= 2" class="absolute z-10 w-full bg-white border border-gray-200 rounded-2xl shadow-xl mt-1 max-h-60 overflow-y-auto">
                                <template v-if="filteredClients.length > 0">
                                    <div 
                                        v-for="client in filteredClients" 
                                        :key="client.id" 
                                        @click="selectClient(client)"
                                        class="p-4 hover:bg-red-50/50 cursor-pointer border-b border-gray-100 transition flex justify-between items-center"
                                    >
                                        <div>
                                            <p class="font-black text-[#0B0F19] text-sm">{{ client.nom }} {{ client.prenom }}</p>
                                            <p class="text-xs text-[#8A8D8F] font-medium">Tél: {{ client.telephone }}</p>
                                        </div>
                                        <span class="text-xs font-bold text-[#E11D48] bg-[#E11D48]/10 px-3 py-1 rounded-xl">Sélectionner ✓</span>
                                    </div>
                                </template>

                                <div v-else class="p-6 text-center space-y-3">
                                    <p class="text-sm text-[#8A8D8F]">Aucun client trouvé pour "<span class="font-bold text-[#0B0F19]">{{ searchQuery }}</span>"</p>
                                    <button type="button" @click="enableNewClientForm" class="px-5 py-2.5 bg-[#E11D48] text-white text-xs font-bold rounded-xl hover:bg-[#BE123C] shadow-sm transition">
                                        + Enregistrer le client
                                    </button>
                                </div>
                            </div>

                            <div class="flex justify-between items-center pt-2">
                                <span class="text-xs text-[#8A8D8F] font-medium">Le client n'est pas dans la liste ?</span>
                                <button type="button" @click="enableNewClientForm" class="text-xs font-bold text-[#E11D48] hover:underline">
                                    Créer un nouveau client manuellement →
                                </button>
                            </div>
                        </div>

                        <!-- Si client sélectionné -->
                        <div v-if="form.client_id && !isNewClientMode" class="space-y-6 pt-4 border-t border-gray-100">
                            <div class="p-5 bg-emerald-50/60 border border-emerald-200 rounded-2xl flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-black text-emerald-700 uppercase tracking-wider block">Client sélectionné</span>
                                    <p class="text-sm font-extrabold text-[#0B0F19] mt-0.5">{{ searchQuery }}</p>
                                </div>
                                <button type="button" @click="form.client_id = ''; searchQuery = ''; selectedExistingVehiculeId = ''" class="text-xs text-[#E11D48] font-bold hover:underline">
                                    Changer
                                </button>
                            </div>

                            <div v-if="selectedClientObj?.vehicules && selectedClientObj.vehicules.length > 0" class="bg-[#F8FAFC] p-6 rounded-2xl border border-gray-200/80 space-y-4">
                                <h4 class="text-sm font-black text-[#0B0F19]">Ce client possède déjà des véhicules enregistrés :</h4>
                                <div class="flex gap-6">
                                    <label class="flex items-center gap-2.5 text-xs font-bold text-[#0B0F19] cursor-pointer">
                                        <input type="radio" value="new" v-model="clientVehicleMode" class="text-[#E11D48] focus:ring-[#E11D48]" />
                                        Ajouter une nouvelle voiture
                                    </label>
                                    <label class="flex items-center gap-2.5 text-xs font-bold text-[#0B0F19] cursor-pointer">
                                        <input type="radio" value="edit_existing" v-model="clientVehicleMode" class="text-[#E11D48] focus:ring-[#E11D48]" />
                                        Modifier un véhicule existant
                                    </label>
                                </div>

                                <div v-if="clientVehicleMode === 'edit_existing'" class="space-y-3 pt-2">
                                    <p class="text-xs text-[#8A8D8F] font-medium">Cliquez sur le véhicule à modifier :</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div 
                                            v-for="vehicule in selectedClientObj.vehicules" 
                                            :key="vehicule.id"
                                            @click="selectExistingVehicule(vehicule)"
                                            class="p-4 bg-white border border-gray-200 rounded-xl hover:border-[#E11D48] cursor-pointer transition flex justify-between items-center shadow-xs"
                                        >
                                            <div>
                                                <p class="text-xs font-black uppercase text-[#0B0F19]">{{ vehicule.immatriculation }}</p>
                                                <p class="text-xs text-[#8A8D8F] font-medium">{{ vehicule.marque }} {{ vehicule.modele }}</p>
                                            </div>
                                            <span class="text-xs font-bold text-[#E11D48]">Sélectionner →</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formulaire Nouveau Client -->
                        <div v-if="isNewClientMode" class="space-y-5 border-t border-gray-100 pt-6">
                            <div class="flex justify-between items-center">
                                <h4 class="font-black text-[#0B0F19] text-sm">Nouveau Client à la volée</h4>
                                <button type="button" @click="isNewClientMode = false" class="text-xs text-[#8A8D8F] hover:text-[#0B0F19] font-bold transition">← Retour à la recherche</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Nom *</label>
                                    <input type="text" v-model="form.nom" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Nom" />
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Prénom *</label>
                                    <input type="text" v-model="form.prenom" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Prénom" />
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Téléphone *</label>
                                    <input type="text" v-model="form.telephone" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="0700000000" />
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">E-mail *</label>
                                    <input type="email" v-model="form.email" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="email@example.com" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Adresse *</label>
                                    <textarea v-model="form.adresse" rows="2" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- ÉTAPE 2 : VÉHICULE -->
                    <!-- ========================================== -->
                    <div v-if="currentStep === 2" class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-8">
                        <div class="flex items-center gap-4 border-b border-gray-100 pb-6">
                            <div class="w-12 h-12 rounded-2xl bg-[#E11D48]/10 text-[#E11D48] flex items-center justify-center font-black text-base border border-[#E11D48]/30">2</div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Informations du Véhicule</h3>
                                <p class="text-xs text-[#8A8D8F] font-medium mt-0.5">Saisissez les caractéristiques techniques de la voiture.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Immatriculation *</label>
                                <input type="text" v-model="form.immatriculation" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 uppercase shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="AB-123-CD" />
                                <div v-if="form.errors.immatriculation" class="text-[#E11D48] text-xs font-bold mt-1">{{ form.errors.immatriculation }}</div>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Marque *</label>
                                <input type="text" v-model="form.marque" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Toyota" />
                            </div>
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Modèle *</label>
                                <input type="text" v-model="form.modele" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Corolla" />
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Numéro de Châssis (VIN) *</label>
                                <input type="text" v-model="form.vin" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 uppercase shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="17 caractères" />
                            </div>
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Expiration Assurance *</label>
                                <input type="date" v-model="form.expiration_assurance" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" />
                            </div>
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Expiration SICTA (Visite tech.) *</label>
                                <input type="date" v-model="form.expiration_sicta" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" />
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- ÉTAPE 3 : OT, CIRCUIT & ÉQUIPEMENTS -->
                    <!-- ========================================== -->
                    <div v-if="currentStep === 3" class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-8">
                        <div class="flex items-center gap-4 border-b border-gray-100 pb-6">
                            <div class="w-12 h-12 rounded-2xl bg-[#E11D48]/10 text-[#E11D48] flex items-center justify-center font-black text-base border border-[#E11D48]/30">3</div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Ordre de Réparation, Carburant & Équipements</h3>
                                <p class="text-xs text-[#8A8D8F] font-medium mt-0.5">Paramétrage initial de l'intervention atelier.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Siège *</label>
                                <select v-if="!siege" v-model="form.siege" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]">
                                    <option value="" disabled>Choisir le siège</option>
                                    <option v-for="(nom, code) in $page.props.sieges" :key="code" :value="code">{{ code }} — {{ nom }}</option>
                                </select>
                                <div v-else class="w-full rounded-2xl border border-gray-200 bg-gray-100 p-3.5 text-sm font-bold text-gray-600">
                                    {{ siege }} — {{ $page.props.sieges[siege] }}
                                </div>
                                <p v-if="form.errors.siege" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.siege }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Numéro OT (Généré auto) *</label>
                                <input type="text" v-model="form.numero_ot" required readonly class="w-full rounded-2xl border-gray-200 bg-gray-100 text-sm p-3.5 shadow-xs text-gray-600 font-bold cursor-not-allowed" />
                                <span class="text-[10px] text-[#8A8D8F] mt-1 block">Format : {{ form.siege || 'SGK' }}-JJMMAAAA/001</span>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Date de réception *</label>
                                <input type="date" v-model="form.date_reception" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" />
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Kilométrage actuel (km) *</label>
                                <input type="number" v-model="form.kilometrage" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="45000" />
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Personne à contacter *</label>
                                <input type="text" v-model="form.personne_a_contacter" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Nom ou téléphone" />
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Niveau de Carburant *</label>
                                <select v-model="form.niveau_carburant" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]">
                                    <option value="Vide">Vide (Réserve)</option>
                                    <option value="1/4">1/4</option>
                                    <option value="1/2">1/2</option>
                                    <option value="3/4">3/4</option>
                                    <option value="Plein">Plein</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Précision niveau / Jauge *</label>
                                <input type="text" v-model="form.intervalle_niveau_carburant" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="ex: Exactement la moitié" />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Circuit de traitement *</label>
                                <div class="grid grid-cols-1 gap-4 mt-1">
                                    <label class="border p-4 rounded-2xl cursor-pointer flex items-center gap-3.5 transition shadow-xs border-[#E11D48] bg-red-50/40">
                                        <input type="radio" value="normal" v-model="form.circuit" class="text-[#E11D48] focus:ring-[#E11D48]" checked disabled />
                                        <div>
                                            <span class="font-black text-sm text-[#0B0F19] block">Circuit Normal</span>
                                            <span class="text-xs text-[#8A8D8F] font-medium">Avec diagnostic & essai technique</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Équipements -->
                        <div class="border-t border-gray-100 pt-6 space-y-4">
                            <h4 class="font-black text-sm text-[#0B0F19]">Équipements et accessoires à bord</h4>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 text-xs font-medium text-gray-700">
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.allume_cigare" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Allume-cigare</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.rk7" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> RK7 / Radio</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.rcd" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> RCD / Lecteur CD</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.essuie_glace_av" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Essuie-glace AV</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.essuie_glace_ar" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Essuie-glace AR</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.retro_ext_gauche" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Rétro ext. gauche</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.retro_ext_droit" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Rétro ext. droit</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.retro_int" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Rétro intérieur</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.cric" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Cric</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.manivelle" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Manivelle</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.roue_secours" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Roue de secours</label>
                                <label class="flex items-center gap-2.5 cursor-pointer"><input type="checkbox" v-model="form.trousse" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Trousse à pharmacie</label>
                                <label class="flex items-center gap-2.5 sm:col-span-4 text-[#E11D48] font-black cursor-pointer"><input type="checkbox" v-model="form.pare_brise_fissure" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" /> Pare-brise fissuré</label>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- ÉTAPE 4 : PHOTOS & REMARQUES (WEBCAM EN DIRECT OU GALERIE) -->
                    <!-- ========================================== -->
                    <div v-if="currentStep === 4" class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 space-y-8">
                        <div class="flex items-center gap-4 border-b border-gray-100 pb-6">
                            <div class="w-12 h-12 rounded-2xl bg-[#E11D48]/10 text-[#E11D48] flex items-center justify-center font-black text-base border border-[#E11D48]/30">4</div>
                            <div>
                                <h3 class="text-xl font-black text-[#0B0F19]">Photos réglementaires & Remarques</h3>
                                <p class="text-xs text-[#8A8D8F] font-medium mt-0.5">Prenez en direct la photo ou choisissez-la dans votre galerie.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            
                            <div 
                                v-for="(label, key) in { photo_avant: 'Face Avant', photo_arriere: 'Face Arrière', photo_gauche: 'Côté Gauche', photo_droite: 'Côté Droit' }" 
                                :key="key"
                                class="border-2 border-dashed border-gray-200 bg-[#F8FAFC] rounded-2xl p-5 hover:border-[#E11D48] transition flex flex-col justify-between"
                            >
                                <div class="mb-3 flex justify-between items-center">
                                    <span class="text-xs font-black text-[#0B0F19] uppercase tracking-wider block">{{ label }} *</span>
                                    <span v-if="form[key]" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                        Image chargée ✓
                                    </span>
                                </div>

                                <!-- Aperçu photo -->
                                <div v-if="photoPreviews[key]" class="relative mb-3 rounded-xl overflow-hidden border border-gray-200 h-40 bg-slate-900">
                                    <img :src="photoPreviews[key]" alt="Aperçu photo" class="w-full h-full object-cover" />
                                    <button 
                                        type="button" 
                                        @click="removePhoto(key)" 
                                        class="absolute top-2 right-2 bg-rose-600 text-white rounded-full p-1.5 shadow-md hover:bg-rose-700 transition" 
                                        title="Supprimer la photo"
                                    >
                                        <i class="fa-solid fa-xmark text-xs w-4 h-4 flex items-center justify-center"></i>
                                    </button>
                                </div>

                                <!-- Boutons d'action -->
                                <div class="grid grid-cols-2 gap-2 mt-auto">
                                    <button 
                                        type="button" 
                                        @click="openLiveCamera(key)" 
                                        class="px-3 py-2.5 bg-[#E11D48] hover:bg-[#BE123C] text-white text-[11px] font-bold rounded-xl transition flex items-center justify-center gap-1.5 shadow-xs"
                                    >
                                        <i class="fa-solid fa-camera text-xs"></i>
                                        <span>Prendre photo</span>
                                    </button>

                                    <button 
                                        type="button" 
                                        @click="triggerFileInput(key)" 
                                        class="px-3 py-2.5 bg-white border border-gray-300 hover:bg-gray-100 text-[#0B0F19] text-[11px] font-bold rounded-xl transition flex items-center justify-center gap-1.5 shadow-xs"
                                    >
                                        <i class="fa-solid fa-image text-xs text-blue-600"></i>
                                        <span>Galerie</span>
                                    </button>
                                </div>

                                <!-- Input Masqué pour la Galerie -->
                                <input 
                                    type="file" 
                                    :ref="el => fileInputs[key] = el" 
                                    @change="e => handleFileUpload(e, key)" 
                                    accept="image/*" 
                                    class="hidden" 
                                />
                            </div>

                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Remarques éventuelles / Observations</label>
                            <textarea v-model="form.remarques_eventuelles" rows="3" class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-4 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="État de la carrosserie, rayures constatées, objets de valeur à bord..."></textarea>
                        </div>
                    </div>

                    <!-- BOUTONS DE NAVIGATION -->
                    <div class="flex justify-between pt-4">
                        <button 
                            v-if="currentStep > 1" 
                            type="button" 
                            @click="prevStep" 
                            class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-[#0B0F19] text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-xs"
                        >
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Précédent</span>
                        </button>
                        <div v-else></div>

                        <button 
                            v-if="currentStep < totalSteps" 
                            type="button" 
                            @click="nextStep" 
                            class="px-6 py-3 bg-[#E11D48] hover:bg-[#BE123C] text-white text-xs font-bold rounded-xl shadow-md shadow-[#E11D48]/20 transition ml-auto flex items-center gap-2"
                        >
                            <span>Suivant</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>

                        <button 
                            v-if="currentStep === totalSteps" 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition ml-auto disabled:opacity-50 flex items-center gap-2"
                        >
                            <span>Enregistrer la réception</span>
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- MODALE CAMÉRA EN DIRECT (WEBCAM / MOBILE) -->
        <div v-if="showCameraModal" class="fixed inset-0 z-50 bg-black/90 flex flex-col items-center justify-between p-4 sm:p-6">
            <div class="w-full max-w-xl flex justify-between items-center text-white pb-2">
                <span class="text-xs font-black uppercase tracking-wider">
                    Capture en direct : {{ currentCameraField?.replace('photo_', 'Face ') }}
                </span>
                <button @click="closeCameraModal" class="text-white hover:text-rose-500 text-xl p-2">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Lecteur Vidéo du flux Caméra -->
            <div class="relative w-full max-w-xl flex-1 bg-black rounded-2xl overflow-hidden flex items-center justify-center border border-gray-800">
                <video ref="videoRef" autoplay playsinline class="w-full h-full object-cover"></video>
            </div>

            <!-- Commandes de Déclenchement -->
            <div class="w-full max-w-xl flex items-center justify-center gap-6 pt-4">
                <button 
                    type="button" 
                    @click="closeCameraModal" 
                    class="px-5 py-3 bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold rounded-2xl transition"
                >
                    Annuler
                </button>
                <button 
                    type="button" 
                    @click="capturePhotoFromLive" 
                    class="w-16 h-16 bg-white border-4 border-[#E11D48] rounded-full flex items-center justify-center text-[#E11D48] hover:scale-105 active:scale-95 transition shadow-2xl"
                    title="Déclencher la photo"
                >
                    <div class="w-12 h-12 bg-[#E11D48] rounded-full"></div>
                </button>
            </div>
        </div>

    </AuthenticatedLayout>
</template>