<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    comptesParRole: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
});

const page = usePage();
const prenom = computed(() => (page.props.auth.user?.name || '').split(' ')[0]);
const maintenance = computed(() => !!page.props.parametres?.maintenance);

const salutation = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Bonjour' : h < 18 ? 'Bon après-midi' : 'Bonsoir';
});

// Points d'attention affichés sous le titre (seulement s'il y a quelque chose à signaler)
const signaux = computed(() => [
    maintenance.value && { texte: 'Maintenance active', icone: 'fa-person-digging', route: 'superadmin.systeme', params: { onglet: 'maintenance' }, classe: 'acc-signal-ambre' },
    props.stats.erreurs_ouvertes && { texte: `${props.stats.erreurs_ouvertes} erreur(s) à traiter`, icone: 'fa-bug', route: 'superadmin.erreurs.index', classe: 'acc-signal-rouge' },
    props.stats.alertes_7j && { texte: `${props.stats.alertes_7j} action(s) sensible(s) cette semaine`, icone: 'fa-shield-halved', route: 'superadmin.controle', classe: 'acc-signal-ambre' },
    props.stats.suspendus && { texte: `${props.stats.suspendus} compte(s) suspendu(s)`, icone: 'fa-user-slash', route: 'superadmin.securite', classe: 'acc-signal-neutre' },
].filter(Boolean));

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

const APERCU = 2;
const deplies = ref(new Set());
const basculer = (cle) => {
    const s = new Set(deplies.value);
    s.has(cle) ? s.delete(cle) : s.add(cle);
    deplies.value = s;
};
const visibles = (cle) => (deplies.value.has(cle) ? comptes(cle) : comptes(cle).slice(0, APERCU));

const enCours = ref(null);
const agirEnTantQue = (compte) => {
    enCours.value = compte.id;
    router.post(route('superadmin.impersonate', compte.id), {}, { onFinish: () => { enCours.value = null; } });
};

const initiales = (nom = '') => nom.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase() || '?';

