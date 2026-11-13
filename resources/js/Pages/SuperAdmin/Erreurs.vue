<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    erreurs: { type: Object, required: true },
    statut: { type: String, default: 'ouvertes' },
    compteurs: { type: Object, default: () => ({}) },
});

const FILTRES = [
    { cle: 'ouvertes', label: 'À traiter' },
    { cle: 'resolues', label: 'Résolues' },
    { cle: 'toutes', label: 'Toutes' },
];

const dateHeure = (d) => (d ? new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—');
const nomCourt = (type = '') => type.split('\\').pop();

const filtrer = (statut) => router.get(route('superadmin.erreurs.index'), { statut }, { preserveScroll: true });

// Détail (pile d'appels) chargé à la demande
const details = ref({});
const ouvertes = ref(new Set());
async function basculer(erreur) {
    if (ouvertes.value.has(erreur.id)) {
        ouvertes.value.delete(erreur.id);
        return;
    }
    ouvertes.value.add(erreur.id);
    if (!details.value[erreur.id]) {
        try {
            const { data } = await axios.get(route('superadmin.erreurs.show', erreur.id));
            details.value[erreur.id] = data.erreur;
        } catch {
            details.value[erreur.id] = { trace: 'Impossible de charger le détail.' };
        }
    }
}

const copie = ref(null);
async function copier(erreur) {
    const d = details.value[erreur.id] || erreur;
    const texte = `${erreur.type}: ${erreur.message}\n${erreur.fichier ?? ''}:${erreur.ligne ?? ''}\nURL: ${erreur.url ?? ''}\n\n${d.trace ?? ''}`;
    try {
        await navigator.clipboard.writeText(texte);
        copie.value = erreur.id;
        window.setTimeout(() => { copie.value = null; }, 2000);
    } catch { /* presse-papiers indisponible */ }
}

const resoudre = (e) => router.patch(route('superadmin.erreurs.resoudre', e.id), {}, { preserveScroll: true });
const rouvrir = (e) => router.patch(route('superadmin.erreurs.rouvrir', e.id), {}, { preserveScroll: true });

// Nouvelles erreurs : on recharge la liste toute seule (sauf si un détail est ouvert)
let timer;
onMounted(() => {
    const cible = Number(new URLSearchParams(window.location.search).get('erreur'));
    const erreur = props.erreurs.data.find((e) => e.id === cible);
    if (erreur) {
        basculer(erreur);
        window.setTimeout(() => document.getElementById(`erreur-${cible}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 200);
    }
    timer = window.setInterval(() => {
        if (!document.hidden && !ouvertes.value.size) router.reload({ only: ['erreurs', 'compteurs'], preserveScroll: true });
    }, 15000);
});
onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <Head title="Erreurs de l'application" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <header class="sg-glass sg-glass-dark sg-rise overflow-hidden p-5 sm:p-7">
                    <span class="absolute left-0 top-0 h-1 w-20 rounded-br-full bg-red-500"></span>
                    <Link :href="route('superadmin.accueil')" class="sg-retour">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Accueil super admin
                    </Link>
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="sg-tile h-12 w-12 bg-gradient-to-br from-red-500 to-red-700 text-lg text-white shadow-[0_10px_20px_-10px_rgba(220,38,38,0.8)]">
                                <i class="fa-solid fa-bug" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-white/50">Super administrateur</p>
                                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Erreurs de l'application</h1>
                                <p class="mt-0.5 text-sm text-white/60">Serveur et navigateurs. Vous êtes prévenu dès qu'une nouvelle erreur survient.</p>
                            </div>
                        </div>
                        <dl class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-white/10 px-5 py-3 ring-1 ring-white/10">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-white/50">À traiter</dt>
                                <dd class="sg-stat-value mt-1" :class="compteurs.ouvertes ? 'text-rose-400' : 'text-emerald-300'">{{ compteurs.ouvertes }}</dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-5 py-3 ring-1 ring-white/10">
                                <dt class="text-[11px] font-bold uppercase tracking-wide text-white/50">Aujourd'hui</dt>
                                <dd class="sg-stat-value mt-1">{{ compteurs.aujourdhui }}</dd>
                            </div>
                        </dl>
                    </div>
                </header>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="sg-segment" role="group" aria-label="Filtrer les erreurs">
                        <button
                            v-for="f in FILTRES"
                            :key="f.cle"
                            type="button"
                            class="sg-pill"
                            :class="{ 'sg-pill-active': statut === f.cle }"
                            :aria-pressed="statut === f.cle"
                            @click="filtrer(f.cle)"
                        >{{ f.label }}</button>
                    </div>
                    <p class="text-xs text-gray-500">{{ erreurs.total }} erreur{{ erreurs.total > 1 ? 's' : '' }} · actualisation automatique</p>
                </div>

                <div v-if="!erreurs.data.length" class="sg-empty px-6 py-14 text-center">
                    <span class="sg-tile mx-auto h-14 w-14 text-2xl text-emerald-500"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i></span>
                    <p class="mt-4 text-lg font-black text-[#0B0F19]">Rien à signaler</p>
                    <p class="mt-1 text-sm text-gray-500">Aucune erreur dans cette catégorie.</p>
                </div>

                <article
                    v-for="e in erreurs.data"
                    :id="`erreur-${e.id}`"
                    :key="e.id"
                    class="sg-glass sg-task"
                    :class="e.resolue_at ? 'sg-accent-termine' : 'sg-accent-retard'"
                >
                    <div class="flex flex-wrap items-start gap-4 p-4 pl-5 sm:p-5 sm:pl-6">
                        <span class="sg-tile h-10 w-10" :class="e.resolue_at ? 'text-emerald-600' : 'text-red-600'" :title="e.source === 'client' ? 'Erreur dans le navigateur' : 'Erreur serveur'">
                            <i class="fa-solid" :class="e.source === 'client' ? 'fa-display' : 'fa-server'" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <h2 class="font-mono text-sm font-black text-red-700">{{ nomCourt(e.type) }}</h2>
                                <span class="sg-chip" :class="e.occurrences > 1 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'">× {{ e.occurrences }}</span>
                                <span class="sg-chip bg-white text-gray-600">{{ e.source === 'client' ? 'Navigateur' : 'Serveur' }}</span>
                                <span v-if="e.resolue_at" class="sg-chip bg-emerald-100 text-emerald-800">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i>Résolue par {{ e.resolveur?.name || '—' }}
                                </span>
                            </div>
                            <p class="mt-1.5 break-words text-sm font-semibold text-[#0B0F19]">{{ e.message }}</p>
                            <p v-if="e.fichier" class="mt-1 truncate font-mono text-[11px] text-gray-500">{{ e.fichier }}<template v-if="e.ligne">:{{ e.ligne }}</template></p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500">
                                <span v-if="e.url" class="max-w-full truncate"><i class="fa-solid fa-link mr-1" aria-hidden="true"></i><span class="font-mono">{{ e.method }} {{ e.url }}</span></span>
                                <span v-if="e.user">
                                    <i class="fa-regular fa-user mr-1" aria-hidden="true"></i>
                                    <Link :href="route('superadmin.supervision.index', { user: e.user.id })" class="font-bold text-[#0B0F19] underline decoration-dotted underline-offset-2 hover:text-[#E11D48]">{{ e.user.name }}</Link>
                                </span>
                                <span><i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>Dernière : {{ dateHeure(e.derniere_fois) }}</span>
                                <span v-if="e.occurrences > 1">Première : {{ dateHeure(e.premiere_fois) }}</span>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button type="button" class="sg-btn sg-btn-sm sg-btn-glass" :aria-expanded="ouvertes.has(e.id)" @click="basculer(e)">
                                <i class="fa-solid fa-code text-[10px]" aria-hidden="true"></i>{{ ouvertes.has(e.id) ? 'Masquer' : 'Détails' }}
                            </button>
                            <button v-if="!e.resolue_at" type="button" class="sg-btn sg-btn-sm sg-btn-green" @click="resoudre(e)">
                                <i class="fa-solid fa-check" aria-hidden="true"></i>Résolue
                            </button>
                            <button v-else type="button" class="sg-btn sg-btn-sm sg-btn-glass" @click="rouvrir(e)">
                                <i class="fa-solid fa-rotate-left text-[10px]" aria-hidden="true"></i>Rouvrir
                            </button>
                        </div>
                    </div>

                    <div v-if="ouvertes.has(e.id)" class="sg-divider border-t bg-white/30 p-4 sm:p-5">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-[11px] font-black uppercase tracking-wide text-gray-500">Pile d'appels</p>
                            <button type="button" class="sg-btn sg-btn-sm sg-btn-glass" @click="copier(e)">
                                <i class="fa-solid text-[10px]" :class="copie === e.id ? 'fa-check text-emerald-600' : 'fa-copy'" aria-hidden="true"></i>
                                {{ copie === e.id ? 'Copié' : 'Copier pour corriger' }}
                            </button>
                        </div>
                        <pre class="sg-scroll max-h-96 overflow-auto rounded-xl bg-[#0B0F19] p-4 font-mono text-[11px] leading-relaxed text-white/85">{{ details[e.id]?.trace || (details[e.id] ? '(pas de pile d’appels)' : 'Chargement…') }}</pre>
                    </div>
                </article>

                <!-- Pagination -->
                <nav v-if="erreurs.last_page > 1" class="flex flex-wrap justify-center gap-1.5" aria-label="Pagination">
                    <template v-for="lien in erreurs.links" :key="lien.label">
                        <Link
                            v-if="lien.url"
                            :href="lien.url"
                            preserve-scroll
                            class="sg-pill inline-flex items-center"
                            :class="{ 'sg-pill-active': lien.active }"
                        ><span v-html="lien.label"></span></Link>
                        <span v-else class="sg-pill inline-flex items-center opacity-40"><span v-html="lien.label"></span></span>
                    </template>
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
