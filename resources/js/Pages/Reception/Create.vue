<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted, nextTick } from 'vue';

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

// Photos : 4 obligatoires + photos supplémentaires facultatives (10 photos maximum au total)
const PHOTOS_OBLIGATOIRES = {
    photo_avant: 'Face Avant',
    photo_arriere: 'Face Arrière',
    photo_gauche: 'Côté Gauche',
    photo_droite: 'Côté Droit',
};
const MAX_PHOTOS_TOTAL = 10;
const MAX_PHOTOS_SUP = MAX_PHOTOS_TOTAL - Object.keys(PHOTOS_OBLIGATOIRES).length; // 6
const extraPreviews = ref([]);
const extraFileInput = ref(null);
const photoMessage = ref('');

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
    photos_supplementaires: [],
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
    // On ne passe à l'étape suivante que si les champs obligatoires de l'étape sont remplis
    if (!validateStep(currentStep.value)) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

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
/* Validation des champs obligatoires, étape par étape                 */
/* ------------------------------------------------------------------ */
const FIELD_LABELS = {
    nom: 'Nom',
    prenom: 'Prénom',
    telephone: 'Téléphone',
    email: 'E-mail',
    adresse: 'Adresse',
    immatriculation: 'Immatriculation',
    marque: 'Marque',
    modele: 'Modèle',
    vin: 'Numéro de châssis (VIN)',
    expiration_assurance: 'Expiration assurance',
    expiration_sicta: 'Expiration SICTA',
    siege: 'Siège',
    date_reception: 'Date de réception',
    kilometrage: 'Kilométrage actuel',
    personne_a_contacter: 'Personne à contacter',
    niveau_carburant: 'Niveau de carburant',
    intervalle_niveau_carburant: 'Précision niveau / jauge',
};

// Étape (1 à 4) à laquelle appartient chaque champ, pour afficher l'erreur au bon endroit
const STEP_DE_CHAMP = {
    client_id: 1, nom: 1, prenom: 1, telephone: 1, email: 1, adresse: 1, type_client: 1,
    immatriculation: 2, marque: 2, modele: 2, vin: 2, expiration_assurance: 2, expiration_sicta: 2,
    siege: 3, numero_ot: 3, date_reception: 3, kilometrage: 3, personne_a_contacter: 3,
    niveau_carburant: 3, intervalle_niveau_carburant: 3, circuit: 3,
    photo_avant: 4, photo_arriere: 4, photo_gauche: 4, photo_droite: 4,
    remarques_eventuelles: 4,
};

const stepOfField = (field) => {
    if (STEP_DE_CHAMP[field]) return STEP_DE_CHAMP[field];
    if (field.startsWith('photos_supplementaires')) return 4;
    return null;
};

const estRempli = (value) => value !== null && value !== undefined && String(value).trim() !== '';

// Retourne { champ: message } pour une étape donnée (objet vide = étape valide)
const stepErrors = (step) => {
    const e = {};
    const exiger = (field) => {
        if (!estRempli(form[field])) {
            e[field] = `Le champ « ${FIELD_LABELS[field]} » est obligatoire.`;
        }
    };

    if (step === 1) {
        if (isNewClientMode.value) {
            ['nom', 'prenom', 'telephone', 'email', 'adresse'].forEach(exiger);
            if (!e.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
                e.email = "L'adresse e-mail n'est pas valide.";
            }
        } else if (!form.client_id) {
            e.client_id = 'Sélectionnez un client existant ou créez-en un nouveau.';
        }
    }

    if (step === 2) {
        ['immatriculation', 'marque', 'modele', 'vin', 'expiration_assurance', 'expiration_sicta'].forEach(exiger);
    }

    if (step === 3) {
        if (!props.siege) exiger('siege');
        ['date_reception', 'kilometrage', 'personne_a_contacter', 'niveau_carburant', 'intervalle_niveau_carburant'].forEach(exiger);
        if (!e.kilometrage && (!Number.isInteger(Number(form.kilometrage)) || Number(form.kilometrage) < 0)) {
            e.kilometrage = 'Le kilométrage doit être un nombre entier positif.';
        }
    }

    if (step === 4) {
        Object.entries(PHOTOS_OBLIGATOIRES).forEach(([key, label]) => {
            if (!form[key]) e[key] = `La photo « ${label} » est obligatoire.`;
        });
    }

    return e;
};

