<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';

// Récupération des utilisateurs envoyés par le contrôleur Laravel
defineProps({
    users: Array,
});

// Traduction propre des rôles pour l'affichage
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

// Fonction pour supprimer un utilisateur
const deleteUser = (id) => {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce collaborateur ?')) {
        router.delete(route('admin.users.destroy', id));
    }
};

// Fonction de déconnexion
const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">
      
      <!-- En-tête -->
      <div class="flex justify-between items-center mb-8">
        <div>
          <h1 class="text-3xl font-bold" style="color: #1A1A1A;">Gestion des Collaborateurs</h1>
          <p class="text-sm mt-1" style="color: #8A8D8F;">Liste de tous les accès et rôles du garage</p>
        </div>
        
        <!-- Actions d'en-tête (Déconnexion + Ajout) -->
        <div class="flex items-center gap-4">
          <button @click="logout" 
                  class="text-sm font-semibold px-4 py-2.5 rounded-lg border border-gray-300 hover:bg-gray-100 transition shadow-sm bg-white"
                  style="color: #1A1A1A;">
            Déconnexion
          </button>
          <Link :href="route('admin.users.create')" 
                class="text-white px-5 py-2.5 rounded-lg font-semibold shadow-md transition duration-200 flex items-center gap-2"
                style="background-color: #C8102E;">
            <span>+ Ajouter un employé</span>
          </Link>
        </div>
      </div>

      <!-- Message flash de succès ou d'erreur -->
      <div v-if="$page.props.flash?.success" class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg shadow-sm border border-green-200">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg shadow-sm border border-red-200">
        {{ $page.props.flash.error }}
      </div>

      <!-- Tableau des utilisateurs -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: #1A1A1A;">Nom</th>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: #1A1A1A;">Email</th>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: #1A1A1A;">Rôle</th>
              <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: #1A1A1A;">Date de création</th>
              <th class="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider" style="color: #1A1A1A;">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 transition">
              <td class="px-6 py-4 whitespace-nowrap font-medium" style="color: #1A1A1A;">
                {{ user.name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: #8A8D8F;">
                {{ user.email }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full"
                      :style="user.role === 'admin' ? 'background-color: #1A1A1A; color: #FFFFFF;' : 'background-color: #F3F4F6; color: #C8102E; border: 1px solid #C8102E;'">
                  {{ formatRole(user.role) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: #8A8D8F;">
                {{ new Date(user.created_at).toLocaleDateString() }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex items-center justify-end gap-3">
                  <!-- Bouton Modifier -->
                  <Link :href="route('admin.users.edit', user.id)" class="text-blue-600 hover:text-blue-900 font-semibold transition">
                    Modifier
                  </Link>

                  <!-- Bouton Supprimer (masqué sur son propre compte) -->
                  <button v-if="user.id !== $page.props.auth.user.id" 
                          @click="deleteUser(user.id)" 
                          class="text-red-600 hover:text-red-900 font-semibold transition">
                    Supprimer
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="users.length === 0">
              <td colspan="5" class="px-6 py-8 text-center text-sm" style="color: #8A8D8F;">
                Aucun collaborateur enregistré pour le moment.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</template>