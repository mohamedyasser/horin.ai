<script setup lang="ts">
import AlertChainManager from '@/components/alerts/AlertChainManager.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { useAlerts } from '@/composables/useAlerts';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import type { Alert, AlertHistory, BacktestResult } from '@/types/alerts';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    BellRing,
    Brain,
    Clock,
    Copy,
    Edit,
    FlaskConical,
    Lightbulb,
    Shapes,
    Target,
    Trash2,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface Props {
    alert: {
        data: Alert & {
            history?: AlertHistory[];
        };
    };
    userAlerts?: Alert[];
    backtestResult?: BacktestResult | null;
}

const props = defineProps<Props>();

const {
    alertTypeLabels,
    triggerTypeLabels,
    snoozeAlert,
    deleteAlert,
    duplicateAlert,
} = useAlerts();

const alert = computed(() => props.alert.data);
const recentHistory = computed(() => alert.value.history || []);

const isRunningBacktest = ref(false);

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'price':
            return TrendingUp;
        case 'prediction':
            return Brain;
        case 'signal':
            return BarChart3;
        case 'anomaly':
            return AlertTriangle;
        case 'recommendation':
            return Lightbulb;
        case 'pattern':
            return Shapes;
        default:
            return TrendingUp;
    }
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'triggered':
            return 'secondary';
        case 'paused':
            return 'outline';
        case 'expired':
            return 'destructive';
        default:
            return 'default';
    }
};

const getPriorityVariant = (priority: string) => {
    switch (priority) {
        case 'critical':
            return 'destructive';
        case 'high':
            return 'default';
        case 'medium':
            return 'secondary';
        case 'low':
            return 'outline';
        default:
            return 'secondary';
    }
};

const formatParameterValue = (key: string, value: unknown): string => {
    if (typeof value === 'number') {
        if (
            key.includes('percent') ||
            key.includes('confidence') ||
            key.includes('strength') ||
            key.includes('rate')
        ) {
            return `${(value * 100).toFixed(0)}%`;
        }
        return value.toLocaleString(locale.value);
    }
    if (Array.isArray(value)) {
        return value.join(', ');
    }
    return String(value);
};

const handleSnooze = (preset: string) => {
    snoozeAlert(alert.value.id, preset);
};

const handleDelete = () => {
    if (confirm(t('alerts.confirmDelete'))) {
        deleteAlert(alert.value.id);
    }
};

const handleDuplicate = () => {
    duplicateAlert(alert.value.id);
};

const handleBacktest = () => {
    isRunningBacktest.value = true;
    router.post(
        `/alerts/${alert.value.id}/backtest`,
        {
            lookback_days: 90,
            include_ml_signals: false,
        },
        {
            onFinish: () => {
                isRunningBacktest.value = false;
            },
        },
    );
};

const handleRefresh = () => {
    router.reload({ only: ['alert', 'userAlerts'] });
};

const breadcrumbs: BreadcrumbItemType[] = [
    { title: t('alerts.title'), href: '/alerts' },
    {
        title: alert.value.asset?.symbol || t('alerts.details'),
        href: `/alerts/${alert.value.id}`,
    },
];

const TypeIcon = computed(() => getTypeIcon(alert.value.type));

const snoozePresets = [
    { value: '1h', label: '1h' },
    { value: '4h', label: '4h' },
    { value: '1d', label: '1d' },
];
</script>

