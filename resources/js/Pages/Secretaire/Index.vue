<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecretariatChat from '@/Components/SecretariatChat.vue';
import { PRIORITES, STATUTS, dateHeure, etatDelai, progression } from '@/constants/secretariat';

const props = defineProps({
    taches: { type: Array, default: () => [] },
    terminees: { type: Array, default: () => [] },
});

const page = usePage();
const moi = computed(() => page.props.auth.user);
const chat = ref(null);

// Horloge pour les comptes à rebours
const maintenant = ref(Date.now());
let horloge;
onMounted(() => { horloge = window.setInterval(() => { maintenant.value = Date.now(); }, 30000); });
onBeforeUnmount(() => window.clearInterval(horloge));

// ─── Regroupement par délai ─────────────────────────────────────────
const finDeJournee = (decalageJours = 0) => {
    const d = new Date(maintenant.value);
    d.setHours(23, 59, 59, 999);
    d.setDate(d.getDate() + decalageJours);
    return d.getTime();
};

const groupes = computed(() => {
    const g = [
        { cle: 'retard', titre: 'En retard', icone: 'fa-triangle-exclamation', couleur: 'text-red-600', taches: [] },
        { cle: 'aujourdhui', titre: "Aujourd'hui", icone: 'fa-sun', couleur: 'text-amber-600', taches: [] },
        { cle: 'semaine', titre: '7 prochains jours', icone: 'fa-calendar-week', couleur: 'text-[#0B0F19]', taches: [] },
        { cle: 'plus_tard', titre: 'Plus tard', icone: 'fa-calendar', couleur: 'text-gray-500', taches: [] },
    ];
    for (const t of props.taches) {
        const e = new Date(t.echeance).getTime();
        if (e < maintenant.value) g[0].taches.push(t);
        else if (e <= finDeJournee()) g[1].taches.push(t);
        else if (e <= finDeJournee(7)) g[2].taches.push(t);
        else g[3].taches.push(t);
    }
    // Dans un même groupe : priorité haute d'abord, puis délai le plus proche
    const rang = { haute: 0, normale: 1, basse: 2 };
    g.forEach((x) => x.taches.sort((a, b) => rang[a.priorite] - rang[b.priorite] || new Date(a.echeance) - new Date(b.echeance)));
    return g;
});

const compteurs = computed(() => ({
    retard: groupes.value[0].taches.length,
    aujourdhui: groupes.value[1].taches.length,
    total: props.taches.length,
}));

// ─── Actions ────────────────────────────────────────────────────────
const enCours = ref(new Set());
const options = (cle) => ({
    preserveScroll: true,
    preserveState: true,
    onStart: () => enCours.value.add(cle),
    onFinish: () => enCours.value.delete(cle),
});

const cocherEtape = (item) => router.patch(route('secretaire.items.toggle', item.id), {}, options(`item-${item.id}`));

const changerStatut = (tache, statut) =>
    router.patch(route('secretaire.taches.statut', tache.id), { statut }, options(`tache-${tache.id}`));

const basculerTache = (tache) => changerStatut(tache, tache.statut === 'terminee' ? 'en_cours' : 'terminee');

const nouvellesEtapes = ref({});
const ajouterEtape = (tache) => {
    const libelle = (nouvellesEtapes.value[tache.id] || '').trim();
    if (!libelle) return;
    router.post(route('secretaire.items.store', tache.id), { libelle }, {
        ...options(`ajout-${tache.id}`),
        onSuccess: () => { nouvellesEtapes.value[tache.id] = ''; },
    });
};

const supprimerEtape = (item) => router.delete(route('secretaire.items.destroy', item.id), options(`item-${item.id}`));

