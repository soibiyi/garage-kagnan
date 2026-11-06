<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientEntete from '@/Components/ClientEntete.vue';
import Conversation from '@/Pages/EspaceClient/sections/Conversation.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    client: Object,
});
</script>

<template>
    <Head :title="`Messages : ${client.nom} — Garage Kagnan`" />

    <AuthenticatedLayout>
        <template #header>
            <ClientEntete
                :client="client"
                :href="route('charge_client.clients.show', client.id)"
                libelle-retour="Retour au client"
                section="Messages"
            />
        </template>

        <div class="min-h-screen bg-white py-8 sm:py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                    <Conversation audience="staff" :client-id="Number(client.id)" />
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
