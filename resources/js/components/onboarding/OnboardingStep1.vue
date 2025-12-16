<script setup lang="ts">
import SelectableCard from '@/components/SelectableCard.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const experienceLevel = defineModel<string>('experienceLevel', {
    required: true,
});
const riskLevel = defineModel<string>('riskLevel', { required: true });

interface Props {
    errors: Record<string, string>;
}

defineProps<Props>();

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
</script>

<template>
    <div class="space-y-8">
        <div class="text-center">
            <h2 class="text-2xl font-bold">
                {{ t('onboarding.step1.title') }}
            </h2>
            <p class="mt-2 text-muted-foreground">
                {{ t('onboarding.step1.description') }}
            </p>
        </div>

        <!-- Experience Level -->
        <div class="space-y-4">
            <h3 class="font-semibold">
                {{ t('onboarding.experienceLevel.label') }}
            </h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <SelectableCard
                    v-for="level in experienceLevels"
                    :key="level.value"
                    :selected="experienceLevel === level.value"
                    @select="experienceLevel = level.value"
                >
                    <span class="text-3xl">{{ level.icon }}</span>
                    <span class="font-medium">{{
                        t(`onboarding.experienceLevel.${level.value}`)
                    }}</span>
                    <span class="text-sm text-muted-foreground">{{
                        t(`onboarding.experienceLevel.${level.value}Desc`)
                    }}</span>
                </SelectableCard>
            </div>
            <p v-if="errors.experience_level" class="text-sm text-destructive">
                {{ errors.experience_level }}
            </p>
        </div>

        <!-- Risk Level -->
        <div class="space-y-4">
            <h3 class="font-semibold">{{ t('onboarding.riskLevel.label') }}</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <SelectableCard
                    v-for="level in riskLevels"
                    :key="level.value"
                    :selected="riskLevel === level.value"
                    @select="riskLevel = level.value"
                >
                    <span class="text-3xl">{{ level.icon }}</span>
                    <span class="font-medium">{{
                        t(`onboarding.riskLevel.${level.value}`)
                    }}</span>
                    <span class="text-sm text-muted-foreground">{{
                        t(`onboarding.riskLevel.${level.value}Desc`)
                    }}</span>
                </SelectableCard>
            </div>
            <p v-if="errors.risk_level" class="text-sm text-destructive">
                {{ errors.risk_level }}
            </p>
        </div>
    </div>
</template>
