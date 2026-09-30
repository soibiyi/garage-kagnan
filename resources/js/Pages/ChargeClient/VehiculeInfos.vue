<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VehiculeEntete from '@/Components/VehiculeEntete.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faCar,
    faUser,
    faGasPump,
    faNoteSticky,
    faScrewdriverWrench,
    faCamera,
    faCheck,
    faXmark,
    faClipboardList,
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    vehicule: Object,
});

/* ------------------------------------------------------------------ */
/* Fiche de réception affichée                                          */
/* ------------------------------------------------------------------ */
// Réceptions du véhicule, la plus récente en premier
const receptions = computed(() =>
    [...(props.vehicule.interventions || [])].sort((a, b) => {
        const da = new Date(a.date_reception || 0);
        const db = new Date(b.date_reception || 0);
        return db - da || b.id - a.id;
    })
);

const receptionChoisie = ref(receptions.value[0]?.id ?? null);

const fiche = computed(() => receptions.value.find((r) => r.id === receptionChoisie.value) || null);

/* ------------------------------------------------------------------ */
/* Utilitaires                                                         */
/* ------------------------------------------------------------------ */
const lireDate = (valeur) => {
    if (!valeur) return null;
    const [a, m, j] = String(valeur).slice(0, 10).split('-').map(Number);
    if (!a || !m || !j) return null;
    return new Date(a, m - 1, j);
};

const formatDateLongue = (date) =>
    date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const dateOuVide = (valeur, vide = 'Non renseignée') => {
    const date = lireDate(valeur);
    return date ? formatDateLongue(date) : vide;
};

const dateReception = (valeur) => {
    const date = valeur ? new Date(valeur) : null;
    return date && !isNaN(date) ? formatDateLongue(date) : 'date non renseignée';
};

const heureReception = (valeur) => {
    const date = valeur ? new Date(valeur) : null;
    return date && !isNaN(date)
        ? date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
        : '';
};

const kilometrage = (valeur) =>
    valeur || valeur === 0 ? `${Number(valeur).toLocaleString('fr-FR')} km` : 'Non renseigné';

// Traduction et style des statuts
const getStatutBadge = (statut) => {
    const badges = {
        reception: { text: 'Sur le parc (réception)', classe: 'bg-blue-50 text-blue-700 ring-blue-200' },
        atelier: { text: 'En atelier', classe: 'bg-amber-50 text-amber-700 ring-amber-200' },
        en_cours: { text: 'En réparation', classe: 'bg-purple-50 text-purple-700 ring-purple-200' },
        attente_accord: { text: 'Attente accord devis', classe: 'bg-[#E11D48]/10 text-[#E11D48] ring-[#E11D48]/25' },
        accepte: { text: 'Devis accepté', classe: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    };
    return badges[statut] || { text: statut || 'En cours', classe: 'bg-gray-100 text-gray-700 ring-gray-200' };
};

// URL des images stockées dans Laravel
const imageUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('blob:')) return path;
    if (path.startsWith('/storage/')) return path;
    if (path.startsWith('storage/')) return '/' + path;
    return `/storage/${path.replace(/^\/+/, '')}`;
};

const equipmentsList = [
    { key: 'allume_cigare', label: 'Allume-cigare' },
    { key: 'rk7', label: 'RK7 / Radio' },
    { key: 'rcd', label: 'RCD / Lecteur CD' },
    { key: 'essuie_glace_av', label: 'Essuie-glace AV' },
    { key: 'essuie_glace_ar', label: 'Essuie-glace AR' },
    { key: 'retro_ext_gauche', label: 'Rétro ext. gauche' },
    { key: 'retro_ext_droit', label: 'Rétro ext. droit' },
    { key: 'retro_int', label: 'Rétro intérieur' },
    { key: 'cric', label: 'Cric' },
    { key: 'manivelle', label: 'Manivelle' },
    { key: 'roue_secours', label: 'Roue de secours' },
    { key: 'trousse', label: 'Trousse à pharmacie' },
];

