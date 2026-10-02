<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const toast = ref(null); // { type: 'success' | 'error', message: string }
let timer = null;

const hide = () => {
    clearTimeout(timer);
    toast.value = null;
};

// Se déclenche à chaque nouvelle réponse Inertia (donc à chaque redirection avec ->with(...))
watch(
    () => page.props.flash,
    (flash) => {
        const message = flash?.error || flash?.success;
        if (!message) return;

        clearTimeout(timer);
        toast.value = { type: flash.error ? 'error' : 'success', message };
        timer = setTimeout(hide, 5000);
    },
    { immediate: true }
);
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 top-32 z-50 flex justify-center px-4 sm:justify-end sm:px-6 lg:px-8 print:hidden"
    >
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast"
                :role="toast.type === 'error' ? 'alert' : 'status'"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white p-4 shadow-xl"
                :class="toast.type === 'error' ? 'border-[#E11D48]/40' : 'border-emerald-300'"
            >
                <span
                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm"
                    :class="toast.type === 'error' ? 'bg-[#E11D48]/10 text-[#E11D48]' : 'bg-emerald-100 text-emerald-600'"
                >
                    <i :class="toast.type === 'error' ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-check'"></i>
                </span>

                <p class="flex-1 text-sm font-semibold text-[#0B0F19]">{{ toast.message }}</p>

                <button
                    type="button"
                    aria-label="Fermer"
                    class="text-[#8A8D8F] transition hover:text-[#0B0F19]"
                    @click="hide"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </Transition>
    </div>
</template>