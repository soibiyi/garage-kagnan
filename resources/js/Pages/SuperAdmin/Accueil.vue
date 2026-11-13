<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';
import TableauDeBordAdmin from '@/Components/Admin/TableauDeBordAdmin.vue';

const props = defineProps({
    // Données du tableau de bord administrateur (bloc 3), identiques à la page admin
    admin: { type: Object, required: true },
    comptesParRole: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
});

const page = usePage();
const prenom = computed(() => (page.props.auth.user?.name || '').split(' ')[0]);

// ─── Outils propres au super administrateur ─────────────────────────
const OUTILS = computed(() => [
    {
        titre: 'Supervision en direct',
        texte: 'Qui est connecté, sur quelle page, et tout ce que fait chaque collaborateur.',
        icone: 'fa-satellite-dish',
        route: 'superadmin.supervision.index',
        teinte: 'rouge',
        badge: props.stats.en_ligne ? `${props.stats.en_ligne} en ligne` : null,
        badgeClasse: 'pastille-verte',
    },
    {
        titre: "Erreurs de l'application",
        texte: 'Les erreurs serveur et navigateur, signalées à vous en premier.',
        icone: 'fa-bug',
        route: 'superadmin.erreurs.index',
        teinte: 'encre',
        badge: props.stats.erreurs_ouvertes ? `${props.stats.erreurs_ouvertes} à traiter` : 'Aucune',
        badgeClasse: props.stats.erreurs_ouvertes ? 'pastille-rouge' : 'pastille-neutre',
    },
    {
        titre: 'Petites fournitures',
        texte: 'Total, par mois et par semaine : factures soldées et non soldées.',
        icone: 'fa-screwdriver-wrench',
        route: 'superadmin.petites-fournitures',
        teinte: 'encre',
    },
    {
        titre: 'Comptes & administrateurs',
        texte: 'Créer, modifier ou supprimer les comptes, y compris les administrateurs.',
        icone: 'fa-user-shield',
        route: 'superadmin.accueil',
        ancre: '#employes',
        teinte: 'encre',
        badge: `${props.stats.administrateurs} admin${props.stats.administrateurs > 1 ? 's' : ''}`,
        badgeClasse: 'pastille-neutre',
    },
]);

// ─── Agir en tant que : une carte par rôle ──────────────────────────
const ROLES = [
    { cle: 'receptionniste', label: 'Réceptionniste', texte: 'Accueil des véhicules, état initial, photos.', icone: 'fa-clipboard-user', couleur: '#2563eb', espace: { label: 'Réception', route: 'reception.create' } },
    { cle: 'mecanicien', label: 'Mécanicien', texte: 'Atelier : diagnostic, réparations, avancement.', icone: 'fa-wrench', couleur: '#b45309', espace: { label: 'Atelier', route: 'mecanicien.index' } },
    { cle: 'administratif', label: 'Administratif', texte: 'Dossiers, devis, facturation, encaissements.', icone: 'fa-file-invoice-dollar', couleur: '#7c3aed', espace: { label: 'Dossiers', route: 'administration.dossiers.index' } },
    { cle: 'charge_client', label: 'Chargé client', texte: 'Suivi des clients, relances, messagerie.', icone: 'fa-headset', couleur: '#e11d48', espace: { label: 'Suivi client', route: 'charge_client.clients.index' } },
    { cle: 'secretaire', label: 'Secrétaire', texte: 'Tâches confiées par l’administration.', icone: 'fa-list-check', couleur: '#0f766e', espace: { label: 'Secrétariat', route: 'admin.secretariat.index' } },
    { cle: 'magasinier', label: 'Magasinier', texte: 'Sorties de pièces et stock par siège.', icone: 'fa-boxes-stacked', couleur: '#c2410c', espace: { label: 'Magasin', route: 'magasinier.pieces.index' } },
    { cle: 'chauffeur', label: 'Chauffeur', texte: 'Missions de convoyage.', icone: 'fa-truck-fast', couleur: '#0369a1', espace: null },
    { cle: 'depanneur', label: 'Dépanneur', texte: 'Missions de dépannage.', icone: 'fa-truck-pickup', couleur: '#0369a1', espace: null },
];

const comptes = (cle) => props.comptesParRole[cle] || [];
const enLigne = (cle) => comptes(cle).filter((c) => c.en_ligne).length;

