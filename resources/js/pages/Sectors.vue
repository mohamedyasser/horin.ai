<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LocalizedLink from '@/components/LocalizedLink.vue';
import ClickableTableRow from '@/components/ClickableTableRow.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {
    Search,
    ChevronDown,
    TrendingUp,
    Target,
    Layers,
    ArrowRight,
} from 'lucide-vue-next';
import type { MarketsBreakdown } from '@/types';
import { show as sectorRoute } from '@/actions/App/Http/Controllers/SectorController';

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

// Navigate to sector detail
const goToSector = (sectorId: string) => {
    router.visit(sectorRoute.url({ locale: locale.value, sector: sectorId }));
};

interface SectorListItem {
    id: string;
    name: string;
    description: string | null;
    assetCount: number;
    predictionCount: number;
    marketsBreakdown: MarketsBreakdown[];
}

interface Props {
    canLogin: boolean;
    canRegister: boolean;
    sectors: SectorListItem[];
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// State
const searchQuery = ref('');
const sortBy = ref<'alphabetical' | 'predictions'>('predictions');

// Computed - use props.sectors directly
const filteredSectors = computed(() => {
    let result = [...props.sectors];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (s) =>
                s.name.toLowerCase().includes(query) ||
                (s.description && s.description.toLowerCase().includes(query))
        );
    }

    // Sort
    if (sortBy.value === 'predictions') {
        result.sort((a, b) => b.predictionCount - a.predictionCount);
    } else {
        result.sort((a, b) => a.name.localeCompare(b.name));
    }

    return result;
});

const topSectors = computed(() =>
    [...props.sectors].sort((a, b) => b.predictionCount - a.predictionCount).slice(0, 5)
);

const trendingSector = computed(() =>
    props.sectors.length > 0
        ? [...props.sectors].sort((a, b) => b.predictionCount - a.predictionCount)[0]
        : null
);
</script>

<template>
    <Head :title="t('sectors.title')">
        <meta name="description" :content="t('meta.sectors')">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
            <!-- Hero Section -->
            <section class="border-b border-border/40 bg-muted/30">
                <div class="mx-auto max-w-7xl px-4 py-12 text-center">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ t('sectors.title') }}
                    </h1>
                    <p class="mt-3 text-lg text-muted-foreground">
                        {{ t('sectors.subtitle') }}
                    </p>

                    <!-- Search Bar -->
                    <div class="relative mx-auto mt-8 max-w-xl">
                        <Search class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('sectors.searchPlaceholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                </div>
            </section>

            <!-- Filter Bar -->
            <section class="border-b border-border/40">
                <div class="mx-auto max-w-7xl px-4 py-4">
                    <div class="flex flex-wrap items-center justify-end gap-4">
                        <!-- Sort Dropdown -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ t('sectors.sortBy') }}
                                    <ChevronDown class="ms-1 size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="sortBy = 'predictions'">
                                    {{ t('sectors.sortPredictions') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="sortBy = 'alphabetical'">
                                    {{ t('sectors.sortAlphabetical') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </section>

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-8">
                <div class="grid gap-8 lg:grid-cols-4">
                    <!-- Sectors Grid -->
                    <div class="lg:col-span-3">
                        <!-- Table View -->
                        <div class="rounded-lg border border-border">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-border bg-muted/50">
                                            <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.sector') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.assets') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.predictions') }}
                                            </th>
                                            <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.markets') }}
                                            </th>
                                            <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.action') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <ClickableTableRow
                                            v-for="sector in filteredSectors"
                                            :key="sector.id"
                                            :aria-label="`${t('sectors.viewPredictions')} ${sector.name}`"
                                            @click="goToSector(sector.id)"
                                        >
                                            <td class="px-4 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                        <Layers class="size-5" />
                                                    </div>
                                                    <div>
                                                        <p class="font-medium">{{ sector.name }}</p>
                                                        <p v-if="sector.description" class="text-sm text-muted-foreground">
                                                            {{ sector.description }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-end">
                                                <span class="font-medium">{{ sector.assetCount }}</span>
                                                <span class="ms-1 text-sm text-muted-foreground">{{ t('sectors.assets') }}</span>
                                            </td>
                                            <td class="px-4 py-4 text-end">
                                                <span class="font-medium text-primary">{{ sector.predictionCount }}</span>
                                                <span class="ms-1 text-sm text-muted-foreground">{{ t('sectors.predictions') }}</span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="flex flex-wrap gap-1">
                                                    <span
                                                        v-for="m in sector.marketsBreakdown.slice(0, 4)"
                                                        :key="m.marketId"
                                                        class="rounded bg-muted px-2 py-0.5 text-xs font-medium"
                                                    >
                                                        {{ m.marketCode }}: {{ m.count }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                <Button variant="ghost" size="sm" @click.stop="goToSector(sector.id)">
                                                    {{ t('sectors.viewPredictions') }}
                                                    <ArrowRight class="ms-1 size-4 rtl:rotate-180" />
                                                </Button>
                                            </td>
                                        </ClickableTableRow>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="filteredSectors.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <Search class="size-12 text-muted-foreground/50" />
                                <p class="mt-4 text-muted-foreground">
                                    {{ t('sectors.noResults') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Top Sectors -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <TrendingUp class="size-4 text-green-500" />
                                    {{ t('sectors.topSectors') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('sectors.topSectorsDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <LocalizedLink
                                    v-for="sector in topSectors"
                                    :key="sector.id"
                                    :href="`/sectors/${sector.id}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <span class="font-medium">{{ sector.name }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        {{ sector.predictionCount }} {{ t('sectors.predictions') }}
                                    </span>
                                </LocalizedLink>
                            </CardContent>
                        </Card>

                        <!-- Trending Sector -->
                        <Card v-if="trendingSector">
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Target class="size-4 text-blue-500" />
                                    {{ t('sectors.trendingSector') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('sectors.trendingSectorDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent>
                                <LocalizedLink
                                    :href="`/sectors/${trendingSector.id}`"
                                    class="block hover:bg-muted/30 -mx-2 px-2 py-2 rounded transition-colors"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                                <Layers class="size-5" />
                                            </div>
                                            <div>
                                                <p class="font-medium">{{ trendingSector.name }}</p>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ trendingSector.predictionCount }} {{ t('sectors.predictions') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </LocalizedLink>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
    </GuestLayout>
</template>