const photos = [
    { champ: 'photo_avant', label: 'Avant' },
    { champ: 'photo_arriere', label: 'Arrière' },
    { champ: 'photo_gauche', label: 'Gauche' },
    { champ: 'photo_droite', label: 'Droite' },
];
</script>

<template>
    <Head title="Infos du véhicule — Garage Kagnan" />

    <AuthenticatedLayout>
        <template #header>
            <VehiculeEntete
                :vehicule="vehicule"
                :href="route('charge_client.vehicules.show', vehicule.id)"
                libelle-retour="Retour au véhicule"
                section="Infos du véhicule"
            />
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

                <!-- BANDEAU : FICHE DE RÉCEPTION -->
                <div
                    v-if="fiche"
                    class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-[#F8FAFC] p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <h3 class="text-base font-black text-[#0B0F19]">
                                Fiche de réception, OT
                                <span class="text-[#E11D48]">{{ fiche.numero_ot || '#' + fiche.id }}</span>
                            </h3>
                            <span
                                class="inline-flex rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                :class="getStatutBadge(fiche.statut).classe"
                            >
                                {{ getStatutBadge(fiche.statut).text }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs font-medium text-[#8A8D8F]">
                            Enregistré le {{ dateReception(fiche.date_reception) }}
                            <template v-if="heureReception(fiche.date_reception)">
                                à {{ heureReception(fiche.date_reception) }}
                            </template>
                        </p>
                    </div>

                    <div v-if="receptions.length > 1" class="sm:w-64">
                        <label for="choix-reception" class="mb-1.5 block text-xs font-semibold text-[#8A8D8F]">
                            Réception affichée
                        </label>
                        <select
                            id="choix-reception"
                            v-model="receptionChoisie"
                            class="min-h-11 w-full rounded-xl border border-gray-200 bg-white px-3.5 text-sm font-semibold text-[#0B0F19] transition duration-200 focus:border-[#E11D48] focus:outline-none focus:ring-4 focus:ring-[#E11D48]/10"
                        >
                            <option v-for="r in receptions" :key="r.id" :value="r.id">
                                OT {{ r.numero_ot || '#' + r.id }}, {{ dateReception(r.date_reception) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                    <!-- COLONNE GAUCHE ET CENTRE : INFOS PRINCIPALES -->
                    <div class="space-y-6 lg:col-span-2">

                        <!-- 1. VÉHICULE CONCERNÉ -->
                        <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h3 class="flex items-center gap-2 border-b border-gray-100 pb-3 text-base font-black text-[#0B0F19]">
                                <FontAwesomeIcon :icon="faCar" class="text-sm text-[#E11D48]" />
                                Véhicule concerné
                            </h3>
                            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="block text-xs text-[#8A8D8F]">Immatriculation</dt>
                                    <dd class="mt-0.5 font-mono text-base font-black uppercase text-[#0B0F19]">
                                        {{ vehicule.immatriculation || 'Non renseignée' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="block text-xs text-[#8A8D8F]">Marque et modèle</dt>
                                    <dd class="mt-0.5 font-bold text-[#0B0F19]">{{ vehicule.marque }} {{ vehicule.modele }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="block text-xs text-[#8A8D8F]">Numéro de châssis (VIN)</dt>
                                    <dd class="mt-0.5 break-all font-mono font-medium text-gray-700">
                                        {{ vehicule.vin || 'Non renseigné' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="block text-xs text-[#8A8D8F]">Kilométrage à l'entrée</dt>
                                    <dd class="mt-0.5 font-black tabular-nums text-[#E11D48]">
                                        {{ fiche ? kilometrage(fiche.kilometrage) : 'Aucune réception' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="block text-xs text-[#8A8D8F]">Expiration assurance</dt>
                                    <dd class="mt-0.5 font-medium text-gray-700">
                                        {{ dateOuVide(vehicule.expiration_assurance, 'N/A') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="block text-xs text-[#8A8D8F]">Expiration SICTA (visite technique)</dt>
                                    <dd class="mt-0.5 font-medium text-gray-700">
                                        {{ dateOuVide(vehicule.expiration_sicta, 'N/A') }}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <!-- 2. CLIENT / PROPRIÉTAIRE -->
                        <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h3 class="flex items-center gap-2 border-b border-gray-100 pb-3 text-base font-black text-[#0B0F19]">
                                <FontAwesomeIcon :icon="faUser" class="text-sm text-[#E11D48]" />
                                Client / propriétaire
                            </h3>
                            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="block text-xs text-[#8A8D8F]">Nom et prénom</dt>
                                    <dd class="mt-0.5 font-bold text-[#0B0F19]">
                                        {{ vehicule.client?.nom }} {{ vehicule.client?.prenom }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="block text-xs text-[#8A8D8F]">Téléphone</dt>
                                    <dd class="mt-0.5 font-bold">
                                        <a
                                            v-if="vehicule.client?.telephone"
                                            :href="`tel:${vehicule.client.telephone}`"
                                            class="text-[#0B0F19] transition hover:text-[#E11D48] focus-visible:outline-none focus-visible:text-[#E11D48]"
                                        >
                                            {{ vehicule.client.telephone }}
                                        </a>
                                        <span v-else class="font-medium text-[#8A8D8F]">Non renseigné</span>
                                    </dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="block text-xs text-[#8A8D8F]">E-mail</dt>
                                    <dd class="mt-0.5 break-all text-gray-700">{{ vehicule.client?.email || 'Aucun e-mail' }}</dd>
                                </div>
                                <div>
                                    <dt class="block text-xs text-[#8A8D8F]">Adresse</dt>
                                    <dd class="mt-0.5 text-gray-700">{{ vehicule.client?.adresse || 'Non renseignée' }}</dd>
                                </div>
                            </dl>
                        </section>

                        <template v-if="fiche">
                            <!-- 3. CARBURANT ET TRAITEMENT -->
                            <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                                <h3 class="flex items-center gap-2 border-b border-gray-100 pb-3 text-base font-black text-[#0B0F19]">
                                    <FontAwesomeIcon :icon="faGasPump" class="text-sm text-[#E11D48]" />
                                    Carburant et traitement
                                </h3>
                                <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt class="block text-xs text-[#8A8D8F]">Niveau de carburant</dt>
                                        <dd class="mt-0.5 font-bold text-[#0B0F19]">{{ fiche.niveau_carburant || 'N/A' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="block text-xs text-[#8A8D8F]">Précision / jauge</dt>
                                        <dd class="mt-0.5 font-medium text-gray-700">{{ fiche.intervalle_niveau_carburant || 'N/A' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="block text-xs text-[#8A8D8F]">Circuit</dt>
                                        <dd class="mt-1">
                                            <span class="inline-block rounded-md bg-gray-100 px-2 py-0.5 text-xs font-bold uppercase text-gray-800">
                                                {{ fiche.circuit || 'N/A' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <dt class="block text-xs text-[#8A8D8F]">Personne à contacter</dt>
                                        <dd class="mt-0.5 font-semibold text-[#0B0F19]">
                                            {{ fiche.personne_a_contacter || 'Non renseignée' }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <!-- 4. REMARQUES ET OBSERVATIONS -->
                            <section
                                v-if="fiche.remarques_eventuelles"
                                class="space-y-3 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
                            >
                                <h3 class="flex items-center gap-2 border-b border-gray-100 pb-3 text-base font-black text-[#0B0F19]">
                                    <FontAwesomeIcon :icon="faNoteSticky" class="text-sm text-[#E11D48]" />
                                    Remarques et observations
                                </h3>
                                <p class="whitespace-pre-line rounded-xl border border-gray-100 bg-[#F8FAFC] p-4 text-sm leading-relaxed text-gray-700">
                                    {{ fiche.remarques_eventuelles }}
                                </p>
                            </section>
                        </template>
                    </div>

                    <!-- COLONNE DROITE : ÉQUIPEMENTS ET PHOTOS -->
                    <div class="space-y-6">

                        <template v-if="fiche">
                            <!-- ÉQUIPEMENTS PRÉSENTS -->
                            <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                                <h3 class="flex items-center gap-2 border-b border-gray-100 pb-3 text-base font-black text-[#0B0F19]">
                                    <FontAwesomeIcon :icon="faScrewdriverWrench" class="text-sm text-[#E11D48]" />
                                    Équipements et accessoires
                                </h3>
                                <ul class="space-y-1">
                                    <li
                                        v-for="eq in equipmentsList"
                                        :key="eq.key"
                                        class="flex items-center justify-between border-b border-gray-50 py-1.5 text-xs last:border-0"
                                    >
                                        <span class="text-gray-700">{{ eq.label }}</span>
                                        <span
                                            class="inline-flex items-center gap-1 rounded px-2 py-0.5 font-bold"
                                            :class="fiche[eq.key] ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400'"
                                        >
                                            <FontAwesomeIcon :icon="fiche[eq.key] ? faCheck : faXmark" class="text-[10px]" />
                                            {{ fiche[eq.key] ? 'Présent' : 'Absent' }}
                                        </span>
                                    </li>
                                </ul>

                                <!-- Pare-brise fissuré (alerte spécifique) -->
                                <div class="flex items-center justify-between border-t border-gray-100 pt-3 text-xs">
                                    <span class="font-semibold text-[#E11D48]">Pare-brise fissuré</span>
                                    <span
                                        class="rounded px-2 py-0.5 font-bold"
                                        :class="fiche.pare_brise_fissure ? 'bg-[#E11D48]/10 text-[#E11D48]' : 'bg-emerald-50 text-emerald-700'"
                                    >
                                        {{ fiche.pare_brise_fissure ? 'OUI (attention)' : 'Non' }}
                                    </span>
                                </div>
                            </section>

                            <!-- PHOTOS D'ÉTAT INITIAL -->
                            <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                                <h3 class="flex items-center gap-2 border-b border-gray-100 pb-3 text-base font-black text-[#0B0F19]">
                                    <FontAwesomeIcon :icon="faCamera" class="text-sm text-[#E11D48]" />
                                    Photos d'état initial
                                </h3>
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div v-for="photo in photos" :key="photo.champ" class="space-y-1">
                                        <span class="block text-[10px] font-bold uppercase text-gray-600">{{ photo.label }}</span>
                                        <a
                                            v-if="fiche[photo.champ]"
                                            :href="imageUrl(fiche[photo.champ])"
                                            target="_blank"
                                            rel="noopener"
                                            class="block aspect-video overflow-hidden rounded-lg border border-gray-200 bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40"
                                        >
                                            <img
                                                :src="imageUrl(fiche[photo.champ])"
                                                :alt="`Photo ${photo.label.toLowerCase()} du véhicule`"
                                                loading="lazy"
                                                class="h-full w-full object-cover transition duration-300 hover:scale-105 motion-reduce:transition-none"
                                            />
                                        </a>
                                        <div
                                            v-else
                                            class="flex aspect-video items-center justify-center rounded-lg border border-dashed border-gray-200 bg-[#F8FAFC] text-[10px] text-gray-400"
                                        >
                                            Non disponible
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </template>

                        <!-- AUCUNE RÉCEPTION -->
                        <section
                            v-else
                            class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] px-6 py-12 text-center"
                        >
                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#8A8D8F] ring-1 ring-gray-200">
                                <FontAwesomeIcon :icon="faClipboardList" />
                            </div>
                            <h4 class="text-sm font-extrabold text-[#0B0F19]">Aucune fiche de réception</h4>
                            <p class="mx-auto mt-1 max-w-xs text-xs font-medium leading-relaxed text-[#8A8D8F]">
                                Le carburant, les équipements et les photos apparaîtront ici dès la première réception de ce véhicule à l'atelier.
                            </p>
                        </section>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>