// Les erreurs d'une étape ne s'affichent qu'une fois qu'on a essayé de la valider,
// puis disparaissent d'elles-mêmes dès que le champ est corrigé.
const attempted = ref({ 1: false, 2: false, 3: false, 4: false });

const clientErrors = computed(() => {
    const all = {};
    for (let s = 1; s <= totalSteps; s++) {
        if (attempted.value[s]) Object.assign(all, stepErrors(s));
    }
    return all;
});

const err = (field) => clientErrors.value[field] || form.errors[field];

const validateStep = (step) => {
    attempted.value[step] = true;
    return Object.keys(stepErrors(step)).length === 0;
};

// Messages à afficher dans l'encadré rouge en haut de l'étape courante
const currentStepMessages = computed(() => {
    const champs = new Set([...Object.keys(clientErrors.value), ...Object.keys(form.errors)]);
    const messages = [];
    champs.forEach((champ) => {
        const step = stepOfField(champ);
        if (step !== null && step !== currentStep.value) return;
        const message = err(champ);
        if (message && !messages.includes(message)) messages.push(message);
    });
    return messages;
});

const goToStep = (step) => {
    currentStep.value = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/* ------------------------------------------------------------------ */
/* Compression des photos (évite de dépasser la limite de taille)      */
/* ------------------------------------------------------------------ */
const MAX_DIMENSION = 1600;           // plus grand côté, en pixels
const JPEG_QUALITY = 0.8;
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 Mo : même limite que le serveur

const dimensionsReduites = (w, h) => {
    const ratio = Math.min(1, MAX_DIMENSION / Math.max(w, h));
    return [Math.round(w * ratio), Math.round(h * ratio)];
};

const canvasVersFichier = (canvas, nom) => new Promise((resolve) => {
    canvas.toBlob(
        (blob) => resolve(blob ? new File([blob], nom, { type: 'image/jpeg' }) : null),
        'image/jpeg',
        JPEG_QUALITY
    );
});

// Redimensionne et recompresse une image de la galerie ; renvoie l'original si l'opération échoue
const compresserFichier = async (file) => {
    let url = null;
    try {
        url = URL.createObjectURL(file);
        const img = await new Promise((resolve, reject) => {
            const i = new Image();
            i.onload = () => resolve(i);
            i.onerror = reject;
            i.src = url;
        });
        const [w, h] = dimensionsReduites(img.naturalWidth, img.naturalHeight);
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
        const nom = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
        const compresse = await canvasVersFichier(canvas, nom);
        return compresse && compresse.size < file.size ? compresse : file;
    } catch (e) {
        return file;
    } finally {
        if (url) URL.revokeObjectURL(url);
    }
};

// Vérifie que c'est bien une image, la compresse, puis contrôle le poids final
const preparerPhoto = async (file) => {
    if (!file || !file.type || !file.type.startsWith('image/')) {
        photoMessage.value = "Le fichier choisi n'est pas une image.";
        return null;
    }
    const prete = await compresserFichier(file);
    if (prete.size > MAX_FILE_SIZE) {
        photoMessage.value = `Photo trop lourde (${(prete.size / 1048576).toFixed(1)} Mo) : maximum 5 Mo par photo.`;
        return null;
    }
    return prete;
};

/* ------------------------------------------------------------------ */
/* Photos obligatoires et supplémentaires                              */
/* ------------------------------------------------------------------ */
const totalPhotos = computed(() =>
    Object.keys(PHOTOS_OBLIGATOIRES).filter((k) => form[k]).length + form.photos_supplementaires.length
);
const peutAjouterSup = computed(() => form.photos_supplementaires.length < MAX_PHOTOS_SUP);

const definirPhoto = async (field, file) => {
    photoMessage.value = '';
    const prete = await preparerPhoto(file);
    if (!prete) return;

    if (photoPreviews.value[field]) URL.revokeObjectURL(photoPreviews.value[field]);
    form[field] = prete;
    photoPreviews.value[field] = URL.createObjectURL(prete);
    form.clearErrors(field);
};

const ajouterPhotosSup = async (files) => {
    photoMessage.value = '';
    const restantes = MAX_PHOTOS_SUP - form.photos_supplementaires.length;
    if (restantes <= 0) {
        photoMessage.value = `Maximum atteint : ${MAX_PHOTOS_TOTAL} photos au total.`;
        return;
    }

    const liste = Array.from(files);
    const aTraiter = liste.slice(0, restantes);
    const tropNombreuses = liste.length > restantes;

    for (const file of aTraiter) {
        if (form.photos_supplementaires.length >= MAX_PHOTOS_SUP) break;
        const prete = await preparerPhoto(file);
        if (!prete) continue;
        form.photos_supplementaires.push(prete);
        extraPreviews.value.push(URL.createObjectURL(prete));
    }

    if (tropNombreuses) {
        photoMessage.value = `Seules ${aTraiter.length} photo(s) ont été ajoutées : maximum ${MAX_PHOTOS_TOTAL} photos au total.`;
    }
};

const removePhoto = (field) => {
    if (photoPreviews.value[field]) URL.revokeObjectURL(photoPreviews.value[field]);
    form[field] = null;
    photoPreviews.value[field] = null;
};

const removeExtraPhoto = (index) => {
    URL.revokeObjectURL(extraPreviews.value[index]);
    extraPreviews.value.splice(index, 1);
    form.photos_supplementaires.splice(index, 1);
    photoMessage.value = '';
};

/* ------------------------------------------------------------------ */
/* Gestion de la Caméra en Direct (Live WebCam / Mobile)               */
/* ------------------------------------------------------------------ */
const cameraLabel = computed(() =>
    currentCameraField.value === 'extra'
        ? `Photo supplémentaire ${form.photos_supplementaires.length + 1}`
        : (PHOTOS_OBLIGATOIRES[currentCameraField.value] || '')
);

const openLiveCamera = async (field) => {
    photoMessage.value = '';

    if (field === 'extra' && !peutAjouterSup.value) {
        photoMessage.value = `Maximum atteint : ${MAX_PHOTOS_TOTAL} photos au total.`;
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        photoMessage.value = "Caméra indisponible sur ce navigateur (la page doit être ouverte en HTTPS).";
        return;
    }

    currentCameraField.value = field;
    showCameraModal.value = true;
    await nextTick();

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } }, // Priorité caméra arrière
            audio: false
        });

        // La modale a été fermée pendant l'autorisation : on libère la caméra
        if (!showCameraModal.value) {
            stream.getTracks().forEach(track => track.stop());
            return;
        }

        mediaStream.value = stream;
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
        }
    } catch (erreurCamera) {
        closeCameraModal();
        photoMessage.value = "Impossible d'accéder à la caméra. Vérifiez les autorisations de votre navigateur.";
    }
};

