<script setup>
import { computed, reactive, ref } from 'vue'
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
const profileError = ref('')
const passwordError = ref('')
const busy = reactive({ profile: false, password: false })
const showPwd = ref(false)

const initials = computed(() => state.user.nom.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase())
const messageErreur = (error, defaut) => error.response?.data?.message
  || Object.values(error.response?.data?.errors || {}).flat()[0]
  || defaut

async function saveProfile() {
  profileError.value = ''
  busy.profile = true
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
    profileError.value = messageErreur(error, 'Impossible de mettre à jour le profil.')
  } finally {
    busy.profile = false
  }
}

async function changePassword() {
  passwordError.value = ''
  busy.password = true
  try {
    await axios.patch('/espace-client/api/mot-de-passe', password)
    Object.assign(password, { current_password: '', password: '', password_confirmation: '' })
    toast('Mot de passe modifié.')
  } catch (error) {
    passwordError.value = messageErreur(error, 'Impossible de modifier le mot de passe.')
  } finally {
    busy.password = false
  }
}

async function copyIdentifier() {
  try {
    await navigator.clipboard.writeText(state.user.identifier)
    toast('Identifiant copié.')
  } catch {
    toast('Copie impossible : sélectionnez l’identifiant à la main.')
  }
}
</script>

<template>
  <div class="cols">
    <div class="stack">
      <div class="card">
        <h3><i class="fa-solid fa-address-card"></i> Mes informations</h3>
        <form @submit.prevent="saveProfile">
          <div class="row2">
            <label>Prénom<input v-model="profile.prenom" required maxlength="255" autocomplete="given-name"></label>
            <label>Nom<input v-model="profile.nom" required maxlength="255" autocomplete="family-name"></label>
          </div>
          <label>Téléphone<input v-model="profile.telephone" type="tel" inputmode="tel" required maxlength="191" autocomplete="tel"></label>
          <label>Email<input v-model="profile.email" type="email" maxlength="191" autocomplete="email"></label>
          <label>Adresse<textarea v-model="profile.adresse" rows="3" maxlength="2000"></textarea></label>
          <button class="btn btn-primary" :disabled="busy.profile">
            <i class="fa-solid" :class="busy.profile ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
            {{ busy.profile ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
          <p v-if="profileError" class="err" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ profileError }}</p>
        </form>
      </div>

      <div class="card">
        <h3><i class="fa-solid fa-key"></i> Changer mon mot de passe</h3>
        <form @submit.prevent="changePassword">
          <label class="pwd">Mot de passe actuel
            <input v-model="password.current_password" :type="showPwd ? 'text' : 'password'" autocomplete="current-password" required>
            <button type="button" class="eye" :aria-label="showPwd ? 'Masquer les mots de passe' : 'Afficher les mots de passe'" @click="showPwd = !showPwd">
              <i class="fa-solid" :class="showPwd ? 'fa-eye-slash' : 'fa-eye'"></i>
            </button>
          </label>
          <label>Nouveau mot de passe<input v-model="password.password" :type="showPwd ? 'text' : 'password'" autocomplete="new-password" minlength="10" required></label>
          <label>Confirmer le nouveau mot de passe<input v-model="password.password_confirmation" :type="showPwd ? 'text' : 'password'" autocomplete="new-password" minlength="10" required></label>
          <button class="btn btn-light" :disabled="busy.password">
            <i class="fa-solid" :class="busy.password ? 'fa-spinner fa-spin' : 'fa-lock'"></i>
            {{ busy.password ? 'Modification…' : 'Modifier' }}
          </button>
          <p v-if="passwordError" class="err" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ passwordError }}</p>
        </form>
      </div>
    </div>

    <div class="stack">
      <section class="idcard">
        <div class="idcard-top">
          <span class="avatar">{{ initials }}</span>
          <div>
            <h3>{{ state.user.nom }}</h3>
            <small v-if="state.user.siege"><i class="fa-solid fa-location-dot"></i>Siège : {{ state.user.siege }}</small>
          </div>
        </div>
        <div class="idrow">
          <code>{{ state.user.identifier }}</code>
          <button class="btn btn-ghost" aria-label="Copier l’identifiant client" @click="copyIdentifier"><i class="fa-regular fa-copy"></i> Copier</button>
        </div>
        <p class="idcard-hint">Conservez cet identifiant client pour vos prochaines connexions.</p>
      </section>

    </div>
  </div>
</template>
