<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    vehicules: { type: Object, required: true },
    filtres: { type: Object, required: true },
});

const typesEcheance = [
    { champ: 'expiration_assurance', libelle: 'Assurance' },
    { champ: 'expiration_sicta', libelle: 'Visite technique (SICTA)' },
];
const recherche = ref(props.filtres.search || '');
const dates = reactive({});
const enregistrement = reactive({});

const cle = (vehiculeId, champ) => `${vehiculeId}-${champ}`;

function initialiserDates(vehicules) {
    vehicules.forEach((vehicule) => {
        typesEcheance.forEach(({ champ }) => {
            dates[cle(vehicule.id, champ)] = vehicule[champ] || '';
        });
    });
}

initialiserDates(props.vehicules.data);
watch(() => props.vehicules.data, initialiserDates);

const nombreVehicules = computed(() => props.vehicules.total || 0);
const estEcheanceSelectionnee = (vehicule, champ) =>
    Number(props.filtres.vehicule) === Number(vehicule.id) && props.filtres.type === champ;

function joursAvant(date) {
    if (!date) return null;
    const [annee, mois, jour] = String(date).slice(0, 10).split('-').map(Number);
    if (!annee || !mois || !jour) return null;
    const maintenant = new Date();
    const aujourdHuiUtc = Date.UTC(maintenant.getFullYear(), maintenant.getMonth(), maintenant.getDate());
    return Math.round((Date.UTC(annee, mois - 1, jour) - aujourdHuiUtc) / 86400000);
}

function statutEcheance(date) {
    const jours = joursAvant(date);
    if (jours === null) return { texte: 'Date non renseignée', classe: 'bg-slate-100 text-slate-600' };
    if (jours < 0) return { texte: `Expirée depuis ${Math.abs(jours)} jour${jours < -1 ? 's' : ''}`, classe: 'bg-red-100 text-red-700' };
    if (jours === 0) return { texte: 'Expire aujourd’hui', classe: 'bg-red-100 text-red-700' };
    if (jours <= 5) return { texte: `Expire dans ${jours} jour${jours > 1 ? 's' : ''}`, classe: 'bg-amber-100 text-amber-800' };
    return { texte: 'À jour', classe: 'bg-emerald-100 text-emerald-700' };
}

function rechercher() {
    router.get(route('charge_client.vehicules.echeances.index'), {
        search: recherche.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function mettreAJour(vehicule, champ) {
    const cleDate = cle(vehicule.id, champ);
    enregistrement[cleDate] = true;
    router.patch(route('charge_client.vehicules.echeances.update', vehicule.id), {
        [champ]: dates[cleDate] || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            enregistrement[cleDate] = false;
        },
    });
}

onMounted(() => {
    if (!props.filtres.vehicule || !props.filtres.type) return;
    nextTick(() => {
        document.getElementById(`echeance-${props.filtres.vehicule}-${props.filtres.type}`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
});
</script>

<template>
    <Head title="Échéances des véhicules" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-black text-[#0B0F19]">Échéances des véhicules</h1>
                    <p class="mt-1 text-sm text-gray-500">Assurances et visites techniques à suivre.</p>
                </div>
                <Link
                    v-if="filtres.vehicule"
                    :href="route('charge_client.vehicules.echeances.index')"
                    class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50"
                >
                    Voir tous les véhicules
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="rechercher">
                <input
                    v-model="recherche"
                    type="search"
                    placeholder="Rechercher une immatriculation, un véhicule ou un client"
                    class="min-h-11 flex-1 rounded-xl border-gray-200 text-sm focus:border-[#E11D48] focus:ring-[#E11D48]"
                />
                <button type="submit" class="rounded-xl bg-[#0B0F19] px-5 py-2.5 text-sm font-bold text-white hover:bg-gray-800">
                    Rechercher
                </button>
            </form>

            <div class="text-sm font-semibold text-gray-500">
                {{ nombreVehicules }} véhicule{{ nombreVehicules > 1 ? 's' : '' }}
            </div>

            <div v-if="!vehicules.data.length" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                Aucun véhicule ne correspond à cette recherche.
            </div>

            <article
                v-for="vehicule in vehicules.data"
                :key="vehicule.id"
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-slate-50 px-5 py-4">
                    <div>
                        <p class="font-mono text-sm font-black uppercase text-[#E11D48]">{{ vehicule.immatriculation || 'Immatriculation non renseignée' }}</p>
                        <p class="mt-0.5 text-sm font-bold text-[#0B0F19]">
                            {{ vehicule.marque }} {{ vehicule.modele }}
                            <span class="font-normal text-gray-500">· {{ vehicule.client?.prenom }} {{ vehicule.client?.nom }}</span>
                        </p>
                    </div>
                    <Link
                        :href="route('charge_client.vehicules.show', vehicule.id)"
                        class="text-xs font-bold text-[#E11D48] hover:underline"
                    >
                        Ouvrir la fiche
                    </Link>
                </div>

                <div class="grid gap-4 p-5 md:grid-cols-2">
                    <section
                        v-for="type in typesEcheance"
                        :id="`echeance-${vehicule.id}-${type.champ}`"
                        :key="type.champ"
                        class="rounded-xl border border-gray-200 p-4"
                        :class="estEcheanceSelectionnee(vehicule, type.champ) ? 'ring-2 ring-amber-400' : ''"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-sm font-extrabold text-[#0B0F19]">{{ type.libelle }}</h2>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="statutEcheance(vehicule[type.champ]).classe">
                                {{ statutEcheance(vehicule[type.champ]).texte }}
                            </span>
                        </div>
                        <form class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="mettreAJour(vehicule, type.champ)">
                            <label class="flex-1 text-xs font-semibold text-gray-600">
                                Date d’expiration
                                <input
                                    v-model="dates[cle(vehicule.id, type.champ)]"
                                    type="date"
                                    class="mt-1 min-h-10 w-full rounded-lg border-gray-200 text-sm focus:border-[#E11D48] focus:ring-[#E11D48]"
                                />
                            </label>
                            <button
                                type="submit"
                                :disabled="enregistrement[cle(vehicule.id, type.champ)]"
                                class="min-h-10 rounded-lg bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                            >
                                {{ enregistrement[cle(vehicule.id, type.champ)] ? 'Enregistrement...' : 'Mettre à jour' }}
                            </button>
                        </form>
                    </section>
                </div>
            </article>

            <div v-if="vehicules.last_page > 1" class="flex items-center justify-between">
                <Link
                    v-if="vehicules.prev_page_url"
                    :href="vehicules.prev_page_url"
                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50"
                >
                    Précédent
                </Link>
                <span v-else></span>
                <span class="text-xs font-semibold text-gray-500">Page {{ vehicules.current_page }} sur {{ vehicules.last_page }}</span>
                <Link
                    v-if="vehicules.next_page_url"
                    :href="vehicules.next_page_url"
                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50"
                >
                    Suivant
                </Link>
                <span v-else></span>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
