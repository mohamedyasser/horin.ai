<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAlerts } from '@/composables/useAlerts';
import type { Alert } from '@/types/alerts';
import { router } from '@inertiajs/vue3';
import { AlertCircle, ArrowRight, Link2, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();
const { alertTypeLabels, triggerTypeLabels } = useAlerts();

interface Props {
    alert: Alert;
    userAlerts: Alert[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    refresh: [];
}>();

const showAddChainDialog = ref(false);
const selectedAlertId = ref<string | null>(null);
const isSubmitting = ref(false);

// Filter available alerts that can be chained (not already chained, not self)
const availableAlerts = computed(() => {
    return props.userAlerts.filter(
        (a) =>
            a.id !== props.alert.id &&
            a.status === 'active' &&
            !a.chain_from_id,
    );
});

// Get the child alerts (alerts that trigger after this one)
const childAlerts = computed(() => {
    return props.userAlerts.filter((a) => a.chain_from_id === props.alert.id);
});

// Get the parent alert (if this alert is chained from another)
const parentAlert = computed(() => {
    if (!props.alert.chain_from_id) return null;
    return props.userAlerts.find((a) => a.id === props.alert.chain_from_id);
});

const addChainedAlert = async () => {
    if (!selectedAlertId.value) return;

    isSubmitting.value = true;

    router.patch(
        `/alerts/${selectedAlertId.value}`,
        {
            chain_from_id: props.alert.id,
        },
        {
            onSuccess: () => {
                showAddChainDialog.value = false;
                selectedAlertId.value = null;
                emit('refresh');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const removeChain = async (alertId: string) => {
    router.patch(
        `/alerts/${alertId}`,
        {
            chain_from_id: null,
        },
        {
            onSuccess: () => {
                emit('refresh');
            },
        },
    );
};

const getAlertLabel = (alert: Alert) => {
    const typeLabel =
        locale.value === 'ar'
            ? alertTypeLabels[alert.type]?.ar
            : alertTypeLabels[alert.type]?.en;
    const symbol = alert.asset?.symbol || t('alerts.multiple_assets');
    return `${symbol} - ${typeLabel}`;
};

const getStatusVariant = (status: string) => {
    return (
        {
            active: 'default' as const,
            triggered: 'secondary' as const,
            paused: 'outline' as const,
            chained: 'secondary' as const,
        }[status] || ('outline' as const)
    );
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Link2 class="size-5" />
                {{ t('alerts.chains.title') }}
            </CardTitle>
            <CardDescription>{{
                t('alerts.chains.description')
            }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Parent Alert (if exists) -->
            <div v-if="parentAlert" class="space-y-2">
                <Label class="text-xs text-muted-foreground uppercase">
                    {{ t('alerts.chains.triggered_by') }}
                </Label>
                <div class="flex items-center gap-3 rounded-lg border p-3">
                    <AlertCircle class="size-5 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ getAlertLabel(parentAlert) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                locale === 'ar'
                                    ? triggerTypeLabels[
                                          parentAlert.trigger_type
                                      ]?.ar
                                    : triggerTypeLabels[
                                          parentAlert.trigger_type
                                      ]?.en
                            }}
                        </p>
                    </div>
                    <Badge :variant="getStatusVariant(parentAlert.status)">
                        {{ t(`alerts.status.${parentAlert.status}`) }}
                    </Badge>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="removeChain(alert.id)"
                    >
                        <Trash2
                            class="size-4 text-muted-foreground hover:text-destructive"
                        />
                    </Button>
                </div>
            </div>

            <!-- Current Alert -->
            <div class="space-y-2">
                <Label class="text-xs text-muted-foreground uppercase">
                    {{ t('alerts.chains.current_alert') }}
                </Label>
                <div
                    class="flex items-center gap-3 rounded-md border-2 border-foreground/50 bg-muted p-3"
                >
                    <div
                        class="flex size-8 items-center justify-center rounded-full bg-foreground text-background"
                    >
                        {{ childAlerts.length + 1 }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ getAlertLabel(alert) }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                locale === 'ar'
                                    ? triggerTypeLabels[alert.trigger_type]?.ar
                                    : triggerTypeLabels[alert.trigger_type]?.en
                            }}
                        </p>
                    </div>
                    <Badge :variant="getStatusVariant(alert.status)">
                        {{ t(`alerts.status.${alert.status}`) }}
                    </Badge>
                </div>
            </div>

            <!-- Child Alerts (Chain After) -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label class="text-xs text-muted-foreground uppercase">
                        {{ t('alerts.chains.then_trigger') }}
                    </Label>
                    <Badge variant="outline" v-if="childAlerts.length > 0">
                        {{ childAlerts.length }}
                    </Badge>
                </div>

                <TransitionGroup name="list" tag="div" class="space-y-2">
                    <div
                        v-for="child in childAlerts"
                        :key="child.id"
                        class="flex items-center gap-3"
                    >
                        <ArrowRight class="size-4 text-muted-foreground" />
                        <div
                            class="flex flex-1 items-center gap-3 rounded-lg border p-3"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">
                                    {{ getAlertLabel(child) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        locale === 'ar'
                                            ? triggerTypeLabels[
                                                  child.trigger_type
                                              ]?.ar
                                            : triggerTypeLabels[
                                                  child.trigger_type
                                              ]?.en
                                    }}
                                </p>
                            </div>
                            <Badge :variant="getStatusVariant(child.status)">
                                {{ t(`alerts.status.${child.status}`) }}
                            </Badge>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="removeChain(child.id)"
                            >
                                <Trash2
                                    class="size-4 text-muted-foreground hover:text-destructive"
                                />
                            </Button>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Empty State -->
                <div
                    v-if="childAlerts.length === 0"
                    class="rounded-lg border-2 border-dashed p-6 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ t('alerts.chains.no_chained') }}
                    </p>
                </div>

                <!-- Add Chain Button -->
                <Dialog v-model:open="showAddChainDialog">
                    <DialogTrigger as-child>
                        <Button
                            variant="outline"
                            class="w-full"
                            :disabled="availableAlerts.length === 0"
                        >
                            <Plus class="me-2 size-4" />
                            {{ t('alerts.chains.add_chain') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{{
                                t('alerts.chains.add_title')
                            }}</DialogTitle>
                            <DialogDescription>
                                {{ t('alerts.chains.add_description') }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="space-y-4 py-4">
                            <div class="space-y-2">
                                <Label>{{
                                    t('alerts.chains.select_alert')
                                }}</Label>
                                <Select v-model="selectedAlertId">
                                    <SelectTrigger>
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'alerts.chains.select_placeholder',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="a in availableAlerts"
                                            :key="a.id"
                                            :value="a.id"
                                        >
                                            {{ getAlertLabel(a) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div
                                class="flex items-center gap-3 rounded-lg bg-muted p-3"
                            >
                                <div class="text-sm">
                                    <p class="font-medium">
                                        {{ t('alerts.chains.flow_preview') }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ getAlertLabel(alert) }}
                                        <ArrowRight
                                            class="mx-1 inline size-3"
                                        />
                                        {{
                                            selectedAlertId
                                                ? getAlertLabel(
                                                      availableAlerts.find(
                                                          (a) =>
                                                              a.id ===
                                                              selectedAlertId,
                                                      )!,
                                                  )
                                                : '...'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                variant="outline"
                                @click="showAddChainDialog = false"
                            >
                                {{ t('common.cancel') }}
                            </Button>
                            <Button
                                :disabled="!selectedAlertId || isSubmitting"
                                @click="addChainedAlert"
                            >
                                {{
                                    isSubmitting
                                        ? t('common.saving')
                                        : t('alerts.chains.add_chain')
                                }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Info -->
            <p class="text-xs text-muted-foreground">
                {{ t('alerts.chains.info') }}
            </p>
        </CardContent>
    </Card>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: all 0.3s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
