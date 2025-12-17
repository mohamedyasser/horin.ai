<script setup lang="ts">
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import OnboardingStep1 from '@/components/onboarding/OnboardingStep1.vue';
import OnboardingStep2 from '@/components/onboarding/OnboardingStep2.vue';
import OnboardingStep3 from '@/components/onboarding/OnboardingStep3.vue';
import OnboardingStep4 from '@/components/onboarding/OnboardingStep4.vue';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface Country {
    id: string;
    name: string;
    code: string;
}

interface Market {
    id: string;
    name: string;
    code: string;
}

interface Sector {
    id: string;
    name: string;
}

interface Props {
    step: number;
    totalSteps: number;
    countries: Country[];
    markets: Market[];
    sectors: Sector[];
    user: {
        experience_level: string | null;
        risk_level: string | null;
        investment_goal: string | null;
        trading_style: string | null;
        country_id: string | null;
        markets: string[];
        sectors: string[];
    };
}

const props = defineProps<Props>();

const formData = ref({
    experience_level: props.user.experience_level || '',
    risk_level: props.user.risk_level || '',
    investment_goal: props.user.investment_goal || '',
    trading_style: props.user.trading_style || '',
    country_id: props.user.country_id || '',
    markets: props.user.markets || [],
    sectors: props.user.sectors || [],
});

const processing = ref(false);
const errors = ref<Record<string, string>>({});

const currentDir = computed(() => (locale.value === 'ar' ? 'rtl' : 'ltr'));

const canProceed = computed(() => {
    switch (props.step) {
        case 1:
            return formData.value.experience_level && formData.value.risk_level;
        case 2:
            return (
                formData.value.investment_goal && formData.value.trading_style
            );
        case 3:
            return (
                formData.value.country_id && formData.value.markets.length > 0
            );
        case 4:
            return formData.value.sectors.length > 0;
        default:
            return false;
    }
});

const goBack = () => {
    if (props.step > 1) {
        router.get('/onboarding', { step: props.step - 1 });
    }
};

const submitStep = () => {
    processing.value = true;
    errors.value = {};

    const data: Record<string, unknown> = { step: props.step };

    switch (props.step) {
        case 1:
            data.experience_level = formData.value.experience_level;
            data.risk_level = formData.value.risk_level;
            break;
        case 2:
            data.investment_goal = formData.value.investment_goal;
            data.trading_style = formData.value.trading_style;
            break;
        case 3:
            data.country_id = formData.value.country_id;
            data.markets = formData.value.markets;
            break;
        case 4:
            data.sectors = formData.value.sectors;
            break;
    }

    router.post('/onboarding', data, {
        onFinish: () => {
            processing.value = false;
        },
        onError: (errs) => {
            errors.value = errs;
        },
    });
};
</script>

<template>
    <div
        class="relative min-h-svh bg-background p-6 md:p-10"
        :dir="currentDir"
        :lang="locale"
    >
        <Head :title="t('onboarding.title')" />

        <div class="absolute end-4 top-4 flex items-center gap-2">
            <LanguageSwitcher />
            <Button
                as-child
                variant="ghost"
                size="icon"
            >
                <Link :href="logout()" as="button" :title="t('common.logout')">
                    <LogOut class="h-4 w-4" />
                    <span class="sr-only">{{ t('common.logout') }}</span>
                </Link>
            </Button>
        </div>

        <div class="mx-auto max-w-2xl">
            <!-- Progress -->
            <div class="mb-8">
                <div class="mb-2 text-sm text-muted-foreground">
                    {{
                        t('onboarding.step', {
                            current: step,
                            total: totalSteps,
                        })
                    }}
                </div>
                <div class="h-2 rounded-full bg-muted">
                    <div
                        class="h-2 rounded-full bg-primary transition-all"
                        :style="{ width: `${(step / totalSteps) * 100}%` }"
                    />
                </div>
            </div>

            <!-- Step Content -->
            <div class="mb-8">
                <OnboardingStep1
                    v-if="step === 1"
                    v-model:experienceLevel="formData.experience_level"
                    v-model:riskLevel="formData.risk_level"
                    :errors="errors"
                />
                <OnboardingStep2
                    v-else-if="step === 2"
                    v-model:investmentGoal="formData.investment_goal"
                    v-model:tradingStyle="formData.trading_style"
                    :errors="errors"
                />
                <OnboardingStep3
                    v-else-if="step === 3"
                    v-model:countryId="formData.country_id"
                    v-model:markets="formData.markets"
                    :countries="countries"
                    :availableMarkets="markets"
                    :errors="errors"
                />
                <OnboardingStep4
                    v-else-if="step === 4"
                    v-model:sectors="formData.sectors"
                    :availableSectors="sectors"
                    :errors="errors"
                />
            </div>

            <!-- Navigation -->
            <div class="flex justify-between gap-4">
                <Button
                    v-if="step > 1"
                    type="button"
                    variant="outline"
                    @click="goBack"
                >
                    {{ t('onboarding.buttons.back') }}
                </Button>
                <div v-else />

                <Button
                    type="button"
                    :disabled="!canProceed || processing"
                    @click="submitStep"
                >
                    {{
                        step === totalSteps
                            ? t('onboarding.buttons.complete')
                            : t('onboarding.buttons.next')
                    }}
                </Button>
            </div>
        </div>
    </div>
</template>
