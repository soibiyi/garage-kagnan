<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Entete from '@/Components/SuperAdmin/Entete.vue';
import Pagination from '@/Components/SuperAdmin/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import { dateHeure } from '@/utils/format.js';

const props = defineProps({
    comptes: { type: Array, default: () => [] },
    connexions: { type: Object, required: true },
    filtre: { type: String, default: null },
    userFiltre: { type: Number, default: null },
    horaires: { type: Object, default: () => ({}) },
    resume: { type: Object, default: () => ({}) },
});

const ROLES = {
    superadmin: 'Super admin', admin: 'Administrateur', receptionniste: 'Réceptionniste', mecanicien: 'Mécanicien',
    administratif: 'Administratif', charge_client: 'Chargé client', magasinier: 'Magasinier', depanneur: 'Dépanneur',
    depaneur: 'Dépanneur', chauffeur: 'Chauffeur', secretaire: 'Secrétaire',
};

// ─── Comptes ───
const recherche = ref('');
const statut = ref('tous');
const comptesFiltres = computed(() => {
    const q = recherche.value.trim().toLowerCase();
    return props.comptes.filter((c) => {
        if (statut.value === 'en_ligne' && !c.en_ligne) return false;
        if (statut.value === 'suspendus' && !c.suspendu) return false;
        if (statut.value === 'echecs' && !c.echecs_24h) return false;
        return !q || `${c.name} ${c.email} ${c.role} ${c.siege || ''}`.toLowerCase().includes(q);
    });
});
const protege = (c) => c.superadmin;

const enCours = ref(null);
function deconnecter(c) {
    if (!window.confirm(`Fermer toutes les sessions de ${c.name} ? Il devra se reconnecter.`)) return;
    enCours.value = c.id;
    router.post(route('superadmin.securite.deconnecter', c.id), {}, { preserveScroll: true, onFinish: () => { enCours.value = null; } });
}
function reactiver(c) {
    enCours.value = c.id;
    router.post(route('superadmin.securite.reactiver', c.id), {}, { preserveScroll: true, onFinish: () => { enCours.value = null; } });
}

const cible = ref(null);
const formSuspension = useForm({ motif: '' });
const ouvrirSuspension = (c) => { cible.value = c; formSuspension.reset(); };
function suspendre() {
    formSuspension.post(route('superadmin.securite.suspendre', cible.value.id), {
        preserveScroll: true,
        onSuccess: () => { cible.value = null; },
    });
}

// ─── Historique des connexions ───
const FILTRES = [
    { cle: null, libelle: 'Toutes' },
    { cle: 'reussies', libelle: 'Réussies' },
    { cle: 'echecs', libelle: 'Échecs' },
    { cle: 'hors_horaires', libelle: 'Hors horaires' },
];
const filtrer = (params) => router.get(
    route('superadmin.securite'),
    Object.fromEntries(Object.entries({ filtre: props.filtre, user: props.userFiltre, ...params }).filter(([, v]) => v)),
    { preserveScroll: true, preserveState: true },
);
const nomFiltre = computed(() => props.comptes.find((c) => c.id === props.userFiltre)?.name);
</script>

