<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faBoxesStacked, faFileLines } from '@fortawesome/free-solid-svg-icons';

defineProps({
    users: Array,
    stats: Object,
});

const formatRole = (role) => {
    const roles = {
        admin: 'Administrateur',
        receptionniste: 'Réceptionniste',
        mecanicien: 'Mécanicien',
        administratif: 'Administratif',
        charge_client: 'Chargé de Suivi Client',
    };
    return roles[role] || role;
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
          <p class="text-sm mt-1" style="color: #8A8D8F;">Vue d'ensemble de la gestion et des activités</p>
        </div>
        
        <div>
          <button @click="logout" 
                  class="text-sm font-semibold px-4 py-2.5 rounded-lg border border-gray-300 hover:bg-gray-100 transition shadow-sm bg-white"
                  style="color: #1A1A1A;">
            Déconnexion
          </button>
        </div>
      </div>

      <!-- Messages flash -->
      <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 text-green-700 rounded-lg shadow-sm border border-green-200">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="p-4 bg-red-100 text-red-700 rounded-lg shadow-sm border border-red-200">
        {{ $page.props.flash.error }}
      </div>

      <!-- SECTION 1 : Statistiques -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
          <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Chiffre d'affaires</p>
          <p class="text-2xl font-black mt-2" style="color: #1A1A1A;">{{ stats?.chiffre_affaires || '0 FCFA' }}</p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
          <p class="text-xs font-bold uppercase tracking-wider" style="color: #8A8D8F;">Voitures enregistrées</p>
          <p class="text-2xl font-black mt-2" style="color: #1A1A1A;">{{ stats?.nombre_voitures || '0' }}</p>
        </div>
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
          <p class="text-sm" style="color: #8A8D8F;">Accéder au catalogue des pièces détachées et inventaire.</p>
        </Link>

        <!-- Suivi de tous les Devis (Route corrigée) -->
        <Link :href="route('administration.devis.index')" 
              class="bg-white p-6 rounded-xl shadow-md border border-gray-200 hover:border-[#C8102E] transition group block">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-bold group-hover:text-[#C8102E] transition" style="color: #1A1A1A;">Suivi des Devis</h3>
            <span class="w-9 h-9 flex items-center justify-center rounded-lg shrink-0 transition"
                  style="background-color: #F3F4F6; color: #C8102E;">
              <font-awesome-icon :icon="faFileLines" class="text-base" />
            </span>
          </div>
          <p class="text-sm" style="color: #8A8D8F;">Consulter tous les devis du garage.</p>
        </Link>
      </div>

      <!-- SECTION 3 : Liste des Collaborateurs -->
      <div class="space-y-4">
        <div class="flex justify-between items-center">
          <div>
            <h2 class="text-xl font-bold" style="color: #1A1A1A;">Liste des Employés</h2>
            <p class="text-xs mt-0.5" style="color: #8A8D8F;">Gestion des accès et rôles du garage</p>
          </div>
          <Link :href="route('admin.users.create')" 
                class="text-white px-4 py-2.5 rounded-lg text-xs font-semibold shadow transition flex items-center gap-2"
                style="background-color: #C8102E;">
            <span>+ Ajouter un employé</span>
          </Link>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="user in users" :key="user.id" class="bg-white p-6 rounded-xl shadow-md border border-gray-200 flex flex-col justify-between hover:shadow-lg transition">
            <div>
              <div class="flex items-start justify-between gap-2 mb-3">
                <h3 class="font-bold text-base truncate" style="color: #1A1A1A;" :title="user.name">
                  {{ user.name }}
                </h3>
                <span class="px-2.5 py-0.5 inline-flex text-[11px] leading-4 font-semibold rounded-full shrink-0"
                      :style="user.role === 'admin' ? 'background-color: #1A1A1A; color: #FFFFFF;' : 'background-color: #F3F4F6; color: #C8102E; border: 1px solid #C8102E;'">
                  {{ formatRole(user.role) }}
                </span>
              </div>
              
              <div class="space-y-1.5 text-xs text-gray-500 mb-6">
                <p class="flex items-center gap-2 truncate">
                  <span class="font-medium text-gray-700">Email :</span> {{ user.email }}
                </p>
                <p class="flex items-center gap-2">
                  <span class="font-medium text-gray-700">Créé le :</span> {{ new Date(user.created_at).toLocaleDateString() }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 text-xs font-medium">
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

          <div v-if="users.length === 0" class="col-span-full bg-white p-8 rounded-xl shadow-md border border-gray-200 text-center text-sm" style="color: #8A8D8F;">
            Aucun collaborateur enregistré pour le moment.
          </div>
        </div>
      </div>

    </div>
  </div>
</template>