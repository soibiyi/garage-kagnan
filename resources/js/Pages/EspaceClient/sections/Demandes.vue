<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { state, loadSession, toast } from '../store.js'

const props = defineProps({
  type: {
    type: String,
    required: true,
  },
})

const config = {
  rendez_vous: { title: 'Demander un rendez-vous', submit: 'Envoyer ma demande' },
  depannage: { title: 'Demander un dépannage', submit: 'Envoyer ma demande' },
  convoyage: { title: 'Demander un convoyage', submit: 'Envoyer ma demande' },
}
const activeConfig = computed(() => config[props.type])
const today = new Date().toISOString().slice(0, 10)
const form = reactive({
  vehicule_id: '',
  prestation: 'Révision / vidange',
  date_souhaitee: '',
  lieu: '',
  lieu_depart: '',
  lieu_arrivee: '',
  precision: '',
})
const busy = ref(false)
const error = ref('')
const services = ['Révision / vidange', 'Freinage', 'Diagnostic électronique', 'Climatisation', 'Carrosserie', 'Autre']
let refreshTimer

const demandes = computed(() => state.user.demandes.filter(demande => demande.type === props.type))

const titresDetails = {
  prestation: 'Prestation',
  date_souhaitee: 'Date souhaitée',
  lieu: 'Lieu du véhicule',
  lieu_depart: 'Départ',
  lieu_arrivee: 'Arrivée',
  precision: 'Précisions',
}
const statutLisible = statut => ({
  en_attente: 'En attente de confirmation',
  accepte: 'Acceptée par le garage',
  refuse: 'Refusée',
})[statut] || statut.replaceAll('_', ' ')
const dateLisible = date => date ? new Date(date).toLocaleDateString('fr-FR') : '—'

async function submit() {
  busy.value = true
  error.value = ''

  const payload = {
    type: props.type,
    vehicule_id: form.vehicule_id || null,
  }
  for (const key of ['prestation', 'date_souhaitee', 'lieu', 'lieu_depart', 'lieu_arrivee', 'precision']) {
    if (form[key]) payload[key] = form[key]
  }

  try {
    const { data } = await axios.post('/espace-client/api/demandes', payload)
    await loadSession()
    toast(data.message)
    Object.assign(form, {
      date_souhaitee: '',
      lieu: '',
      lieu_depart: '',
      lieu_arrivee: '',
      precision: '',
    })
  } catch (requestError) {
    error.value = requestError.response?.data?.message
      || Object.values(requestError.response?.data?.errors || {}).flat()[0]
      || 'Impossible de transmettre votre demande. Réessayez.'
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  refreshTimer = window.setInterval(() => {
    loadSession().catch(() => {
      error.value = 'Impossible d’actualiser le statut de vos demandes.'
    })
  }, 10000)
})

onBeforeUnmount(() => window.clearInterval(refreshTimer))
</script>

<template>
  <div class="cols">
    <div class="card">
      <h3>{{ activeConfig.title }}</h3>
      <p class="hint" style="margin-bottom:14px">
        Votre demande sera transmise au garage et restera en attente jusqu’à confirmation par l’équipe.
      </p>
      <form @submit.prevent="submit">
        <label v-if="state.user.vehicules.length">Véhicule concerné
          <select v-model="form.vehicule_id">
            <option value="">Aucun véhicule précisé</option>
            <option v-for="vehicule in state.user.vehicules" :key="vehicule.id" :value="vehicule.id">
              {{ vehicule.modele }} — {{ vehicule.immat }}
            </option>
          </select>
        </label>

        <template v-if="type === 'rendez_vous'">
          <label>Prestation souhaitée
            <select v-model="form.prestation">
              <option v-for="service in services" :key="service">{{ service }}</option>
            </select>
          </label>
          <label>Date souhaitée<input v-model="form.date_souhaitee" type="date" :min="today" required></label>
          <label>Précisions<textarea v-model="form.precision" rows="3" maxlength="2000"></textarea></label>
        </template>
        <template v-else-if="type === 'depannage'">
          <label>Lieu du véhicule<input v-model="form.lieu" maxlength="500" required></label>
          <label>Description du besoin<textarea v-model="form.precision" rows="3" maxlength="2000"></textarea></label>
        </template>
        <template v-else>
          <label>Lieu de départ<input v-model="form.lieu_depart" maxlength="500" required></label>
          <label>Lieu d’arrivée<input v-model="form.lieu_arrivee" maxlength="500" required></label>
          <label>Précisions<textarea v-model="form.precision" rows="3" maxlength="2000"></textarea></label>
        </template>

        <button class="btn btn-primary" :disabled="busy">
          {{ busy ? 'Envoi…' : activeConfig.submit }}
        </button>
        <p v-if="error" class="err" role="alert">{{ error }}</p>
      </form>
    </div>

    <div class="card">
      <h3>Mes demandes</h3>
      <article v-for="demande in demandes" :key="demande.id" class="notif">
        <div class="row-line">
          <strong>{{ statutLisible(demande.statut) }}</strong>
          <small class="hint">{{ dateLisible(demande.created_at) }}</small>
        </div>
        <p v-if="demande.vehicule" class="hint">
          {{ demande.vehicule.marque }} {{ demande.vehicule.modele }} — {{ demande.vehicule.immatriculation }}
        </p>
        <p v-for="(value, key) in demande.details" :key="key" class="hint">
          <strong>{{ titresDetails[key] || key }} :</strong> {{ value }}
        </p>
      </article>
      <p v-if="!demandes.length" class="hint">Aucune demande envoyée dans cette section.</p>
    </div>
  </div>
</template>
