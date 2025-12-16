<script setup lang="ts">
import SelectableCard from '@/components/SelectableCard.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const sectors = defineModel<string[]>('sectors', { required: true });

interface Sector {
    id: string;
    name: string;
}

interface Props {
    availableSectors: Sector[];
    errors: Record<string, string>;
}

const props = defineProps<Props>();

const toggleSector = (sectorId: string) => {
    const index = sectors.value.indexOf(sectorId);
    if (index === -1) {
        sectors.value = [...sectors.value, sectorId];
    } else {
        sectors.value = sectors.value.filter((id) => id !== sectorId);
    }
};
</script>

<template>
    <div class="space-y-8">
        <div class="text-center">
            <h2 class="text-2xl font-bold">
                {{ t('onboarding.step4.title') }}
            </h2>
            <p class="mt-2 text-muted-foreground">
                {{ t('onboarding.step4.description') }}
            </p>
        </div>

        <!-- Sectors Multi-Select -->
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold">
                    {{ t('onboarding.sectors.label') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ t('onboarding.sectors.description') }}
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <SelectableCard
                    v-for="sector in props.availableSectors"
                    :key="sector.id"
                    :selected="sectors.includes(sector.id)"
                    @select="toggleSector(sector.id)"
                >
                    <span class="font-medium">{{ sector.name }}</span>
                </SelectableCard>
            </div>
            <p v-if="errors.sectors" class="text-sm text-destructive">
                {{ errors.sectors }}
            </p>
        </div>
    </div>
</template>
