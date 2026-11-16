<script setup>
// Espace administrateur du super administrateur : le tableau de bord admin complet.
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Entete from '@/Components/SuperAdmin/Entete.vue';
import SiegeSwitcher from '@/Components/SiegeSwitcher.vue';
import TableauDeBordAdmin from '@/Components/Admin/TableauDeBordAdmin.vue';

defineProps({
    admin: { type: Object, required: true },
});
</script>

<template>
    <Head title="Espace administrateur" />
    <AuthenticatedLayout>
        <div class="sg-page">
            <div class="sg-orbs" aria-hidden="true">
                <span class="sg-orb sg-orb-1"></span>
                <span class="sg-orb sg-orb-2"></span>
                <span class="sg-orb sg-orb-3"></span>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <Entete
                    titre="Espace administrateur"
                    surtitre="Super administrateur · Agir en tant qu'admin"
                    icone="fa-user-shield"
                    :texte="`Chiffre d'affaires, devis, pièces, suivi et employés${admin.siegeFiltre ? ` · siège ${admin.siegeFiltre}` : ''}.`"
                >
                    <div class="rounded-2xl bg-white/95 px-3 py-2">
                        <SiegeSwitcher :current="admin.siegeFiltre" />
                    </div>
                </Entete>

                <TableauDeBordAdmin integre :users="admin.users" :stats="admin.stats" :siege-filtre="admin.siegeFiltre" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style src="../../../css/secretariat-glass.css"></style>
