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
    <Head title="Garage Kagnan — Excellence & Maintenance Automobile" />

    <div class="min-h-screen text-gray-900 font-sans selection:bg-red-600 selection:text-white" style="background-color: #8A8D8F;">
        
        <!-- HEADER / NAVIGATION -->
        <header class="fixed top-0 left-0 right-0 z-50 backdrop-blur-md border-b border-gray-700/40" style="background-color: rgba(138, 141, 143, 0.9);">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                
                <!-- Logo & Nom de l'entreprise -->
                <a href="#" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-white/25 border border-white/40 flex items-center justify-center text-red-600 group-hover:bg-red-600 group-hover:text-white transition duration-300 shadow-sm">
                        <i class="fa-solid fa-wrench text-lg"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-lg font-extrabold tracking-tight leading-none">
                            <span class="text-white">GARAGE</span> <span class="text-red-600">KAGNAN</span>
                        </span>
                        <span class="text-[10px] tracking-widest text-gray-200 uppercase mt-0.5 font-medium">Automotive Center</span>
                    </div>
                </a>

                <!-- Liens de navigation ancrés -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-gray-100">
                    <a href="#guide" class="hover:text-white transition">Guide du Site</a>
                    <a href="#modules" class="hover:text-white transition">Modules Métier</a>
                    <a href="#workflow" class="hover:text-white transition">Processus Atelier</a>
                    <a href="#contact" class="hover:text-white transition">Contact & Infos</a>
                </nav>

                <!-- Actions Authentification -->
                <div class="flex items-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Link :href="route('dashboard')" class="px-5 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition duration-200 flex items-center gap-2 shadow-lg shadow-black/10">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Tableau de bord</span>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="px-4 py-2 rounded-xl text-sm font-bold text-white hover:text-gray-200 transition">
                            Connexion
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="px-5 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition duration-200 flex items-center gap-2 shadow-lg shadow-black/10">
                            <span>Commencer</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- HERO SECTION -->
        <section class="relative pt-32 pb-20 md:pt-44 md:pb-32 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="max-w-3xl mx-auto text-center space-y-6">
                    
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/40 border border-white/60 text-xs font-bold text-gray-900 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                        Plateforme de Gestion Intégrée — Garage Kagnan
                    </div>

                    <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-white leading-[1.1] drop-shadow-sm">
                        L'excellence mécanique au service de votre <span class="text-red-700">flotte automobile</span>.
                    </h1>

                    <p class="text-base sm:text-lg text-gray-100 max-w-2xl mx-auto font-medium leading-relaxed drop-shadow-sm">
                        Bienvenue sur l'outil central du <strong class="text-white font-bold">GARAGE KAGNAN</strong>. Supervision complète des véhicules en réception, ordres de travail, devis dynamiques et facturation normalisée.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                        <template v-if="$page.props.auth.user">
                            <Link :href="route('dashboard')" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700 transition duration-200 flex items-center justify-center gap-3 shadow-xl shadow-black/15">
                                <i class="fa-solid fa-chart-pie text-sm"></i>
                               <span>Accéder à votre espace</span>
                            </Link>
                        </template>
                        <template v-else>
                            <Link :href="route('login')" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700 transition duration-200 flex items-center justify-center gap-3 shadow-xl shadow-black/15">
                                <i class="fa-solid fa-right-to-bracket text-sm"></i>
                                <span>Se connecter au portail</span>
                            </Link>
                            <a href="#guide" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-white/40 border border-white/60 text-gray-900 font-bold hover:bg-white/60 transition duration-200 flex items-center justify-center gap-3 shadow-sm">
                                <i class="fa-solid fa-compass text-gray-700"></i>
                                <span>Découvrir le guide</span>
                            </a>
                        </template>
                    </div>
                </div>

                <!-- Indicateurs clés -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-20 pt-12 border-t border-white/30">
                    <div class="bg-white/20 border border-white/30 p-6 rounded-2xl text-center shadow-sm backdrop-blur-sm">
                        <div class="text-3xl font-extrabold text-white mb-1">100%</div>
                        <div class="text-xs text-gray-100 font-bold">Traçabilité des Entrées</div>
                    </div>
                    <div class="bg-white/20 border border-white/30 p-6 rounded-2xl text-center shadow-sm backdrop-blur-sm">
                        <div class="text-3xl font-extrabold text-red-700 mb-1">70%</div>
                        <div class="text-xs text-gray-100 font-bold">Avance Conditionnelle Devis</div>
                    </div>
                    <div class="bg-white/20 border border-white/30 p-6 rounded-2xl text-center shadow-sm backdrop-blur-sm">
                        <div class="text-3xl font-extrabold text-white mb-1">Temps Réel</div>
                        <div class="text-xs text-gray-100 font-bold">Suivi Atelier & Dossiers</div>
                    </div>
                    <div class="bg-white/20 border border-white/30 p-6 rounded-2xl text-center shadow-sm backdrop-blur-sm">
                        <div class="text-3xl font-extrabold text-red-700 mb-1">A4 / Print</div>
                        <div class="text-xs text-gray-100 font-bold">Édition Devis & Factures</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- GUIDE ET EXPLICATIONS DETAILLEES DES MODULES -->
        <section id="guide" class="py-24 relative border-t border-white/30" style="background-color: rgba(138, 141, 143, 0.95);">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                
                <div class="text-center max-w-2xl mx-auto space-y-3">
                    <h2 class="text-xs font-extrabold tracking-widest text-red-700 uppercase">Visite Guidée de la Plateforme</h2>
                    <h3 class="text-3xl font-extrabold text-white">Comment fonctionne le site ?</h3>
                    <p class="text-sm text-gray-100 font-medium">Une architecture logicielle conçue pour accompagner chaque phase de vie d'un véhicule au sein du garage.</p>
                </div>

                <div id="modules" class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <!-- Bloc 1 -->
                    <div class="bg-white/90 border border-white/50 rounded-2xl p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl space-y-5 flex flex-col justify-between shadow-md">
                        <div class="space-y-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-blue-600">
                                <i class="fa-solid fa-car-tunnel text-xl"></i>
                            </div>
                            <h4 class="text-lg font-extrabold text-gray-900">1. Réception & Parc Véhicule</h4>
                            <p class="text-sm text-gray-700 leading-relaxed font-medium">
                                Enregistrement à l'entrée : immatriculation, kilométrage, niveau de carburant, inventaire complet des équipements (cric, roue de secours, radio) et prise de photos numériques des 4 faces pour sécuriser l'état initial.
                            </p>
                        </div>
                        <div class="pt-4 border-t border-gray-200 flex items-center justify-between text-xs font-bold text-blue-600">
                            <span>Module Réception</span>
                            <i class="fa-solid fa-arrow-right-long"></i>
                        </div>
                    </div>

                    <!-- Bloc 2 -->
                    <div class="bg-white/90 border border-white/50 rounded-2xl p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl space-y-5 flex flex-col justify-between shadow-md">
                        <div class="space-y-4">
                            <div class="w-12 h-12 rounded-xl bg-red-600/10 border border-red-600/30 flex items-center justify-center text-red-600">
                                <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                            </div>
                            <h4 class="text-lg font-extrabold text-gray-900">2. Devis & Lignes de Prestations</h4>
                            <p class="text-sm text-gray-700 leading-relaxed font-medium">
                                Gestion centralisée des devis rattachés aux interventions. Saisie détaillée des lignes (quantité, désignation, référence pièce, prix unitaire net, remises et gestion de la TVA à 18% ou exonération).
                            </p>
                        </div>
                        <div class="pt-4 border-t border-gray-200 flex items-center justify-between text-xs font-bold text-red-600">
                            <span>Module Administration</span>
                            <i class="fa-solid fa-arrow-right-long"></i>
                        </div>
                    </div>

                    <!-- Bloc 3 -->
                    <div class="bg-white/90 border border-white/50 rounded-2xl p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl space-y-5 flex flex-col justify-between shadow-md">
                        <div class="space-y-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-600">
                                <i class="fa-solid fa-print text-xl"></i>
                            </div>
                            <h4 class="text-lg font-extrabold text-gray-900">3. Facturation & Impression A4</h4>
                            <p class="text-sm text-gray-700 leading-relaxed font-medium">
                                Édition des documents officiels de facturation. Format A4 prêt pour l'impression intégrant les mentions légales du garage (NCC, RCCM, régime TEE) et les conditions de paiement (avance de 70%).
                            </p>
                        </div>
                        <div class="pt-4 border-t border-gray-200 flex items-center justify-between text-xs font-bold text-purple-600">
                            <span>Module Facturation</span>
                            <i class="fa-solid fa-arrow-right-long"></i>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- WORKFLOW METIER -->
        <section id="workflow" class="py-24 border-t border-white/30" style="background-color: rgba(138, 141, 143, 0.85);">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
                
                <div class="text-center max-w-2xl mx-auto space-y-3">
                    <h2 class="text-xs font-extrabold tracking-widest text-red-700 uppercase">Processus Opérationnel</h2>
                    <h3 class="text-3xl font-extrabold text-white">De l'entrée au garage à la restitution</h3>
                    <p class="text-sm text-gray-100 font-medium">Un parcours transparent et rigoureux orchestré pour les équipes techniques et administratives.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    
                    <div class="bg-white/90 border border-white/50 p-6 rounded-2xl space-y-4 shadow-md">
                        <div class="text-2xl font-black text-red-600">01</div>
                        <h4 class="text-base font-extrabold text-gray-900">Réception sur Parc</h4>
                        <p class="text-xs text-gray-700 leading-relaxed font-medium">Enregistrement physique du véhicule, attribution d'un Ordre de Travail (OT) et sécurisation de l'état initial par clichés visuels.</p>
                    </div>

                    <div class="bg-white/90 border border-white/50 p-6 rounded-2xl space-y-4 shadow-md">
                        <div class="text-2xl font-black text-red-600">02</div>
                        <h4 class="text-base font-extrabold text-gray-900">Diagnostic & Atelier</h4>
                        <p class="text-xs text-gray-700 leading-relaxed font-medium">Prise en charge par le mécanicien attitré, analyse technique approfondie et remontée des pièces nécessaires aux réparations.</p>
                    </div>

                    <div class="bg-white/90 border border-white/50 p-6 rounded-2xl space-y-4 shadow-md">
                        <div class="text-2xl font-black text-red-600">03</div>
                        <h4 class="text-base font-extrabold text-gray-900">Validation du Devis</h4>
                        <p class="text-xs text-gray-700 leading-relaxed font-medium">Établissement du devis détaillé, accord client et versement exigé de l'avance de 70% pour déclencher les travaux en atelier.</p>
                    </div>

                    <div class="bg-white/90 border border-white/50 p-6 rounded-2xl space-y-4 shadow-md">
                        <div class="text-2xl font-black text-red-600">04</div>
                        <h4 class="text-base font-extrabold text-gray-900">Clôture & Facturation</h4>
                        <p class="text-xs text-gray-700 leading-relaxed font-medium">Fin des interventions, génération de la facture définitive, impression normalisée et restitution sécurisée du véhicule.</p>
                    </div>

                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer id="contact" class="border-t border-white/40 py-12 text-sm text-gray-100" style="background-color: #8A8D8F;">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
                
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/30 border border-white/50 flex items-center justify-center text-red-600 shadow-sm">
                        <i class="fa-solid fa-wrench text-sm"></i>
                    </div>
                    <span class="font-extrabold text-white">
                        <span class="text-white">GARAGE</span> <span class="text-red-700">KAGNAN</span>
                    </span>
                </div>

                <div class="text-center md:text-right text-xs space-y-1 font-medium">
                    <p>Siège : Yopougon Zone Industrielle & Terminus 27 | NCC : 1113876 J | RCCM : CI-ABJ-2012-B-5126</p>
                    <p>Contact : 25 23 01 90 86 / 07 08 38 83 25 — E-mail : garagekagnan@gmail.com</p>
                    <p class="text-white pt-2 font-bold">© 2026 Garage Kagnan. Tous droits réservés.</p>
                </div>

            </div>
        </footer>

    </div>
</template>