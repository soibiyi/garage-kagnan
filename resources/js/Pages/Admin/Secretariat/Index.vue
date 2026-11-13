<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import SecretariatChat from '@/Components/SecretariatChat.vue';
import { PRIORITES, STATUTS, dateHeure, etatDelai, progression, versInputDateHeure } from '@/constants/secretariat';

const props = defineProps({
    secretaires: { type: Array, default: () => [] },
    selection: { type: Object, default: null },
    taches: { type: Array, default: () => [] },
    filtre: { type: String, default: 'actives' },
});

const FILTRES = [
    { cle: 'actives', label: 'En cours' },
    { cle: 'terminees', label: 'Terminées' },
    { cle: 'toutes', label: 'Toutes' },
];

const chat = ref(null);
const maintenant = ref(Date.now());
let horloge;
onMounted(() => { horloge = window.setInterval(() => { maintenant.value = Date.now(); }, 30000); });
onBeforeUnmount(() => window.clearInterval(horloge));

const naviguer = (params) =>
    router.get(route('admin.secretariat.index'), {
        secretaire: props.selection?.id,
        filtre: props.filtre,
        ...params,
    }, { preserveScroll: true, preserveState: false });

// Le chat a lu les messages : on remet le compteur de la secrétaire à zéro localement
const lues = ref(new Set());
const secretairesAffichees = computed(() =>
    props.secretaires.map((s) => (lues.value.has(s.id) ? { ...s, messages_non_lus: 0 } : s))
);
const marquerLu = () => {
    if (props.selection) lues.value.add(props.selection.id);
};

// ─── Formulaire de tâche (création / modification) ─────────────────
const modal = ref(false);
const tacheEditee = ref(null);

const form = useForm({
    secretaire_id: null,
    titre: '',
    instructions: '',
    echeance: '',
    priorite: 'normale',
    etapes: [],
});

const delaiDans = (heures) => versInputDateHeure(Date.now() + heures * 3600 * 1000);
const delaiA = (jours, heure) => {
    const d = new Date();
    d.setDate(d.getDate() + jours);
    d.setHours(heure, 0, 0, 0);
    return versInputDateHeure(d);
};
const RACCOURCIS = [
    { label: 'Dans 2 h', valeur: () => delaiDans(2) },
    { label: 'Ce soir 18 h', valeur: () => delaiA(0, 18) },
    { label: 'Demain 12 h', valeur: () => delaiA(1, 12) },
    { label: 'Dans 3 jours', valeur: () => delaiA(3, 17) },
    { label: 'Dans 1 semaine', valeur: () => delaiA(7, 17) },
];

function nouvelleTache() {
    tacheEditee.value = null;
    form.reset();
    form.clearErrors();
    form.secretaire_id = props.selection?.id ?? null;
    form.echeance = delaiA(1, 12);
    form.etapes = [];
    modal.value = true;
}

function modifierTache(tache) {
    tacheEditee.value = tache;
    form.clearErrors();
    form.secretaire_id = tache.secretaire_id;
    form.titre = tache.titre;
    form.instructions = tache.instructions || '';
    form.echeance = versInputDateHeure(tache.echeance);
    form.priorite = tache.priorite;
    form.etapes = tache.items.map((i) => ({ id: i.id, libelle: i.libelle, fait: i.fait }));
    modal.value = true;
}

const ajouterEtape = () => form.etapes.push({ id: null, libelle: '' });
const retirerEtape = (index) => form.etapes.splice(index, 1);

// Coller plusieurs lignes dans une étape = une étape par ligne
function collerEtapes(event, index) {
    const lignes = (event.clipboardData?.getData('text') || '').split(/\r?\n/).map((l) => l.replace(/^[-*•\d.)\s]+/, '').trim()).filter(Boolean);
    if (lignes.length < 2) return;
    event.preventDefault();
    form.etapes[index].libelle = lignes[0];
    form.etapes.splice(index + 1, 0, ...lignes.slice(1).map((libelle) => ({ id: null, libelle })));
}

