<script setup lang="ts">
import SelectableCard from '@/components/SelectableCard.vue';
import { SearchableSelect } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const countryId = defineModel<string>('countryId', { required: true });
const markets = defineModel<string[]>('markets', { required: true });

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

interface Props {
    countries: Country[];
    availableMarkets: Market[];
    errors: Record<string, string>;
}

const props = defineProps<Props>();

const toggleMarket = (marketId: string) => {
    const index = markets.value.indexOf(marketId);
    if (index === -1) {
        markets.value = [...markets.value, marketId];
    } else {
        markets.value = markets.value.filter((id) => id !== marketId);
    }
};
</script>

<template>
    <div class="space-y-8">
        <div class="text-center">
            <h2 class="text-2xl font-bold">
                {{ t('onboarding.step3.title') }}
            </h2>
            <p class="mt-2 text-muted-foreground">
                {{ t('onboarding.step3.description') }}
            </p>
        </div>

        <!-- Country Selection -->
        <div class="space-y-4">
            <Label>{{ t('onboarding.country.label') }}</Label>
            <SearchableSelect
                v-model="countryId"
                :options="
                    props.countries.map((country) => ({
                        value: country.id,
                        label: country.name,
                    }))
                "
                :placeholder="t('onboarding.country.placeholder')"
                :search-placeholder="t('onboarding.country.searchPlaceholder')"
                :empty-text="t('onboarding.country.emptyText')"
            />
            <p v-if="errors.country_id" class="text-sm text-destructive">
                {{ errors.country_id }}
            </p>
        </div>

        <!-- Markets Multi-Select -->
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold">
                    {{ t('onboarding.markets.label') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ t('onboarding.markets.description') }}
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <SelectableCard
                    v-for="market in props.availableMarkets"
                    :key="market.id"
                    :selected="markets.includes(market.id)"
                    @select="toggleMarket(market.id)"
                >
                    <span class="font-medium">{{ market.name }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        market.code
                    }}</span>
                </SelectableCard>
            </div>
            <p v-if="errors.markets" class="text-sm text-destructive">
                {{ errors.markets }}
            </p>
        </div>
    </div>
</template>
