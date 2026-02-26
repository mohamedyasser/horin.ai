<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, PaginationMeta } from '@/types';
import type { AlertHistory, AlertHistoryStats } from '@/types/alerts';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Bell, BellRing, Check, Clock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface Props {
    history: {
        data: AlertHistory[];
        meta: PaginationMeta;
    };
    stats: AlertHistoryStats;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: t('alerts.title'), href: '/alerts' },
    { title: t('alerts.history.title'), href: '/alerts/history' },
];

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString(locale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const acknowledge = (historyId: string) => {
    router.post(
        `/alerts/history/${historyId}/acknowledge`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const goToPage = (page: number) => {
    router.get(
        '/alerts/history',
        { page },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const getTriggerTypeBadgeVariant = (triggerType: string) => {
    if (triggerType.includes('price') || triggerType === 'target_price')
        return 'default';
    if (triggerType === 'signal' || triggerType === 'pattern')
        return 'secondary';
    if (triggerType === 'anomaly') return 'destructive';
    return 'outline';
};
</script>

<template>
    <Head :title="t('alerts.history.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div>
                <h1 class="text-lg font-bold tracking-tight">
                    {{ t('alerts.history.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('alerts.history.subtitle') }}
                </p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="flex items-center gap-4 p-4">
                        <div
                            class="flex size-8 items-center justify-center rounded-full bg-muted"
                        >
                            <BellRing class="size-5 text-foreground" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('alerts.history.stats.today') }}
                            </p>
                            <p class="text-2xl font-bold tabular-nums">
                                {{ stats.today }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-4">
                        <div
                            class="flex size-8 items-center justify-center rounded-full bg-muted"
                        >
                            <Clock class="size-5 text-foreground" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('alerts.history.stats.this_week') }}
                            </p>
                            <p class="text-2xl font-bold tabular-nums">
                                {{ stats.this_week }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-4">
                        <div
                            class="flex size-8 items-center justify-center rounded-full bg-muted"
                        >
                            <AlertTriangle class="size-5 text-foreground" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('alerts.history.stats.unacknowledged') }}
                            </p>
                            <p class="text-2xl font-bold tabular-nums">
                                {{ stats.unacknowledged }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- History List -->
            <div class="space-y-4">
                <Card v-for="item in history.data" :key="item.id">
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted"
                                >
                                    <BellRing class="size-5 text-foreground" />
                                </div>
                                <div class="space-y-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3 class="font-semibold">
                                            {{ item.asset?.symbol || '-' }}
                                        </h3>
                                        <Badge
                                            :variant="
                                                getTriggerTypeBadgeVariant(
                                                    item.alert?.trigger_type ||
                                                        '',
                                                )
                                            "
                                        >
                                            {{
                                                item.alert?.trigger_type || '-'
                                            }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ t('alerts.history.trigger_value') }}:
                                        <span dir="ltr" class="tabular-nums">{{
                                            item.trigger_value?.toFixed(2) ||
                                            '-'
                                        }}</span>
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDate(item.triggered_at) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge
                                    v-if="item.acknowledged_at"
                                    variant="outline"
                                    class="bg-muted text-foreground"
                                >
                                    <Check class="me-1 size-3" />
                                    {{ t('alerts.history.acknowledged') }}
                                </Badge>
                                <Button
                                    v-else
                                    variant="outline"
                                    size="sm"
                                    @click="acknowledge(item.id)"
                                >
                                    <Check class="me-1 size-4" />
                                    {{ t('alerts.history.acknowledge') }}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <div
                    v-if="history.data.length === 0"
                    class="flex flex-col items-center justify-center py-12 text-center"
                >
                    <Bell class="size-12 text-muted-foreground/50" />
                    <h3 class="mt-4 text-lg font-medium">
                        {{ t('alerts.history.empty') }}
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('alerts.history.empty_description') }}
                    </p>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="history.meta.lastPage > 1"
                class="flex items-center justify-center gap-2"
            >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="history.meta.currentPage <= 1"
                    @click="goToPage(history.meta.currentPage - 1)"
                >
                    {{ t('common.previous') }}
                </Button>
                <span
                    dir="ltr"
                    class="text-sm text-muted-foreground tabular-nums"
                >
                    {{ history.meta.currentPage }} / {{ history.meta.lastPage }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        history.meta.currentPage >= history.meta.lastPage
                    "
                    @click="goToPage(history.meta.currentPage + 1)"
                >
                    {{ t('common.next') }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
