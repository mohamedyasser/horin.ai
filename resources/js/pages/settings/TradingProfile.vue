<script setup lang="ts">
import TradingProfileController from '@/actions/App/Http/Controllers/Settings/TradingProfileController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import SelectableCard from '@/components/SelectableCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/trading-profile';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    user: {
        experience_level: string | null;
        risk_level: string | null;
        investment_goal: string | null;
        trading_style: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.tradingProfile.title'),
        href: edit().url,
    },
]);

const saving = ref(false);
const recentlySaved = ref(false);

const experienceLevels = [
    { value: 'beginner', icon: '🌱' },
    { value: 'intermediate', icon: '📈' },
    { value: 'advanced', icon: '🎯' },
];

const riskLevels = [
    { value: 'conservative', icon: '🛡️' },
    { value: 'moderate', icon: '⚖️' },
    { value: 'aggressive', icon: '🚀' },
];

const investmentGoals = [
    { value: 'capital_growth', icon: '📈' },
    { value: 'fixed_income', icon: '💰' },
    { value: 'risk_reduction', icon: '🛡️' },
    { value: 'short_term_speculation', icon: '⚡' },
    { value: 'retirement_planning', icon: '🏖️' },
    { value: 'wealth_preservation', icon: '🏛️' },
    { value: 'passive_income', icon: '💵' },
    { value: 'education_savings', icon: '🎓' },
    { value: 'home_purchase', icon: '🏠' },
    { value: 'emergency_fund', icon: '🆘' },
];

const tradingStyles = [
    { value: 'day_trading', icon: '📊' },
    { value: 'swing_trading', icon: '🔄' },
    { value: 'position_trading', icon: '📅' },
    { value: 'scalping_trading', icon: '⚡' },
];

const updateField = (field: string, value: string) => {
    saving.value = true;
    router.patch(
        TradingProfileController.update().url,
        { [field]: value },
        {
            preserveScroll: true,
            onSuccess: () => {
                recentlySaved.value = true;
                setTimeout(() => {
                    recentlySaved.value = false;
                }, 2000);
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.tradingProfile.title')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.tradingProfile.experienceRisk.heading')"
                    :description="
                        t('settings.tradingProfile.experienceRisk.description')
                    "
                />

                <!-- Experience Level -->
                <div class="space-y-4">
                    <h3 class="font-semibold">
                        {{ t('onboarding.experienceLevel.label') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <SelectableCard
                            v-for="level in experienceLevels"
                            :key="level.value"
                            :selected="
                                props.user.experience_level === level.value
                            "
                            @select="
                                updateField('experience_level', level.value)
                            "
                        >
                            <span class="text-3xl">{{ level.icon }}</span>
                            <span class="font-medium">{{
                                t(`onboarding.experienceLevel.${level.value}`)
                            }}</span>
                        </SelectableCard>
                    </div>
                </div>

                <!-- Risk Level -->
                <div class="space-y-4">
                    <h3 class="font-semibold">
                        {{ t('onboarding.riskLevel.label') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <SelectableCard
                            v-for="level in riskLevels"
                            :key="level.value"
                            :selected="props.user.risk_level === level.value"
                            @select="updateField('risk_level', level.value)"
                        >
                            <span class="text-3xl">{{ level.icon }}</span>
                            <span class="font-medium">{{
                                t(`onboarding.riskLevel.${level.value}`)
                            }}</span>
                        </SelectableCard>
                    </div>
                </div>
            </div>

            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.tradingProfile.goalsStyle.heading')"
                    :description="
                        t('settings.tradingProfile.goalsStyle.description')
                    "
                />

                <!-- Investment Goal -->
                <div class="space-y-4">
                    <h3 class="font-semibold">
                        {{ t('onboarding.investmentGoal.label') }}
                    </h3>
                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <SelectableCard
                            v-for="goal in investmentGoals"
                            :key="goal.value"
                            :selected="
                                props.user.investment_goal === goal.value
                            "
                            @select="updateField('investment_goal', goal.value)"
                        >
                            <span class="text-3xl">{{ goal.icon }}</span>
                            <span class="font-medium">{{
                                t(`onboarding.investmentGoal.${goal.value}`)
                            }}</span>
                        </SelectableCard>
                    </div>
                </div>

                <!-- Trading Style -->
                <div class="space-y-4">
                    <h3 class="font-semibold">
                        {{ t('onboarding.tradingStyle.label') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <SelectableCard
                            v-for="style in tradingStyles"
                            :key="style.value"
                            :selected="props.user.trading_style === style.value"
                            @select="updateField('trading_style', style.value)"
                        >
                            <span class="text-3xl">{{ style.icon }}</span>
                            <span class="font-medium">{{
                                t(`onboarding.tradingStyle.${style.value}`)
                            }}</span>
                        </SelectableCard>
                    </div>
                </div>
            </div>

            <!-- Save indicator -->
            <div class="flex items-center gap-4">
                <span v-if="recentlySaved" class="text-sm text-gain">
                    {{ t('settings.tradingProfile.saved') }}
                </span>
                <span v-if="saving" class="text-sm text-muted-foreground">
                    Saving...
                </span>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
