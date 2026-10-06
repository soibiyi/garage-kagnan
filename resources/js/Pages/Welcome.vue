<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="Garage Kagnan — Plateforme de Gestion" />

    <div class="min-h-screen bg-[#F8FAFC] font-sans text-[#0B0F19] selection:bg-[#E11D48] selection:text-white">
        
        <!-- HEADER / NAVIGATION -->
        <header class="fixed top-0 left-0 right-0 z-50 bg-[#0B0F19]/90 backdrop-blur-md border-b border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                
                <!-- Logo -->                   
                <Link :href="route('dashboard')" class="flex items-center">
                    <div class="rounded-xl bg-white px-3 py-1.5 shadow-md transition duration-300 hover:scale-105">
                        <img
                            src="/images/logo-kagnan.png"
                            alt="Garage Kagnan"
                            class="h-10 w-auto object-contain sm:h-12"
                        />
                    </div>
                </Link>

                <!-- Liens de navigation ancrés -->
                <nav class="hidden md:flex items-center gap-8 text-xs font-extrabold uppercase tracking-wider text-gray-300">
                    <a href="#guide" class="hover:text-[#E11D48] transition">Guide du Site</a>
                    <a href="#modules" class="hover:text-[#E11D48] transition">Modules Métier</a>
                    <a href="#roles" class="hover:text-[#E11D48] transition">Rôles & Accès</a>
                    <a href="#workflow" class="hover:text-[#E11D48] transition">Processus Atelier</a>
                    <a href="#contact" class="hover:text-[#E11D48] transition">Contact</a>
                </nav>

                <!-- Actions Authentification -->
                <div class="flex items-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Link :href="route('dashboard')" class="px-5 py-2.5 rounded-xl bg-[#E11D48] text-white text-xs font-extrabold uppercase tracking-wider hover:bg-[#BE123C] transition duration-200 flex items-center gap-2 shadow-lg shadow-[#E11D48]/20">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Tableau de bord</span>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="px-4 py-2 text-xs font-extrabold uppercase tracking-wider text-gray-300 hover:text-white transition">
                            Connexion
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="px-5 py-2.5 rounded-xl bg-[#E11D48] text-white text-xs font-extrabold uppercase tracking-wider hover:bg-[#BE123C] transition duration-200 flex items-center gap-2 shadow-lg shadow-[#E11D48]/20">
                            <span>Commencer</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- HERO SECTION (SOMBRE & PREMIUM) -->
        <section class="relative pt-32 pb-20 md:pt-40 md:pb-28 bg-[#0B0F19] text-white overflow-hidden">
            <!-- Effets de fond (Lueurs rouges subtiles) -->
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-[#E11D48]/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute top-1/2 right-0 w-[500px] h-[500px] bg-[#E11D48]/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    
                    <!-- Colonne Gauche : Texte & Boutons -->
                    <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-bold text-gray-300 backdrop-blur-sm">
                            <span class="w-2 h-2 rounded-full bg-[#E11D48] animate-pulse"></span>
                            Plateforme de Gestion Intégrée — Garage Kagnan
                        </div>

                        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-[1.1]">
                            L'excellence mécanique au service de votre <span class="text-[#E11D48]">flotte automobile</span>.
                        </h1>

                        <p class="text-base sm:text-lg text-gray-400 font-medium leading-relaxed max-w-2xl">
                            Supervision complète des véhicules en réception, ordres de travail, devis dynamiques et facturation normalisée. Un outil conçu sur mesure pour la rigueur opérationnelle.
                        </p>

                        <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-4">
                            <template v-if="$page.props.auth.user">
                                <Link :href="route('dashboard')" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-[#E11D48] text-white font-extrabold uppercase tracking-wider text-xs hover:bg-[#BE123C] transition duration-200 flex items-center justify-center gap-3 shadow-xl shadow-[#E11D48]/25">
                                    <i class="fa-solid fa-chart-pie text-sm"></i>
                                    <span>Accéder à votre espace</span>
                                </Link>
                            </template>
                            <template v-else>
                                <Link :href="route('login')" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-[#E11D48] text-white font-extrabold uppercase tracking-wider text-xs hover:bg-[#BE123C] transition duration-200 flex items-center justify-center gap-3 shadow-xl shadow-[#E11D48]/25">
                                    <i class="fa-solid fa-right-to-bracket text-sm"></i>
                                    <span>Se connecter au portail</span>
                                </Link>
                                <a href="#guide" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-white/5 border border-white/15 text-white font-extrabold uppercase tracking-wider text-xs hover:bg-white/10 transition duration-200 flex items-center justify-center gap-3">
                                    <i class="fa-solid fa-compass text-gray-400"></i>
                                    <span>Découvrir le guide</span>
                                </a>
                            </template>
                        </div>
                    </div>

                    <!-- Colonne Droite : APERÇU UI / MOCKUP DYNAMIQUE -->
                    <div class="lg:col-span-5 relative">
                        <div class="relative bg-gradient-to-b from-gray-800/80 to-gray-900/90 border border-white/10 rounded-3xl p-6 shadow-2xl shadow-black/50 backdrop-blur-xl">
                            
                            <!-- Header de la Fausse Carte UI -->
                            <div class="flex items-center justify-between pb-4 border-b border-gray-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                </div>
                                <span class="text-[10px] font-black tracking-widest text-gray-400 uppercase">Aperçu Dossier — OT-2026-084</span>
                            </div>

                            <!-- Contenu UI Fictif -->
                            <div class="mt-5 space-y-4 text-left">
                                <div class="flex items-center justify-between bg-white/5 p-3.5 rounded-xl border border-white/5">
                                    <div>
                                        <p class="text-xs font-bold text-white">Toyota Hilux — 4589-HX-01</p>
                                        <p class="text-[11px] text-gray-400 font-medium">Client : Groupe SODECI</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-[#E11D48]/20 text-[#E11D48] border border-[#E11D48]/30 uppercase">
                                        En Atelier
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div class="p-3 bg-white/5 rounded-xl border border-white/5">
                                        <p class="text-[10px] text-gray-400 uppercase font-bold">Kilométrage</p>
                                        <p class="font-extrabold text-white mt-0.5">124,500 km</p>
                                    </div>
                                    <div class="p-3 bg-white/5 rounded-xl border border-white/5">
                                        <p class="text-[10px] text-gray-400 uppercase font-bold">Avance Devis</p>
                                        <p class="font-extrabold text-emerald-400 mt-0.5">70% Reçue</p>
                                    </div>
                                </div>

                                <div class="p-3.5 bg-white/5 rounded-xl border border-white/5 space-y-2">
                                    <div class="flex justify-between text-[11px] font-bold">
                                        <span class="text-gray-300">Remplacement Kit Frein</span>
                                        <span class="text-white">45,000 FCFA</span>
                                    </div>
                                    <div class="flex justify-between text-[11px] font-bold">
                                        <span class="text-gray-300">Vidange Moteur & Filtres</span>
                                        <span class="text-white">32,500 FCFA</span>
                                    </div>
                                    <div class="pt-2 border-t border-gray-700/60 flex justify-between text-xs font-black">
                                        <span class="text-[#E11D48]">Total Net TTC</span>
                                        <span class="text-white">77,500 FCFA</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- INDICATEURS CLÉS (BARRE DU BAS EN HERO) -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-16 pt-10 border-t border-gray-800/80">
                    <div class="bg-white/5 border border-white/10 p-5 rounded-2xl text-center backdrop-blur-xs">
                        <div class="text-2xl sm:text-3xl font-black text-white">100%</div>
                        <div class="text-[11px] text-gray-400 font-extrabold uppercase mt-1">Traçabilité des Entrées</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 p-5 rounded-2xl text-center backdrop-blur-xs">
                        <div class="text-2xl sm:text-3xl font-black text-[#E11D48]">70%</div>
                        <div class="text-[11px] text-gray-400 font-extrabold uppercase mt-1">Avance Conditionnelle</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 p-5 rounded-2xl text-center backdrop-blur-xs">
                        <div class="text-2xl sm:text-3xl font-black text-white">Temps Réel</div>
                        <div class="text-[11px] text-gray-400 font-extrabold uppercase mt-1">Suivi Atelier & Dossiers</div>
                    </div>
                    <div class="bg-white/5 border border-white/10 p-5 rounded-2xl text-center backdrop-blur-xs">
                        <div class="text-2xl sm:text-3xl font-black text-[#E11D48]">A4 / Print</div>
                        <div class="text-[11px] text-gray-400 font-extrabold uppercase mt-1">Documents Normalisés</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- GUIDE ET EXPLICATIONS DETAILLEES DES MODULES -->
        <section id="guide" class="py-24 bg-white relative border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                
                <div class="text-center max-w-2xl mx-auto space-y-3">
                    <h2 class="text-xs font-extrabold tracking-widest text-[#E11D48] uppercase">Visite Guidée de la Plateforme</h2>
                    <h3 class="text-3xl font-black text-[#0B0F19]">Comment fonctionne l'application ?</h3>
                    <p class="text-sm text-[#8A8D8F] font-medium">Une architecture conçue pour orchestrer chaque phase de prise en charge d'un véhicule au garage.</p>
                </div>

                <div id="modules" class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <!-- Bloc 1 -->
                    <div class="bg-[#F8FAFC] border border-gray-200 rounded-3xl p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:border-[#E11D48] space-y-6 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="w-14 h-14 rounded-2xl bg-[#0B0F19] text-white flex items-center justify-center shadow-md">
                                <i class="fa-solid fa-car-tunnel text-2xl"></i>
                            </div>
                            <h4 class="text-xl font-black text-[#0B0F19]">1. Réception & Parc</h4>
                            <p class="text-xs text-gray-600 leading-relaxed font-medium">
                                Enregistrement initial complet : immatriculation, kilométrage, niveau d'essence, inventaire des accessoires (cric, roue de secours, autoradio) et clichés photo des 4 faces pour verrouiller l'état d'entrée.
                            </p>
                        </div>
                        <div class="pt-4 border-t border-gray-200/60 flex items-center justify-between text-xs font-black text-[#0B0F19]">
                            <span>Module Réception</span>
                            <i class="fa-solid fa-arrow-right text-[#E11D48]"></i>
                        </div>
                    </div>

                    <!-- Bloc 2 -->
                    <div class="bg-[#F8FAFC] border border-gray-200 rounded-3xl p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:border-[#E11D48] space-y-6 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="w-14 h-14 rounded-2xl bg-[#E11D48] text-white flex items-center justify-center shadow-md">
                                <i class="fa-solid fa-file-invoice-dollar text-2xl"></i>
                            </div>
                            <h4 class="text-xl font-black text-[#0B0F19]">2. Devis & Prestations</h4>
                            <p class="text-xs text-gray-600 leading-relaxed font-medium">
                                Saisie rigoureuse des devis associés aux interventions. Gestion détaillée des lignes (quantités, pièces, prix unitaire net, remises et calculs de la TVA à 18% ou exonération).
                            </p>
                        </div>
                        <div class="pt-4 border-t border-gray-200/60 flex items-center justify-between text-xs font-black text-[#E11D48]">
                            <span>Module Administration</span>
                            <i class="fa-solid fa-arrow-right text-[#E11D48]"></i>
                        </div>
                    </div>

                    <!-- Bloc 3 -->
                    <div class="bg-[#F8FAFC] border border-gray-200 rounded-3xl p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:border-[#E11D48] space-y-6 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="w-14 h-14 rounded-2xl bg-[#0B0F19] text-white flex items-center justify-center shadow-md">
                                <i class="fa-solid fa-print text-2xl"></i>
                            </div>
                            <h4 class="text-xl font-black text-[#0B0F19]">3. Factures & Impression A4</h4>
                            <p class="text-xs text-gray-600 leading-relaxed font-medium">
                                Édition automatique des factures définitives et impressions A4. Intégration complète des données légales du garage (NCC, RCCM, régime TEE) et gestion des acomptes reçus.
                            </p>
                        </div>
                        <div class="pt-4 border-t border-gray-200/60 flex items-center justify-between text-xs font-black text-[#0B0F19]">
                            <span>Module Facturation</span>
                            <i class="fa-solid fa-arrow-right text-[#E11D48]"></i>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- SECTION ROLES & ACCÈS SÉCURISÉS -->
        <section id="roles" class="py-24 bg-[#0B0F19] text-white border-b border-gray-800 relative overflow-hidden">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-[#E11D48]/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16 relative z-10">
                
                <div class="text-center max-w-2xl mx-auto space-y-3">
                    <h2 class="text-xs font-extrabold tracking-widest text-[#E11D48] uppercase">Espaces & Habilitations</h2>
                    <h3 class="text-3xl font-black text-white">Un accès sur-mesure selon chaque rôle</h3>
                    <p class="text-sm text-gray-400 font-medium">Chaque intervenant dispose d'une interface adaptée à ses responsabilités quotidiennes.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Rôle 1 : Réceptionniste -->
                    <div class="bg-white/5 border border-white/10 rounded-3xl p-6 space-y-4 hover:border-[#E11D48] transition-all duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-[#E11D48]/20 border border-[#E11D48]/30 flex items-center justify-center text-[#E11D48]">
                            <i class="fa-solid fa-clipboard-user text-xl"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-[#E11D48]">Accueil & Parc</span>
                            <h4 class="text-lg font-black text-white">Réceptionniste</h4>
                        </div>
                        <p class="text-xs text-gray-400 leading-relaxed font-medium">
                            Enregistrement rapide des véhicules, fiche d'inspection visuelle, photos des 4 faces et création des Ordres de Travail (OT).
                        </p>
                    </div>

                    <!-- Rôle 2 : Mécanicien -->
                    <div class="bg-white/5 border border-white/10 rounded-3xl p-6 space-y-4 hover:border-[#E11D48] transition-all duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center text-blue-400">
                            <i class="fa-solid fa-screwdriver-wrench text-xl"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-blue-400">Atelier & Tech</span>
                            <h4 class="text-lg font-black text-white">Mécanicien</h4>
                        </div>
                        <p class="text-xs text-gray-400 leading-relaxed font-medium">
                            Consultation des travaux affectés, saisie du diagnostic technique, demande de pièces au magasin et mise à jour des statuts.
                        </p>
                    </div>

                    <!-- Rôle 3 : Administration -->
                    <div class="bg-white/5 border border-white/10 rounded-3xl p-6 space-y-4 hover:border-[#E11D48] transition-all duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                            <i class="fa-solid fa-user-tie text-xl"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Gestion & Compta</span>
                            <h4 class="text-lg font-black text-white">Administration</h4>
                        </div>
                        <p class="text-xs text-gray-400 leading-relaxed font-medium">
                            Chiffrage des devis, validation du versement des 70% d'acompte, génération des factures normalisées A4 et suivi financier.
                        </p>
                    </div>

                    <!-- Rôle 4 : Client -->
                    <div class="bg-white/5 border border-white/10 rounded-3xl p-6 space-y-4 hover:border-[#E11D48] transition-all duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-purple-400">
                            <i class="fa-solid fa-building-user text-xl"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-purple-400">Portail Client</span>
                            <h4 class="text-lg font-black text-white">Client / Flotte</h4>
                        </div>
                        <p class="text-xs text-gray-400 leading-relaxed font-medium">
                            Suivi de l'état d'avancement des réparations en temps réel, approbation des devis et accès à l'historique de maintenance.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- WORKFLOW METIER (SECTION GRIS TRÈS CLAIR) -->
        <section id="workflow" class="py-24 bg-[#F8FAFC]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                
                <div class="text-center max-w-2xl mx-auto space-y-3">
                    <h2 class="text-xs font-extrabold tracking-widest text-[#E11D48] uppercase">Processus Opérationnel</h2>
                    <h3 class="text-3xl font-black text-[#0B0F19]">De l'entrée au garage à la restitution</h3>
                    <p class="text-sm text-[#8A8D8F] font-medium">Un parcours fluide et sécurisé partagé entre les équipes techniques et administratives.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    
                    <div class="bg-white border border-gray-200 p-8 rounded-3xl space-y-4 shadow-sm hover:shadow-md transition">
                        <div class="text-3xl font-black text-[#E11D48]">01</div>
                        <h4 class="text-lg font-black text-[#0B0F19]">Réception sur Parc</h4>
                        <p class="text-xs text-gray-600 leading-relaxed font-medium">Enregistrement physique du véhicule, génération de l'OT et photos d'état initial.</p>
                    </div>

                    <div class="bg-white border border-gray-200 p-8 rounded-3xl space-y-4 shadow-sm hover:shadow-md transition">
                        <div class="text-3xl font-black text-[#0B0F19]">02</div>
                        <h4 class="text-lg font-black text-[#0B0F19]">Diagnostic Atelier</h4>
                        <p class="text-xs text-gray-600 leading-relaxed font-medium">Prise en charge par le mécanicien attitré et identification des pièces à remplacer.</p>
                    </div>

                    <div class="bg-white border border-gray-200 p-8 rounded-3xl space-y-4 shadow-sm hover:shadow-md transition">
                        <div class="text-3xl font-black text-[#E11D48]">03</div>
                        <h4 class="text-lg font-black text-[#0B0F19]">Validation Devis</h4>
                        <p class="text-xs text-gray-600 leading-relaxed font-medium">Établissement du devis, accord client et enregistrement de l'avance obligatoire de 70%.</p>
                    </div>

                    <div class="bg-white border border-gray-200 p-8 rounded-3xl space-y-4 shadow-sm hover:shadow-md transition">
                        <div class="text-3xl font-black text-[#0B0F19]">04</div>
                        <h4 class="text-lg font-black text-[#0B0F19]">Facture & Restitution</h4>
                        <p class="text-xs text-gray-600 leading-relaxed font-medium">Fin des travaux, solde de la facture, impression des documents et livraison au client.</p>
                    </div>

                </div>
            </div>
        </section>

        <!-- FOOTER SOMBRE ET ELEGANT -->
        <footer id="contact" class="bg-[#0B0F19] border-t border-gray-800 py-12 text-sm text-gray-400">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
                
                <div class="rounded-xl bg-white px-3 py-1.5 shadow-md">
                    <img
                        src="/images/logo-kagnan.png"
                        alt="Garage Kagnan"
                        class="h-10 w-auto object-contain"
                    />
                </div>

                <div class="text-center md:text-right text-xs space-y-1 font-medium text-gray-400">
                    <p><strong class="text-white">Garage Kagnan</strong> — Yopougon Zone Industrielle & Terminus 27</p>
                    <p>NCC : 1113876 J | RCCM : CI-ABJ-2012-B-5126</p>
                    <p>Contact : 25 23 01 90 86 / 07 08 38 83 25 — E-mail : garagekagnan@gmail.com</p>
                    <p class="text-white pt-2 font-extrabold">© 2026 Garage Kagnan. Tous droits réservés.</p>
                </div>

            </div>
        </footer>

    </div>
</template>