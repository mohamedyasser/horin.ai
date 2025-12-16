<script setup lang="ts">
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    botUsername: string;
    errors?: {
        telegram?: string;
    };
}>();

const widgetLoaded = ref(false);

const onTelegramAuth = (user: Record<string, unknown>) => {
    const params = new URLSearchParams(user as Record<string, string>);
    window.location.href = `/auth/telegram/callback?${params.toString()}`;
};

onMounted(() => {
    // Expose callback to global scope for Telegram widget
    (window as unknown as Record<string, unknown>).onTelegramAuth = onTelegramAuth;

    // Load Telegram widget script
    const script = document.createElement('script');
    script.src = 'https://telegram.org/js/telegram-widget.js?22';
    script.setAttribute('data-telegram-login', props.botUsername);
    script.setAttribute('data-size', 'large');
    script.setAttribute('data-radius', '8');
    script.setAttribute('data-request-access', 'write');
    script.setAttribute('data-onauth', 'onTelegramAuth(user)');
    script.async = true;
    script.onload = () => {
        widgetLoaded.value = true;
    };

    document.getElementById('telegram-widget-container')?.appendChild(script);
});
</script>

<template>
    <AuthBase
        :title="t('auth.telegram.title')"
        :description="t('auth.telegram.description')"
    >
        <Head :title="t('auth.telegram.title')" />

        <div
            v-if="errors?.telegram"
            class="mb-4 text-center text-sm font-medium text-red-600"
        >
            {{ errors.telegram }}
        </div>

        <div class="flex flex-col items-center gap-6">
            <div
                id="telegram-widget-container"
                class="flex min-h-[48px] items-center justify-center"
            >
                <div
                    v-if="!widgetLoaded"
                    class="h-12 w-48 animate-pulse rounded-lg bg-muted"
                />
            </div>

            <p class="text-center text-sm text-muted-foreground">
                {{ t('auth.telegram.terms') }}
            </p>
        </div>
    </AuthBase>
</template>
