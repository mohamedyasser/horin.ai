<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GuestLayout from '@/layouts/GuestLayout.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';

const { t } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

const questions = [
    'whatDoes',
    'guaranteed',
    'updateFrequency',
    'confidenceScore',
    'howToUse',
    'markets',
    'trading',
    'financialInfo',
    'horizons',
    'accuracy',
    'negative',
    'missing',
] as const;
</script>

<template>
    <Head :title="t('faq.title')">
        <meta name="description" :content="t('meta.faq')">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <div class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="text-3xl font-bold tracking-tight">
                {{ t('faq.title') }}
            </h1>

            <div class="mt-10 space-y-6">
                <div
                    v-for="q in questions"
                    :key="q"
                    class="border-b border-border pb-6 last:border-0"
                >
                    <h2 class="text-base font-semibold">
                        {{ t(`faq.questions.${q}.q`) }}
                    </h2>
                    <p class="mt-2 text-muted-foreground leading-relaxed">
                        {{ t(`faq.questions.${q}.a`) }}
                    </p>
                </div>

                <!-- Report Issue -->
                <div class="border-b border-border pb-6">
                    <h2 class="text-base font-semibold">
                        {{ t('faq.questions.reportIssue.q') }}
                    </h2>
                    <p class="mt-2 text-muted-foreground leading-relaxed">
                        {{ t('faq.questions.reportIssue.a') }}
                        <LocalizedLink href="/contact" class="underline hover:text-foreground">
                            {{ t('contact.title') }}
                        </LocalizedLink>
                    </p>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