const capturePhotoFromLive = async () => {
    const video = videoRef.value;
    if (!video || !video.videoWidth) return;

    // Image réduite dès la capture : pas de photo de plusieurs Mo envoyée au serveur
    const [w, h] = dimensionsReduites(video.videoWidth, video.videoHeight);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(video, 0, 0, w, h);

    const field = currentCameraField.value;
    const file = await canvasVersFichier(canvas, `${field}_${Date.now()}.jpg`);
    closeCameraModal();

    if (!file) {
        photoMessage.value = 'La capture a échoué, veuillez réessayer.';
        return;
    }

    if (field === 'extra') {
        await ajouterPhotosSup([file]);
    } else {
        await definirPhoto(field, file);
    }
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
    Object.values(photoPreviews.value).forEach((url) => url && URL.revokeObjectURL(url));
    extraPreviews.value.forEach((url) => URL.revokeObjectURL(url));
});

/* ------------------------------------------------------------------ */
/* Import depuis Galerie                                              */
/* ------------------------------------------------------------------ */
const triggerFileInput = (field) => {
    if (fileInputs.value[field]) {
        fileInputs.value[field].click();
    }
};

const handleFileUpload = async (event, field) => {
    const file = event.target.files[0];
    event.target.value = ''; // permet de re-choisir le même fichier
    if (file) await definirPhoto(field, file);
};

const handleExtraUpload = async (event) => {
    const files = Array.from(event.target.files); // copie avant de vider l'input
    event.target.value = '';
    if (files.length) await ajouterPhotosSup(files);
};

