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
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="sg-tile sg-tile-red h-12 w-12 text-lg">
                                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-widest text-white/50">Secrétariat</p>
                                <h1 class="truncate text-2xl font-black tracking-tight sm:text-3xl">Bonjour {{ moi?.name?.split(' ')[0] }}</h1>
                                <p class="mt-0.5 text-sm text-white/60">Vos instructions de l'administration, classées par délai.</p>
                            </div>
                        </div>

                        <dl class="grid grid-cols-3 gap-2 sm:gap-3">
                            <div class="rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10 sm:px-5">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-white/50">En retard</dt>
                                <dd class="sg-stat-value mt-1" :class="compteurs.retard ? 'text-rose-400' : 'text-white'">{{ compteurs.retard }}</dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10 sm:px-5">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-white/50">Aujourd'hui</dt>
                                <dd class="sg-stat-value mt-1 text-amber-300">{{ compteurs.aujourdhui }}</dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10 sm:px-5">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-white/50">En attente</dt>
                                <dd class="sg-stat-value mt-1">{{ compteurs.total }}</dd>
                            </div>
                        </dl>
                    </div>
                </header>

                <div class="grid gap-6 lg:grid-cols-5">
                    <!-- ═══ CHECKLIST ═══ -->
                    <div class="space-y-7 lg:col-span-3">
                        <div v-if="!taches.length" class="sg-empty sg-rise px-6 py-14 text-center" style="--d: 80ms">
                            <span class="sg-tile mx-auto h-14 w-14 text-2xl text-emerald-500">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            </span>
                            <p class="mt-4 text-lg font-black text-[#0B0F19]">Tout est à jour</p>
                            <p class="mt-1 text-sm text-gray-500">Les nouvelles instructions de l'administrateur apparaîtront ici.</p>
                        </div>

                        <section
                            v-for="(groupe, gi) in groupes.filter((g) => g.taches.length)"
                            :key="groupe.cle"
                            class="sg-rise space-y-3"
                            :style="{ '--d': `${80 + gi * 70}ms` }"
                        >
                            <h3 class="sg-section-title px-1" :class="groupe.couleur">
                                <i class="fa-solid" :class="groupe.icone" aria-hidden="true"></i>
                                {{ groupe.titre }}
                                <span class="sg-chip bg-white/80 text-gray-500">{{ groupe.taches.length }}</span>
                                <span class="ml-2 h-px flex-1 bg-gradient-to-r from-[#0B0F19]/10 to-transparent"></span>
                            </h3>

                            <article
                                v-for="tache in groupe.taches"
                                :key="tache.id"
                                class="sg-glass sg-task"
                                :class="`sg-accent-${etatDelai(tache, maintenant).niveau}`"
                            >
                                <div class="flex items-start gap-3 p-4 pl-5 sm:gap-4 sm:p-5 sm:pl-6">
                                    <!-- Case de la tâche : seule cochable s'il n'y a pas d'étapes -->
                                    <button
                                        v-if="!tache.items.length"
                                        type="button"
                                        class="sg-check mt-1"
                                        :disabled="enCours.has(`tache-${tache.id}`)"
                                        :aria-label="`Marquer « ${tache.titre} » comme terminée`"
                                        @click="basculerTache(tache)"
                                    >
                                        <i class="fa-solid fa-check text-xs" aria-hidden="true"></i>
                                    </button>
                                    <span
                                        v-else
                                        class="sg-ring"
                                        :style="{ '--p': progression(tache).pourcent }"
                                        :title="`${progression(tache).faits} étape(s) sur ${progression(tache).total}`"
                                    ><span>{{ progression(tache).faits }}/{{ progression(tache).total }}</span></span>

                                    <button type="button" class="min-w-0 flex-1 text-left" :aria-expanded="ouvertes.has(tache.id)" @click="basculer(tache.id)">
                                        <span class="flex flex-wrap items-center gap-1.5">
                                            <span class="mr-1 text-[15px] font-black leading-snug text-[#0B0F19]">{{ tache.titre }}</span>
                                            <span class="sg-chip" :class="PRIORITES[tache.priorite]?.classe">
                                                <i v-if="tache.priorite === 'haute'" class="fa-solid fa-bolt text-[9px]" aria-hidden="true"></i>
                                                {{ PRIORITES[tache.priorite]?.label }}
                                            </span>
                                            <span class="sg-chip" :class="STATUTS[tache.statut]?.classe">{{ STATUTS[tache.statut]?.label }}</span>
                                        </span>
                                        <span class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                            <span class="inline-flex items-center gap-1.5 text-gray-500">
                                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ dateHeure(tache.echeance) }}
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 font-bold" :class="etatDelai(tache, maintenant).classe">
                                                <i class="fa-regular fa-hourglass-half" aria-hidden="true"></i>{{ etatDelai(tache, maintenant).texte }}
                                            </span>
                                        </span>
                                        <span v-if="tache.items.length" class="sg-progress mt-3 block">
                                            <span :style="{ width: `${progression(tache).pourcent}%` }"></span>
                                        </span>
                                    </button>

                                    <span class="sg-icon-btn pointer-events-none mt-0.5">
                                        <i class="fa-solid fa-chevron-down text-xs transition-transform duration-300" :class="{ 'rotate-180': ouvertes.has(tache.id) }" aria-hidden="true"></i>
                                    </span>
                                </div>

                                <div v-if="ouvertes.has(tache.id)" class="sg-divider space-y-4 border-t bg-white/30 px-4 py-4 pl-5 sm:px-5 sm:pl-6">
                                    <div v-if="tache.instructions" class="sg-surface p-4">
                                        <p class="flex items-center gap-2 text-[11px] font-black uppercase tracking-wide text-gray-500">
                                            <i class="fa-solid fa-quote-left text-[#E11D48]" aria-hidden="true"></i>
                                            Instructions de {{ tache.admin?.name || "l'administrateur" }}
                                        </p>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-[#0B0F19]">{{ tache.instructions }}</p>
                                    </div>

                                    <ul v-if="tache.items.length" class="sg-surface divide-y divide-[#0B0F19]/5 overflow-hidden">
                                        <li v-for="item in tache.items" :key="item.id" class="group flex items-center gap-3 px-3 py-2.5 transition hover:bg-white/70">
                                            <input
                                                :id="`item-${item.id}`"
                                                type="checkbox"
                                                :checked="item.fait"
                                                :disabled="enCours.has(`item-${item.id}`)"
                                                class="h-5 w-5 cursor-pointer rounded-md border-gray-300 text-emerald-500 transition focus:ring-emerald-500/30"
                                                @change="cocherEtape(item)"
                                            />
                                            <label :for="`item-${item.id}`" class="flex-1 cursor-pointer text-sm transition" :class="item.fait ? 'text-gray-400 line-through' : 'font-medium text-[#0B0F19]'">
                                                {{ item.libelle }}
                                                <span v-if="item.ajoute_par_secretaire" class="sg-chip ml-1 bg-gray-100 text-[10px] text-gray-500 no-underline">perso</span>
                                            </label>
                                            <button
                                                v-if="item.ajoute_par_secretaire"
                                                type="button"
                                                class="sg-icon-btn sg-icon-btn-danger opacity-0 focus:opacity-100 group-hover:opacity-100"
                                                :aria-label="`Supprimer l'étape ${item.libelle}`"
                                                @click="supprimerEtape(item)"
                                            >
                                                <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
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
                                            class="sg-field min-w-0 flex-1 py-2"
                                        />
                                        <button type="submit" class="sg-btn sg-btn-glass !px-3.5" :disabled="enCours.has(`ajout-${tache.id}`)" aria-label="Ajouter l'étape">
                                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="sg-btn sg-btn-sm sg-btn-glass" @click="questionner(tache)">
                                            <i class="fa-solid fa-comment-dots text-[#E11D48]" aria-hidden="true"></i>Écrire à l'admin
                                        </button>
                                        <button
                                            v-if="tache.statut === 'a_faire' && !tache.items.length"
                                            type="button"
                                            class="sg-btn sg-btn-sm sg-btn-glass text-amber-700"
                                            @click="changerStatut(tache, 'en_cours')"
                                        >
                                            <i class="fa-solid fa-play text-[10px]" aria-hidden="true"></i>Je commence
                                        </button>
                                        <button
                                            v-if="!tache.items.length"
                                            type="button"
                                            class="sg-btn sg-btn-sm sg-btn-green"
                                            @click="changerStatut(tache, 'terminee')"
                                        >
                                            <i class="fa-solid fa-check" aria-hidden="true"></i>Marquer terminée
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <!-- Tâches terminées récemment -->
                        <section v-if="terminees.length" class="sg-glass sg-glass-strong sg-rise overflow-hidden" style="--d: 300ms">
                            <button type="button" class="flex w-full items-center gap-3 px-5 py-4 text-left" :aria-expanded="afficherTerminees" @click="afficherTerminees = !afficherTerminees">
                                <span class="sg-tile h-9 w-9 text-emerald-500"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                                <span class="flex-1">
                                    <span class="block text-sm font-black text-[#0B0F19]">Terminées récemment</span>
                                    <span class="block text-xs text-gray-500">30 derniers jours · {{ terminees.length }} tâche{{ terminees.length > 1 ? 's' : '' }}</span>
                                </span>
                                <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-300" :class="{ 'rotate-180': afficherTerminees }" aria-hidden="true"></i>
                            </button>
                            <ul v-if="afficherTerminees" class="sg-divider divide-y divide-[#0B0F19]/5 border-t">
                                <li v-for="tache in terminees" :key="tache.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
                                    <i class="fa-solid fa-check text-xs text-emerald-500" aria-hidden="true"></i>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold text-gray-500 line-through">{{ tache.titre }}</p>
                                        <p class="text-xs" :class="etatDelai(tache, maintenant).classe">
                                            {{ etatDelai(tache, maintenant).texte }} · {{ dateHeure(tache.terminee_at) }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="!tache.items.length"
                                        type="button"
                                        class="sg-btn sg-btn-sm sg-btn-glass"
                                        @click="changerStatut(tache, 'en_cours')"
                                    >
                                        <i class="fa-solid fa-rotate-left text-[10px]" aria-hidden="true"></i>Rouvrir
                                    </button>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <!-- ═══ MESSAGERIE ═══ -->
                    <div id="messagerie" class="sg-rise scroll-mt-4 lg:sticky lg:top-4 lg:col-span-2 lg:self-start" style="--d: 120ms">
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
