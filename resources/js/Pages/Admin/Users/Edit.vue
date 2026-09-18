<script setup>
import { useForm, Link } from '@inertiajs/vue3';

// Récupération de l'utilisateur passé par le contrôleur
const props = defineProps({
    user: Object,
});

// Initialisation du formulaire avec les données actuelles de l'utilisateur
const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '', // Vide par défaut (si vide, on ne le change pas)
    role: props.user.role,
});

const submit = () => {
    form.put(route('admin.users.update', props.user.id), {
        onSuccess: () => form.reset('password'),
    });
};
</script>

<template>
  <div class="max-w-3xl mx-auto mt-12 bg-white p-8 rounded-xl shadow-lg border-t-4" style="border-color: #C8102E;">
    
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold" style="color: #1A1A1A;">Modifier le Collaborateur : {{ user.name }}</h1>
      <Link :href="route('admin.users.index')" class="text-sm font-medium" style="color: #8A8D8F;">← Retour à la liste</Link>
    </div>

    <!-- Erreurs -->
    <div v-if="Object.keys(form.errors).length > 0" class="mb-4 p-4 bg-red-100 border-l-4 rounded" style="border-color: #C8102E; color: #C8102E;">
      <ul>
        <li v-for="(error, key) in form.errors" :key="key">• {{ error }}</li>
      </ul>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Nom -->
      <div>
        <label class="block text-sm font-semibold mb-2" style="color: #1A1A1A;">Nom complet</label>
        <input type="text" v-model="form.name" required 
          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2" style="border-color: #8A8D8F;">
      </div>

      <!-- Email -->
      <div>
        <label class="block text-sm font-semibold mb-2" style="color: #1A1A1A;">Adresse Email</label>
        <input type="email" v-model="form.email" required 
          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2" style="border-color: #8A8D8F;">
      </div>

      <!-- Rôle -->
      <div>
        <label class="block text-sm font-semibold mb-2" style="color: #1A1A1A;">Rôle dans le Garage</label>
        <select v-model="form.role" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 bg-white" style="border-color: #8A8D8F;">
          <option value="receptionniste">Réceptionniste (Accueil, état initial, photos)</option>
          <option value="mecanicien">Mécanicien (Essais, diagnostic, réparations)</option>
          <option value="administratif">Administratif (Devis, facturation)</option>
          <option value="charge_client">Chargé de Suivi Client & Relances</option>
          <option value="admin">Administrateur (Accès total)</option>
        </select>
      </div>

      <!-- Mot de passe -->
      <div>
        <label class="block text-sm font-semibold mb-2" style="color: #1A1A1A;">Nouveau mot de passe <span class="text-xs font-normal text-gray-500">(Laisser vide pour ne pas modifier)</span></label>
        <input type="password" v-model="form.password" 
          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2" style="border-color: #8A8D8F;">
      </div>

      <!-- Bouton de validation -->
      <div class="flex justify-end pt-4">
        <button type="submit" :disabled="form.processing" class="text-white px-6 py-3 rounded-lg font-semibold transition duration-200 shadow-md disabled:opacity-50" style="background-color: #C8102E;">
          Mettre à jour
        </button>
      </div>
    </form>
  </div>
</template>