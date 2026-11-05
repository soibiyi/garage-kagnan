<script setup>
import { reactive, ref } from 'vue'
import { state, save, toast, deleteAccount } from '../store.js'

const u = state.user
const p = reactive({ nom: u.nom, tel: u.tel, email: u.email, adresse: u.adresse, sms: u.prefs.sms, mail: u.prefs.email })
const pwd = ref('')
const v = reactive({ modele: '', immat: '' })

function saveProfil() {
  Object.assign(u, { nom: p.nom, tel: p.tel, email: p.email, adresse: p.adresse })
  u.prefs = { sms: p.sms, email: p.mail }; save(); toast('Profil mis à jour.')
}
function savePwd() { u.password = pwd.value; save(); pwd.value = ''; toast('Mot de passe modifié.') }
function addVeh() { u.vehicules.push({ modele: v.modele, immat: v.immat }); save(); v.modele = ''; v.immat = '' }
function retirer(i) { u.vehicules.splice(i, 1); save() }
function supprimer() { if (confirm('Supprimer définitivement votre compte ?')) deleteAccount() }
</script>

<template>
  <div class="cols">
    <div class="card">
      <h3>Mes informations</h3>
      <form @submit.prevent="saveProfil">
        <label>Nom complet<input v-model="p.nom"></label>
        <div class="row2"><label>Téléphone<input v-model="p.tel"></label><label>Email<input v-model="p.email"></label></div>
        <label>Adresse<input v-model="p.adresse"></label>
        <label class="check"><input v-model="p.sms" type="checkbox"> Recevoir les notifications par SMS</label>
        <label class="check"><input v-model="p.mail" type="checkbox"> Recevoir les notifications par email</label>
        <button class="btn btn-primary">Enregistrer</button>
      </form>
      <h3 style="margin-top:26px">Mot de passe</h3>
      <form @submit.prevent="savePwd">
        <label>Nouveau mot de passe<input v-model="pwd" type="password" minlength="6" required></label>
        <button class="btn btn-light">Modifier</button>
      </form>
    </div>
    <div class="card">
      <h3>Mes véhicules</h3>
      <div v-for="(x, i) in u.vehicules" :key="i" class="notif" style="display:flex;justify-content:space-between">
        <span><b>{{ x.modele }}</b><br><small>{{ x.immat }}</small></span>
        <a v-if="u.vehicules.length > 1" style="color:#d93636" @click="retirer(i)">Retirer</a>
      </div>
      <form @submit.prevent="addVeh">
        <div class="row2"><label>Modèle<input v-model="v.modele" required></label><label>Immatriculation<input v-model="v.immat" required></label></div>
        <button class="btn btn-light">Ajouter un véhicule</button>
      </form>
      <button class="btn full" style="margin-top:20px;background:#fdeaea;color:#d93636" @click="supprimer">Supprimer mon compte</button>
    </div>
  </div>
</template>
