<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import MultiSelectList from '@/components/MultiSelectList.vue';
import SearchableSelect from '@/components/ui/combobox/SearchableSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';
import { edit } from '@/routes/market-preferences';
import MarketPreferencesController from '@/actions/App/Http/Controllers/Settings/MarketPreferencesController';

const { t } = useI18n();

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
    countries: Country[];
    markets: Market[];
    sectors: Sector[];
    user: {
        country_id: string | null;
        markets: string[];
        sectors: string[];
    };
}

const props = defineProps<Props>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.marketPreferences.title'),
        href: edit().url,
    },
]);

const selectedCountry = ref(props.user.country_id);
const selectedMarkets = ref<string[]>(props.user.markets);
const selectedSectors = ref<string[]>(props.user.sectors);

const savingCountry = ref(false);
const savingMarkets = ref(false);
const savingSectors = ref(false);
const countrySaved = ref(false);

const updateCountry = (countryId: string) => {
    selectedCountry.value = countryId;
    savingCountry.value = true;

    router.patch(MarketPreferencesController.update().url, { country_id: countryId }, {
        preserveScroll: true,
        onSuccess: () => {
            countrySaved.value = true;
            setTimeout(() => {
                countrySaved.value = false;
            }, 2000);
        },
        onFinish: () => {
            savingCountry.value = false;
        },
    });
};

const saveMarkets = (markets: string[]) => {
    savingMarkets.value = true;

    router.patch(MarketPreferencesController.update().url, { markets }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedMarkets.value = markets;
        },
        onFinish: () => {
            savingMarkets.value = false;
        },
    });
};

const saveSectors = (sectors: string[]) => {
    savingSectors.value = true;

    router.patch(MarketPreferencesController.update().url, { sectors }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedSectors.value = sectors;
        },
        onFinish: () => {
            savingSectors.value = false;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.marketPreferences.title')" />

        <SettingsLayout>
            <!-- Country -->
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.marketPreferences.country.heading')"
                    :description="t('settings.marketPreferences.country.description')"
                />

                <div class="max-w-md">
                    <SearchableSelect
                        :model-value="selectedCountry"
                        :options="countries.map((country) => ({
                            value: country.id,
                            label: country.name,
                        }))"
                        :placeholder="t('settings.marketPreferences.country.placeholder')"
                        @update:model-value="updateCountry"
                    />
                    <p
                        v-if="countrySaved"
                        class="mt-2 text-sm text-green-600"
                    >
                        {{ t('settings.marketPreferences.saved') }}
                    </p>
                </div>
            </div>

            <!-- Markets -->
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.marketPreferences.markets.heading')"
                    :description="t('settings.marketPreferences.markets.description')"
                />

                <MultiSelectList
                    v-model="selectedMarkets"
                    :items="markets"
                    :save-label="t('settings.marketPreferences.save')"
                    :saved-label="t('settings.marketPreferences.saved')"
                    :loading="savingMarkets"
                    @save="saveMarkets"
                />
            </div>

            <!-- Sectors -->
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.marketPreferences.sectors.heading')"
                    :description="t('settings.marketPreferences.sectors.description')"
                />

                <MultiSelectList
                    v-model="selectedSectors"
                    :items="sectors"
                    :save-label="t('settings.marketPreferences.save')"
                    :saved-label="t('settings.marketPreferences.saved')"
                    :loading="savingSectors"
                    @save="saveSectors"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
