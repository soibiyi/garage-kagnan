<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({
  missions: { type: Object, required: true },
  chauffeurFiltre: { type: Object, default: null },
})

const typeLabel = type => type === 'depannage' ? 'Dépannage' : 'Convoyage'
const dateLabel = value => value ? new Date(value).toLocaleString('fr-FR') : '—'
const priceLabel = value => value === null || value === undefined
  ? 'Tarif à confirmer'
  : `${Number(value).toLocaleString('fr-FR')} FCFA`
const details = mission => mission.details || {}
</script>

<template>
  <Head title="Historique des missions" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-black text-[#0B0F19]">Historique des missions</h2>
          <p class="text-sm text-gray-500">
            {{ chauffeurFiltre
              ? `Missions terminées par ${chauffeurFiltre.name} — siège ${chauffeurFiltre.siege || '—'}.`
              : 'Missions de dépannage et convoyage terminées, tous sièges confondus.' }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('admin.chauffeurs.history')"
            class="rounded-xl border bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50"
          >
            Historique par chauffeur
          </Link>
          <Link
            v-if="chauffeurFiltre"
            :href="route('admin.missions.history')"
            class="rounded-xl border bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50"
          >
            Tous les chauffeurs
          </Link>
          <span class="self-center rounded-full bg-gray-100 px-3 py-1 text-sm font-bold">
            {{ missions.total }} mission{{ missions.total > 1 ? 's' : '' }}
          </span>
        </div>
      </div>
    </template>

    <div class="mx-auto max-w-7xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
      <article
        v-for="mission in missions.data"
        :key="mission.id"
        class="space-y-4 rounded-2xl border bg-white p-5 shadow-sm"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-black">{{ typeLabel(mission.type) }} #{{ mission.id }}</h3>
              <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                {{ mission.statut === 'vehicule_arrive_au_garage' ? 'Arrivée au garage' : 'Terminée' }}
              </span>
              <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-700">
                Siège {{ mission.siege || '—' }}
              </span>
            </div>
            <p class="mt-2 text-sm text-gray-700">
              <strong>Chauffeur responsable :</strong>
              {{ mission.chauffeur?.name || 'Chauffeur supprimé' }}
              <span v-if="mission.chauffeur?.email" class="text-gray-500"> · {{ mission.chauffeur.email }}</span>
            </p>
          </div>
          <strong>{{ priceLabel(mission.prix_estime) }}</strong>
        </div>

        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <dt class="font-bold text-gray-500">Client</dt>
            <dd>{{ mission.client?.prenom }} {{ mission.client?.nom }}</dd>
            <dd v-if="mission.client?.telephone" class="text-gray-600">{{ mission.client.telephone }}</dd>
          </div>
          <div>
            <dt class="font-bold text-gray-500">Véhicule</dt>
            <dd v-if="mission.vehicule">
              {{ mission.vehicule.marque }} {{ mission.vehicule.modele }}
              <span v-if="mission.vehicule.immatriculation">— {{ mission.vehicule.immatriculation }}</span>
            </dd>
            <dd v-else>Non précisé</dd>
          </div>
          <div>
            <dt class="font-bold text-gray-500">Demande reçue</dt>
            <dd>{{ dateLabel(mission.created_at) }}</dd>
          </div>
          <div>
            <dt class="font-bold text-gray-500">Acceptée par le chauffeur</dt>
            <dd>{{ dateLabel(mission.chauffeur_accepted_at) }}</dd>
          </div>
          <div>
            <dt class="font-bold text-gray-500">Début de mission</dt>
            <dd>{{ dateLabel(mission.mission_started_at) }}</dd>
          </div>
          <div>
            <dt class="font-bold text-gray-500">Mission terminée</dt>
            <dd>{{ dateLabel(mission.mission_completed_at) }}</dd>
          </div>
          <div v-if="mission.distance_parcourue_km !== null && mission.distance_parcourue_km !== undefined">
            <dt class="font-bold text-gray-500">Distance parcourue</dt>
            <dd>{{ Number(mission.distance_parcourue_km).toFixed(1) }} km</dd>
          </div>
          <div v-for="(value, key) in details(mission)" :key="key">
            <dt class="font-bold capitalize text-gray-500">{{ key.replaceAll('_', ' ') }}</dt>
            <dd>{{ value }}</dd>
          </div>
          <div v-if="mission.tarif_reference">
            <dt class="font-bold text-gray-500">Référence du tarif</dt>
            <dd>{{ mission.tarif_reference }}</dd>
          </div>
        </dl>
      </article>

      <div v-if="!missions.data.length" class="rounded-2xl border border-dashed bg-white p-10 text-center">
        <i class="fa-solid fa-clock-rotate-left text-3xl text-gray-400" aria-hidden="true"></i>
        <h3 class="mt-3 font-bold">Aucune mission terminée</h3>
        <p class="mt-1 text-sm text-gray-500">Les missions clôturées par les chauffeurs apparaîtront ici.</p>
      </div>

      <nav
        v-if="missions.last_page > 1"
        class="flex flex-wrap justify-center gap-2"
        aria-label="Pagination de l’historique des missions"
      >
        <template v-for="(link, index) in missions.links" :key="index">
          <Link
            v-if="link.url"
            :href="link.url"
            preserve-scroll
            class="rounded-lg border px-3 py-2 text-sm"
            :class="link.active ? 'border-blue-700 bg-blue-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
            v-html="link.label"
          />
          <span v-else class="rounded-lg border bg-gray-50 px-3 py-2 text-sm text-gray-400" v-html="link.label" />
        </template>
      </nav>
    </div>
  </AuthenticatedLayout>
</template>