function enregistrer() {
    const options = {
        preserveScroll: true,
        onSuccess: () => { modal.value = false; },
    };
    form.transform((data) => ({
        ...data,
        // Envoi en ISO (UTC) pour éviter toute ambiguïté de fuseau horaire
        echeance: data.echeance ? new Date(data.echeance).toISOString() : '',
        etapes: data.etapes.filter((e) => e.libelle.trim()).map(({ id, libelle }) => ({ id, libelle: libelle.trim() })),
    }));
    if (tacheEditee.value) form.put(route('admin.secretariat.taches.update', tacheEditee.value.id), options);
    else form.post(route('admin.secretariat.taches.store'), options);
}

const erreursEtapes = computed(() =>
    Object.entries(form.errors).filter(([k]) => k.startsWith('etapes')).map(([, v]) => v)[0]
);

// ─── Suppression ────────────────────────────────────────────────────
const aSupprimer = ref(null);
const supprimer = () => {
    router.delete(route('admin.secretariat.taches.destroy', aSupprimer.value.id), {
        preserveScroll: true,
        onFinish: () => { aSupprimer.value = null; },
    });
};

const discuter = (tache) => {
    chat.value?.citerTache(tache.id);
    document.getElementById('messagerie')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const tachesActives = computed(() => props.taches.filter((t) => t.statut !== 'terminee'));

const bordure = (tache) => ({
    retard: 'border-l-red-500',
    urgent: 'border-l-amber-500',
    proche: 'border-l-amber-300',
    ok: 'border-l-gray-200',
    termine: 'border-l-emerald-500',
})[etatDelai(tache, maintenant.value).niveau];
</script>

<template>
    <Head title="Secrétariat" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <!-- ═══ EN-TÊTE ═══ -->
                <header class="sg-glass sg-glass-dark sg-rise overflow-hidden p-5 sm:p-7" style="--d: 0ms">
                    <span class="absolute left-0 top-0 h-1 w-20 rounded-br-full bg-[#E11D48]"></span>
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="sg-tile sg-tile-red h-12 w-12 text-lg">
                                <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-widest text-white/50">Administration</p>
                                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Secrétariat</h1>
                                <p class="mt-0.5 text-sm text-white/60">Confiez des tâches avec un délai, suivez leur avancement, échangez en direct.</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="route('admin.users.index')" class="sg-btn bg-white/10 text-white ring-1 ring-white/15 hover:bg-white/15">
                                <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>Tableau de bord
                            </Link>
                            <button v-if="secretaires.length" type="button" class="sg-btn sg-btn-red" @click="nouvelleTache">
                                <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>Nouvelle tâche
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Aucune secrétaire -->
                <div v-if="!secretaires.length" class="sg-empty sg-rise px-6 py-14 text-center" style="--d: 80ms">
                    <span class="sg-tile mx-auto h-14 w-14 text-2xl text-gray-400">
                        <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                    </span>
                    <p class="mt-4 text-lg font-black text-[#0B0F19]">Aucune secrétaire enregistrée</p>
                    <p class="mt-1 text-sm text-gray-500">Créez un collaborateur avec le rôle « Secrétaire » pour lui confier des tâches.</p>
                    <Link :href="route('admin.users.create')" class="sg-btn sg-btn-ink mt-5">
                        <i class="fa-solid fa-user-plus text-xs" aria-hidden="true"></i>Ajouter un collaborateur
                    </Link>
                </div>

                <div v-else class="grid gap-6 lg:grid-cols-12">
                    <!-- ═══ SECRÉTAIRES ═══ -->
                    <aside class="sg-rise lg:col-span-3" style="--d: 80ms">
                        <div class="sg-glass p-2 lg:p-3">
                            <p class="sg-section-title px-2 pb-2 pt-1 text-gray-500">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>Secrétaires
                                <span class="sg-chip bg-white/80 text-gray-500">{{ secretaires.length }}</span>
                            </p>
                            <ul class="sg-scroll flex gap-1.5 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0">
                                <li v-for="s in secretairesAffichees" :key="s.id" class="shrink-0">
                                    <button
                                        type="button"
                                        class="sg-person w-60 lg:w-full"
                                        :class="{ 'sg-person-active': selection?.id === s.id }"
                                        :aria-current="selection?.id === s.id ? 'true' : undefined"
                                        @click="naviguer({ secretaire: s.id })"
                                    >
                                        <span class="sg-avatar">
                                            {{ s.name.split(' ').slice(0, 2).map((p) => p[0]).join('').toUpperCase() }}
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-bold text-[#0B0F19]">{{ s.name }}</span>
                                            <span class="mt-0.5 flex flex-wrap items-center gap-1 text-[11px]">
                                                <span class="text-gray-500">{{ s.taches_ouvertes }} en cours</span>
                                                <span v-if="s.taches_en_retard" class="sg-chip bg-red-100 !py-0 text-red-700">{{ s.taches_en_retard }} en retard</span>
                                            </span>
                                        </span>
                                        <span
                                            v-if="s.messages_non_lus"
                                            class="grid h-5 min-w-5 place-items-center rounded-full bg-[#E11D48] px-1.5 text-[10px] font-bold text-white shadow-[0_4px_10px_-4px_rgba(225,29,72,0.8)]"
                                            :title="`${s.messages_non_lus} message(s) non lu(s)`"
                                        >{{ s.messages_non_lus }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </aside>

                    <!-- ═══ TÂCHES ═══ -->
                    <section class="sg-rise space-y-4 lg:col-span-5" style="--d: 140ms">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-widest text-gray-500">Tâches de</p>
                                <h2 class="truncate text-xl font-black tracking-tight text-[#0B0F19]">{{ selection?.name }}</h2>
                            </div>
                            <div class="sg-segment" role="group" aria-label="Filtrer les tâches">
                                <button
                                    v-for="f in FILTRES"
                                    :key="f.cle"
                                    type="button"
                                    class="sg-pill"
                                    :class="{ 'sg-pill-active': filtre === f.cle }"
                                    :aria-pressed="filtre === f.cle"
                                    @click="naviguer({ filtre: f.cle })"
                                >{{ f.label }}</button>
                            </div>
                        </div>

                        <div v-if="!taches.length" class="sg-empty px-6 py-12 text-center">
                            <span class="sg-tile mx-auto h-12 w-12 text-xl text-gray-400">
                                <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                            </span>
                            <p class="mt-3 text-sm font-bold text-gray-600">Aucune tâche dans cette catégorie.</p>
                            <button type="button" class="sg-btn sg-btn-sm sg-btn-red mt-4" @click="nouvelleTache">
                                <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i>Confier une tâche
                            </button>
                        </div>

                        <article
                            v-for="tache in taches"
                            :key="tache.id"
                            class="sg-glass sg-task p-4 pl-5 sm:p-5 sm:pl-6"
                            :class="`sg-accent-${etatDelai(tache, maintenant).niveau}`"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <h3 class="mr-1 text-[15px] font-black leading-snug text-[#0B0F19]">{{ tache.titre }}</h3>
                                        <span class="sg-chip" :class="PRIORITES[tache.priorite]?.classe">
                                            <i v-if="tache.priorite === 'haute'" class="fa-solid fa-bolt text-[9px]" aria-hidden="true"></i>
                                            {{ PRIORITES[tache.priorite]?.label }}
                                        </span>
                                        <span class="sg-chip" :class="STATUTS[tache.statut]?.classe">{{ STATUTS[tache.statut]?.label }}</span>
                                    </div>
                                    <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                        <span class="inline-flex items-center gap-1.5 text-gray-500">
                                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ dateHeure(tache.echeance) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5 font-bold" :class="etatDelai(tache, maintenant).classe">
                                            <i class="fa-regular fa-hourglass-half" aria-hidden="true"></i>{{ etatDelai(tache, maintenant).texte }}
                                        </span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-0.5 rounded-full bg-white/50 p-0.5 ring-1 ring-[#0B0F19]/5">
                                    <button type="button" class="sg-icon-btn" :aria-label="`Discuter de ${tache.titre}`" title="Discuter" @click="discuter(tache)">
                                        <i class="fa-solid fa-comment-dots text-sm" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="sg-icon-btn" :aria-label="`Modifier ${tache.titre}`" title="Modifier" @click="modifierTache(tache)">
                                        <i class="fa-solid fa-pen text-xs" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="sg-icon-btn sg-icon-btn-danger" :aria-label="`Supprimer ${tache.titre}`" title="Supprimer" @click="aSupprimer = tache">
                                        <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <p v-if="tache.instructions" class="mt-3 line-clamp-3 whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ tache.instructions }}</p>

                            <div v-if="tache.items.length" class="sg-surface mt-4 p-3">
                                <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <span><i class="fa-solid fa-list-check mr-1.5" aria-hidden="true"></i>Checklist</span>
                                    <span class="tabular-nums text-[#0B0F19]">{{ progression(tache).faits }}/{{ progression(tache).total }}</span>
                                </div>
                                <div class="sg-progress mt-2">
                                    <div :style="{ width: `${progression(tache).pourcent}%` }"></div>
                                </div>
                                <ul class="mt-3 space-y-1.5">
                                    <li v-for="item in tache.items" :key="item.id" class="flex items-center gap-2.5 text-sm">
                                        <span
                                            class="grid h-[18px] w-[18px] shrink-0 place-items-center rounded-md text-[10px]"
                                            :class="item.fait ? 'bg-emerald-500 text-white shadow-[0_2px_6px_-2px_rgba(16,185,129,0.7)]' : 'bg-white ring-1 ring-[#0B0F19]/15'"
                                            aria-hidden="true"
                                        >
                                            <i v-if="item.fait" class="fa-solid fa-check"></i>
                                        </span>
                                        <span :class="item.fait ? 'text-gray-400 line-through' : 'text-gray-700'">{{ item.libelle }}</span>
                                        <span v-if="item.ajoute_par_secretaire" class="sg-chip bg-gray-100 text-[10px] text-gray-500">ajoutée par la secrétaire</span>
                                        <span class="sr-only">{{ item.fait ? 'fait' : 'à faire' }}</span>
                                    </li>
                                </ul>
                            </div>
                            <p class="mt-3 flex items-center gap-1.5 text-[11px] text-gray-400">
                                <i class="fa-regular fa-user" aria-hidden="true"></i>
                                Confiée par {{ tache.admin?.name || 'un administrateur' }} · {{ dateHeure(tache.created_at) }}
                            </p>
                        </article>
                    </section>

                    <!-- ═══ MESSAGERIE ═══ -->
                    <div id="messagerie" class="sg-rise scroll-mt-4 lg:sticky lg:top-4 lg:col-span-4 lg:self-start" style="--d: 200ms">
                        <SecretariatChat
                            v-if="selection"
                            ref="chat"
                            :secretaire-id="selection.id"
                            :taches="tachesActives"
                            :titre="`Messagerie · ${selection.name}`"
                            :sous-titre="selection.siege ? `Siège ${selection.siege}` : ''"
                            @lu="marquerLu"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ MODALE : NOUVELLE / MODIFIER TÂCHE ═══ -->
        <Modal :show="modal" max-width="2xl" @close="modal = false">
            <form class="sg-modal space-y-5 p-6 sm:p-7" @submit.prevent="enregistrer">
                <div class="flex items-center gap-3">
                    <span class="sg-tile sg-tile-red h-10 w-10">
                        <i class="fa-solid" :class="tacheEditee ? 'fa-pen' : 'fa-plus'" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-black tracking-tight text-[#0B0F19]">{{ tacheEditee ? 'Modifier la tâche' : 'Nouvelle tâche' }}</h3>
                        <p class="text-xs text-gray-500">La secrétaire est notifiée immédiatement.</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="t-secretaire" class="sg-label">Secrétaire</label>
                        <select id="t-secretaire" v-model="form.secretaire_id" required class="sg-field">
                            <option v-for="s in secretaires" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="form.errors.secretaire_id" class="mt-1 text-xs text-red-600">{{ form.errors.secretaire_id }}</p>
                    </div>
                    <div>
                        <label for="t-priorite" class="sg-label">Priorité</label>
                        <select id="t-priorite" v-model="form.priorite" class="sg-field">
                            <option v-for="(p, cle) in PRIORITES" :key="cle" :value="cle">{{ p.label }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="t-titre" class="sg-label">Tâche</label>
                    <input id="t-titre" v-model="form.titre" type="text" required maxlength="255" placeholder="Ex. : Relancer les factures impayées d'octobre" class="sg-field" />
                    <p v-if="form.errors.titre" class="mt-1 text-xs text-red-600">{{ form.errors.titre }}</p>
                </div>

                <div>
                    <label for="t-instructions" class="sg-label">Instructions</label>
                    <textarea id="t-instructions" v-model="form.instructions" rows="3" maxlength="5000" placeholder="Détails, contexte, personnes à contacter…" class="sg-field"></textarea>
                    <p v-if="form.errors.instructions" class="mt-1 text-xs text-red-600">{{ form.errors.instructions }}</p>
                </div>

                <div>
                    <label for="t-echeance" class="sg-label">Délai · à rendre avant le</label>
                    <input id="t-echeance" v-model="form.echeance" type="datetime-local" required class="sg-field sm:w-auto" />
                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                        <button
                            v-for="r in RACCOURCIS"
                            :key="r.label"
                            type="button"
                            class="sg-btn sg-btn-sm sg-btn-glass !min-h-[30px] !text-[11px]"
                            @click="form.echeance = r.valeur()"
                        >{{ r.label }}</button>
                    </div>
                    <p v-if="form.errors.echeance" class="mt-1 text-xs text-red-600">{{ form.errors.echeance }}</p>
                </div>

                <div class="sg-surface p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="sg-label !mb-0">Étapes de la checklist <span class="font-medium normal-case tracking-normal text-gray-400">(facultatif)</span></span>
                        <button type="button" class="sg-btn sg-btn-sm sg-btn-glass text-[#E11D48]" @click="ajouterEtape">
                            <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i>Étape
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400">Sans étape, la secrétaire coche la tâche entière. Astuce : collez une liste pour créer une étape par ligne.</p>
                    <ul class="mt-3 space-y-2">
                        <li v-for="(etape, index) in form.etapes" :key="etape.id ?? `n-${index}`" class="flex items-center gap-2">
                            <span
                                class="grid h-[18px] w-[18px] shrink-0 place-items-center rounded-md text-[10px]"
                                :class="etape.fait ? 'bg-emerald-500 text-white' : 'bg-white ring-1 ring-[#0B0F19]/15'"
                                aria-hidden="true"
                            ><i v-if="etape.fait" class="fa-solid fa-check"></i></span>
                            <label :for="`etape-${index}`" class="sr-only">Étape {{ index + 1 }}</label>
                            <input
                                :id="`etape-${index}`"
                                v-model="etape.libelle"
                                type="text"
                                maxlength="255"
                                class="sg-field min-w-0 flex-1 py-1.5"
                                :placeholder="`Étape ${index + 1}`"
                                @paste="collerEtapes($event, index)"
                                @keydown.enter.prevent="ajouterEtape"
                            />
                            <button type="button" class="sg-icon-btn sg-icon-btn-danger" :aria-label="`Retirer l'étape ${index + 1}`" @click="retirerEtape(index)">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </li>
                    </ul>
                    <p v-if="erreursEtapes" class="mt-1 text-xs text-red-600">{{ erreursEtapes }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t border-[#0B0F19]/5 pt-5">
                    <button type="button" class="sg-btn sg-btn-glass" @click="modal = false">Annuler</button>
                    <button type="submit" :disabled="form.processing" class="sg-btn sg-btn-red">
                        <i class="fa-solid" :class="form.processing ? 'fa-circle-notch fa-spin' : 'fa-paper-plane'" aria-hidden="true"></i>
                        {{ tacheEditee ? 'Enregistrer' : 'Attribuer la tâche' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ═══ MODALE : SUPPRESSION ═══ -->
        <Modal :show="!!aSupprimer" max-width="md" @close="aSupprimer = null">
            <div class="sg-modal p-6 sm:p-7">
                <span class="grid h-11 w-11 place-items-center rounded-full bg-red-50 text-red-600 ring-1 ring-red-100">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </span>
                <h3 class="mt-4 text-lg font-black tracking-tight text-[#0B0F19]">Supprimer la tâche ?</h3>
                <p class="mt-2 text-sm text-gray-600">« {{ aSupprimer?.titre }} » et sa checklist seront supprimées. Les messages liés sont conservés.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="sg-btn sg-btn-glass" @click="aSupprimer = null">Annuler</button>
                    <button type="button" class="sg-btn bg-gradient-to-b from-red-500 to-red-700 text-white shadow-[0_8px_18px_-8px_rgba(220,38,38,0.7)]" @click="supprimer">
                        <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>Supprimer
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style src="../../../../css/secretariat-glass.css"></style>