<template>
    <Head title="Sécurité des comptes" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <Entete titre="Sécurité des comptes" icone="fa-user-lock" :texte="`Heures habituelles : ${horaires.debut} – ${horaires.fin} (modifiables dans Système).`" />

                <!-- ═══ RÉSUMÉ ═══ -->
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 sg-rise" style="--d: 60ms" aria-label="Résumé">
                    <div class="sg-glass sg-glass-strong sg-stat">
                        <span class="sg-tile h-11 w-11 text-emerald-600"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></span>
                        <div><p class="sg-stat-value">{{ resume.connexions_24h }}</p><p class="text-xs text-gray-500">connexion(s) en 24 h</p></div>
                    </div>
                    <button type="button" class="sg-glass sg-glass-strong sg-stat text-left" @click="filtrer({ filtre: 'echecs', page: null })">
                        <span class="sg-tile h-11 w-11 text-red-600"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>
                        <div><p class="sg-stat-value" :class="resume.echecs_24h ? 'text-red-700' : ''">{{ resume.echecs_24h }}</p><p class="text-xs text-gray-500">échec(s) en 24 h</p></div>
                    </button>
                    <button type="button" class="sg-glass sg-glass-strong sg-stat text-left" @click="filtrer({ filtre: 'hors_horaires', page: null })">
                        <span class="sg-tile h-11 w-11 text-violet-600"><i class="fa-solid fa-moon" aria-hidden="true"></i></span>
                        <div><p class="sg-stat-value">{{ resume.hors_horaires_7j }}</p><p class="text-xs text-gray-500">hors horaires (7 j)</p></div>
                    </button>
                    <button type="button" class="sg-glass sg-glass-strong sg-stat text-left" @click="statut = 'suspendus'">
                        <span class="sg-tile h-11 w-11 text-amber-600"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></span>
                        <div><p class="sg-stat-value">{{ resume.suspendus }}</p><p class="text-xs text-gray-500">compte(s) suspendu(s)</p></div>
                    </button>
                </section>

                <!-- ═══ COMPTES ═══ -->
                <section class="sg-glass sg-glass-strong overflow-hidden sg-rise" style="--d: 120ms" aria-labelledby="titre-comptes">
                    <div class="flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                        <h2 id="titre-comptes" class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-users text-[#E11D48]" aria-hidden="true"></i>Comptes ({{ comptesFiltres.length }})</h2>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <label class="sr-only" for="c-q">Rechercher un compte</label>
                            <input id="c-q" v-model="recherche" type="search" class="sg-field sm:w-60" placeholder="Nom, e-mail, rôle…" />
                            <div class="sg-segment overflow-x-auto" role="group" aria-label="Filtrer les comptes">
                                <button v-for="[cle, lib] in [['tous', 'Tous'], ['en_ligne', 'En ligne'], ['suspendus', 'Suspendus'], ['echecs', 'Échecs 24 h']]" :key="cle" type="button" class="sg-pill whitespace-nowrap" :class="{ 'sg-pill-active': statut === cle }" :aria-pressed="statut === cle" @click="statut = cle">{{ lib }}</button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-sm">
                            <thead>
                                <tr class="border-y border-[#0B0F19]/10 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                    <th class="px-4 py-2 font-bold sm:px-5">Collaborateur</th>
                                    <th class="px-3 py-2 font-bold">État</th>
                                    <th class="px-3 py-2 font-bold">Dernière connexion</th>
                                    <th class="px-3 py-2 text-right font-bold">Sessions</th>
                                    <th class="px-4 py-2 text-right font-bold sm:px-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in comptesFiltres" :key="c.id" class="border-b border-[#0B0F19]/5 align-top" :class="{ 'bg-red-50/60': c.suspendu }">
                                    <td class="px-4 py-3 sm:px-5">
                                        <p class="font-bold text-[#0B0F19]">{{ c.name }}</p>
                                        <p class="text-[11px] text-gray-500">{{ ROLES[c.role] || c.role }}<template v-if="c.siege"> · {{ c.siege }}</template> · {{ c.email }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span v-if="c.suspendu" class="sg-chip bg-red-100 text-red-800"><i class="fa-solid fa-user-slash text-[9px]" aria-hidden="true"></i>Suspendu</span>
                                        <span v-else-if="c.en_ligne" class="sg-chip bg-emerald-100 text-emerald-800"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>En ligne</span>
                                        <span v-else class="sg-chip bg-gray-100 text-gray-600">Hors ligne</span>
                                        <p v-if="c.suspendu" class="mt-1 max-w-[220px] text-[11px] text-red-700">
                                            Depuis {{ dateHeure(c.suspendu.depuis) }}<template v-if="c.suspendu.par"> par {{ c.suspendu.par }}</template><template v-if="c.suspendu.motif"> — {{ c.suspendu.motif }}</template>
                                        </p>
                                        <p v-if="c.echecs_24h" class="mt-1 text-[11px] font-bold text-red-700">{{ c.echecs_24h }} échec(s) de connexion en 24 h</p>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-600">
                                        <template v-if="c.derniere_connexion">
                                            <p>{{ dateHeure(c.derniere_connexion.date) }}
                                                <span v-if="c.derniere_connexion.hors_horaires" class="sg-chip ml-1 bg-violet-100 text-violet-800">hors horaires</span>
                                            </p>
                                            <p class="text-[11px] text-gray-400">{{ c.derniere_connexion.appareil }} · IP {{ c.derniere_connexion.ip || '?' }}</p>
                                        </template>
                                        <span v-else class="text-gray-400">Pas encore enregistrée</span>
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums">
                                        <span class="font-bold">{{ c.sessions }}</span>
                                        <p v-if="c.derniere_activite" class="text-[11px] text-gray-400">actif {{ dateHeure(c.derniere_activite) }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right sm:px-5">
                                        <span v-if="protege(c)" class="text-[11px] text-gray-400">Super admin</span>
                                        <div v-else class="inline-flex flex-wrap justify-end gap-1.5">
                                            <button type="button" class="sg-icon-btn" :title="`Historique de ${c.name}`" :aria-label="`Historique des connexions de ${c.name}`" @click="filtrer({ user: c.id, page: null })">
                                                <i class="fa-solid fa-clock-rotate-left text-xs" aria-hidden="true"></i>
                                            </button>
                                            <button type="button" class="sg-btn sg-btn-sm sg-btn-glass" :disabled="!c.sessions || enCours === c.id" @click="deconnecter(c)">
                                                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Déconnecter
                                            </button>
                                            <button v-if="c.suspendu" type="button" class="sg-btn sg-btn-sm sg-btn-green" :disabled="enCours === c.id" @click="reactiver(c)">
                                                <i class="fa-solid fa-user-check" aria-hidden="true"></i>Réactiver
                                            </button>
                                            <button v-else type="button" class="sg-btn sg-btn-sm sg-btn-red" @click="ouvrirSuspension(c)">
                                                <i class="fa-solid fa-user-slash" aria-hidden="true"></i>Suspendre
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!comptesFiltres.length">
                                    <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">Aucun compte ne correspond.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- ═══ HISTORIQUE DES CONNEXIONS ═══ -->
                <section class="sg-glass sg-glass-strong overflow-hidden sg-rise" style="--d: 180ms" aria-labelledby="titre-connexions">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <h2 id="titre-connexions" class="sg-section-title text-[#0B0F19]">
                            <i class="fa-solid fa-list text-[#E11D48]" aria-hidden="true"></i>Historique des connexions
                            <span v-if="nomFiltre" class="sg-chip bg-[#0B0F19] normal-case tracking-normal text-white">
                                {{ nomFiltre }}
                                <button type="button" class="ml-1" aria-label="Retirer le filtre par personne" @click="filtrer({ user: null, page: null })"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                            </span>
                        </h2>
                        <div class="sg-segment overflow-x-auto" role="group" aria-label="Filtrer les connexions">
                            <button v-for="f in FILTRES" :key="f.libelle" type="button" class="sg-pill whitespace-nowrap" :class="{ 'sg-pill-active': filtre === f.cle }" :aria-pressed="filtre === f.cle" @click="filtrer({ filtre: f.cle, page: null })">{{ f.libelle }}</button>
                        </div>
                    </div>

                    <p v-if="!connexions.data.length" class="sg-empty mx-4 mb-5 p-8 text-center text-sm text-gray-500 sm:mx-5">
                        Aucune connexion enregistrée pour ce filtre. L'historique démarre à partir de maintenant.
                    </p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-sm">
                            <thead>
                                <tr class="border-y border-[#0B0F19]/10 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                    <th class="px-4 py-2 font-bold sm:px-5">Date</th>
                                    <th class="px-3 py-2 font-bold">Compte</th>
                                    <th class="px-3 py-2 font-bold">Résultat</th>
                                    <th class="px-3 py-2 font-bold">Appareil</th>
                                    <th class="px-4 py-2 font-bold sm:px-5">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in connexions.data" :key="c.id" class="border-b border-[#0B0F19]/5">
                                    <td class="whitespace-nowrap px-4 py-2.5 text-gray-600 sm:px-5">{{ dateHeure(c.date) }}</td>
                                    <td class="px-3 py-2.5">
                                        <p class="font-bold text-[#0B0F19]">{{ c.nom || '—' }}</p>
                                        <p class="text-[11px] text-gray-500">{{ c.email }}</p>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span v-if="c.reussie" class="sg-chip bg-emerald-100 text-emerald-800"><i class="fa-solid fa-check text-[9px]" aria-hidden="true"></i>Réussie</span>
                                        <span v-else class="sg-chip bg-red-100 text-red-800"><i class="fa-solid fa-xmark text-[9px]" aria-hidden="true"></i>{{ c.motif || 'Échec' }}</span>
                                        <span v-if="c.hors_horaires" class="sg-chip ml-1 bg-violet-100 text-violet-800"><i class="fa-solid fa-moon text-[9px]" aria-hidden="true"></i>hors horaires</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-gray-600">{{ c.appareil }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-500 sm:px-5">{{ c.ip || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 sm:p-5"><Pagination :page="connexions" /></div>
                </section>
            </div>
        </div>

        <!-- Suspension -->
        <Modal :show="!!cible" max-width="md" @close="cible = null">
            <form v-if="cible" class="sg-modal p-6" @submit.prevent="suspendre">
                <h2 class="text-lg font-black text-[#0B0F19]">Suspendre le compte de {{ cible.name }} ?</h2>
                <p class="mt-1 text-sm text-gray-600">Toutes ses sessions sont fermées immédiatement et il ne pourra plus se connecter tant que vous ne réactivez pas le compte. Ses données ne sont pas supprimées.</p>
                <label class="sg-label mt-4" for="motif">Motif (affiché à la personne)</label>
                <input id="motif" v-model="formSuspension.motif" type="text" maxlength="250" class="sg-field" placeholder="Ex. départ de l'entreprise, enquête en cours…" />
                <p v-if="formSuspension.errors.motif" class="mt-1 text-xs text-red-700">{{ formSuspension.errors.motif }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="sg-btn sg-btn-glass" @click="cible = null">Annuler</button>
                    <button type="submit" class="sg-btn sg-btn-red" :disabled="formSuspension.processing">
                        <i class="fa-solid fa-user-slash" aria-hidden="true"></i>Suspendre
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
