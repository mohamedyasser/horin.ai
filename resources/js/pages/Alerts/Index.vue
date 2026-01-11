<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Alert, AlertStats } from '@/types/alerts';
import type { BreadcrumbItemType, PaginationMeta } from '@/types';
import { useAlerts } from '@/composables/useAlerts';
import AlertCard from '@/components/alerts/AlertCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Bell, BellRing, Activity } from 'lucide-vue-next';

const { t } = useI18n();

interface Props {
    alerts: {
        data: Alert[];
        meta: PaginationMeta;
    };
    filters: {
        type?: string;
        status?: string;
        asset_id?: string;
    };
    stats: AlertStats;
}

const props = defineProps<Props>();

const { snoozeAlert, deleteAlert } = useAlerts();

const selectedFilter = ref(props.filters.status || 'all');

const filteredAlerts = computed(() => {
    if (selectedFilter.value === 'all') {
        return props.alerts.data;
    }
    return props.alerts.data.filter(a => a.status === selectedFilter.value);
});

const applyFilter = (status: string) => {
    selectedFilter.value = status;
    router.get('/alerts', { status: status === 'all' ? undefined : status }, {
        preserveState: true,
        replace: true,
    });
};

const handleSnooze = (alertId: string, preset: string) => {
    snoozeAlert(alertId, preset);
};

const handleDelete = (alertId: string) => {
    if (confirm(t('alerts.confirmDelete'))) {
        deleteAlert(alertId);
    }
};

const breadcrumbs: BreadcrumbItemType[] = [
    { title: t('alerts.title'), href: '/alerts' },
];

const filterOptions = ['all', 'active', 'triggered', 'paused', 'expired'] as const;
</script>

<template>
    <Head :title="t('alerts.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ t('alerts.title') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ t('alerts.subtitle') }}
                    </p>
                </div>
                <Button as-child>
                    <Link href="/alerts/create">
                        <Plus class="me-2 size-4" />
                        {{ t('alerts.create') }}
                    </Link>
                </Button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex size-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <Bell class="size-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">{{ t('alerts.stats.active') }}</p>
                            <p class="text-2xl font-bold">{{ stats.active }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex size-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                            <BellRing class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">{{ t('alerts.stats.triggered_today') }}</p>
                            <p class="text-2xl font-bold">{{ stats.triggered_today }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-4">
                        <div class="flex size-10 items-center justify-center rounded-full bg-muted">
                            <Activity class="size-5 text-muted-foreground" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">{{ t('alerts.stats.total') }}</p>
                            <p class="text-2xl font-bold">{{ stats.total }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <div class="flex gap-2 overflow-x-auto">
                <Button
                    v-for="status in filterOptions"
                    :key="status"
                    :variant="selectedFilter === status ? 'default' : 'outline'"
                    size="sm"
                    class="whitespace-nowrap"
                    @click="applyFilter(status)"
                >
                    {{ status === 'all' ? t('alerts.filters.all') : t(`alerts.status.${status}`) }}
                </Button>
            </div>

            <!-- Alert List -->
            <div class="space-y-4">
                <AlertCard
                    v-for="alert in filteredAlerts"
                    :key="alert.id"
                    :alert="alert"
                    @snooze="handleSnooze(alert.id, $event)"
                    @delete="handleDelete(alert.id)"
                />

                <!-- Empty State -->
                <div v-if="filteredAlerts.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                    <Bell class="size-12 text-muted-foreground/50" />
                    <h3 class="mt-4 text-lg font-medium">{{ t('alerts.empty.title') }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">{{ t('alerts.empty.description') }}</p>
                    <Button as-child class="mt-6">
                        <Link href="/alerts/create">
                            {{ t('alerts.create_first') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="alerts.meta.lastPage > 1" class="flex items-center justify-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="alerts.meta.currentPage <= 1"
                    @click="router.get('/alerts', { ...filters, page: alerts.meta.currentPage - 1 })"
                >
                    {{ t('common.previous') }}
                </Button>
                <span dir="ltr" class="text-sm text-muted-foreground">
                    {{ alerts.meta.currentPage }} / {{ alerts.meta.lastPage }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="alerts.meta.currentPage >= alerts.meta.lastPage"
                    @click="router.get('/alerts', { ...filters, page: alerts.meta.currentPage + 1 })"
                >
                    {{ t('common.next') }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
