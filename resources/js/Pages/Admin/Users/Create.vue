<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { 
    faUserPlus, 
    faArrowLeft, 
    faUser, 
    faEnvelope, 
    faUserShield, 
    faLock,
    faCircleNotch
} from '@fortawesome/free-solid-svg-icons';

// Le collaborateur est toujours créé dans le siège actif de l'administrateur
defineProps({
    siegeActif: { type: String, default: null },
});

// Initialisation du formulaire avec Inertia
const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'receptionniste',
});

const submit = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => form.reset('password'),
    });
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
      
      <!-- Lien retour -->
      <div class="mb-6">
        <Link :href="route('admin.users.index')" 
              class="inline-flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-gray-900 transition">
          <font-awesome-icon :icon="faArrowLeft" />
          <span>Retour à la liste des collaborateurs</span>
        </Link>
      </div>

      <!-- Carte du formulaire -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        
        <!-- En-tête de la carte -->
        <div class="p-6 sm:p-8 bg-white border-b border-gray-100 flex items-center gap-4">
          <span class="w-12 h-12 flex items-center justify-center rounded-xl text-white shadow-sm shrink-0" style="background-color: #C8102E;">
            <font-awesome-icon :icon="faUserPlus" class="text-xl" />
          </span>
          <div>
            <h1 class="text-2xl font-bold" style="color: #1A1A1A;">Ajouter un Collaborateur</h1>
            <p class="text-xs text-gray-500 mt-1">Définissez ses identifiants et son rôle dans l'organisation du garage</p>
          </div>
        </div>

        <!-- Formulaire -->
        <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">
          
          <!-- Nom complet -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider mb-2 text-gray-700">
              Nom complet <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <font-awesome-icon :icon="faUser" />
              </span>
              <input 
                type="text" 
                v-model="form.name" 
                required 
                placeholder="ex: Jean Dupont"
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#C8102E]/20 focus:border-[#C8102E] transition"
                :class="{ 'border-red-500 focus:ring-red-200': form.errors.name }"
              />
            </div>
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.name }}</p>
          </div>

          <!-- Adresse Email -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider mb-2 text-gray-700">
              Adresse Email <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <font-awesome-icon :icon="faEnvelope" />
              </span>
              <input 
                type="email" 
                v-model="form.email" 
                required 
                placeholder="jean.dupont@garage.com"
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#C8102E]/20 focus:border-[#C8102E] transition"
                :class="{ 'border-red-500 focus:ring-red-200': form.errors.email }"
              />
            </div>
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.email }}</p>
          </div>

          <!-- Rôle -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider mb-2 text-gray-700">
              Rôle dans le Garage <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <font-awesome-icon :icon="faUserShield" />
              </span>
              <select 
                v-model="form.role" 
                required 
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#C8102E]/20 focus:border-[#C8102E] transition"
                :class="{ 'border-red-500 focus:ring-red-200': form.errors.role }"
              >
                <option value="receptionniste">Réceptionniste (Accueil, état initial, photos)</option>
                <option value="mecanicien">Mécanicien (Essais, diagnostic, réparations)</option>
                <option value="administratif">Administratif (Devis, facturation)</option>
                <option value="charge_client">Chargé de Suivi Client & Relances</option>
                <option value="admin">Administrateur (Accès total)</option>
              </select>
            </div>
            <p v-if="form.errors.role" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.role }}</p>
          </div>

          <!-- Siège d'affectation : toujours le siège actif (pas pour l'administrateur) -->
          <div v-if="form.role !== 'admin'">
            <label class="block text-xs font-bold uppercase tracking-wider mb-2 text-gray-700">
              Siège d'affectation
            </label>
            <div class="w-full px-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm font-semibold text-gray-800">
              {{ siegeActif }} — {{ $page.props.sieges[siegeActif] }}
            </div>
            <p class="mt-1 text-[11px] text-gray-500">
              Le collaborateur sera rattaché au siège actif. Pour en ajouter un dans un autre siège, changez de siège depuis le tableau de bord.
            </p>
          </div>

          <!-- Mot de passe temporaire -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider mb-2 text-gray-700">
              Mot de passe temporaire <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <font-awesome-icon :icon="faLock" />
              </span>
              <input 
                type="password" 
                v-model="form.password" 
                required 
                placeholder="••••••••"
                class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-800 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#C8102E]/20 focus:border-[#C8102E] transition"
                :class="{ 'border-red-500 focus:ring-red-200': form.errors.password }"
              />
            </div>
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.password }}</p>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
            <Link 
              :href="route('admin.users.index')" 
              class="px-5 py-2.5 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-100 transition"
            >
              Annuler
            </Link>
            <button 
              type="submit" 
              :disabled="form.processing" 
              class="text-white px-6 py-2.5 rounded-xl text-xs font-semibold transition duration-200 shadow-md hover:shadow-lg disabled:opacity-50 flex items-center gap-2"
              style="background-color: #C8102E;"
            >
              <font-awesome-icon v-if="form.processing" :icon="faCircleNotch" class="animate-spin" />
              <span>{{ form.processing ? 'Enregistrement...' : 'Enregistrer l\'employé' }}</span>
            </button>
          </div>

        </form>
      </div>

    </div>
  </div>
</template>