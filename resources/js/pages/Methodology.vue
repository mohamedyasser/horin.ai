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
</script>

<template>
    <Head :title="t('methodology.title')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <div class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="text-3xl font-bold tracking-tight">
                {{ t('methodology.title') }}
            </h1>
            <p class="mt-2 text-muted-foreground">
                {{ t('methodology.subtitle') }}
            </p>

            <div class="mt-10 space-y-10">
                <!-- What Predictions Represent -->
                <section>
                    <h2 class="text-xl font-semibold">
                        {{ t('methodology.sections.represent.title') }}
                    </h2>
                    <dl class="mt-4 space-y-3">
                        <div v-for="item in ['meaning', 'scope', 'output']" :key="item">
                            <dt class="text-sm font-medium">{{ t(`methodology.sections.represent.${item}.label`) }}</dt>
                            <dd class="mt-1 text-muted-foreground">{{ t(`methodology.sections.represent.${item}.value`) }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- How Predictions Are Generated -->
                <section>
                    <h2 class="text-xl font-semibold">
                        {{ t('methodology.sections.generated.title') }}
                    </h2>
                    <ol class="mt-4 space-y-4">
                        <li v-for="(step, index) in ['collection', 'extraction', 'evaluation', 'prediction', 'confidence']" :key="step" class="flex gap-4">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary">{{ index + 1 }}</span>
                            <div>
                                <p class="font-medium">{{ t(`methodology.sections.generated.steps.${step}.label`) }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ t(`methodology.sections.generated.steps.${step}.value`) }}</p>
                            </div>
                        </li>
                    </ol>
                </section>

                <!-- Horizons -->
                <section>
                    <h2 class="text-xl font-semibold">
                        {{ t('methodology.sections.horizons.title') }}
                    </h2>
                    <div class="mt-4 overflow-hidden rounded-lg border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-4 py-2 text-start font-medium">{{ t('methodology.sections.horizons.horizon') }}</th>
                                    <th class="px-4 py-2 text-start font-medium">{{ t('methodology.sections.horizons.meaning') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="h in ['short', 'medium', 'long']" :key="h" class="border-t border-border">
                                    <td class="px-4 py-2 font-medium">{{ t(`methodology.sections.horizons.items.${h}.label`) }}</td>
                                    <td class="px-4 py-2 text-muted-foreground">{{ t(`methodology.sections.horizons.items.${h}.value`) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">{{ t('methodology.sections.horizons.note') }}</p>
                </section>

                <!-- Confidence Score -->
                <section>
                    <h2 class="text-xl font-semibold">
                        {{ t('methodology.sections.confidence.title') }}
                    </h2>
                    <dl class="mt-4 space-y-3">
                        <div v-for="item in ['purpose', 'range', 'notGuarantee']" :key="item">
                            <dt class="text-sm font-medium">{{ t(`methodology.sections.confidence.${item}.label`) }}</dt>
                            <dd class="mt-1 text-muted-foreground">{{ t(`methodology.sections.confidence.${item}.value`) }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Factors Affecting Accuracy -->
                <section>
                    <h2 class="text-xl font-semibold">
                        {{ t('methodology.sections.factors.title') }}
                    </h2>
                    <div class="mt-4 overflow-hidden rounded-lg border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-4 py-2 text-start font-medium">{{ t('methodology.sections.factors.factor') }}</th>
                                    <th class="px-4 py-2 text-start font-medium">{{ t('methodology.sections.factors.effect') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="f in ['news', 'liquidity', 'history', 'instability']" :key="f" class="border-t border-border">
                                    <td class="px-4 py-2 font-medium">{{ t(`methodology.sections.factors.items.${f}.label`) }}</td>
                                    <td class="px-4 py-2 text-muted-foreground">{{ t(`methodology.sections.factors.items.${f}.value`) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Limitations -->
                <section>
                    <h2 class="text-xl font-semibold">
                        {{ t('methodology.sections.limitations.title') }}
                    </h2>
                    <dl class="mt-4 space-y-3">
                        <div v-for="item in ['noRealtime', 'noFundamentals', 'notAdvice']" :key="item">
                            <dt class="text-sm font-medium">{{ t(`methodology.sections.limitations.${item}.label`) }}</dt>
                            <dd class="mt-1 text-muted-foreground">{{ t(`methodology.sections.limitations.${item}.value`) }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Update Frequency -->
                <section>
                    <h2 class="text-xl font-semibold">
                        {{ t('methodology.sections.updates.title') }}
                    </h2>
                    <dl class="mt-4 space-y-3">
                        <div v-for="item in ['dataRefresh', 'predictionRefresh', 'variation']" :key="item">
                            <dt class="text-sm font-medium">{{ t(`methodology.sections.updates.${item}.label`) }}</dt>
                            <dd class="mt-1 text-muted-foreground">{{ t(`methodology.sections.updates.${item}.value`) }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- How to Use Predictions Safely -->
                <section>
                    <h2 class="text-xl font-semibold">
                        {{ t('methodology.sections.safeUse.title') }}
                    </h2>
                    <ul class="mt-4 space-y-3">
                        <li v-for="item in ['compare', 'checkConfidence', 'followTrends', 'avoidOverreliance']" :key="item" class="flex gap-3">
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>
                            <div>
                                <p class="font-medium">{{ t(`methodology.sections.safeUse.${item}.label`) }}</p>
                                <p class="text-sm text-muted-foreground">{{ t(`methodology.sections.safeUse.${item}.value`) }}</p>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Contact -->
                <section class="border-t border-border pt-8">
                    <h2 class="text-lg font-semibold">
                        {{ t('methodology.sections.contact.title') }}
                    </h2>
                    <p class="mt-2 text-muted-foreground">
                        {{ t('methodology.sections.contact.content') }}
                        <LocalizedLink href="/contact" class="underline hover:text-foreground">
                            {{ t('contact.title') }}
                        </LocalizedLink>
                    </p>
                </section>
            </div>
        </div>
    </GuestLayout>
</template>
