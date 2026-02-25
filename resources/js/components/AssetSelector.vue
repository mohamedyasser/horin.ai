<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { useDebounceFn } from '@vueuse/core';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Asset {
    id: string;
    symbol: string;
    name: string;
    name_ar: string;
    last_price?: number | null;
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
    sectors?: FilterOption[];
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    selectedAsset: null,
    markets: () => [],
    sectors: () => [],
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:selectedAsset': [asset: Asset | null];
}>();

const { t, locale } = useI18n();

const open = ref(false);
const searchQuery = ref('');
const selectedMarket = ref<string | undefined>(undefined);
const selectedSector = ref<string | undefined>(undefined);
const assets = ref<Asset[]>([]);
const loading = ref(false);
const currentAsset = ref<Asset | null>(props.selectedAsset);

// Watch for prop changes
watch(
    () => props.selectedAsset,
    (newVal) => {
        currentAsset.value = newVal;
    },
);

watch(
    () => props.modelValue,
    (newVal) => {
        if (!newVal) {
            currentAsset.value = null;
        }
    },
);

const displayValue = computed(() => {
    if (currentAsset.value) {
        return `${currentAsset.value.symbol} - ${locale.value === 'ar' ? currentAsset.value.name_ar : currentAsset.value.name}`;
    }
    return '';
});

const hasFilters = computed(() => {
    return searchQuery.value || selectedMarket.value || selectedSector.value;
});

const searchAssets = useDebounceFn(async () => {
    if (!hasFilters.value) {
        assets.value = [];
        return;
    }

    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (searchQuery.value) params.append('search', searchQuery.value);
        if (selectedMarket.value)
            params.append('market_id', selectedMarket.value);
        if (selectedSector.value)
            params.append('sector_id', selectedSector.value);

        const response = await fetch(
            `/alerts/search-assets?${params.toString()}`,
        );
        const data = await response.json();
        assets.value = data.assets;
    } catch (error) {
        console.error('Failed to search assets:', error);
        assets.value = [];
    } finally {
        loading.value = false;
    }
}, 300);

// Watch for filter changes and trigger search
watch([searchQuery, selectedMarket, selectedSector], () => {
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
    selectedMarket.value = undefined;
    selectedSector.value = undefined;
    searchQuery.value = '';
    assets.value = [];
};

const getLocalizedName = (item: FilterOption) => {
    return locale.value === 'ar' ? item.name_ar : item.name;
};

const handleMarketChange = (value: string) => {
    selectedMarket.value = value === 'all' ? undefined : value;
};

const handleSectorChange = (value: string) => {
    selectedSector.value = value === 'all' ? undefined : value;
};
</script>

<template>
    <div class="space-y-3">
        <!-- Filters Row -->
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-1.5">
                <Label class="text-xs text-muted-foreground">{{
                    t('alerts.filters.market')
                }}</Label>
                <Select
                    :model-value="selectedMarket || 'all'"
                    @update:model-value="handleMarketChange"
                >
                    <SelectTrigger class="h-9">
                        <SelectValue
                            :placeholder="t('alerts.filters.all_markets')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{
                            t('alerts.filters.all_markets')
                        }}</SelectItem>
                        <SelectItem
                            v-for="market in markets"
                            :key="market.id"
                            :value="market.id"
                        >
                            {{ getLocalizedName(market) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-1.5">
                <Label class="text-xs text-muted-foreground">{{
                    t('alerts.filters.sector')
                }}</Label>
                <Select
                    :model-value="selectedSector || 'all'"
                    @update:model-value="handleSectorChange"
                >
                    <SelectTrigger class="h-9">
                        <SelectValue
                            :placeholder="t('alerts.filters.all_sectors')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{
                            t('alerts.filters.all_sectors')
                        }}</SelectItem>
                        <SelectItem
                            v-for="sector in sectors"
                            :key="sector.id"
                            :value="sector.id"
                        >
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
                    v-if="selectedMarket || selectedSector"
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
                            <ChevronsUpDown
                                class="size-4 shrink-0 opacity-50"
                            />
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
                                {{
                                    hasFilters
                                        ? t('alerts.no_assets_found')
                                        : t('alerts.search_to_find_assets')
                                }}
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
                                        :class="
                                            cn(
                                                'me-2 size-4',
                                                currentAsset?.id === asset.id
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )
                                        "
                                    />
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{
                                            asset.symbol
                                        }}</span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                locale === 'ar'
                                                    ? asset.name_ar
                                                    : asset.name
                                            }}
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
