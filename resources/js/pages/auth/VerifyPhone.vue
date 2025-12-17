<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    botUsername: string;
    telegramId: number;
    status?: string;
}>();

const checking = ref(false);
const resending = ref(false);
let pollTimeout: ReturnType<typeof setTimeout> | null = null;
let pollDelay = 3000; // Start with 3 seconds
const maxPollDelay = 15000; // Max 15 seconds
const pollBackoffMultiplier = 1.5;

const openTelegram = () => {
    window.open(`https://t.me/${props.botUsername}`, '_blank');
};

const checkStatus = async () => {
    if (checking.value) return;
    
    checking.value = true;
    try {
        const response = await fetch('/api/user/phone-status');
        const data = await response.json();
        if (data.verified) {
            router.visit('/onboarding');
            return;
        }
        
        // Schedule next check with exponential backoff
        pollDelay = Math.min(pollDelay * pollBackoffMultiplier, maxPollDelay);
        pollTimeout = setTimeout(checkStatus, pollDelay);
    } catch (error) {
        console.error('Failed to check phone status:', error);
        // On error, retry with same delay
        pollTimeout = setTimeout(checkStatus, pollDelay);
    } finally {
        checking.value = false;
    }
};

const resendRequest = () => {
    resending.value = true;
    router.post('/verify-phone/resend', {}, {
        onFinish: () => {
            resending.value = false;
            // Reset polling delay after resend
            pollDelay = 3000;
        },
    });
};

onMounted(() => {
    // Start polling
    checkStatus();
});

onUnmounted(() => {
    if (pollTimeout) {
        clearTimeout(pollTimeout);
    }
});
</script>

<template>
    <AuthLayout
        :title="t('auth.verifyPhone.title')"
        :description="t('auth.verifyPhone.telegramDescription')"
    >
        <Head :title="t('auth.verifyPhone.title')" />

        <div
            v-if="status === 'verification-request-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ t('auth.verifyPhone.requestSent') }}
        </div>

        <div class="flex flex-col items-center gap-6">
            <Button
                size="lg"
                class="w-full gap-2"
                @click="openTelegram"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.242-1.865-.442-.751-.244-1.349-.374-1.297-.789.027-.216.324-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.015 3.333-1.386 4.025-1.627 4.477-1.635.099-.002.321.023.465.141.121.099.155.232.17.324.015.092.033.301.019.465z"/>
                </svg>
                {{ t('auth.verifyPhone.openTelegram') }}
            </Button>

            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Spinner v-if="checking" class="h-4 w-4" />
                <span>{{ t('auth.verifyPhone.waitingForVerification') }}</span>
            </div>

            <div class="w-full rounded-lg border border-border bg-muted/50 p-4">
                <h3 class="mb-2 font-medium">{{ t('auth.verifyPhone.howItWorks') }}</h3>
                <ol class="list-inside list-decimal space-y-1 text-sm text-muted-foreground">
                    <li>{{ t('auth.verifyPhone.step1') }}</li>
                    <li>{{ t('auth.verifyPhone.step2') }}</li>
                    <li>{{ t('auth.verifyPhone.step3') }}</li>
                    <li>{{ t('auth.verifyPhone.step4') }}</li>
                </ol>
            </div>

            <button
                type="button"
                class="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                :disabled="resending"
                @click="resendRequest"
            >
                <Spinner v-if="resending" class="mr-2 inline h-3 w-3" />
                {{ t('auth.verifyPhone.resendRequest') }}
            </button>
        </div>
    </AuthLayout>
</template>
