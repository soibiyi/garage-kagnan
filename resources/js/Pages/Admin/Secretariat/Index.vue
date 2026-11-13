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
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-black text-[#0B0F19]">Secrétariat</h2>
                    <p class="text-sm text-gray-500">Confiez des tâches avec un délai, suivez leur avancement et échangez avec vos secrétaires.</p>
                </div>
                <button
                    v-if="secretaires.length"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#E11D48] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#BE123C]"
                    @click="nouvelleTache"
                >
                    <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>Nouvelle tâche
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Aucune secrétaire -->
            <div v-if="!secretaires.length" class="rounded-2xl border border-dashed bg-white px-6 py-12 text-center">
                <i class="fa-solid fa-user-tie text-3xl text-gray-400" aria-hidden="true"></i>
                <p class="mt-3 font-black text-[#0B0F19]">Aucune secrétaire enregistrée</p>
                <p class="mt-1 text-sm text-gray-500">Créez un collaborateur avec le rôle « Secrétaire » pour lui confier des tâches.</p>
                <Link :href="route('admin.users.create')" class="mt-4 inline-flex rounded-xl bg-[#0B0F19] px-4 py-2 text-sm font-bold text-white">
                    Ajouter un collaborateur
                </Link>
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-12">
                <!-- ═══ SECRÉTAIRES ═══ -->
                <aside class="lg:col-span-3">
                    <h3 class="mb-2 text-xs font-black uppercase tracking-wide text-gray-500">Secrétaires</h3>
                    <ul class="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible">
                        <li v-for="s in secretairesAffichees" :key="s.id" class="shrink-0">
                            <button
                                type="button"
                                class="flex w-56 items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition lg:w-full"
                                :class="selection?.id === s.id ? 'border-[#0B0F19] bg-white shadow-sm' : 'border-transparent bg-white/60 hover:bg-white'"
                                @click="naviguer({ secretaire: s.id })"
                            >
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-rose-50 text-xs font-black text-rose-700">
                                    {{ s.name.split(' ').slice(0, 2).map((p) => p[0]).join('').toUpperCase() }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-bold text-[#0B0F19]">{{ s.name }}</span>
                                    <span class="block text-xs text-gray-500">
                                        {{ s.taches_ouvertes }} en cours
                                        <span v-if="s.taches_en_retard" class="font-bold text-red-600"> · {{ s.taches_en_retard }} en retard</span>
                                    </span>
                                </span>
                                <span
                                    v-if="s.messages_non_lus"
                                    class="grid h-5 min-w-5 place-items-center rounded-full bg-[#E11D48] px-1 text-[10px] font-bold text-white"
                                    :title="`${s.messages_non_lus} message(s) non lu(s)`"
                                >{{ s.messages_non_lus }}</span>
                            </button>
                        </li>
                    </ul>
                </aside>

                <!-- ═══ TÂCHES ═══ -->
                <section class="space-y-4 lg:col-span-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-lg font-black text-[#0B0F19]">Tâches de {{ selection?.name }}</h3>
                        <div class="flex rounded-xl border bg-white p-1" role="group" aria-label="Filtrer les tâches">
                            <button
                                v-for="f in FILTRES"
                                :key="f.cle"
                                type="button"
                                class="rounded-lg px-3 py-1 text-xs font-bold transition"
                                :class="filtre === f.cle ? 'bg-[#0B0F19] text-white' : 'text-gray-600 hover:text-[#0B0F19]'"
                                :aria-pressed="filtre === f.cle"
                                @click="naviguer({ filtre: f.cle })"
                            >{{ f.label }}</button>
                        </div>
                    </div>

                    <div v-if="!taches.length" class="rounded-2xl border border-dashed bg-white px-6 py-10 text-center text-sm text-gray-500">
                        Aucune tâche dans cette catégorie.
                        <button type="button" class="mt-2 block w-full font-bold text-[#E11D48]" @click="nouvelleTache">Confier une tâche</button>
                    </div>

                    <article
                        v-for="tache in taches"
                        :key="tache.id"
                        class="rounded-2xl border border-l-4 bg-white p-4 shadow-sm"
                        :class="bordure(tache)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="font-black text-[#0B0F19]">{{ tache.titre }}</h4>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="PRIORITES[tache.priorite]?.classe">{{ PRIORITES[tache.priorite]?.label }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="STATUTS[tache.statut]?.classe">{{ STATUTS[tache.statut]?.label }}</span>
                                </div>
                                <p class="mt-1 text-xs">
                                    <span class="text-gray-500"><i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>{{ dateHeure(tache.echeance) }}</span>
                                    <span class="ml-2 font-bold" :class="etatDelai(tache, maintenant).classe">{{ etatDelai(tache, maintenant).texte }}</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#0B0F19]" :aria-label="`Discuter de ${tache.titre}`" @click="discuter(tache)">
                                    <i class="fa-solid fa-comment-dots text-sm" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#0B0F19]" :aria-label="`Modifier ${tache.titre}`" @click="modifierTache(tache)">
                                    <i class="fa-solid fa-pen text-sm" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600" :aria-label="`Supprimer ${tache.titre}`" @click="aSupprimer = tache">
                                    <i class="fa-solid fa-trash-can text-sm" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <p v-if="tache.instructions" class="mt-3 line-clamp-3 whitespace-pre-line text-sm text-gray-700">{{ tache.instructions }}</p>

                        <div v-if="tache.items.length" class="mt-3">
                            <div class="flex items-center justify-between text-xs font-bold text-gray-500">
                                <span>Checklist</span>
                                <span>{{ progression(tache).faits }}/{{ progression(tache).total }}</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: `${progression(tache).pourcent}%` }"></div>
                            </div>
                            <ul class="mt-2 space-y-1">
                                <li v-for="item in tache.items" :key="item.id" class="flex items-center gap-2 text-sm">
                                    <i class="fa-solid w-4 text-center text-xs" :class="item.fait ? 'fa-square-check text-emerald-600' : 'fa-square text-gray-300'" aria-hidden="true"></i>
                                    <span :class="item.fait ? 'text-gray-400 line-through' : 'text-gray-700'">{{ item.libelle }}</span>
                                    <span v-if="item.ajoute_par_secretaire" class="text-[10px] font-bold text-gray-400">(ajoutée par la secrétaire)</span>
                                    <span class="sr-only">{{ item.fait ? 'fait' : 'à faire' }}</span>
                                </li>
                            </ul>
                        </div>
                        <p class="mt-3 text-[11px] text-gray-400">
                            Confiée par {{ tache.admin?.name || 'un administrateur' }} le {{ dateHeure(tache.created_at) }}
                        </p>
                    </article>
                </section>

                <!-- ═══ MESSAGERIE ═══ -->
                <div id="messagerie" class="scroll-mt-4 lg:sticky lg:top-4 lg:col-span-4 lg:self-start">
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

        <!-- ═══ MODALE : NOUVELLE / MODIFIER TÂCHE ═══ -->
        <Modal :show="modal" max-width="2xl" @close="modal = false">
            <form class="space-y-4 p-6" @submit.prevent="enregistrer">
                <h3 class="text-lg font-black text-[#0B0F19]">{{ tacheEditee ? 'Modifier la tâche' : 'Nouvelle tâche' }}</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="t-secretaire" class="text-xs font-bold text-gray-700">Secrétaire</label>
                        <select id="t-secretaire" v-model="form.secretaire_id" required class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                            <option v-for="s in secretaires" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="form.errors.secretaire_id" class="mt-1 text-xs text-red-600">{{ form.errors.secretaire_id }}</p>
                    </div>
                    <div>
                        <label for="t-priorite" class="text-xs font-bold text-gray-700">Priorité</label>
                        <select id="t-priorite" v-model="form.priorite" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                            <option v-for="(p, cle) in PRIORITES" :key="cle" :value="cle">{{ p.label }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="t-titre" class="text-xs font-bold text-gray-700">Tâche</label>
                    <input id="t-titre" v-model="form.titre" type="text" required maxlength="255" placeholder="Ex. : Relancer les factures impayées d'octobre" class="mt-1 w-full rounded-xl border-gray-200 text-sm" />
                    <p v-if="form.errors.titre" class="mt-1 text-xs text-red-600">{{ form.errors.titre }}</p>
                </div>

                <div>
                    <label for="t-instructions" class="text-xs font-bold text-gray-700">Instructions</label>
                    <textarea id="t-instructions" v-model="form.instructions" rows="3" maxlength="5000" placeholder="Détails, contexte, personnes à contacter…" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea>
                    <p v-if="form.errors.instructions" class="mt-1 text-xs text-red-600">{{ form.errors.instructions }}</p>
                </div>

                <div>
                    <label for="t-echeance" class="text-xs font-bold text-gray-700">Délai (à rendre avant le)</label>
                    <input id="t-echeance" v-model="form.echeance" type="datetime-local" required class="mt-1 w-full rounded-xl border-gray-200 text-sm sm:w-auto" />
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button
                            v-for="r in RACCOURCIS"
                            :key="r.label"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-[11px] font-bold text-gray-600 hover:border-[#0B0F19] hover:text-[#0B0F19]"
                            @click="form.echeance = r.valeur()"
                        >{{ r.label }}</button>
                    </div>
                    <p v-if="form.errors.echeance" class="mt-1 text-xs text-red-600">{{ form.errors.echeance }}</p>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-700">Étapes de la checklist <span class="font-normal text-gray-400">(facultatif)</span></span>
                        <button type="button" class="text-xs font-bold text-[#E11D48]" @click="ajouterEtape">
                            <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Ajouter une étape
                        </button>
                    </div>
                    <p class="mt-0.5 text-[11px] text-gray-400">Sans étape, la secrétaire coche la tâche entière. Astuce : collez une liste pour créer une étape par ligne.</p>
                    <ul class="mt-2 space-y-2">
                        <li v-for="(etape, index) in form.etapes" :key="etape.id ?? `n-${index}`" class="flex items-center gap-2">
                            <i class="fa-solid w-4 text-center text-xs" :class="etape.fait ? 'fa-square-check text-emerald-600' : 'fa-square text-gray-300'" aria-hidden="true"></i>
                            <label :for="`etape-${index}`" class="sr-only">Étape {{ index + 1 }}</label>
                            <input
                                :id="`etape-${index}`"
                                v-model="etape.libelle"
                                type="text"
                                maxlength="255"
                                class="min-w-0 flex-1 rounded-xl border-gray-200 py-1.5 text-sm"
                                :placeholder="`Étape ${index + 1}`"
                                @paste="collerEtapes($event, index)"
                                @keydown.enter.prevent="ajouterEtape"
                            />
                            <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600" :aria-label="`Retirer l'étape ${index + 1}`" @click="retirerEtape(index)">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </li>
                    </ul>
                    <p v-if="erreursEtapes" class="mt-1 text-xs text-red-600">{{ erreursEtapes }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" class="rounded-xl border px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50" @click="modal = false">Annuler</button>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-[#E11D48] px-4 py-2 text-sm font-bold text-white hover:bg-[#BE123C] disabled:opacity-50">
                        {{ tacheEditee ? 'Enregistrer' : 'Attribuer la tâche' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ═══ MODALE : SUPPRESSION ═══ -->
        <Modal :show="!!aSupprimer" max-width="md" @close="aSupprimer = null">
            <div class="p-6">
                <h3 class="text-lg font-black text-[#0B0F19]">Supprimer la tâche ?</h3>
                <p class="mt-2 text-sm text-gray-600">« {{ aSupprimer?.titre }} » et sa checklist seront supprimées. Les messages liés sont conservés.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-xl border px-4 py-2 text-sm font-bold text-gray-700" @click="aSupprimer = null">Annuler</button>
                    <button type="button" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700" @click="supprimer">Supprimer</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