const deplies = ref(new Set());
const basculer = (cle) => (deplies.value.has(cle) ? deplies.value.delete(cle) : deplies.value.add(cle));
const visibles = (cle) => (deplies.value.has(cle) ? comptes(cle) : comptes(cle).slice(0, 3));

const enCours = ref(null);
const agirEnTantQue = (compte) => {
    enCours.value = compte.id;
    router.post(route('superadmin.impersonate', compte.id), {}, { onFinish: () => { enCours.value = null; } });
};

const initiales = (nom = '') => nom.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase() || '?';

</script>

<template>
    <Head title="Super administrateur" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="acc-conteneur">
                <!-- ═══ EN-TÊTE ═══ -->
                <header class="sg-glass sg-glass-dark sg-rise acc-entete">
                    <span class="acc-filet" aria-hidden="true"></span>
                    <div class="acc-entete-gauche">
                        <span class="sg-tile sg-tile-red acc-tuile-xl"><i class="fa-solid fa-crown" aria-hidden="true"></i></span>
                        <div>
                            <p class="acc-surtitre">Super administrateur</p>
                            <h1 class="acc-titre">Bonjour {{ prenom }}</h1>
                            <p class="acc-sous-titre">Vos outils, et un accès direct à l’espace de chaque rôle.</p>
                        </div>
                    </div>
                    <dl class="acc-stats">
                        <div class="acc-stat">
                            <dt><span class="acc-point acc-point-vert" aria-hidden="true"></span>En ligne</dt>
                            <dd class="acc-vert">{{ stats.en_ligne }}</dd>
                        </div>
                        <div class="acc-stat">
                            <dt>Erreurs</dt>
                            <dd :class="stats.erreurs_ouvertes ? 'acc-rouge' : ''">{{ stats.erreurs_ouvertes }}</dd>
                        </div>
                        <div class="acc-stat">
                            <dt>Actions auj.</dt>
                            <dd>{{ stats.actions_aujourdhui }}</dd>
                        </div>
                    </dl>
                </header>

                <!-- ═══ 1. OUTILS DU SUPER ADMINISTRATEUR ═══ -->
                <section class="acc-section sg-rise" style="--d: 70ms" aria-labelledby="titre-outils">
                    <div class="acc-section-tete">
                        <span class="acc-numero">1</span>
                        <div>
                            <h2 id="titre-outils" class="acc-h2">Vos outils de super administrateur</h2>
                            <p class="acc-aide">Réservés à vous seul.</p>
                        </div>
                    </div>
                    <div class="acc-grille-outils">
                        <Link v-for="o in OUTILS" :key="o.titre" :href="route(o.route) + (o.ancre || '')" class="sg-glass sg-glass-strong acc-outil group">
                            <span class="sg-tile acc-tuile" :class="o.teinte === 'rouge' ? 'sg-tile-red' : 'sg-tile-ink'">
                                <i class="fa-solid" :class="o.icone" aria-hidden="true"></i>
                            </span>
                            <span class="acc-outil-corps">
                                <span class="acc-outil-titre">{{ o.titre }}</span>
                                <span class="acc-outil-texte">{{ o.texte }}</span>
                            </span>
                            <span class="acc-outil-pied">
                                <span v-if="o.badge" class="acc-pastille" :class="o.badgeClasse">{{ o.badge }}</span>
                                <span v-else></span>
                                <i class="fa-solid fa-arrow-right acc-fleche" aria-hidden="true"></i>
                            </span>
                        </Link>
                    </div>
                </section>

                <!-- ═══ 2. AGIR EN TANT QUE ═══ -->
                <section class="acc-section sg-rise" style="--d: 140ms" aria-labelledby="titre-roles">
                    <div class="acc-section-tete">
                        <span class="acc-numero">2</span>
                        <div>
                            <h2 id="titre-roles" class="acc-h2">Agir en tant que…</h2>
                            <p class="acc-aide">
                                Ouvrez l’application avec le compte d’un collaborateur pour voir exactement son écran.
                                Un bandeau vous permet de revenir à tout moment ; chaque action reste tracée.
                            </p>
                        </div>
                    </div>

                    <div class="acc-grille-roles">
                        <article v-for="r in ROLES" :key="r.cle" class="sg-glass acc-role" :style="{ '--role': r.couleur }">
                            <header class="acc-role-tete">
                                <span class="acc-role-icone"><i class="fa-solid" :class="r.icone" aria-hidden="true"></i></span>
                                <div class="acc-role-titres">
                                    <h3 class="acc-role-titre">{{ r.label }}</h3>
                                    <p class="acc-role-texte">{{ r.texte }}</p>
                                </div>
                            </header>

                            <p class="acc-role-compte">
                                {{ comptes(r.cle).length }} compte{{ comptes(r.cle).length > 1 ? 's' : '' }}
                                <span v-if="enLigne(r.cle)" class="acc-en-ligne">· <span class="acc-point acc-point-vert" aria-hidden="true"></span>{{ enLigne(r.cle) }} en ligne</span>
                            </p>

                            <ul v-if="comptes(r.cle).length" class="acc-comptes">
                                <li v-for="c in visibles(r.cle)" :key="c.id" class="acc-compte">
                                    <span class="acc-avatar">
                                        {{ initiales(c.name) }}
                                        <span class="acc-avatar-point" :class="{ 'acc-avatar-point-on': c.en_ligne }" :title="c.en_ligne ? 'En ligne' : 'Hors ligne'"></span>
                                    </span>
                                    <span class="acc-compte-nom">
                                        <span class="acc-compte-nom-1">{{ c.name }}</span>
                                        <span class="acc-compte-nom-2">{{ c.siege ? `Siège ${c.siege}` : 'Sans siège' }}</span>
                                    </span>
                                    <button
                                        type="button"
                                        class="acc-agir"
                                        :disabled="enCours !== null"
                                        :aria-label="`Agir en tant que ${c.name}`"
                                        @click="agirEnTantQue(c)"
                                    >
                                        <i class="fa-solid" :class="enCours === c.id ? 'fa-circle-notch fa-spin' : 'fa-user-secret'" aria-hidden="true"></i>
                                        <span>Agir</span>
                                    </button>
                                </li>
                            </ul>
                            <div v-else class="acc-vide">
                                Aucun compte pour ce rôle.
                                <Link :href="route('admin.users.create')" class="acc-lien">Créer un compte</Link>
                            </div>

                            <footer class="acc-role-pied">
                                <button v-if="comptes(r.cle).length > 3" type="button" class="acc-lien" :aria-expanded="deplies.has(r.cle)" @click="basculer(r.cle)">
                                    {{ deplies.has(r.cle) ? 'Réduire' : `Voir les ${comptes(r.cle).length} comptes` }}
                                </button>
                                <span v-else></span>
                                <Link v-if="r.espace" :href="route(r.espace.route)" class="acc-lien acc-lien-espace" :title="`Ouvrir l’espace ${r.espace.label} avec vos droits de super administrateur`">
                                    Espace {{ r.espace.label }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </Link>
                            </footer>
                        </article>
                    </div>
                </section>

                <!-- ═══ 3. ESPACE ADMINISTRATEUR (tableau de bord admin intégré) ═══ -->
                <section id="espace-admin" class="acc-section acc-ancre sg-rise" style="--d: 210ms" aria-labelledby="titre-admin">
                    <div class="acc-section-tete acc-section-tete-large">
                        <span class="acc-numero acc-numero-gris">3</span>
                        <div class="acc-section-titres">
                            <h2 id="titre-admin" class="acc-h2">Espace administrateur</h2>
                            <p class="acc-aide">
                                Chiffre d’affaires, devis, pièces, suivi et employés
                                <template v-if="admin.siegeFiltre"> · siège <strong>{{ admin.siegeFiltre }}</strong></template>.
                            </p>
                        </div>
                        <div class="acc-siege">
                            <SiegeSwitcher :current="admin.siegeFiltre" />
                        </div>
                    </div>
                    <TableauDeBordAdmin integre :users="admin.users" :stats="admin.stats" :siege-filtre="admin.siegeFiltre" />
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>

<style scoped>
/* Mise en page propre à la page : ne dépend pas des classes Tailwind générées à la volée */
.acc-conteneur {
    max-width: 80rem;
    margin: 0 auto;
    padding: 1.5rem 1rem 3rem;
    display: flex;
    flex-direction: column;
    gap: 2.25rem;
}
@media (min-width: 640px) { .acc-conteneur { padding: 2rem 1.5rem 3.5rem; } }
@media (min-width: 1024px) { .acc-conteneur { padding: 2rem 2rem 4rem; } }

/* En-tête */
.acc-entete {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    overflow: hidden;
    padding: 1.5rem;
}
@media (min-width: 1024px) {
    .acc-entete { flex-direction: row; align-items: center; justify-content: space-between; padding: 1.75rem 2rem; }
}
.acc-filet { position: absolute; left: 0; top: 0; height: 4px; width: 5rem; border-radius: 0 0 9999px 0; background: #e11d48; }
.acc-entete-gauche { display: flex; align-items: center; gap: 1rem; }
.acc-tuile-xl { height: 3.25rem; width: 3.25rem; font-size: 1.25rem; }
.acc-surtitre { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255, 255, 255, 0.5); }
.acc-titre { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 900; letter-spacing: -0.02em; line-height: 1.15; }
.acc-sous-titre { margin-top: 0.15rem; font-size: 0.875rem; color: rgba(255, 255, 255, 0.6); }
.acc-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; }
.acc-stat { min-width: 6.5rem; padding: 0.7rem 1rem; border-radius: 18px; background: rgba(255, 255, 255, 0.08); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1); }
.acc-stat dt { display: flex; align-items: center; gap: 0.4rem; font-size: 0.66rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: rgba(255, 255, 255, 0.5); }
.acc-stat dd { margin-top: 0.3rem; font-size: 1.6rem; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; }
.acc-vert { color: #6ee7b7; }
.acc-rouge { color: #fb7185; }
.acc-point { display: inline-block; height: 8px; width: 8px; border-radius: 9999px; }
.acc-point-vert { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }

/* Sections */
.acc-section { display: flex; flex-direction: column; gap: 1rem; }
.acc-section-tete { display: flex; align-items: flex-start; gap: 0.85rem; padding: 0 0.25rem; }
.acc-numero {
    display: inline-grid; place-items: center; flex-shrink: 0;
    height: 2rem; width: 2rem; border-radius: 9999px;
    font-size: 0.85rem; font-weight: 900; color: #fff;
    background: linear-gradient(160deg, #f43f5e, #e11d48 55%, #be123c);
    box-shadow: 0 6px 14px -6px rgba(225, 29, 72, 0.7);
}
.acc-numero-gris { background: linear-gradient(160deg, #3a3d46, #0b0f19); box-shadow: 0 6px 14px -6px rgba(11, 15, 25, 0.5); }
.acc-h2 { font-size: 1.2rem; font-weight: 900; letter-spacing: -0.015em; color: #0b0f19; }
.acc-aide { margin-top: 0.15rem; max-width: 46rem; font-size: 0.8rem; color: #6b6e70; }

/* 1. Outils */
.acc-grille-outils { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
@media (min-width: 640px) { .acc-grille-outils { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 1100px) { .acc-grille-outils { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.acc-outil {
    display: flex; flex-direction: column; gap: 0.9rem; padding: 1.25rem;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;
}
.acc-outil:hover { transform: translateY(-3px); box-shadow: inset 0 1.5px 0 #fff, 0 24px 44px -20px rgba(225, 29, 72, 0.35); }
.acc-outil:focus-visible { outline: 2px solid #e11d48; outline-offset: 3px; }
.acc-tuile { height: 2.75rem; width: 2.75rem; font-size: 1rem; }
.acc-outil-corps { display: flex; flex: 1; flex-direction: column; gap: 0.3rem; }
.acc-outil-titre { font-size: 0.98rem; font-weight: 900; color: #0b0f19; }
.acc-outil-texte { font-size: 0.8rem; line-height: 1.45; color: #6b6e70; }
.acc-outil-pied { display: flex; align-items: center; justify-content: space-between; }
.acc-fleche { font-size: 0.75rem; color: #9ca3af; transition: transform 0.3s ease, color 0.3s ease; }
.acc-outil:hover .acc-fleche { transform: translateX(4px); color: #e11d48; }
.acc-pastille { border-radius: 9999px; padding: 0.15rem 0.6rem; font-size: 0.7rem; font-weight: 800; }
.pastille-verte { color: #047857; background: #d1fae5; }
.pastille-rouge { color: #b91c1c; background: #fee2e2; }
.pastille-neutre { color: #4b5563; background: rgba(11, 15, 25, 0.06); }

/* 2. Rôles */
.acc-grille-roles { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
@media (min-width: 640px) { .acc-grille-roles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 1100px) { .acc-grille-roles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.acc-role { display: flex; flex-direction: column; gap: 0.8rem; padding: 1.1rem; }
.acc-role::after {
    content: ''; position: absolute; left: 1.1rem; right: 1.1rem; top: 0; height: 3px;
    border-radius: 0 0 4px 4px; background: var(--role); opacity: 0.85;
}
.acc-role-tete { display: flex; align-items: flex-start; gap: 0.75rem; }
.acc-role-icone {
    display: inline-grid; place-items: center; flex-shrink: 0;
    height: 2.6rem; width: 2.6rem; border-radius: 14px;
    color: var(--role);
    background: color-mix(in srgb, var(--role) 12%, white);
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px color-mix(in srgb, var(--role) 18%, transparent);
}
.acc-role-titres { min-width: 0; }
.acc-role-titre { font-size: 0.98rem; font-weight: 900; color: #0b0f19; }
.acc-role-texte { margin-top: 0.1rem; font-size: 0.75rem; line-height: 1.4; color: #6b6e70; }
.acc-role-compte { font-size: 0.72rem; font-weight: 700; color: #6b6e70; }
.acc-en-ligne { color: #047857; }
.acc-en-ligne .acc-point { margin: 0 0.25rem 1px 0.1rem; }
.acc-comptes {
    display: flex; flex-direction: column; overflow: hidden;
    border-radius: 16px; background: rgba(255, 255, 255, 0.62);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 0 0 1px rgba(11, 15, 25, 0.06);
}
.acc-compte { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem 0.6rem; }
.acc-compte + .acc-compte { border-top: 1px solid rgba(11, 15, 25, 0.05); }
.acc-avatar {
    position: relative; display: inline-grid; place-items: center; flex-shrink: 0;
    height: 2rem; width: 2rem; border-radius: 9999px;
    font-size: 0.65rem; font-weight: 900; color: var(--role);
    background: color-mix(in srgb, var(--role) 12%, white);
}
.acc-avatar-point { position: absolute; right: -1px; bottom: -1px; height: 10px; width: 10px; border-radius: 9999px; background: #d1d5db; box-shadow: 0 0 0 2px #fff; }
.acc-avatar-point-on { background: #10b981; }
.acc-compte-nom { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.acc-compte-nom-1 { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 0.82rem; font-weight: 800; color: #0b0f19; }
.acc-compte-nom-2 { font-size: 0.68rem; color: #9ca3af; }
.acc-agir {
    display: inline-flex; align-items: center; gap: 0.35rem; flex-shrink: 0;
    min-height: 32px; padding: 0 0.75rem; border-radius: 9999px;
    font-size: 0.72rem; font-weight: 800; color: #fff;
    background: linear-gradient(180deg, #3a3d46, #0b0f19);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25), 0 6px 12px -6px rgba(11, 15, 25, 0.6);
    transition: transform 0.2s ease, filter 0.2s ease;
}
.acc-agir:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.15); }
.acc-agir:active:not(:disabled) { transform: scale(0.95); }
.acc-agir:disabled { opacity: 0.5; cursor: wait; }
.acc-agir:focus-visible { outline: 2px solid #e11d48; outline-offset: 2px; }
.acc-vide {
    display: flex; flex-direction: column; align-items: flex-start; gap: 0.3rem;
    padding: 0.8rem; border-radius: 14px; border: 1px dashed rgba(11, 15, 25, 0.15);
    font-size: 0.75rem; color: #6b6e70;
}
.acc-role-pied { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: auto; }
.acc-lien { font-size: 0.74rem; font-weight: 800; color: #0b0f19; }
.acc-lien:hover { color: #e11d48; }
.acc-lien-espace { display: inline-flex; align-items: center; gap: 0.35rem; color: var(--role); }
.acc-lien-espace i { font-size: 0.6rem; transition: transform 0.25s ease; }
.acc-lien-espace:hover i { transform: translateX(3px); }

/* 3. Espace administrateur */
.acc-ancre { scroll-margin-top: 1rem; }
.acc-section-tete-large { flex-wrap: wrap; }
.acc-section-titres { flex: 1 1 16rem; min-width: 0; }
.acc-siege { flex: 0 1 18rem; min-width: 12rem; }
</style>
