<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';
import { 
    faBoxesStacked, 
    faFileLines, 
    faUserShield, 
    faUserGear, 
    faScrewdriverWrench, 
    faClipboardUser, 
    faHeadset, 
    faUserGroup,
    faChevronDown,
    faUsers
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    users: Array,
    stats: Object,
    siegeFiltre: { type: String, default: null },
});

// Chiffre d'affaires par mois (mois courant sélectionné par défaut)
const moisSelectionne = ref(props.stats?.chiffre_affaires_mensuel?.[0]?.cle ?? '');

const chiffreAffairesAffiche = computed(() => {
    const mois = props.stats?.chiffre_affaires_mensuel?.find(m => m.cle === moisSelectionne.value);
    const total = mois?.total ?? 0;
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(total) + ' FCFA';
});

// Définition des rôles avec labels et icônes
const ROLES_CONFIG = {
    admin: { label: 'Administrateurs', icone: faUserShield, badgeColor: 'bg-gray-900 text-white' },
    receptionniste: { label: 'Réceptionnistes', icone: faClipboardUser, badgeColor: 'bg-blue-50 text-blue-700 border border-blue-200' },
    mecanicien: { label: 'Mécaniciens', icone: faScrewdriverWrench, badgeColor: 'bg-amber-50 text-amber-700 border border-amber-200' },
    administratif: { label: 'Administratifs', icone: faUserGear, badgeColor: 'bg-purple-50 text-purple-700 border border-purple-200' },
    charge_client: { label: 'Chargés de Suivi Client', icone: faHeadset, badgeColor: 'bg-rose-50 text-rose-700 border border-rose-200' },
};

// Accordéon principal (Liste globale des employés)
const isEmployeesMenuOpen = ref(true);

const toggleEmployeesMenu = () => {
    isEmployeesMenuOpen.value = !isEmployeesMenuOpen.value;
};

// Sous-accordéons par rôle
const activeRoles = ref({
    admin: true,
    receptionniste: false,
    mecanicien: false,
    administratif: false,
    charge_client: false,
});

const toggleRole = (roleKey) => {
    activeRoles.value[roleKey] = !activeRoles.value[roleKey];
};

// Regroupement des employés par rôle
const groupedUsers = computed(() => {
    if (!props.users) return {};

    const groups = {};
    
    Object.keys(ROLES_CONFIG).forEach(role => {
        groups[role] = [];
    });

    props.users.forEach(user => {
        if (groups[user.role]) {
            groups[user.role].push(user);
        } else {
            if (!groups['autre']) groups['autre'] = [];
            groups['autre'].push(user);
        }
    });

    return groups;
});

const formatRole = (role) => {
    return ROLES_CONFIG[role]?.label || role;
};

const deleteUser = (id) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce collaborateur ?')) {
        router.delete(route('admin.users.destroy', id));
    }
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">
      
      <!-- En-tête principal -->
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold" style="color: #1A1A1A;">Tableau de Bord - Garage</h1>
          <p class="text-sm mt-1" style="color: #8A8D8F;">Vue d'ensemble de la gestion et des activités<span v-if="siegeFiltre" class="font-bold" style="color: #C8102E;"> — Siège {{ siegeFiltre }}</span></p>
        </div>
        
        <div class="flex items-center gap-3">
          <SiegeSwitcher :current="siegeFiltre" />
          <button @click="logout" 
                  class="text-sm font-semibold px-4 py-2.5 rounded-lg border border-gray-300 hover:bg-gray-100 transition shadow-sm bg-white"
                  style="color: #1A1A1A;">
            Déconnexion
          </button>
        </div>
      </div>

      

     <!-- SECTION 1 : Statistiques -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
    <div class="flex items-center justify-between gap-2">
      <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Chiffre d'affaires</p>
      <select
        v-model="moisSelectionne"
        class="text-xs font-semibold border border-gray-200 rounded-lg py-1 pl-2 pr-7 focus:border-[#C8102E] focus:ring-0"
      >
        <option v-for="m in stats?.chiffre_affaires_mensuel" :key="m.cle" :value="m.cle">
          {{ m.label }}
        </option>
      </select>
    </div>
    <p class="text-2xl font-black mt-2" style="color: #1A1A1A;">{{ chiffreAffairesAffiche }}</p>
    <Link :href="route('admin.chiffre-affaires')" class="inline-block mt-3 text-xs font-semibold text-[#C8102E] hover:underline">
      Voir l'historique par année →
    </Link>
  </div>

  <!-- CARD VOITURES ENREGISTRÉES CLIQUABLE -->
  <Link 
    :href="route('admin.vehicules.status')" 
    class="bg-white p-6 rounded-xl shadow-md border border-gray-200 hover:border-[#C8102E] transition group block cursor-pointer"
  >
    <div class="flex items-center justify-between">
      <p class="text-xs font-bold uppercase tracking-wider group-hover:text-[#C8102E] transition" style="color: #8A8D8F;">
        Voitures enregistrées
      </p>
      <span class="text-[10px] font-semibold text-[#C8102E] bg-red-50 px-2 py-0.5 rounded-full">
        Voir tout →
      </span>
    </div>
    <p class="text-2xl font-black mt-2" style="color: #1A1A1A;">{{ stats?.nombre_voitures || '0' }}</p>
  </Link>

  <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
    <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Clients totaux</p>
    <p class="text-2xl font-black mt-2" style="color: #1A1A1A;">{{ stats?.nombre_clients || '0' }}</p>
  </div>
