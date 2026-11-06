<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientEntete from '@/Components/ClientEntete.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    client: Object,
    portalAccess: Object,
});
const showTemporaryPassword = ref(false);

const regenerateTemporaryPassword = () => {
    if (!window.confirm('Générer un nouveau mot de passe provisoire ? L’ancien mot de passe sera invalidé.')) return;
    router.post(route('charge_client.clients.portal-password.reset', props.client.id));
};

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

/* ------------------------------------------------------------------ */
/* Cartes : chaque carte mène à sa propre vue                           */
/* ------------------------------------------------------------------ */
const requetes = computed(() => props.client.portal_requests || []);
const enAttente = (liste) => liste.filter((r) => r.statut === 'en_attente').length;

const rendezVous = computed(() => requetes.value.filter((r) => r.type === 'rendez_vous'));
const demandes = computed(() => requetes.value.filter((r) => r.type !== 'rendez_vous' && r.type !== 'message'));

const cartes = computed(() => [
    {
        cle: 'chat',
        titre: 'Messages',
        icone: 'fa-solid fa-comments',
        href: route('charge_client.clients.chat', props.client.id),
        resume: 'Conversation avec le client',
        alerte: null,
    },
    {
        cle: 'demandes',
        titre: 'Demandes',
        icone: 'fa-solid fa-truck-medical',
        href: route('charge_client.clients.demandes', props.client.id),
        resume: demandes.value.length ? pluriel(demandes.value.length, 'demande') : 'Aucune demande',
        alerte: enAttente(demandes.value)
            ? `${pluriel(enAttente(demandes.value), 'demande')} en attente`
            : null,
    },
    {
        cle: 'rendez-vous',
        titre: 'Rendez-vous',
        icone: 'fa-solid fa-calendar-check',
        href: route('charge_client.clients.rendez-vous', props.client.id),
        resume: rendezVous.value.length ? pluriel(rendezVous.value.length, 'rendez-vous') : 'Aucun rendez-vous',
        alerte: enAttente(rendezVous.value)
            ? `${enAttente(rendezVous.value)} à accepter`
            : null,
    },
]);

// Fonction pour vérifier l'expiration et la règle des 10 jours
const checkExpirationDetail = (dateStr) => {
    if (!dateStr) return { text: 'Non renseigné', class: 'bg-gray-100 text-gray-800' };
    const date = new Date(dateStr);
    const today = new Date();

    const tenDaysLimit = new Date();
    tenDaysLimit.setDate(today.getDate() + 10);

    if (date < today) {
        return { text: `Expiré le ${dateStr}`, class: 'bg-[#E11D48]/10 text-[#E11D48] border border-[#E11D48]/30 font-bold' };
    } else if (date <= tenDaysLimit) {
        return { text: `Expire bientôt (${dateStr})`, class: 'bg-amber-50 text-amber-700 border border-amber-200 font-bold' };
    }
    return { text: `Valide jusqu'au ${dateStr}`, class: 'bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold' };
};
</script>

