<script setup lang="ts">
import LocalizedLink from '@/components/LocalizedLink.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

const supportEmail = 'support@horin.com';
</script>

<template>
    <Head :title="t('contact.title')">
        <meta name="description" :content="t('meta.contact')" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <div class="mx-auto max-w-3xl px-6 py-12">
            <h1 class="text-3xl font-bold tracking-tight">
                {{ t('contact.title') }}
            </h1>

            <div class="mt-10 space-y-8">
                <!-- Email Section -->
                <section>
                    <p class="leading-relaxed text-muted-foreground">
                        {{ t('contact.message') }}
                    </p>

                    <a
                        :href="`mailto:${supportEmail}`"
                        class="mt-6 inline-flex items-center gap-3 rounded-md border border-border px-6 py-4 text-lg font-medium transition-colors hover:bg-muted"
                    >
                        <Mail class="size-5 text-foreground" />
                        {{ supportEmail }}
                    </a>
                </section>

                <!-- Response Time -->
                <section>
                    <h2 class="text-lg font-semibold">
                        {{ t('contact.responseTime.title') }}
                    </h2>
                    <p class="mt-2 leading-relaxed text-muted-foreground">
                        {{ t('contact.responseTime.content') }}
                    </p>
                </section>

                <!-- What to Include -->
                <section>
                    <h2 class="text-lg font-semibold">
                        {{ t('contact.include.title') }}
                    </h2>
                    <ul
                        class="mt-2 list-inside list-disc space-y-1 leading-relaxed text-muted-foreground"
                    >
                        <li>{{ t('contact.include.email') }}</li>
                        <li>{{ t('contact.include.page') }}</li>
                        <li>{{ t('contact.include.description') }}</li>
                    </ul>
                </section>

                <!-- Links -->
                <section class="border-t border-border pt-8">
                    <p class="text-sm text-muted-foreground">
                        {{ t('contact.links.text') }}
                        <LocalizedLink
                            href="/privacy"
                            class="underline hover:text-foreground"
                        >
                            {{ t('privacy.title') }}
                        </LocalizedLink>
                        &middot;
                        <LocalizedLink
                            href="/terms"
                            class="underline hover:text-foreground"
                        >
                            {{ t('terms.title') }}
                        </LocalizedLink>
                    </p>
                </section>
            </div>
        </div>
    </GuestLayout>
</template>
