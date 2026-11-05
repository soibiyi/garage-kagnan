<script setup>
import { reactive, ref } from 'vue'
import axios from 'axios'
import { state, toast } from '../store.js'

const profile = reactive({
  prenom: state.user.prenom,
  nom: state.user.nomFamille,
  telephone: state.user.tel,
  email: state.user.email,
  adresse: state.user.adresse,
})
const password = reactive({ current_password: '', password: '', password_confirmation: '' })
const vehicle = reactive({ marque: '', modele: '', immatriculation: '' })
const profileError = ref('')
const passwordError = ref('')
const vehicleError = ref('')

async function saveProfile() {
  profileError.value = ''
  try {
    await axios.patch('/espace-client/api/profil', profile)
    state.user.prenom = profile.prenom
    state.user.nomFamille = profile.nom
    state.user.nom = `${profile.prenom} ${profile.nom}`.trim()
    state.user.tel = profile.telephone
    state.user.email = profile.email
    state.user.adresse = profile.adresse
    toast('Profil mis à jour.')
  } catch (error) {
    profileError.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible de mettre à jour le profil.'
  }
}

async function changePassword() {
  passwordError.value = ''
  try {
    await axios.patch('/espace-client/api/mot-de-passe', password)
    Object.assign(password, { current_password: '', password: '', password_confirmation: '' })
    toast('Mot de passe modifié.')
  } catch (error) {
    passwordError.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible de modifier le mot de passe.'
  }
}

async function addVehicle() {
  vehicleError.value = ''
  try {
    const { data } = await axios.post('/espace-client/api/vehicules', vehicle)
    state.user.vehicules.push(data.vehicle)
    Object.assign(vehicle, { marque: '', modele: '', immatriculation: '' })
    toast('Véhicule ajouté à votre dossier.')
  } catch (error) {
    vehicleError.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible d’ajouter ce véhicule.'
  }
}
</script>

<template>
  <div class="cols">
    <div class="card">
      <h3>Mes informations</h3>
      <form @submit.prevent="saveProfile">
        <div class="row2">
          <label>Prénom<input v-model="profile.prenom" required maxlength="255"></label>
          <label>Nom<input v-model="profile.nom" required maxlength="255"></label>
        </div>
        <label>Téléphone<input v-model="profile.telephone" required maxlength="191"></label>
        <label>Email<input v-model="profile.email" type="email" maxlength="191"></label>
        <label>Adresse<textarea v-model="profile.adresse" rows="3" maxlength="2000"></textarea></label>
        <button class="btn btn-primary">Enregistrer</button>
        <p v-if="profileError" class="err">{{ profileError }}</p>
      </form>

      <h3 style="margin-top:26px">Changer mon mot de passe</h3>
      <form @submit.prevent="changePassword">
        <label>Mot de passe actuel<input v-model="password.current_password" type="password" autocomplete="current-password" required></label>
        <label>Nouveau mot de passe<input v-model="password.password" type="password" autocomplete="new-password" minlength="10" required></label>
        <label>Confirmer le nouveau mot de passe<input v-model="password.password_confirmation" type="password" autocomplete="new-password" minlength="10" required></label>
        <button class="btn btn-light">Modifier</button>
        <p v-if="passwordError" class="err">{{ passwordError }}</p>
      </form>
    </div>

    <div class="card">
      <h3>Mon identifiant client</h3>
      <p class="hint">Conservez cet identifiant pour vos prochaines connexions.</p>
      <p class="client-identifier">{{ state.user.identifier }}</p>
      <p class="hint" style="margin-top:14px">Siège de rattachement : <strong>{{ state.user.siege }}</strong></p>
    </div>

    <div class="card">
      <h3>Mes véhicules</h3>
      <div v-for="vehicle in state.user.vehicules" :key="vehicle.id" class="notif">
        <strong>{{ vehicle.modele }}</strong><br>
        <small>{{ vehicle.immat || 'Immatriculation non renseignée' }}</small>
      </div>
      <p v-if="!state.user.vehicules.length" class="hint">Aucun véhicule n’est associé à votre dossier.</p>
      <h3 style="margin-top:22px">Ajouter un véhicule</h3>
      <form @submit.prevent="addVehicle">
        <label>Marque<input v-model="vehicle.marque" maxlength="100"></label>
        <label>Modèle<input v-model="vehicle.modele" required maxlength="100"></label>
        <label>Immatriculation<input v-model="vehicle.immatriculation" required maxlength="50"></label>
        <button class="btn btn-primary">Ajouter à mon dossier</button>
        <p v-if="vehicleError" class="err">{{ vehicleError }}</p>
      </form>
    </div>
  </div>
</template>