</div>
      <!-- SECTION 2 : Navigation Rapide -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Stock -->
        <Link :href="route('administration.stocks.index')" 
              class="bg-white p-6 rounded-xl shadow-md border border-gray-200 hover:border-[#C8102E] transition group block">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-bold group-hover:text-[#C8102E] transition" style="color: #1A1A1A;">Gestion des Stocks</h3>
            <span class="w-9 h-9 flex items-center justify-center rounded-lg shrink-0 transition"
                  style="background-color: #F3F4F6; color: #C8102E;">
              <font-awesome-icon :icon="faBoxesStacked" class="text-base" />
            </span>
          </div>
          <p class="text-sm" style="color: #8A8D8F;">Accéder au catalogue des pièces détachées et inventaire (commun à tous les sièges).</p>
        </Link>

        <!-- Suivi des Interactions -->
        <Link :href="route('admin.users.interactionindex')" 
              class="bg-white p-6 rounded-xl shadow-md border border-gray-200 hover:border-[#C8102E] transition group block">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-bold group-hover:text-[#C8102E] transition" style="color: #1A1A1A;">Suivi des Interactions</h3>
            <span class="w-9 h-9 flex items-center justify-center rounded-lg shrink-0 transition"
                  style="background-color: #F3F4F6; color: #C8102E;">
              <font-awesome-icon :icon="faFileLines" class="text-base" />
            </span>
          </div>
          <p class="text-sm" style="color: #8A8D8F;">Consulter les Interactions entre les chargés client et les clients.</p>
        </Link>
      </div>

      <!-- SECTION 3 : Grand Menu Dépliant "Liste des Employés" -->
      <div class="bg-white rounded-2xl border border-gray-200 shadow-md overflow-hidden transition">
        
        <!-- EN-TÊTE PRINCIPAL DU MENU DÉROULANT -->
        <div 
          @click="toggleEmployeesMenu"
          class="flex items-center justify-between p-6 bg-white hover:bg-gray-50/80 cursor-pointer select-none transition border-b border-transparent"
          :class="{ 'border-gray-200 bg-gray-50/30': isEmployeesMenuOpen }"
        >
          <div class="flex items-center gap-4">
            <span class="w-10 h-10 flex items-center justify-center rounded-xl text-white shadow-sm" style="background-color: #1A1A1A;">
              <font-awesome-icon :icon="faUsers" class="text-lg" />
            </span>
            <div>
              <h2 class="text-xl font-extrabold" style="color: #1A1A1A;">Liste des Employés</h2>
              <p class="text-xs mt-0.5" style="color: #8A8D8F;">
                Cliquez pour afficher ou masquer les différents rôles et collaborateurs
              </p>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <Link :href="route('admin.users.create')" 
                  @click.stop
                  class="text-white px-4 py-2 rounded-lg text-xs font-semibold shadow transition flex items-center gap-2"
                  style="background-color: #C8102E;">
              <span>+ Ajouter un employé</span>
            </Link>
            
            <div class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500">
              <font-awesome-icon 
                :icon="faChevronDown" 
                class="text-sm transition-transform duration-300"
                :class="{ 'rotate-180': isEmployeesMenuOpen }"
              />
            </div>
          </div>
        </div>

        <!-- CONTENU DU GRAND MENU : SOUS-MENUS PAR RÔLE -->
        <div v-show="isEmployeesMenuOpen" class="p-6 space-y-4 bg-gray-50/50 border-t border-gray-100">
          
          <template v-for="(usersList, roleKey) in groupedUsers" :key="roleKey">
            <div v-if="usersList.length > 0" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden transition">
              
              <!-- Bouton Sous-Menu Rôle -->
              <button 
                type="button"
                @click="toggleRole(roleKey)"
                class="w-full flex items-center justify-between p-4 text-left bg-white hover:bg-gray-50 transition border-b border-transparent"
                :class="{ 'border-gray-200 bg-gray-50/50': activeRoles[roleKey] }"
              >
                <div class="flex items-center gap-3">
                  <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-700 text-sm">
                    <font-awesome-icon :icon="ROLES_CONFIG[roleKey]?.icone || faUserGroup" />
                  </span>
                  <div>
                    <h3 class="text-sm font-extrabold text-gray-800">
                      {{ ROLES_CONFIG[roleKey]?.label || 'Autres Rôles' }}
                    </h3>
                    <p class="text-[11px] text-gray-400">
                      {{ usersList.length }} {{ usersList.length > 1 ? 'collaborateurs' : 'collaborateur' }}
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-600">
                    {{ usersList.length }}
                  </span>
                  <font-awesome-icon 
                    :icon="faChevronDown" 
                    class="text-gray-400 text-xs transition-transform duration-200"
                    :class="{ 'rotate-180': activeRoles[roleKey] }"
                  />
                </div>
              </button>

              <!-- Liste des cartes employés du rôle -->
              <div v-show="activeRoles[roleKey]" class="p-5 bg-gray-50/30 border-t border-gray-100">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                  <div v-for="user in usersList" :key="user.id" class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                      <div class="flex items-start justify-between gap-2 mb-3">
                        <h4 class="font-bold text-sm truncate" style="color: #1A1A1A;" :title="user.name">
                          {{ user.name }}
                        </h4>
                        <span class="px-2 py-0.5 inline-flex text-[10px] leading-4 font-semibold rounded-full shrink-0"
                              :class="ROLES_CONFIG[user.role]?.badgeColor || 'bg-gray-100 text-gray-700'">
                          {{ formatRole(user.role) }}
                        </span>
                      </div>
                      
                      <div class="space-y-1 text-xs text-gray-500 mb-5">
                        <p class="flex items-center gap-1.5 truncate">
                          <span class="font-medium text-gray-700">Email :</span> {{ user.email }}
                        </p>
                        <p class="flex items-center gap-1.5">
                          <span class="font-medium text-gray-700">Créé le :</span> {{ new Date(user.created_at).toLocaleDateString() }}
                        </p>
                        <p v-if="user.siege" class="flex items-center gap-1.5">
                          <span class="font-medium text-gray-700">Siège :</span> {{ user.siege }}
                        </p>
                      </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 text-xs font-medium">
                      <Link :href="route('admin.users.edit', user.id)" class="text-blue-600 hover:text-blue-900 transition">
                        Modifier
                      </Link>
                      <button v-if="user.id !== $page.props.auth.user.id" 
                              @click="deleteUser(user.id)" 
                              class="text-red-600 hover:text-red-900 transition">
                        Supprimer
                      </button>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </template>

          <!-- Aucun utilisateur -->
          <div v-if="!users || users.length === 0" class="bg-white p-8 rounded-xl border border-gray-200 text-center text-sm" style="color: #8A8D8F;">
            Aucun collaborateur enregistré pour le moment.
          </div>

        </div>
      </div>

    </div>
  </div>
</template>