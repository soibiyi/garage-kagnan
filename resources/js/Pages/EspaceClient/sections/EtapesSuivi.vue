<script setup>
import { computed } from 'vue'

const props = defineProps({
  statut: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})

const ETAPES = [
  { label: 'Réception', icon: 'fa-car-side' },
  { label: 'Diagnostic', icon: 'fa-stethoscope' },
  { label: 'Votre accord', icon: 'fa-file-signature' },
  { label: 'Réparation', icon: 'fa-screwdriver-wrench' },
  { label: 'Livraison', icon: 'fa-key' },
]
const INDEX = { reception: 0, atelier: 1, diagnostic: 1, attente_accord: 2, accepte: 3, en_cours: 3, termine: 4, facture: 4 }

const index = computed(() => INDEX[props.statut] ?? -1)
const termine = computed(() => ['termine', 'facture'].includes(props.statut))
const etat = i => (termine.value || i < index.value ? 'done' : i === index.value ? 'now' : 'todo')
const resume = computed(() => (termine.value ? 'Intervention terminée' : `Étape en cours : ${ETAPES[index.value]?.label}`))
</script>

<template>
  <ol v-if="index >= 0" class="steps-track" :class="{ compact }" :aria-label="resume">
    <li v-for="(e, i) in ETAPES" :key="e.label" :class="etat(i)" :aria-current="etat(i) === 'now' ? 'step' : null">
      <span class="dot"><i class="fa-solid" :class="etat(i) === 'done' ? 'fa-check' : e.icon"></i></span>
      <span class="lbl">{{ e.label }}</span>
    </li>
  </ol>
</template>
