<script setup lang="ts">
import SelectableCard from '@/components/SelectableCard.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const investmentGoal = defineModel<string>('investmentGoal', {
    required: true,
});
const tradingStyle = defineModel<string>('tradingStyle', { required: true });

interface Props {
    errors: Record<string, string>;
}

defineProps<Props>();

const investmentGoals = [
    'capital_growth',
    'fixed_income',
    'risk_reduction',
    'short_term_speculation',
    'retirement_planning',
    'wealth_preservation',
    'passive_income',
    'education_savings',
    'home_purchase',
    'emergency_fund',
];

const tradingStyles = [
    { value: 'day_trading', icon: '⚡' },
    { value: 'swing_trading', icon: '🔄' },
    { value: 'position_trading', icon: '📊' },
    { value: 'scalping_trading', icon: '⏱️' },
];
</script>

<template>
    <div class="space-y-8">
        <div class="text-center">
            <h2 class="text-2xl font-bold">
                {{ t('onboarding.step2.title') }}
            </h2>
            <p class="mt-2 text-muted-foreground">
                {{ t('onboarding.step2.description') }}
            </p>
        </div>

        <!-- Investment Goal -->
        <div class="space-y-4">
            <h3 class="font-semibold">
                {{ t('onboarding.investmentGoal.label') }}
            </h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <SelectableCard
                    v-for="goal in investmentGoals"
                    :key="goal"
                    :selected="investmentGoal === goal"
                    @select="investmentGoal = goal"
                >
                    <span class="text-sm font-medium">{{
                        t(`onboarding.investmentGoal.${goal}`)
                    }}</span>
                </SelectableCard>
            </div>
            <p v-if="errors.investment_goal" class="text-sm text-destructive">
                {{ errors.investment_goal }}
            </p>
        </div>

        <!-- Trading Style -->
        <div class="space-y-4">
            <h3 class="font-semibold">
                {{ t('onboarding.tradingStyle.label') }}
            </h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <SelectableCard
                    v-for="style in tradingStyles"
                    :key="style.value"
                    :selected="tradingStyle === style.value"
                    @select="tradingStyle = style.value"
                >
                    <span class="text-2xl">{{ style.icon }}</span>
                    <span class="font-medium">{{
                        t(`onboarding.tradingStyle.${style.value}`)
                    }}</span>
                </SelectableCard>
            </div>
            <p v-if="errors.trading_style" class="text-sm text-destructive">
                {{ errors.trading_style }}
            </p>
        </div>
    </div>
</template>
