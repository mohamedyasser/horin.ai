<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDebounceFn } from '@vueuse/core';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

interface Asset {
    id: string;
    symbol: string;
    name: string;
    name_ar: string;
}

interface FilterOption {
    id: string;
    name: string;
    name_ar: string;
}

interface Props {
    modelValue?: string;
    selectedAsset?: Asset | null;
    markets?: FilterOption[];
    countries?: FilterOption[];
    sectors?: FilterOption[];
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    selectedAsset: null,
    markets: () => [],
    countries: () => [],
    sectors: () => [],
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:selectedAsset': [asset: Asset | null];
}>();

const { t, locale } = useI18n();

const open = ref(false);
const searchQuery = ref('');
const selectedMarket = ref('');
const selectedCountry = ref('');
const selectedSector = ref('');
const assets = ref<Asset[]>([]);
const loading = ref(false);
const currentAsset = ref<Asset | null>(props.selectedAsset);

// Watch for prop changes
watch(() => props.selectedAsset, (newVal) => {
    currentAsset.value = newVal;
});

watch(() => props.modelValue, (newVal) => {
    if (!newVal) {
        currentAsset.value = null;
    }
});

const displayValue = computed(() => {
    if (currentAsset.value) {
        return `${currentAsset.value.symbol} - ${locale.value === 'ar' ? currentAsset.value.name_ar : currentAsset.value.name}`;
    }
    return '';
});

const searchAssets = useDebounceFn(async () => {
    if (!searchQuery.value && !selectedMarket.value && !selectedCountry.value && !selectedSector.value) {
        assets.value = [];
        return;
    }

    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (searchQuery.value) params.append('search', searchQuery.value);
        if (selectedMarket.value) params.append('market_id', selectedMarket.value);
        if (selectedCountry.value) params.append('country_id', selectedCountry.value);
        if (selectedSector.value) params.append('sector_id', selectedSector.value);

        const response = await fetch(`/alerts/search-assets?${params.toString()}`);
        const data = await response.json();
        assets.value = data.assets;
    } catch (error) {
        console.error('Failed to search assets:', error);
        assets.value = [];
    } finally {
        loading.value = false;
    }
}, 300);

watch([searchQuery, selectedMarket, selectedCountry, selectedSector], () => {
    searchAssets();
});

const selectAsset = (asset: Asset) => {
    currentAsset.value = asset;
    emit('update:modelValue', asset.id);
    emit('update:selectedAsset', asset);
    open.value = false;
};

const clearSelection = () => {
    currentAsset.value = null;
    emit('update:modelValue', '');
    emit('update:selectedAsset', null);
};

const clearFilters = () => {
    selectedMarket.value = '';
    selectedCountry.value = '';
    selectedSector.value = '';
    searchQuery.value = '';
};

const getLocalizedName = (item: FilterOption) => {
    return locale.value === 'ar' ? item.name_ar : item.name;
};
</script>

<template>
    <div class="space-y-3">
        <!-- Filters Row -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="space-y-1.5">
                <Label class="text-xs text-muted-foreground">{{ t('alerts.filters.market') }}</Label>
                <Select v-model="selectedMarket">
                    <SelectTrigger class="h-9">
                        <SelectValue :placeholder="t('alerts.filters.all_markets')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">{{ t('alerts.filters.all_markets') }}</SelectItem>
                        <SelectItem v-for="market in markets" :key="market.id" :value="market.id">
                            {{ getLocalizedName(market) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-1.5">
                <Label class="text-xs text-muted-foreground">{{ t('alerts.filters.country') }}</Label>
                <Select v-model="selectedCountry">
                    <SelectTrigger class="h-9">
                        <SelectValue :placeholder="t('alerts.filters.all_countries')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">{{ t('alerts.filters.all_countries') }}</SelectItem>
                        <SelectItem v-for="country in countries" :key="country.id" :value="country.id">
                            {{ getLocalizedName(country) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-1.5">
                <Label class="text-xs text-muted-foreground">{{ t('alerts.filters.sector') }}</Label>
                <Select v-model="selectedSector">
                    <SelectTrigger class="h-9">
                        <SelectValue :placeholder="t('alerts.filters.all_sectors')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">{{ t('alerts.filters.all_sectors') }}</SelectItem>
                        <SelectItem v-for="sector in sectors" :key="sector.id" :value="sector.id">
                            {{ getLocalizedName(sector) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Asset Search -->
        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <Label>{{ t('alerts.fields.asset') }}</Label>
                <Button
                    v-if="selectedMarket || selectedCountry || selectedSector"
                    variant="ghost"
                    size="sm"
                    class="h-auto px-2 py-1 text-xs"
                    @click="clearFilters"
                >
                    {{ t('alerts.filters.clear') }}
                </Button>
            </div>
            <Popover v-model:open="open">
                <PopoverTrigger as-child>
                    <Button
                        variant="outline"
                        role="combobox"
                        :aria-expanded="open"
                        class="w-full justify-between"
                    >
                        <span v-if="currentAsset" class="truncate">
                            {{ displayValue }}
                        </span>
                        <span v-else class="text-muted-foreground">
                            {{ t('alerts.select_asset') }}
                        </span>
                        <div class="flex items-center gap-1">
                            <X
                                v-if="currentAsset"
                                class="size-4 shrink-0 opacity-50 hover:opacity-100"
                                @click.stop="clearSelection"
                            />
                            <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
                        </div>
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[400px] p-0" align="start">
                    <Command>
                        <CommandInput
                            v-model="searchQuery"
                            :placeholder="t('alerts.search_asset_placeholder')"
                        />
                        <CommandList>
                            <CommandEmpty v-if="!loading">
                                {{ searchQuery || selectedMarket || selectedCountry || selectedSector
                                    ? t('alerts.no_assets_found')
                                    : t('alerts.search_to_find_assets') }}
                            </CommandEmpty>
                            <CommandEmpty v-else>
                                {{ t('common.loading') }}
                            </CommandEmpty>
                            <CommandGroup v-if="assets.length > 0">
                                <CommandItem
                                    v-for="asset in assets"
                                    :key="asset.id"
                                    :value="asset.symbol"
                                    @select="selectAsset(asset)"
                                >
                                    <Check
                                        :class="cn(
                                            'mr-2 h-4 w-4',
                                            currentAsset?.id === asset.id ? 'opacity-100' : 'opacity-0'
                                        )"
                                    />
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ asset.symbol }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            {{ locale === 'ar' ? asset.name_ar : asset.name }}
                                        </span>
                                    </div>
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    </div>
</template>
