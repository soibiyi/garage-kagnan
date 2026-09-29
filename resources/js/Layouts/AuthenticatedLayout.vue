<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100 print:min-h-0 print:bg-white">
            <nav class="border-b border-gray-100 bg-white print:hidden">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-20 items-center justify-between">

                        <!-- GAUCHE : logo de l'entreprise -->
                        <Link :href="route('dashboard')" class="flex items-center">
                            <img
                                src="/images/logo-kagnan.png"
                                alt="Garage Kagnan"
                                class="h-16 w-auto object-contain sm:h-[72px]"
                            />
                        </Link>

                        <!-- DROITE : nom de l'utilisateur (lien profil) + Se déconnecter -->
                        <div class="flex items-center gap-2 sm:gap-3">
                            <Link
                                :href="route('profile.edit')"
                                class="inline-flex max-w-[140px] items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-[#0B0F19] transition hover:border-[#0B0F19] hover:bg-gray-50 sm:max-w-[240px] sm:px-4"
                            >
                                <i class="fa-solid fa-user text-[11px] text-[#E11D48]"></i>
                                <span class="truncate">{{ $page.props.auth.user.name }}</span>
                            </Link>

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#E11D48] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#BE123C] sm:px-4"
                            >
                                <i class="fa-solid fa-right-from-bracket text-[11px]"></i>
                                <span class="hidden sm:inline">Se déconnecter</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow print:hidden" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>