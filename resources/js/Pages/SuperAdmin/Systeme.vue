<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Entete from '@/Components/SuperAdmin/Entete.vue';
import Modal from '@/Components/Modal.vue';
import { dateHeure } from '@/utils/format.js';

const props = defineProps({
    parametres: { type: Object, required: true }, // { groupe: [ {cle, libelle, aide, type, valeur, defaut, modifie} ] }
    maintenance: { type: Object, required: true },
    annonces: { type: Array, default: () => [] },
    rolesAnnonce: { type: Object, default: () => ({}) },
});

const ONGLETS = [
    { cle: 'parametres', libelle: 'Paramètres', icone: 'fa-sliders' },
    { cle: 'maintenance', libelle: 'Maintenance', icone: 'fa-person-digging' },
    { cle: 'annonces', libelle: 'Annonces', icone: 'fa-bullhorn' },
];
// Onglet ouvert directement depuis l'accueil (?onglet=maintenance|annonces)
const ongletDemande = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('onglet') : null;
const onglet = ref(ONGLETS.some((o) => o.cle === ongletDemande) ? ongletDemande : 'parametres');

const ICONES_GROUPES = { Facturation: 'fa-file-invoice-dollar', Stock: 'fa-boxes-stacked', 'Contrôle': 'fa-shield-halved', 'Sécurité': 'fa-user-lock', Rapports: 'fa-file-lines' };

// ─── Paramètres ───
const tousLesParametres = computed(() => Object.values(props.parametres).flat());
const formParametres = useForm(Object.fromEntries(tousLesParametres.value.map((p) => [p.cle, p.valeur])));
const enregistrerParametres = () => formParametres.put(route('superadmin.systeme.parametres'), {
    preserveScroll: true,
    onSuccess: () => formParametres.defaults(), // les valeurs enregistrées deviennent la référence
});
const differentDuDefaut = (p) => String(formParametres[p.cle]) !== String(p.defaut);

// ─── Maintenance ───
const formMaintenance = useForm({ active: props.maintenance.active, message: props.maintenance.message });
function basculerMaintenance() {
    const activer = !props.maintenance.active;
    if (activer && !window.confirm('Activer la maintenance ? Tous les collaborateurs (et le portail client) verront la page de maintenance ; seuls les super administrateurs gardent l’accès.')) return;
    formMaintenance.active = activer;
    formMaintenance.put(route('superadmin.systeme.maintenance'), { preserveScroll: true });
}
const enregistrerMessage = () => {
    formMaintenance.active = props.maintenance.active;
    formMaintenance.put(route('superadmin.systeme.maintenance'), { preserveScroll: true });
};

// ─── Annonces ───
const NIVEAUX = {
    info: { libelle: 'Information', classe: 'bg-sky-100 text-sky-800', icone: 'fa-circle-info' },
    attention: { libelle: 'Attention', classe: 'bg-amber-100 text-amber-800', icone: 'fa-triangle-exclamation' },
    urgent: { libelle: 'Urgent', classe: 'bg-red-100 text-red-800', icone: 'fa-bullhorn' },
};
const vide = () => ({ titre: '', message: '', niveau: 'info', roles: [], debut: '', fin: '', active: true });
const edition = ref(null); // null = fermé, 0 = nouvelle, sinon id
const formAnnonce = useForm(vide());

function ouvrirAnnonce(a = null) {
    formAnnonce.clearErrors();
    Object.assign(formAnnonce, a
        ? { titre: a.titre, message: a.message || '', niveau: a.niveau, roles: [...a.roles], debut: a.debut || '', fin: a.fin || '', active: a.active }
        : vide());
    edition.value = a ? a.id : 0;
}
function enregistrerAnnonce() {
    const options = { preserveScroll: true, onSuccess: () => { edition.value = null; } };
    formAnnonce.transform((d) => ({ ...d, debut: d.debut || null, fin: d.fin || null }));
    if (edition.value) formAnnonce.put(route('superadmin.systeme.annonces.update', edition.value), options);
    else formAnnonce.post(route('superadmin.systeme.annonces.store'), options);
}
function basculerAnnonce(a) {
    router.put(route('superadmin.systeme.annonces.update', a.id), {
        titre: a.titre, message: a.message, niveau: a.niveau, roles: a.roles, debut: a.debut || null, fin: a.fin || null, active: !a.active,
    }, { preserveScroll: true });
}
function supprimerAnnonce(a) {
    if (!window.confirm(`Supprimer l'annonce « ${a.titre} » ?`)) return;
    router.delete(route('superadmin.systeme.annonces.destroy', a.id), { preserveScroll: true });
}
const cibles = (a) => (a.roles.length ? a.roles.map((r) => props.rolesAnnonce[r] || r).join(', ') : 'Tout le monde');
</script>