<template>
    <Head :title="`Client : ${client.nom} — Garage Kagnan`" />

    <AuthenticatedLayout>
        <template #header>
            <ClientEntete
                :client="client"
                :href="route('charge_client.clients.index')"
                libelle-retour="Retour à la liste"
                section="Fiche client"
            />
        </template>

        <div class="py-12 bg-white min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- LES 3 CARTES -->
                <nav aria-label="Sections du client">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <Link
                            v-for="carte in cartes"
                            :key="carte.cle"
                            :href="carte.href"
                            class="group flex min-h-24 items-start gap-4 rounded-2xl border border-gray-200 bg-white p-4 text-left text-[#0B0F19] transition duration-200 hover:border-[#0B0F19] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E11D48]/40 active:scale-[0.98] sm:p-5"
                        >
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#E11D48]">
                                <i :class="carte.icone"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-extrabold">{{ carte.titre }}</p>
                                <p class="mt-0.5 truncate text-xs font-medium text-[#8A8D8F]">{{ carte.resume }}</p>
                                <span
                                    v-if="carte.alerte"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-[#E11D48] px-2 py-0.5 text-[11px] font-bold text-white"
                                >
                                    <i class="fa-solid fa-circle-exclamation text-[10px]"></i>
                                    {{ carte.alerte }}
                                </span>
                            </div>

                            <i class="fa-solid fa-arrow-right mt-1 text-xs text-[#8A8D8F] transition-transform duration-200 group-hover:translate-x-0.5"></i>
                        </Link>
                    </div>
                </nav>

                <!-- ACCÈS À L'ESPACE CLIENT (inchangé) -->
                <section class="rounded-3xl border border-gray-200 bg-[#F8FAFC] p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-black text-[#0B0F19]">Accès à l’espace client</h3>
                            <p v-if="portalAccess.passwordChangeRequired" class="mt-1 text-sm text-[#8A8D8F]">
                                Communiquez ces identifiants au client. Il devra changer son mot de passe provisoire à sa première connexion.
                            </p>
                            <p v-else class="mt-1 text-sm text-[#8A8D8F]">
                                Le client a défini son mot de passe. Vous pouvez générer un mot de passe provisoire s’il a besoin de réinitialiser son accès.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="regenerateTemporaryPassword"
                            class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#E11D48] px-4 text-xs font-bold text-white transition hover:bg-[#BE123C]"
                        >
                            Générer un nouveau mot de passe
                        </button>
                    </div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#8A8D8F]">Identifiant</p>
                            <p class="mt-1 select-all font-mono text-sm font-bold text-[#0B0F19]">{{ portalAccess.identifier }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#8A8D8F]">Mot de passe provisoire</p>
                            <div v-if="portalAccess.temporaryPassword" class="mt-1 flex items-center gap-3">
                                <p class="select-all font-mono text-sm font-bold text-[#0B0F19]">
                                    {{ showTemporaryPassword ? portalAccess.temporaryPassword : '••••••••••••••••' }}
                                </p>
                                <button
                                    type="button"
                                    @click="showTemporaryPassword = !showTemporaryPassword"
                                    class="text-xs font-bold text-[#E11D48] hover:underline"
                                >
                                    {{ showTemporaryPassword ? 'Masquer' : 'Afficher' }}
                                </button>
                            </div>
                            <p v-else class="mt-1 text-sm font-semibold text-emerald-700">
                                Aucun mot de passe provisoire en attente.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- VÉHICULES (inchangé) -->
                <h3 class="text-lg font-black text-[#0B0F19] flex items-center gap-2.5">
                    <i class="fa-solid fa-car text-[#E11D48]"></i>
                    <span>Véhicules du client</span>
                </h3>

                <div v-if="client.vehicules && client.vehicules.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="vehicule in client.vehicules" :key="vehicule.id" class="p-6 bg-[#F8FAFC] rounded-3xl border border-gray-200 hover:border-[#E11D48] transition space-y-4 shadow-xs">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="text-base font-black text-[#0B0F19]">{{ vehicule.marque }} {{ vehicule.modele }}</h4>
                                <p class="text-xs text-[#8A8D8F] font-bold uppercase tracking-wider mt-0.5">Immat : {{ vehicule.immatriculation || 'Non immatriculé' }}</p>
                            </div>
                            <Link :href="route('charge_client.vehicules.show', vehicule.id)" class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-[#0B0F19] hover:bg-[#E11D48] hover:text-white hover:border-[#E11D48] transition shadow-xs">
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </Link>
                        </div>

                        <div class="space-y-2 pt-2 border-t border-gray-200/60 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-semibold">Assurance :</span>
                                <span :class="['px-2.5 py-1 rounded-lg', checkExpirationDetail(vehicule.expiration_assurance).class]">
                                    {{ checkExpirationDetail(vehicule.expiration_assurance).text }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-semibold">Visite Technique (SICTA) :</span>
                                <span :class="['px-2.5 py-1 rounded-lg', checkExpirationDetail(vehicule.expiration_sicta).class]">
                                    {{ checkExpirationDetail(vehicule.expiration_sicta).text }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-10 text-[#8A8D8F] text-sm bg-[#F8FAFC] rounded-3xl border border-dashed border-gray-200 font-medium">
                    Ce client n'a aucun véhicule enregistré.
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>