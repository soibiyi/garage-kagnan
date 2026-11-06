<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({
  chauffeurs: { type: Array, required: true },
})

const dateLabel = value => value ? new Date(value).toLocaleDateString('fr-FR') : 'Aucune mission terminée'
const amountLabel = value => value === null || value === undefined
  ? 'Tarif non renseigné'
  : `${Number(value).toLocaleString('fr-FR')} FCFA estimés`
</script>

<template>
  <Head title="Historique par chauffeur" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-black text-[#0B0F19]">Historique par chauffeur</h2>
          <p class="text-sm text-gray-500">Vue d’activité et accès aux missions terminées de chaque chauffeur.</p>
        </div>
        <Link
          :href="route('admin.missions.history')"
          class="rounded-xl border bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50"
        >
          Toutes les missions
        </Link>
      </div>
    </template>

    <div class="mx-auto max-w-7xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
      <Link
        v-for="chauffeur in chauffeurs"
        :key="chauffeur.id"
        :href="route('admin.missions.history', { chauffeur_id: chauffeur.id })"
        class="group flex flex-col gap-4 rounded-2xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-[#C8102E] hover:shadow-md sm:flex-row sm:items-center"
      >
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#C8102E]/10 text-[#C8102E]">
          <i class="fa-solid fa-id-card-clip text-lg" aria-hidden="true"></i>
        </span>
        <span class="min-w-0 flex-1">
          <strong class="block text-lg text-[#0B0F19]">{{ chauffeur.name }}</strong>
          <span class="block text-sm text-gray-500">
            {{ chauffeur.email }} · Siège {{ chauffeur.siege || 'non attribué' }}
          </span>
        </span>
        <span class="grid grid-cols-2 gap-4 text-sm sm:min-w-[280px]">
          <span>
            <strong class="block text-lg text-[#0B0F19]">{{ chauffeur.missions_terminees_count }}</strong>
            <span class="text-gray-500">mission{{ chauffeur.missions_terminees_count === 1 ? '' : 's' }} terminée{{ chauffeur.missions_terminees_count === 1 ? '' : 's' }}</span>
          </span>
          <span>
            <strong class="block text-sm text-[#0B0F19]">{{ amountLabel(chauffeur.montant_estime_missions_terminees) }}</strong>
            <span class="text-gray-500">Dernière : {{ dateLabel(chauffeur.derniere_mission_terminee_le) }}</span>
          </span>
        </span>
        <i class="fa-solid fa-arrow-right text-sm text-gray-300 transition group-hover:translate-x-1 group-hover:text-[#C8102E]" aria-hidden="true"></i>
      </Link>

      <div v-if="!chauffeurs.length" class="rounded-2xl border border-dashed bg-white p-10 text-center">
        <i class="fa-solid fa-users-slash text-3xl text-gray-400" aria-hidden="true"></i>
        <h3 class="mt-3 font-bold">Aucun chauffeur enregistré</h3>
        <p class="mt-1 text-sm text-gray-500">Les chauffeurs apparaîtront ici après la création de leur compte.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