<template>
    <Head title="Système" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <Entete titre="Outils système" icone="fa-gears" texte="Paramètres globaux, mode maintenance et annonces, sans toucher au code." />

                <div class="overflow-x-auto sg-rise" style="--d: 60ms">
                    <div class="sg-segment" role="tablist">
                        <button
                            v-for="o in ONGLETS"
                            :key="o.cle"
                            type="button"
                            role="tab"
                            class="sg-pill inline-flex items-center gap-1.5 whitespace-nowrap"
                            :class="{ 'sg-pill-active': onglet === o.cle }"
                            :aria-selected="onglet === o.cle"
                            @click="onglet = o.cle"
                        >
                            <i class="fa-solid" :class="o.icone" aria-hidden="true"></i>{{ o.libelle }}
                            <span v-if="o.cle === 'maintenance' && maintenance.active" class="h-2 w-2 rounded-full bg-amber-400" aria-label="active"></span>
                        </button>
                    </div>
                </div>

                <!-- ═══ PARAMÈTRES ═══ -->
                <form v-if="onglet === 'parametres'" class="space-y-4" @submit.prevent="enregistrerParametres">
                    <section v-for="(liste, groupe) in parametres" :key="groupe" class="sg-glass sg-glass-strong p-5 sm:p-6">
                        <h2 class="sg-section-title text-[#0B0F19]"><i class="fa-solid text-[#E11D48]" :class="ICONES_GROUPES[groupe] || 'fa-sliders'" aria-hidden="true"></i>{{ groupe }}</h2>
                        <div class="mt-4 grid gap-5 sm:grid-cols-2">
                            <div v-for="p in liste" :key="p.cle" :class="{ 'sm:col-span-2': p.type === 'booleen' }">
                                <template v-if="p.type === 'booleen'">
                                    <label class="flex cursor-pointer items-center gap-3">
                                        <input v-model="formParametres[p.cle]" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" />
                                        <span class="text-sm font-bold text-[#0B0F19]">{{ p.libelle }}</span>
                                    </label>
                                </template>
                                <template v-else>
                                    <label class="sg-label" :for="`p-${p.cle}`">{{ p.libelle }}</label>
                                    <input
                                        :id="`p-${p.cle}`"
                                        v-model="formParametres[p.cle]"
                                        :type="p.type === 'heure' ? 'time' : 'number'"
                                        :step="p.type === 'nombre' ? 'any' : undefined"
                                        min="0"
                                        class="sg-field"
                                    />
                                    <p v-if="formParametres.errors[p.cle]" class="mt-1 text-xs text-red-700">{{ formParametres.errors[p.cle] }}</p>
                                    <p v-if="p.aide" class="mt-1 text-xs text-gray-500">{{ p.aide }}</p>
                                </template>
                                <p class="mt-1 text-[11px] text-gray-400">
                                    Par défaut : {{ p.type === 'booleen' ? (p.defaut ? 'activé' : 'désactivé') : p.defaut }}
                                    <template v-if="p.modifie"> · modifié {{ dateHeure(p.modifie.le) }}<template v-if="p.modifie.par"> par {{ p.modifie.par }}</template></template>
                                    <button v-if="differentDuDefaut(p)" type="button" class="ml-1 font-bold text-[#E11D48] hover:underline" @click="formParametres[p.cle] = p.defaut">rétablir</button>
                                </p>
                            </div>
                        </div>
                    </section>
                    <div class="sticky bottom-4 z-10 flex justify-end">
                        <button type="submit" class="sg-btn sg-btn-red shadow-xl" :disabled="formParametres.processing || !formParametres.isDirty">
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Enregistrer les paramètres
                        </button>
                    </div>
                </form>

                <!-- ═══ MAINTENANCE ═══ -->
                <section v-if="onglet === 'maintenance'" class="sg-glass sg-glass-strong p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-person-digging text-[#E11D48]" aria-hidden="true"></i>Mode maintenance</h2>
                            <p class="mt-2 max-w-xl text-sm text-gray-600">
                                Pendant la maintenance, tous les collaborateurs et le portail client voient une page d'attente.
                                Vous (super administrateur) continuez à utiliser l'application normalement.
                            </p>
                            <p class="mt-3">
                                <span v-if="maintenance.active" class="sg-chip bg-amber-100 text-amber-900"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500" aria-hidden="true"></span>Active<template v-if="maintenance.depuis"> depuis {{ dateHeure(maintenance.depuis) }}</template></span>
                                <span v-else class="sg-chip bg-emerald-100 text-emerald-800"><i class="fa-solid fa-check text-[9px]" aria-hidden="true"></i>Application ouverte à tous</span>
                            </p>
                        </div>
                        <button type="button" class="sg-btn shrink-0" :class="maintenance.active ? 'sg-btn-green' : 'sg-btn-red'" :disabled="formMaintenance.processing" @click="basculerMaintenance">
                            <i class="fa-solid" :class="maintenance.active ? 'fa-lock-open' : 'fa-lock'" aria-hidden="true"></i>
                            {{ maintenance.active ? 'Désactiver la maintenance' : 'Activer la maintenance' }}
                        </button>
                    </div>
                    <form class="mt-6" @submit.prevent="enregistrerMessage">
                        <label class="sg-label" for="m-message">Message affiché aux utilisateurs</label>
                        <textarea id="m-message" v-model="formMaintenance.message" rows="3" maxlength="500" class="sg-field"></textarea>
                        <p v-if="formMaintenance.errors.message" class="mt-1 text-xs text-red-700">{{ formMaintenance.errors.message }}</p>
                        <div class="mt-3 flex justify-end">
                            <button type="submit" class="sg-btn sg-btn-sm sg-btn-ink" :disabled="formMaintenance.processing || formMaintenance.message === maintenance.message">Enregistrer le message</button>
                        </div>
                    </form>
                </section>

                <!-- ═══ ANNONCES ═══ -->
                <section v-if="onglet === 'annonces'" class="space-y-4">
                    <div class="sg-glass sg-glass-strong flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="sg-section-title text-[#0B0F19]"><i class="fa-solid fa-bullhorn text-[#E11D48]" aria-hidden="true"></i>Annonces</h2>
                            <p class="mt-1 text-sm text-gray-600">Un bandeau en haut de l'application, pour tout le monde ou pour certains rôles.</p>
                        </div>
                        <button type="button" class="sg-btn sg-btn-red" @click="ouvrirAnnonce()"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nouvelle annonce</button>
                    </div>

                    <p v-if="!annonces.length" class="sg-empty p-10 text-center text-sm text-gray-500">Aucune annonce publiée.</p>
                    <ul v-else class="space-y-3">
                        <li v-for="a in annonces" :key="a.id" class="sg-glass sg-glass-strong p-4 sm:p-5" :class="{ 'opacity-60': !a.en_cours }">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2">
                                        <span class="sg-chip" :class="NIVEAUX[a.niveau]?.classe"><i class="fa-solid text-[9px]" :class="NIVEAUX[a.niveau]?.icone" aria-hidden="true"></i>{{ NIVEAUX[a.niveau]?.libelle }}</span>
                                        <span v-if="a.en_cours" class="sg-chip bg-emerald-100 text-emerald-800">Affichée</span>
                                        <span v-else-if="!a.active" class="sg-chip bg-gray-100 text-gray-600">Désactivée</span>
                                        <span v-else class="sg-chip bg-gray-100 text-gray-600">Hors période</span>
                                        <strong class="text-[#0B0F19]">{{ a.titre }}</strong>
                                    </p>
                                    <p v-if="a.message" class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ a.message }}</p>
                                    <p class="mt-2 text-[11px] text-gray-500">
                                        <i class="fa-solid fa-users mr-1" aria-hidden="true"></i>{{ cibles(a) }}
                                        <template v-if="a.debut || a.fin"> · du {{ a.debut ? dateHeure(a.debut) : '—' }} au {{ a.fin ? dateHeure(a.fin) : '—' }}</template>
                                        · publiée {{ dateHeure(a.created_at) }}<template v-if="a.auteur"> par {{ a.auteur }}</template>
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" class="sg-icon-btn" :title="a.active ? 'Désactiver' : 'Activer'" :aria-label="a.active ? 'Désactiver l’annonce' : 'Activer l’annonce'" @click="basculerAnnonce(a)">
                                        <i class="fa-solid" :class="a.active ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="sg-icon-btn" title="Modifier" aria-label="Modifier l’annonce" @click="ouvrirAnnonce(a)"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                                    <button type="button" class="sg-icon-btn sg-icon-btn-danger" title="Supprimer" aria-label="Supprimer l’annonce" @click="supprimerAnnonce(a)"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <!-- Édition d'une annonce -->
        <Modal :show="edition !== null" max-width="2xl" @close="edition = null">
            <form class="sg-modal space-y-4 p-6" @submit.prevent="enregistrerAnnonce">
                <h2 class="text-lg font-black text-[#0B0F19]">{{ edition ? "Modifier l'annonce" : 'Nouvelle annonce' }}</h2>
                <div>
                    <label class="sg-label" for="a-titre">Titre</label>
                    <input id="a-titre" v-model="formAnnonce.titre" type="text" maxlength="150" required class="sg-field" placeholder="Ex. Inventaire général samedi" />
                    <p v-if="formAnnonce.errors.titre" class="mt-1 text-xs text-red-700">{{ formAnnonce.errors.titre }}</p>
                </div>
                <div>
                    <label class="sg-label" for="a-message">Message (facultatif)</label>
                    <textarea id="a-message" v-model="formAnnonce.message" rows="3" maxlength="2000" class="sg-field"></textarea>
                </div>
                <fieldset>
                    <legend class="sg-label">Niveau</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="(n, cle) in NIVEAUX" :key="cle" class="sg-chip cursor-pointer px-3 py-1.5" :class="formAnnonce.niveau === cle ? n.classe + ' ring-2 ring-current' : 'bg-white text-gray-600'">
                            <input v-model="formAnnonce.niveau" type="radio" :value="cle" class="sr-only" />
                            <i class="fa-solid text-[10px]" :class="n.icone" aria-hidden="true"></i>{{ n.libelle }}
                        </label>
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="sg-label">Destinataires <span class="normal-case tracking-normal text-gray-400">(aucun coché = tout le monde)</span></legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label v-for="(libelle, cle) in rolesAnnonce" :key="cle" class="flex cursor-pointer items-center gap-2 text-sm">
                            <input v-model="formAnnonce.roles" type="checkbox" :value="cle" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" />{{ libelle }}
                        </label>
                    </div>
                </fieldset>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="sg-label" for="a-debut">Début (facultatif)</label>
                        <input id="a-debut" v-model="formAnnonce.debut" type="datetime-local" class="sg-field" />
                    </div>
                    <div>
                        <label class="sg-label" for="a-fin">Fin (facultatif)</label>
                        <input id="a-fin" v-model="formAnnonce.fin" type="datetime-local" class="sg-field" />
                        <p v-if="formAnnonce.errors.fin" class="mt-1 text-xs text-red-700">{{ formAnnonce.errors.fin }}</p>
                    </div>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm font-bold">
                    <input v-model="formAnnonce.active" type="checkbox" class="rounded border-gray-300 text-[#E11D48] focus:ring-[#E11D48]" />Active
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="sg-btn sg-btn-glass" @click="edition = null">Annuler</button>
                    <button type="submit" class="sg-btn sg-btn-red" :disabled="formAnnonce.processing"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>{{ edition ? 'Enregistrer' : 'Publier' }}</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