/* ------------------------------------------------------------------ */
/* Envoi du formulaire                                                */
/* ------------------------------------------------------------------ */
const submit = () => {
    // On contrôle toutes les étapes ; si l'une est incomplète, on y renvoie l'utilisateur
    for (let s = 1; s <= totalSteps; s++) {
        if (!validateStep(s)) {
            goToStep(s);
            return;
        }
    }

    form.clearErrors();
    form.post(route('reception.store'), {
        preserveScroll: true,
        forceFormData: true,
        onError: (errors) => {
            // Erreur renvoyée par le serveur : on ouvre l'étape du premier champ en cause
            const steps = Object.keys(errors).map(stepOfField).filter((s) => s !== null);
            if (steps.length) goToStep(Math.min(...steps));
            else window.scrollTo({ top: 0, behavior: 'smooth' });
        },
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

                <form @submit.prevent="submit" novalidate class="space-y-6">

                    <!-- ENCADRÉ D'ERREURS DE L'ÉTAPE EN COURS -->
                    <div v-if="currentStepMessages.length" class="p-5 bg-red-50 border border-red-200 rounded-2xl space-y-1.5" role="alert">
                        <p class="text-sm font-black text-[#E11D48] flex items-center gap-2">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Veuillez corriger les points suivants :</span>
                        </p>
                        <ul class="list-disc pl-6 text-xs font-bold text-[#E11D48] space-y-0.5">
                            <li v-for="message in currentStepMessages" :key="message">{{ message }}</li>
                        </ul>
                    </div>

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
                                    <p v-if="err('nom')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('nom') }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Prénom *</label>
                                    <input type="text" v-model="form.prenom" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Prénom" />
                                    <p v-if="err('prenom')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('prenom') }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Téléphone *</label>
                                    <input type="text" v-model="form.telephone" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="0700000000" />
                                    <p v-if="err('telephone')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('telephone') }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">E-mail *</label>
                                    <input type="email" v-model="form.email" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="email@example.com" />
                                    <p v-if="err('email')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('email') }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Adresse *</label>
                                    <textarea v-model="form.adresse" rows="2" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]"></textarea>
                                    <p v-if="err('adresse')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('adresse') }}</p>
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
                                <div v-if="err('immatriculation')" class="text-[#E11D48] text-xs font-bold mt-1">{{ err('immatriculation') }}</div>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Marque *</label>
                                <input type="text" v-model="form.marque" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Toyota" />
                                <p v-if="err('marque')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('marque') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Modèle *</label>
                                <input type="text" v-model="form.modele" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Corolla" />
                                <p v-if="err('modele')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('modele') }}</p>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Numéro de Châssis (VIN) *</label>
                                <input type="text" v-model="form.vin" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 uppercase shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="17 caractères" />
                                <p v-if="err('vin')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('vin') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Expiration Assurance *</label>
                                <input type="date" v-model="form.expiration_assurance" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" />
                                <p v-if="err('expiration_assurance')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('expiration_assurance') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Expiration SICTA (Visite tech.) *</label>
                                <input type="date" v-model="form.expiration_sicta" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" />
                                <p v-if="err('expiration_sicta')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('expiration_sicta') }}</p>
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
                                <p v-if="err('siege')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('siege') }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Numéro OT (Généré auto) *</label>
                                <input type="text" v-model="form.numero_ot" required readonly class="w-full rounded-2xl border-gray-200 bg-gray-100 text-sm p-3.5 shadow-xs text-gray-600 font-bold cursor-not-allowed" />
                                <span class="text-[10px] text-[#8A8D8F] mt-1 block">Format : {{ form.siege || 'SGK' }}-JJMMAAAA/001</span>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Date de réception *</label>
                                <input type="date" v-model="form.date_reception" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" />
                                <p v-if="err('date_reception')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('date_reception') }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Kilométrage actuel (km) *</label>
                                <input type="number" v-model="form.kilometrage" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="45000" />
                                <p v-if="err('kilometrage')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('kilometrage') }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Personne à contacter *</label>
                                <input type="text" v-model="form.personne_a_contacter" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="Nom ou téléphone" />
                                <p v-if="err('personne_a_contacter')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('personne_a_contacter') }}</p>
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
                                <p v-if="err('niveau_carburant')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('niveau_carburant') }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-[#0B0F19] uppercase tracking-wider mb-2">Précision niveau / Jauge *</label>
                                <input type="text" v-model="form.intervalle_niveau_carburant" required class="w-full rounded-2xl border-gray-200 bg-[#F8FAFC] text-sm p-3.5 shadow-xs focus:border-[#E11D48] focus:ring-[#E11D48]" placeholder="ex: Exactement la moitié" />
                                <p v-if="err('intervalle_niveau_carburant')" class="mt-1 text-xs font-bold text-[#E11D48]">{{ err('intervalle_niveau_carburant') }}</p>
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
                                v-for="(label, key) in PHOTOS_OBLIGATOIRES" 
                                :key="key"
                                :class="['border-2 border-dashed bg-[#F8FAFC] rounded-2xl p-5 hover:border-[#E11D48] transition flex flex-col justify-between', err(key) ? 'border-[#E11D48]' : 'border-gray-200']"
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
                                <p v-if="err(key)" class="mt-2 text-xs font-bold text-[#E11D48]">{{ err(key) }}</p>
                            </div>

                        </div>

                        <!-- Photos supplémentaires (facultatives) -->
                        <div class="pt-6 border-t border-gray-100 space-y-4">
                            <div class="flex flex-wrap justify-between items-center gap-2">
                                <div>
                                    <h4 class="text-sm font-black text-[#0B0F19]">
                                        Photos supplémentaires <span class="text-[#8A8D8F] font-medium">(facultatif)</span>
                                    </h4>
                                    <p class="text-xs text-[#8A8D8F] font-medium mt-0.5">
                                        Rayures, dégâts, tableau de bord… {{ MAX_PHOTOS_TOTAL }} photos au maximum au total (les 4 photos obligatoires comprises).
                                    </p>
                                </div>
                                <span
                                    :class="['text-[11px] font-black px-2.5 py-1 rounded-lg border', totalPhotos >= MAX_PHOTOS_TOTAL ? 'bg-red-50 text-[#E11D48] border-red-200' : 'bg-gray-50 text-[#0B0F19] border-gray-200']"
                                >
                                    {{ totalPhotos }}/{{ MAX_PHOTOS_TOTAL }} photos
                                </span>
                            </div>

                            <p v-if="photoMessage" class="text-xs font-bold text-[#E11D48] bg-red-50 border border-red-200 rounded-xl px-3 py-2">
                                {{ photoMessage }}
                            </p>

                            <div v-if="extraPreviews.length" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div
                                    v-for="(src, index) in extraPreviews"
                                    :key="src"
                                    class="relative h-32 rounded-xl overflow-hidden border border-gray-200 bg-slate-900"
                                >
                                    <img :src="src" alt="Photo supplémentaire" class="w-full h-full object-cover" />
                                    <button
                                        type="button"
                                        @click="removeExtraPhoto(index)"
                                        class="absolute top-2 right-2 bg-rose-600 text-white rounded-full p-1.5 shadow-md hover:bg-rose-700 transition"
                                        title="Supprimer la photo"
                                    >
                                        <i class="fa-solid fa-xmark text-xs w-4 h-4 flex items-center justify-center"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    :disabled="!peutAjouterSup"
                                    @click="openLiveCamera('extra')"
                                    class="px-3 py-2.5 bg-[#E11D48] hover:bg-[#BE123C] text-white text-[11px] font-bold rounded-xl transition flex items-center justify-center gap-1.5 shadow-xs disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <i class="fa-solid fa-camera text-xs"></i>
                                    <span>Prendre une photo</span>
                                </button>
                                <button
                                    type="button"
                                    :disabled="!peutAjouterSup"
                                    @click="extraFileInput?.click()"
                                    class="px-3 py-2.5 bg-white border border-gray-300 hover:bg-gray-100 text-[#0B0F19] text-[11px] font-bold rounded-xl transition flex items-center justify-center gap-1.5 shadow-xs disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <i class="fa-solid fa-images text-xs text-blue-600"></i>
                                    <span>Galerie (plusieurs)</span>
                                </button>
                            </div>

                            <input
                                type="file"
                                ref="extraFileInput"
                                @change="handleExtraUpload"
                                accept="image/*"
                                multiple
                                class="hidden"
                            />
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
                    Capture en direct : {{ cameraLabel }}
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