// ─── Outils du super administrateur, regroupés ──────────────────────
const GROUPES = computed(() => [
    {
        titre: 'Surveiller',
        texte: 'Ce qui se passe, en direct.',
        icone: 'fa-eye',
        outils: [
            { titre: 'Supervision en direct', texte: 'Qui est connecté et ce qu’il fait', icone: 'fa-satellite-dish', route: 'superadmin.supervision.index',
                badge: props.stats.en_ligne ? `${props.stats.en_ligne} en ligne` : null, badgeClasse: 'pastille-verte' },
            { titre: 'Erreurs', texte: 'Serveur et navigateur', icone: 'fa-bug', route: 'superadmin.erreurs.index',
                badge: props.stats.erreurs_ouvertes ? `${props.stats.erreurs_ouvertes}` : null, badgeClasse: 'pastille-rouge' },
            { titre: 'Contrôle & anti-fraude', texte: 'Actions sensibles, avant / après, inventaire', icone: 'fa-shield-halved', route: 'superadmin.controle',
                badge: props.stats.alertes_7j ? `${props.stats.alertes_7j}` : null, badgeClasse: 'pastille-ambre' },
            { titre: 'Sécurité des comptes', texte: 'Connexions, sessions, suspensions', icone: 'fa-user-lock', route: 'superadmin.securite',
                badge: props.stats.suspendus ? `${props.stats.suspendus} suspendu(s)` : null, badgeClasse: 'pastille-neutre' },
        ],
    },
    {
        titre: 'Analyser',
        texte: 'Les chiffres, pour décider.',
        icone: 'fa-chart-line',
        outils: [
            { titre: 'Comparatif des sièges', texte: 'CA, impayés, délais, stock', icone: 'fa-building', route: 'superadmin.sieges' },
            { titre: 'Performance des équipes', texte: 'Métier par métier', icone: 'fa-people-group', route: 'superadmin.equipes' },
            { titre: 'Rapports', texte: 'Chaque soir et chaque lundi, en PDF', icone: 'fa-file-lines', route: 'superadmin.rapports.index' },
            { titre: 'Petites fournitures', texte: 'Facturées et encaissées', icone: 'fa-screwdriver-wrench', route: 'superadmin.petites-fournitures' },
        ],
    },
    {
        titre: 'Configurer',
        texte: 'Les réglages de l’application.',
        icone: 'fa-sliders',
        outils: [
            { titre: 'Outils système', texte: 'TVA, seuils, horaires', icone: 'fa-gears', route: 'superadmin.systeme' },
            { titre: 'Maintenance', texte: maintenance.value ? 'Active : l’appli est fermée' : 'Fermer l’appli le temps d’une intervention', icone: 'fa-person-digging', route: 'superadmin.systeme', params: { onglet: 'maintenance' },
                badge: maintenance.value ? 'Active' : null, badgeClasse: 'pastille-ambre' },
            { titre: 'Annonces', texte: 'Bandeau pour tous ou certains rôles', icone: 'fa-bullhorn', route: 'superadmin.systeme', params: { onglet: 'annonces' } },
            { titre: 'Comptes & administrateurs', texte: 'Créer, modifier, supprimer', icone: 'fa-user-gear', route: 'superadmin.espace-admin', ancre: '#employes',
                badge: `${props.stats.administrateurs} admin${props.stats.administrateurs > 1 ? 's' : ''}`, badgeClasse: 'pastille-neutre' },
        ],
    },
]);
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
                    <span class="acc-halo" aria-hidden="true"></span>
                    <div class="acc-entete-haut">
                        <div class="acc-entete-gauche">
                            <span class="sg-tile sg-tile-red acc-tuile-xl"><i class="fa-solid fa-crown" aria-hidden="true"></i></span>
                            <div>
                                <p class="acc-surtitre">Super administrateur</p>
                                <h1 class="acc-titre">{{ salutation }} {{ prenom }}</h1>
                                <p class="acc-sous-titre">Entrez dans n’importe quel espace, ou pilotez l’ensemble du garage.</p>
                            </div>
                        </div>
                        <dl class="acc-stats">
                            <div class="acc-stat">
                                <dt><span class="acc-point acc-point-vert" aria-hidden="true"></span>En ligne</dt>
                                <dd class="acc-vert">{{ stats.en_ligne }}</dd>
                            </div>
                            <div class="acc-stat">
                                <dt>Comptes</dt>
                                <dd>{{ stats.comptes }}</dd>
                            </div>
                            <div class="acc-stat">
                                <dt>Actions auj.</dt>
                                <dd>{{ stats.actions_aujourdhui }}</dd>
                            </div>
                        </dl>
                    </div>
                    <nav v-if="signaux.length" class="acc-signaux" aria-label="Points d'attention">
                        <Link v-for="s in signaux" :key="s.texte" :href="route(s.route, s.params || {})" class="acc-signal" :class="s.classe">
                            <i class="fa-solid" :class="s.icone" aria-hidden="true"></i>{{ s.texte }}
                        </Link>
                    </nav>
                    <p v-else class="acc-tout-va-bien"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Rien à signaler : aucune erreur ouverte ni action sensible cette semaine.</p>
                </header>

                <!-- ═══ 1. AGIR EN TANT QUE ═══ -->
                <section class="acc-section sg-rise" style="--d: 70ms" aria-labelledby="titre-roles">
                    <div class="acc-section-tete">
                        <span class="acc-numero">1</span>
                        <div>
                            <h2 id="titre-roles" class="acc-h2">Agir en tant que…</h2>
                            <p class="acc-aide">Ouvrez l’espace d’un rôle ou le compte d’un collaborateur. Un bandeau permet de revenir ; tout reste tracé.</p>
                        </div>
                    </div>

                    <!-- Carte Administrateur (tableau de bord admin) -->
                    <Link :href="route('superadmin.espace-admin')" class="sg-glass sg-glass-dark acc-admin group">
                        <span class="acc-admin-motif" aria-hidden="true"></span>
                        <span class="sg-tile sg-tile-red acc-admin-icone"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>
                        <span class="acc-admin-corps">
                            <span class="acc-admin-sur">Rôle principal</span>
                            <span class="acc-admin-titre">Administrateur</span>
                            <span class="acc-admin-texte">Le tableau de bord admin complet : chiffre d’affaires, devis, pièces, suivi des véhicules et employés, siège par siège.</span>
                        </span>
                        <span class="acc-admin-puces" aria-hidden="true">
                            <span><i class="fa-solid fa-chart-line"></i>CA</span>
                            <span><i class="fa-solid fa-file-signature"></i>Devis</span>
                            <span><i class="fa-solid fa-gears"></i>Pièces</span>
                            <span><i class="fa-solid fa-users"></i>Employés</span>
                        </span>
                        <span class="acc-admin-cta">
                            Ouvrir l’espace admin<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </span>
                    </Link>

                    <div class="acc-grille-roles">
                        <article v-for="r in ROLES" :key="r.cle" class="sg-glass sg-glass-strong acc-role" :style="{ '--role': r.couleur }">
                            <header class="acc-role-tete">
                                <span class="acc-role-icone"><i class="fa-solid" :class="r.icone" aria-hidden="true"></i></span>
                                <div class="acc-role-titres">
                                    <h3 class="acc-role-titre">{{ r.label }}</h3>
                                    <p class="acc-role-compte">
                                        {{ comptes(r.cle).length }} compte{{ comptes(r.cle).length > 1 ? 's' : '' }}
                                        <span v-if="enLigne(r.cle)" class="acc-en-ligne">· <span class="acc-point acc-point-vert" aria-hidden="true"></span>{{ enLigne(r.cle) }} en ligne</span>
                                    </p>
                                </div>
                            </header>
                            <p class="acc-role-texte">{{ r.texte }}</p>

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
                                        :title="`Agir en tant que ${c.name}`"
                                        @click="agirEnTantQue(c)"
                                    >
                                        <i class="fa-solid" :class="enCours === c.id ? 'fa-circle-notch fa-spin' : 'fa-user-secret'" aria-hidden="true"></i>
                                    </button>
                                </li>
                            </ul>
                            <div v-else class="acc-vide">
                                Aucun compte.
                                <Link :href="route('admin.users.create')" class="acc-lien">Créer un compte</Link>
                            </div>

                            <footer class="acc-role-pied">
                                <button v-if="comptes(r.cle).length > APERCU" type="button" class="acc-lien" :aria-expanded="deplies.has(r.cle)" @click="basculer(r.cle)">
                                    {{ deplies.has(r.cle) ? 'Réduire' : `+ ${comptes(r.cle).length - APERCU} autre(s)` }}
                                </button>
                                <span v-else></span>
                                <Link v-if="r.espace" :href="route(r.espace.route)" class="acc-lien acc-lien-espace" :title="`Ouvrir l’espace ${r.espace.label} avec vos droits de super administrateur`">
                                    {{ r.espace.label }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </Link>
                            </footer>
                        </article>
                    </div>
                </section>

                <!-- ═══ 2. OUTILS DU SUPER ADMINISTRATEUR ═══ -->
                <section class="acc-section sg-rise" style="--d: 140ms" aria-labelledby="titre-outils">
                    <div class="acc-section-tete">
                        <span class="acc-numero acc-numero-encre">2</span>
                        <div>
                            <h2 id="titre-outils" class="acc-h2">Vos outils de super administrateur</h2>
                            <p class="acc-aide">Réservés à vous seul.</p>
                        </div>
                    </div>

                    <div class="acc-grille-groupes">
                        <div v-for="g in GROUPES" :key="g.titre" class="sg-glass sg-glass-strong acc-groupe">
                            <div class="acc-groupe-tete">
                                <span class="sg-tile sg-tile-ink acc-groupe-icone"><i class="fa-solid" :class="g.icone" aria-hidden="true"></i></span>
                                <div>
                                    <h3 class="acc-groupe-titre">{{ g.titre }}</h3>
                                    <p class="acc-groupe-texte">{{ g.texte }}</p>
                                </div>
                            </div>
                            <ul class="acc-outils">
                                <li v-for="o in g.outils" :key="o.titre">
                                    <Link :href="route(o.route, o.params || {}) + (o.ancre || '')" class="acc-outil">
                                        <span class="acc-outil-icone"><i class="fa-solid" :class="o.icone" aria-hidden="true"></i></span>
                                        <span class="acc-outil-corps">
                                            <span class="acc-outil-titre">{{ o.titre }}</span>
                                            <span class="acc-outil-texte">{{ o.texte }}</span>
                                        </span>
                                        <span v-if="o.badge" class="acc-pastille" :class="o.badgeClasse">{{ o.badge }}</span>
                                        <i class="fa-solid fa-chevron-right acc-fleche" aria-hidden="true"></i>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
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
    gap: 2.5rem;
}
@media (min-width: 640px) { .acc-conteneur { padding: 2rem 1.5rem 3.5rem; } }
@media (min-width: 1024px) { .acc-conteneur { padding: 2rem 2rem 4rem; } }

