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

const sections = [
    'whatWeDo',
    'whatWeDont',
    'howItWorks',
    'whyWeBuilt',
    'whatToExpect',
    'whatWeDontTrack',
    'whoWeAre',
] as const;
</script>

<template>
    <Head :title="t('about.title')">
        <meta name="description" :content="t('meta.about')">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <div class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="text-3xl font-bold tracking-tight">
                {{ t('about.title') }}
            </h1>

            <div class="mt-10 space-y-8">
                <section v-for="section in sections" :key="section">
                    <h2 class="text-lg font-semibold">
                        {{ t(`about.sections.${section}.title`) }}
                    </h2>
                    <p class="mt-2 text-muted-foreground leading-relaxed">
                        {{ t(`about.sections.${section}.content`) }}
                    </p>
                </section>

                <!-- Contact Link -->
                <section class="border-t border-border pt-8">
                    <h2 class="text-lg font-semibold">
                        {{ t('about.contact.title') }}
                    </h2>
                    <p class="mt-2 text-muted-foreground leading-relaxed">
                        {{ t('about.contact.content') }}
                        <LocalizedLink href="/contact" class="underline hover:text-foreground">
                            {{ t('contact.title') }}
                        </LocalizedLink>
                    </p>
                </section>
            </div>
        </div>
    </GuestLayout>
</template>