const questionner = (tache) => {
    chat.value?.citerTache(tache.id);
    document.getElementById('messagerie')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const afficherTerminees = ref(false);
const ouvertes = ref(new Set(props.taches.slice(0, 3).map((t) => t.id)));
const basculer = (id) => (ouvertes.value.has(id) ? ouvertes.value.delete(id) : ouvertes.value.add(id));

const bordure = (tache) => ({
    retard: 'border-l-red-500',
    urgent: 'border-l-amber-500',
    proche: 'border-l-amber-300',
    ok: 'border-l-gray-200',
    termine: 'border-l-emerald-500',
})[etatDelai(tache, maintenant.value).niveau];
</script>

<template>
    <Head title="Mes tâches" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-[#0B0F19]">Mes tâches</h2>
                    <p class="text-sm text-gray-500">Instructions reçues de l'administration, classées par délai.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-sm font-bold">
                    <span class="rounded-full px-3 py-1" :class="compteurs.retard ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'">
                        {{ compteurs.retard }} en retard
                    </span>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800">{{ compteurs.aujourdhui }} pour aujourd'hui</span>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-700">{{ compteurs.total }} en attente</span>
                </div>
            </div>
        </template>

        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-5 lg:px-8">
            <!-- ═══ CHECKLIST ═══ -->
            <div class="space-y-6 lg:col-span-3">
                <div v-if="!taches.length" class="rounded-2xl border border-dashed bg-white px-6 py-12 text-center">
                    <i class="fa-solid fa-circle-check text-3xl text-emerald-500" aria-hidden="true"></i>
                    <p class="mt-3 font-black text-[#0B0F19]">Aucune tâche en attente</p>
                    <p class="mt-1 text-sm text-gray-500">Les nouvelles instructions de l'administrateur apparaîtront ici.</p>
                </div>

                <section v-for="groupe in groupes.filter((g) => g.taches.length)" :key="groupe.cle" class="space-y-3">
                    <h3 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide" :class="groupe.couleur">
                        <i class="fa-solid" :class="groupe.icone" aria-hidden="true"></i>
                        {{ groupe.titre }}
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-500 shadow-sm">{{ groupe.taches.length }}</span>
                    </h3>

                    <article
                        v-for="tache in groupe.taches"
                        :key="tache.id"
                        class="overflow-hidden rounded-2xl border border-l-4 bg-white shadow-sm"
                        :class="bordure(tache)"
                    >
                        <div class="flex items-start gap-3 p-4">
                            <!-- Case de la tâche : seule cochable s'il n'y a pas d'étapes -->
                            <button
                                v-if="!tache.items.length"
                                type="button"
                                class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-lg border-2 border-gray-300 transition hover:border-emerald-500"
                                :disabled="enCours.has(`tache-${tache.id}`)"
                                :aria-label="`Marquer « ${tache.titre} » comme terminée`"
                                @click="basculerTache(tache)"
                            ></button>
                            <span
                                v-else
                                class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-gray-100 text-[10px] font-black text-gray-600"
                                :title="`${progression(tache).faits} étape(s) sur ${progression(tache).total}`"
                            >{{ progression(tache).faits }}/{{ progression(tache).total }}</span>

                            <button type="button" class="min-w-0 flex-1 text-left" :aria-expanded="ouvertes.has(tache.id)" @click="basculer(tache.id)">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-black text-[#0B0F19]">{{ tache.titre }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="PRIORITES[tache.priorite]?.classe">
                                        {{ PRIORITES[tache.priorite]?.label }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="STATUTS[tache.statut]?.classe">
                                        {{ STATUTS[tache.statut]?.label }}
                                    </span>
                                </span>
                                <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs">
                                    <span class="text-gray-500"><i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>{{ dateHeure(tache.echeance) }}</span>
                                    <span class="font-bold" :class="etatDelai(tache, maintenant).classe">{{ etatDelai(tache, maintenant).texte }}</span>
                                </span>
                                <span v-if="tache.items.length" class="mt-2 block h-1.5 overflow-hidden rounded-full bg-gray-100">
                                    <span class="block h-full rounded-full bg-emerald-500 transition-all" :style="{ width: `${progression(tache).pourcent}%` }"></span>
                                </span>
                            </button>

                            <i class="fa-solid fa-chevron-down mt-1.5 text-xs text-gray-400 transition-transform" :class="{ 'rotate-180': ouvertes.has(tache.id) }" aria-hidden="true"></i>
                        </div>

                        <div v-if="ouvertes.has(tache.id)" class="space-y-4 border-t bg-gray-50/60 px-4 py-4">
                            <div v-if="tache.instructions" class="rounded-xl border bg-white p-3">
                                <p class="text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    Instructions de {{ tache.admin?.name || "l'administrateur" }}
                                </p>
                                <p class="mt-1 whitespace-pre-line text-sm text-[#0B0F19]">{{ tache.instructions }}</p>
                            </div>

                            <ul v-if="tache.items.length" class="space-y-1">
                                <li v-for="item in tache.items" :key="item.id" class="group flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-white">
                                    <input
                                        :id="`item-${item.id}`"
                                        type="checkbox"
                                        :checked="item.fait"
                                        :disabled="enCours.has(`item-${item.id}`)"
                                        class="h-5 w-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                        @change="cocherEtape(item)"
                                    />
                                    <label :for="`item-${item.id}`" class="flex-1 cursor-pointer text-sm" :class="item.fait ? 'text-gray-400 line-through' : 'text-[#0B0F19]'">
                                        {{ item.libelle }}
                                        <span v-if="item.ajoute_par_secretaire" class="ml-1 text-[10px] font-bold text-gray-400 no-underline">(ajoutée par moi)</span>
                                    </label>
                                    <button
                                        v-if="item.ajoute_par_secretaire"
                                        type="button"
                                        class="text-xs text-gray-400 opacity-0 transition hover:text-red-600 group-hover:opacity-100 focus:opacity-100"
                                        :aria-label="`Supprimer l'étape ${item.libelle}`"
                                        @click="supprimerEtape(item)"
                                    >
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                    </button>
                                </li>
                            </ul>

                            <form class="flex gap-2" @submit.prevent="ajouterEtape(tache)">
                                <label :for="`nouvelle-${tache.id}`" class="sr-only">Ajouter une étape</label>
                                <input
                                    :id="`nouvelle-${tache.id}`"
                                    v-model="nouvellesEtapes[tache.id]"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Ajouter une étape pour m'organiser…"
                                    class="min-w-0 flex-1 rounded-xl border-gray-200 text-sm focus:border-[#E11D48] focus:ring-[#E11D48]/20"
                                />
                                <button type="submit" class="rounded-xl border bg-white px-3 text-sm font-bold text-gray-700 hover:bg-gray-50" :disabled="enCours.has(`ajout-${tache.id}`)">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                </button>
                            </form>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-xl border bg-white px-3 py-2 text-xs font-bold text-[#0B0F19] hover:bg-gray-50" @click="questionner(tache)">
                                    <i class="fa-solid fa-comment-dots mr-1.5 text-[#E11D48]" aria-hidden="true"></i>Écrire à l'admin sur cette tâche
                                </button>
                                <button
                                    v-if="tache.statut === 'a_faire' && !tache.items.length"
                                    type="button"
                                    class="rounded-xl border bg-white px-3 py-2 text-xs font-bold text-amber-800 hover:bg-amber-50"
                                    @click="changerStatut(tache, 'en_cours')"
                                >
                                    Je commence
                                </button>
                                <button
                                    v-if="!tache.items.length"
                                    type="button"
                                    class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700"
                                    @click="changerStatut(tache, 'terminee')"
                                >
                                    <i class="fa-solid fa-check mr-1.5" aria-hidden="true"></i>Marquer terminée
                                </button>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- Tâches terminées récemment -->
                <section v-if="terminees.length" class="rounded-2xl border bg-white shadow-sm">
                    <button type="button" class="flex w-full items-center justify-between px-4 py-3 text-left" :aria-expanded="afficherTerminees" @click="afficherTerminees = !afficherTerminees">
                        <span class="text-sm font-black text-[#0B0F19]">
                            <i class="fa-solid fa-circle-check mr-2 text-emerald-500" aria-hidden="true"></i>
                            Terminées (30 derniers jours) · {{ terminees.length }}
                        </span>
                        <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform" :class="{ 'rotate-180': afficherTerminees }" aria-hidden="true"></i>
                    </button>
                    <ul v-if="afficherTerminees" class="divide-y border-t">
                        <li v-for="tache in terminees" :key="tache.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-gray-500 line-through">{{ tache.titre }}</p>
                                <p class="text-xs" :class="etatDelai(tache, maintenant).classe">
                                    {{ etatDelai(tache, maintenant).texte }} · {{ dateHeure(tache.terminee_at) }}
                                </p>
                            </div>
                            <button
                                v-if="!tache.items.length"
                                type="button"
                                class="text-xs font-bold text-gray-500 hover:text-[#0B0F19]"
                                @click="changerStatut(tache, 'en_cours')"
                            >
                                Rouvrir
                            </button>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- ═══ MESSAGERIE ═══ -->
            <div id="messagerie" class="scroll-mt-4 lg:sticky lg:top-4 lg:col-span-2 lg:self-start">
                <SecretariatChat
                    v-if="moi"
                    ref="chat"
                    :secretaire-id="moi.id"
                    :taches="taches"
                    titre="Messagerie avec l'administration"
                    sous-titre="Questions, comptes rendus, précisions sur une tâche"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