<template>
    <Head
        :title="`${alert.asset?.symbol || t('alerts.details')} - ${t('alerts.title')}`"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="router.visit('/alerts')"
                    >
                        <ArrowLeft class="size-4 rtl:rotate-180" />
                    </Button>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-12 items-center justify-center rounded-full bg-muted"
                        >
                            <component
                                :is="TypeIcon"
                                class="size-6 text-foreground"
                            />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-2xl font-bold tracking-tight">
                                    {{
                                        alert.asset?.symbol ||
                                        t('alerts.multiple_assets')
                                    }}
                                </h1>
                                <Badge
                                    :variant="getStatusVariant(alert.status)"
                                >
                                    {{ t(`alerts.status.${alert.status}`) }}
                                </Badge>
                                <Badge
                                    :variant="
                                        getPriorityVariant(alert.priority)
                                    "
                                >
                                    {{ t(`alerts.priority.${alert.priority}`) }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    locale === 'ar'
                                        ? alertTypeLabels[alert.type].ar
                                        : alertTypeLabels[alert.type].en
                                }}
                                &bull;
                                {{
                                    locale === 'ar'
                                        ? triggerTypeLabels[alert.trigger_type]
                                              ?.ar
                                        : triggerTypeLabels[alert.trigger_type]
                                              ?.en
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="handleDuplicate"
                    >
                        <Copy class="me-2 size-4" />
                        {{ t('common.duplicate') }}
                    </Button>
                    <Button as-child size="sm">
                        <Link :href="`/alerts/${alert.id}/edit`">
                            <Edit class="me-2 size-4" />
                            {{ t('common.edit') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Alert Configuration -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Target class="size-5" />
                                {{ t('alerts.configuration') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Asset Info -->
                            <div
                                v-if="alert.asset"
                                class="flex items-center gap-4 rounded-lg bg-muted/50 p-4"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ alert.asset.symbol }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            locale === 'ar'
                                                ? alert.asset.name_ar
                                                : alert.asset.name
                                        }}
                                    </p>
                                </div>
                                <Separator
                                    orientation="vertical"
                                    class="h-10"
                                />
                                <div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ t('alerts.current_price') }}
                                    </p>
                                    <p class="font-medium tabular-nums">
                                        {{ alert.asset.last_price }}
                                        {{ t('common.currency') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Parameters -->
                            <div class="grid gap-4 sm:grid-cols-2">
                                <template
                                    v-for="(value, key) in alert.parameters"
                                    :key="key"
                                >
                                    <div
                                        v-if="
                                            value !== null &&
                                            value !== undefined
                                        "
                                        class="space-y-1"
                                    >
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ t(`alerts.params.${key}`, key) }}
                                        </p>
                                        <p class="font-medium">
                                            {{
                                                formatParameterValue(
                                                    key as string,
                                                    value,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </template>
                            </div>

                            <Separator />

                            <!-- Settings -->
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="space-y-1">
                                    <p class="text-sm text-muted-foreground">
                                        {{ t('alerts.fields.recurring') }}
                                    </p>
                                    <p class="font-medium">
                                        {{
                                            alert.is_recurring
                                                ? t('common.yes')
                                                : t('common.no')
                                        }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm text-muted-foreground">
                                        {{ t('alerts.fields.cooldown') }}
                                    </p>
                                    <p class="font-medium">
                                        {{ alert.cooldown_minutes }}
                                        {{ t('common.minutes') }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            t('alerts.fields.market_hours_only')
                                        }}
                                    </p>
                                    <p class="font-medium">
                                        {{
                                            alert.market_hours_only
                                                ? t('common.yes')
                                                : t('common.no')
                                        }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Backtest Results -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <FlaskConical class="size-5" />
                                    {{ t('alerts.backtest.title') }}
                                </CardTitle>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="isRunningBacktest"
                                    @click="handleBacktest"
                                >
                                    <FlaskConical class="me-2 size-4" />
                                    {{
                                        isRunningBacktest
                                            ? t('alerts.backtest.running')
                                            : t('alerts.backtest.run')
                                    }}
                                </Button>
                            </div>
                            <CardDescription>{{
                                t('alerts.backtest.description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="backtestResult" class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-4">
                                    <div
                                        class="rounded-md bg-muted/50 p-3 text-center"
                                    >
                                        <p
                                            class="text-2xl font-bold tabular-nums"
                                        >
                                            {{ backtestResult.trigger_count }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t('alerts.backtest.triggers') }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-md bg-muted/50 p-3 text-center"
                                    >
                                        <p
                                            class="text-2xl font-bold tabular-nums"
                                            :class="{
                                                'text-gain':
                                                    (backtestResult.avg_return_1d ||
                                                        0) > 0,
                                                'text-loss':
                                                    (backtestResult.avg_return_1d ||
                                                        0) < 0,
                                            }"
                                        >
                                            {{
                                                backtestResult.avg_return_1d
                                                    ? `${(backtestResult.avg_return_1d * 100).toFixed(1)}%`
                                                    : '-'
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t('alerts.backtest.avg_1d') }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-md bg-muted/50 p-3 text-center"
                                    >
                                        <p
                                            class="text-2xl font-bold tabular-nums"
                                            :class="{
                                                'text-gain':
                                                    (backtestResult.avg_return_1w ||
                                                        0) > 0,
                                                'text-loss':
                                                    (backtestResult.avg_return_1w ||
                                                        0) < 0,
                                            }"
                                        >
                                            {{
                                                backtestResult.avg_return_1w
                                                    ? `${(backtestResult.avg_return_1w * 100).toFixed(1)}%`
                                                    : '-'
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t('alerts.backtest.avg_1w') }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-md bg-muted/50 p-3 text-center"
                                    >
                                        <p
                                            class="text-2xl font-bold tabular-nums"
                                        >
                                            {{
                                                backtestResult.win_rate
                                                    ? `${(backtestResult.win_rate * 100).toFixed(0)}%`
                                                    : '-'
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t('alerts.backtest.win_rate') }}
                                        </p>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t('alerts.backtest.lookback', {
                                            days: backtestResult.lookback_days,
                                        })
                                    }}
                                    &bull;
                                    {{ t('alerts.backtest.completed_at') }}:
                                    {{
                                        new Date(
                                            backtestResult.completed_at,
                                        ).toLocaleString(locale)
                                    }}
                                </p>
                            </div>
                            <div
                                v-else
                                class="py-8 text-center text-muted-foreground"
                            >
                                <FlaskConical
                                    class="mx-auto size-8 opacity-50"
                                />
                                <p class="mt-2">
                                    {{ t('alerts.backtest.no_results') }}
                                </p>
                                <p class="text-sm">
                                    {{ t('alerts.backtest.run_hint') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Alert Chains -->
                    <AlertChainManager
                        v-if="userAlerts && userAlerts.length > 0"
                        :alert="alert"
                        :user-alerts="userAlerts"
                        @refresh="handleRefresh"
                    />

                    <!-- Recent History -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <Activity class="size-5" />
                                    {{ t('alerts.recent_history') }}
                                </CardTitle>
                                <Button as-child variant="ghost" size="sm">
                                    <Link href="/alerts-history">{{
                                        t('common.view_all')
                                    }}</Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="recentHistory.length > 0"
                                class="space-y-3"
                            >
                                <div
                                    v-for="history in recentHistory"
                                    :key="history.id"
                                    class="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-8 items-center justify-center rounded-full bg-muted"
                                        >
                                            <BellRing
                                                class="size-4 text-foreground"
                                            />
                                        </div>
                                        <div>
                                            <p class="font-medium">
                                                {{ t('alerts.triggered_at') }}:
                                                {{
                                                    new Date(
                                                        history.triggered_at,
                                                    ).toLocaleString(locale)
                                                }}
                                            </p>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ t('alerts.trigger_value') }}:
                                                {{ history.trigger_value }}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge
                                        v-if="history.acknowledged_at"
                                        variant="outline"
                                    >
                                        {{ t('alerts.acknowledged') }}
                                    </Badge>
                                </div>
                            </div>
                            <div
                                v-else
                                class="py-8 text-center text-muted-foreground"
                            >
                                <Activity class="mx-auto size-8 opacity-50" />
                                <p class="mt-2">{{ t('alerts.no_history') }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Quick Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('common.actions') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <!-- Snooze -->
                            <div class="space-y-2">
                                <p class="text-sm font-medium">
                                    {{ t('alerts.snooze.title') }}
                                </p>
                                <div class="flex gap-2">
                                    <Button
                                        v-for="preset in snoozePresets"
                                        :key="preset.value"
                                        variant="outline"
                                        size="sm"
                                        class="flex-1"
                                        @click="handleSnooze(preset.value)"
                                    >
                                        <Clock class="me-1 size-3" />
                                        {{ preset.label }}
                                    </Button>
                                </div>
                            </div>

                            <Separator />

                            <Button as-child class="w-full">
                                <Link :href="`/alerts/${alert.id}/edit`">
                                    <Edit class="me-2 size-4" />
                                    {{ t('common.edit') }}
                                </Link>
                            </Button>

                            <Button
                                variant="outline"
                                class="w-full"
                                @click="handleDuplicate"
                            >
                                <Copy class="me-2 size-4" />
                                {{ t('common.duplicate') }}
                            </Button>

                            <Button
                                variant="destructive"
                                class="w-full"
                                @click="handleDelete"
                            >
                                <Trash2 class="me-2 size-4" />
                                {{ t('common.delete') }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Statistics -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('alerts.statistics') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">{{
                                        t('alerts.fields.triggered_count')
                                    }}</span>
                                    <span class="font-medium tabular-nums">{{
                                        alert.triggered_count
                                    }}</span>
                                </div>
                                <div
                                    v-if="alert.max_triggers"
                                    class="space-y-1"
                                >
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">{{
                                            t('alerts.fields.max_triggers')
                                        }}</span>
                                        <span class="font-medium tabular-nums"
                                            >{{ alert.triggered_count }}/{{
                                                alert.max_triggers
                                            }}</span
                                        >
                                    </div>
                                    <Progress
                                        :model-value="
                                            (alert.triggered_count /
                                                alert.max_triggers) *
                                            100
                                        "
                                    />
                                </div>
                            </div>

                            <Separator />

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">{{
                                        t('common.created')
                                    }}</span>
                                    <span>{{
                                        new Date(
                                            alert.created_at,
                                        ).toLocaleDateString(locale)
                                    }}</span>
                                </div>
                                <div
                                    v-if="alert.last_triggered_at"
                                    class="flex justify-between"
                                >
                                    <span class="text-muted-foreground">{{
                                        t('alerts.last_triggered')
                                    }}</span>
                                    <span>{{
                                        new Date(
                                            alert.last_triggered_at,
                                        ).toLocaleDateString(locale)
                                    }}</span>
                                </div>
                                <div
                                    v-if="alert.expires_at"
                                    class="flex justify-between"
                                >
                                    <span class="text-muted-foreground">{{
                                        t('alerts.expires')
                                    }}</span>
                                    <span>{{
                                        new Date(
                                            alert.expires_at,
                                        ).toLocaleDateString(locale)
                                    }}</span>
                                </div>
                                <div
                                    v-if="
                                        alert.is_snoozed && alert.snoozed_until
                                    "
                                    class="flex justify-between"
                                >
                                    <span class="text-muted-foreground">{{
                                        t('alerts.snoozed_until')
                                    }}</span>
                                    <span>{{
                                        new Date(
                                            alert.snoozed_until,
                                        ).toLocaleString(locale)
                                    }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