/* ─── En-tête ─── */
.acc-entete { display: flex; flex-direction: column; gap: 1.25rem; overflow: hidden; padding: 1.5rem; }
@media (min-width: 1024px) { .acc-entete { padding: 1.9rem 2.1rem; } }
.acc-entete-haut { display: flex; flex-direction: column; gap: 1.5rem; }
@media (min-width: 1024px) { .acc-entete-haut { flex-direction: row; align-items: center; justify-content: space-between; } }
.acc-filet { position: absolute; left: 0; top: 0; height: 4px; width: 5rem; border-radius: 0 0 9999px 0; background: #e11d48; }
.acc-halo {
    position: absolute; right: -6rem; top: -8rem; z-index: -1; height: 20rem; width: 20rem; border-radius: 9999px;
    background: radial-gradient(circle, rgba(225, 29, 72, 0.35), rgba(225, 29, 72, 0) 70%);
    pointer-events: none;
}
.acc-entete-gauche { display: flex; align-items: center; gap: 1rem; }
.acc-tuile-xl { height: 3.4rem; width: 3.4rem; font-size: 1.3rem; }
.acc-surtitre { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255, 255, 255, 0.5); }
.acc-titre { font-size: clamp(1.5rem, 3vw, 2.1rem); font-weight: 900; letter-spacing: -0.02em; line-height: 1.15; }
.acc-sous-titre { margin-top: 0.15rem; font-size: 0.875rem; color: rgba(255, 255, 255, 0.6); }
.acc-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; }
.acc-stat { min-width: 6rem; padding: 0.7rem 1rem; border-radius: 18px; background: rgba(255, 255, 255, 0.08); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1); }
.acc-stat dt { display: flex; align-items: center; gap: 0.4rem; font-size: 0.66rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: rgba(255, 255, 255, 0.5); }
.acc-stat dd { margin-top: 0.3rem; font-size: 1.6rem; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; }
.acc-vert { color: #6ee7b7; }
.acc-point { display: inline-block; height: 8px; width: 8px; border-radius: 9999px; }
.acc-point-vert { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }

.acc-signaux { display: flex; flex-wrap: wrap; gap: 0.5rem; padding-top: 1.1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
.acc-signal {
    display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.4rem 0.8rem; border-radius: 9999px;
    font-size: 0.74rem; font-weight: 800; transition: transform 0.2s ease, filter 0.2s ease;
}
.acc-signal:hover { transform: translateY(-1px); filter: brightness(1.12); }
.acc-signal:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
.acc-signal-rouge { color: #fecdd3; background: rgba(225, 29, 72, 0.25); box-shadow: inset 0 0 0 1px rgba(251, 113, 133, 0.4); }
.acc-signal-ambre { color: #fde68a; background: rgba(245, 158, 11, 0.2); box-shadow: inset 0 0 0 1px rgba(251, 191, 36, 0.35); }
.acc-signal-neutre { color: rgba(255, 255, 255, 0.85); background: rgba(255, 255, 255, 0.1); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.15); }
.acc-tout-va-bien { display: flex; align-items: center; gap: 0.5rem; padding-top: 1.1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.8rem; color: #6ee7b7; }

/* ─── Sections ─── */
.acc-section { display: flex; flex-direction: column; gap: 1rem; }
.acc-section-tete { display: flex; align-items: flex-start; gap: 0.85rem; padding: 0 0.25rem; }
.acc-numero {
    display: inline-grid; place-items: center; flex-shrink: 0;
    height: 2rem; width: 2rem; border-radius: 9999px;
    font-size: 0.85rem; font-weight: 900; color: #fff;
    background: linear-gradient(160deg, #f43f5e, #e11d48 55%, #be123c);
    box-shadow: 0 6px 14px -6px rgba(225, 29, 72, 0.7);
}
.acc-numero-encre { background: linear-gradient(160deg, #3a3d46, #0b0f19); box-shadow: 0 6px 14px -6px rgba(11, 15, 25, 0.5); }
.acc-h2 { font-size: 1.2rem; font-weight: 900; letter-spacing: -0.015em; color: #0b0f19; }
.acc-aide { margin-top: 0.15rem; max-width: 46rem; font-size: 0.8rem; color: #6b6e70; }

/* ─── Carte Administrateur ─── */
.acc-admin {
    display: grid; gap: 1rem; overflow: hidden; padding: 1.4rem;
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas: 'icone corps' 'puces puces' 'cta cta';
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;
}
@media (min-width: 900px) {
    .acc-admin { grid-template-columns: auto minmax(0, 1fr) auto; grid-template-areas: 'icone corps cta' 'icone puces cta'; align-items: center; padding: 1.6rem 1.9rem; column-gap: 1.4rem; }
}
.acc-admin:hover { transform: translateY(-3px); box-shadow: inset 0 1.5px 0 rgba(255, 255, 255, 0.2), 0 28px 50px -22px rgba(225, 29, 72, 0.55); }
.acc-admin:focus-visible { outline: 2px solid #e11d48; outline-offset: 3px; }
.acc-admin-motif {
    position: absolute; inset: 0; z-index: -1; pointer-events: none;
    background:
        radial-gradient(500px 220px at 100% 100%, rgba(225, 29, 72, 0.32), transparent 70%),
        repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.035) 0 1px, transparent 1px 14px);
}
.acc-admin-icone { grid-area: icone; height: 3.6rem; width: 3.6rem; font-size: 1.35rem; align-self: start; }
@media (min-width: 900px) { .acc-admin-icone { align-self: center; height: 4.2rem; width: 4.2rem; font-size: 1.6rem; } }
.acc-admin-corps { grid-area: corps; display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
.acc-admin-sur { font-size: 0.66rem; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: #fb7185; }
.acc-admin-titre { font-size: 1.35rem; font-weight: 900; letter-spacing: -0.02em; color: #fff; }
.acc-admin-texte { font-size: 0.82rem; line-height: 1.45; color: rgba(255, 255, 255, 0.65); max-width: 40rem; }
.acc-admin-puces { grid-area: puces; display: flex; flex-wrap: wrap; gap: 0.45rem; }
.acc-admin-puces span {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.7rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 800; color: rgba(255, 255, 255, 0.85);
    background: rgba(255, 255, 255, 0.08); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
}
.acc-admin-puces i { font-size: 0.62rem; color: #fb7185; }
.acc-admin-cta {
    grid-area: cta; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem;
    min-height: 46px; padding: 0 1.4rem; border-radius: 9999px;
    font-size: 0.85rem; font-weight: 800; color: #fff; white-space: nowrap;
    background: linear-gradient(180deg, #f43f5e, #e11d48 55%, #be123c);
    box-shadow: inset 0 1.5px 0 rgba(255, 255, 255, 0.45), 0 10px 22px -10px rgba(225, 29, 72, 0.8);
}
.acc-admin-cta i { font-size: 0.75rem; transition: transform 0.3s ease; }
.acc-admin:hover .acc-admin-cta i { transform: translateX(4px); }

/* ─── Rôles ─── */
.acc-grille-roles { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
@media (min-width: 560px) { .acc-grille-roles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 1100px) { .acc-grille-roles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.acc-role {
    display: flex; flex-direction: column; gap: 0.7rem; padding: 1.05rem;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;
}
.acc-role:hover { transform: translateY(-2px); box-shadow: inset 0 1.5px 0 #fff, 0 22px 40px -22px color-mix(in srgb, var(--role) 55%, transparent); }
.acc-role::after {
    content: ''; position: absolute; left: 1.05rem; right: 1.05rem; top: 0; height: 3px;
    border-radius: 0 0 4px 4px; background: var(--role); opacity: 0.85;
}
.acc-role-tete { display: flex; align-items: center; gap: 0.7rem; }
.acc-role-icone {
    display: inline-grid; place-items: center; flex-shrink: 0;
    height: 2.5rem; width: 2.5rem; border-radius: 14px;
    color: var(--role);
    background: color-mix(in srgb, var(--role) 12%, white);
    box-shadow: inset 0 1px 0 #fff, 0 0 0 1px color-mix(in srgb, var(--role) 18%, transparent);
}
.acc-role-titres { min-width: 0; }
.acc-role-titre { font-size: 0.95rem; font-weight: 900; color: #0b0f19; }
.acc-role-texte { font-size: 0.74rem; line-height: 1.4; color: #6b6e70; }
.acc-role-compte { font-size: 0.7rem; font-weight: 700; color: #8a8d8f; }
.acc-en-ligne { color: #047857; }
.acc-en-ligne .acc-point { margin: 0 0.25rem 1px 0.1rem; }
.acc-comptes {
    display: flex; flex-direction: column; overflow: hidden;
    border-radius: 14px; background: rgba(255, 255, 255, 0.7);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 0 0 1px rgba(11, 15, 25, 0.06);
}
.acc-compte { display: flex; align-items: center; gap: 0.55rem; padding: 0.45rem 0.5rem; }
.acc-compte + .acc-compte { border-top: 1px solid rgba(11, 15, 25, 0.05); }
.acc-avatar {
    position: relative; display: inline-grid; place-items: center; flex-shrink: 0;
    height: 1.9rem; width: 1.9rem; border-radius: 9999px;
    font-size: 0.62rem; font-weight: 900; color: var(--role);
    background: color-mix(in srgb, var(--role) 12%, white);
}
.acc-avatar-point { position: absolute; right: -1px; bottom: -1px; height: 9px; width: 9px; border-radius: 9999px; background: #d1d5db; box-shadow: 0 0 0 2px #fff; }
.acc-avatar-point-on { background: #10b981; }
.acc-compte-nom { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.acc-compte-nom-1 { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 0.8rem; font-weight: 800; color: #0b0f19; }
.acc-compte-nom-2 { font-size: 0.66rem; color: #9ca3af; }
.acc-agir {
    display: inline-grid; place-items: center; flex-shrink: 0;
    height: 30px; width: 30px; border-radius: 9999px;
    font-size: 0.72rem; color: #fff;
    background: linear-gradient(180deg, #3a3d46, #0b0f19);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25), 0 6px 12px -6px rgba(11, 15, 25, 0.6);
    transition: transform 0.2s ease, background 0.2s ease;
}
.acc-agir:hover:not(:disabled) { transform: translateY(-1px); background: var(--role); }
.acc-agir:active:not(:disabled) { transform: scale(0.92); }
.acc-agir:disabled { opacity: 0.5; cursor: wait; }
.acc-agir:focus-visible { outline: 2px solid #e11d48; outline-offset: 2px; }
.acc-vide {
    display: flex; align-items: center; justify-content: space-between; gap: 0.3rem;
    padding: 0.6rem 0.75rem; border-radius: 14px; border: 1px dashed rgba(11, 15, 25, 0.15);
    font-size: 0.74rem; color: #6b6e70;
}
.acc-role-pied { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: auto; }
.acc-lien { font-size: 0.73rem; font-weight: 800; color: #0b0f19; }
.acc-lien:hover { color: #e11d48; }
.acc-lien-espace { display: inline-flex; align-items: center; gap: 0.35rem; color: var(--role); }
.acc-lien-espace i { font-size: 0.6rem; transition: transform 0.25s ease; }
.acc-lien-espace:hover i { transform: translateX(3px); }

/* ─── Outils, regroupés ─── */
.acc-grille-groupes { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
@media (min-width: 1024px) { .acc-grille-groupes { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
.acc-groupe { display: flex; flex-direction: column; gap: 0.9rem; padding: 1.15rem; }
.acc-groupe-tete { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.15rem; }
.acc-groupe-icone { height: 2.4rem; width: 2.4rem; font-size: 0.9rem; }
.acc-groupe-titre { font-size: 1rem; font-weight: 900; color: #0b0f19; }
.acc-groupe-texte { font-size: 0.74rem; color: #6b6e70; }
.acc-outils {
    display: flex; flex-direction: column; overflow: hidden; border-radius: 16px;
    background: rgba(255, 255, 255, 0.62);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 0 0 1px rgba(11, 15, 25, 0.06);
}
.acc-outils li + li { border-top: 1px solid rgba(11, 15, 25, 0.05); }
.acc-outil { display: flex; align-items: center; gap: 0.7rem; padding: 0.7rem 0.8rem; transition: background 0.2s ease; }
.acc-outil:hover { background: rgba(255, 255, 255, 0.95); }
.acc-outil:focus-visible { outline: 2px solid #e11d48; outline-offset: -2px; border-radius: 12px; }
.acc-outil-icone {
    display: inline-grid; place-items: center; flex-shrink: 0; height: 2.1rem; width: 2.1rem; border-radius: 12px;
    font-size: 0.8rem; color: #e11d48; background: rgba(225, 29, 72, 0.08);
    transition: background 0.2s ease, color 0.2s ease;
}
.acc-outil:hover .acc-outil-icone { color: #fff; background: #e11d48; }
.acc-outil-corps { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.acc-outil-titre { font-size: 0.84rem; font-weight: 800; color: #0b0f19; }
.acc-outil-texte { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 0.7rem; color: #8a8d8f; }
.acc-fleche { font-size: 0.65rem; color: #c0c3c7; transition: transform 0.25s ease, color 0.25s ease; }
.acc-outil:hover .acc-fleche { transform: translateX(3px); color: #e11d48; }
.acc-pastille { flex-shrink: 0; border-radius: 9999px; padding: 0.12rem 0.55rem; font-size: 0.68rem; font-weight: 800; }
.pastille-verte { color: #047857; background: #d1fae5; }
.pastille-rouge { color: #b91c1c; background: #fee2e2; }
.pastille-ambre { color: #92400e; background: #fef3c7; }
.pastille-neutre { color: #4b5563; background: rgba(11, 15, 25, 0.06); }

@media (prefers-reduced-motion: reduce) {
    .acc-admin, .acc-role, .acc-signal, .acc-agir, .acc-fleche, .acc-admin-cta i { transition: none; }
}
</style>
