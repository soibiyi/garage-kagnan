<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientEntete from '@/Components/ClientEntete.vue';
import Conversation from '@/Pages/EspaceClient/sections/Conversation.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    client: Object,
    adminInstruction: Object,
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
                <section
                    v-if="adminInstruction"
                    class="mb-6 rounded-2xl border-2 border-[#E11D48] bg-rose-50 p-5 shadow-md sm:p-7"
                    role="alert"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#E11D48] text-white">
                            <i class="fa-solid fa-triangle-exclamation text-xl" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-black text-[#0B0F19] sm:text-xl">Instruction de l’administrateur</p>
                            <p class="mt-1 text-sm font-semibold text-rose-800">{{ adminInstruction.auteur }}</p>
                            <p class="mt-4 whitespace-pre-line text-base font-semibold leading-relaxed text-[#0B0F19] sm:text-lg">
                                {{ adminInstruction.consigne }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                    <Conversation audience="staff" :client-id="Number(client.id)" />
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
