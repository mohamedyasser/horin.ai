<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GuestLayout from '@/layouts/GuestLayout.vue';

const { t } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

const sections = [
    'scope',
    'noAdvice',
    'eligibility',
    'account',
    'acceptableUse',
    'changes',
    'availability',
    'liability',
    'termination',
    'contact',
] as const;
</script>

<template>
    <Head :title="t('terms.title')">
        <meta name="description" :content="t('meta.terms')">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <div class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="text-3xl font-bold tracking-tight">
                {{ t('terms.title') }}
            </h1>
            <p class="mt-2 text-muted-foreground">
                {{ t('terms.lastUpdated') }}
            </p>

            <div class="mt-10 space-y-8">
                <section v-for="section in sections" :key="section">
                    <h2 class="text-lg font-semibold">
                        {{ t(`terms.sections.${section}.title`) }}
                    </h2>
                    <p class="mt-2 text-muted-foreground leading-relaxed">
                        {{ t(`terms.sections.${section}.content`) }}
                    </p>
                </section>
            </div>
        </div>
    </GuestLayout>
</template>
