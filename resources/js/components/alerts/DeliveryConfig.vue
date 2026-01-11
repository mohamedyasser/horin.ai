<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DeliveryConfig, EscalationConfig, AlertPriority } from '@/types/alerts';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    MessageCircle,
    Bell,
    Mail,
    Inbox,
    Plus,
    Trash2,
} from 'lucide-vue-next';

const { t, locale } = useI18n();

interface Props {
    deliveryConfig: DeliveryConfig;
    escalationConfig?: EscalationConfig;
    priority: AlertPriority;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:deliveryConfig': [value: DeliveryConfig];
    'update:escalationConfig': [value: EscalationConfig];
    'update:priority': [value: AlertPriority];
}>();

type Channel = 'telegram' | 'push' | 'email' | 'in_app';

const channels: Array<{ value: Channel; label: { en: string; ar: string }; icon: typeof MessageCircle }> = [
    { value: 'telegram', label: { en: 'Telegram', ar: 'تيليجرام' }, icon: MessageCircle },
    { value: 'push', label: { en: 'Push Notification', ar: 'إشعار فوري' }, icon: Bell },
    { value: 'email', label: { en: 'Email', ar: 'البريد الإلكتروني' }, icon: Mail },
    { value: 'in_app', label: { en: 'In-App', ar: 'داخل التطبيق' }, icon: Inbox },
];

const priorities: AlertPriority[] = ['critical', 'high', 'medium', 'low'];

const selectedChannels = computed(() => props.deliveryConfig.channels || ['telegram', 'in_app']);

const isChannelSelected = (channel: Channel): boolean => {
    return selectedChannels.value.includes(channel);
};

const toggleChannel = (channel: Channel) => {
    const current = [...selectedChannels.value];
    const index = current.indexOf(channel);

    if (index >= 0) {
        // Don't allow removing all channels
        if (current.length > 1) {
            current.splice(index, 1);
        }
    } else {
        current.push(channel);
    }

    emit('update:deliveryConfig', { ...props.deliveryConfig, channels: current });
};

const escalationEnabled = computed({
    get: () => props.escalationConfig?.enabled || false,
    set: (value: boolean) => {
        if (value) {
            emit('update:escalationConfig', {
                enabled: true,
                levels: [
                    { level: 1, channel: 'push', delay_minutes: 5 },
                    { level: 2, channel: 'email', delay_minutes: 15 },
                ],
                max_escalations: 3,
            });
        } else {
            emit('update:escalationConfig', { enabled: false, levels: [], max_escalations: 0 });
        }
    },
});

const addEscalationLevel = () => {
    if (!props.escalationConfig) return;

    const newLevel = {
        level: (props.escalationConfig.levels?.length || 0) + 1,
        channel: 'email' as const,
        delay_minutes: 30,
    };

    emit('update:escalationConfig', {
        ...props.escalationConfig,
        levels: [...(props.escalationConfig.levels || []), newLevel],
    });
};

const removeEscalationLevel = (index: number) => {
    if (!props.escalationConfig) return;

    const updated = [...props.escalationConfig.levels];
    updated.splice(index, 1);

    // Renumber levels
    updated.forEach((level, i) => {
        level.level = i + 1;
    });

    emit('update:escalationConfig', {
        ...props.escalationConfig,
        levels: updated,
    });
};

const updateEscalationLevel = (index: number, key: string, value: unknown) => {
    if (!props.escalationConfig) return;

    const updated = [...props.escalationConfig.levels];
    updated[index] = { ...updated[index], [key]: value };

    emit('update:escalationConfig', {
        ...props.escalationConfig,
        levels: updated,
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Priority -->
        <div class="space-y-3">
            <Label>{{ t('alerts.fields.priority') }}</Label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="p in priorities"
                    :key="p"
                    type="button"
                    class="rounded-md border px-4 py-2 text-sm font-medium transition-colors"
                    :class="{
                        'border-red-500 bg-red-500/10 text-red-600': priority === p && p === 'critical',
                        'border-orange-500 bg-orange-500/10 text-orange-600': priority === p && p === 'high',
                        'border-yellow-500 bg-yellow-500/10 text-yellow-600': priority === p && p === 'medium',
                        'border-gray-400 bg-gray-400/10 text-gray-600': priority === p && p === 'low',
                        'border-border hover:border-primary/50': priority !== p,
                    }"
                    @click="emit('update:priority', p)"
                >
                    {{ t(`alerts.priority.${p}`) }}
                </button>
            </div>
        </div>

        <!-- Delivery Channels -->
        <div class="space-y-3">
            <Label>{{ t('alerts.fields.delivery_channels') }}</Label>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button
                    v-for="channel in channels"
                    :key="channel.value"
                    type="button"
                    class="flex flex-col items-center gap-2 rounded-lg border-2 p-3 transition-colors"
                    :class="{
                        'border-primary bg-primary/5': isChannelSelected(channel.value),
                        'border-border hover:border-primary/50': !isChannelSelected(channel.value),
                    }"
                    @click="toggleChannel(channel.value)"
                >
                    <component :is="channel.icon" class="size-5" />
                    <span class="text-xs font-medium">
                        {{ locale === 'ar' ? channel.label.ar : channel.label.en }}
                    </span>
                </button>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ t('alerts.delivery_channels_hint') }}
            </p>
        </div>

        <!-- Escalation -->
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <Label>{{ t('alerts.escalation.title') }}</Label>
                        <p class="text-sm text-muted-foreground">
                            {{ t('alerts.escalation.description') }}
                        </p>
                    </div>
                    <Switch v-model:checked="escalationEnabled" />
                </div>

                <div v-if="escalationEnabled && escalationConfig" class="mt-4 space-y-4">
                    <div
                        v-for="(level, index) in escalationConfig.levels"
                        :key="index"
                        class="flex items-center gap-3 rounded-lg border p-3"
                    >
                        <Badge variant="outline">{{ t('alerts.escalation.level') }} {{ level.level }}</Badge>

                        <div class="flex flex-1 items-center gap-3">
                            <div class="grid gap-1">
                                <Label class="text-xs text-muted-foreground">{{ t('alerts.escalation.after') }}</Label>
                                <div class="flex items-center gap-1">
                                    <Input
                                        :model-value="level.delay_minutes"
                                        type="number"
                                        min="1"
                                        class="w-16"
                                        @update:model-value="updateEscalationLevel(index, 'delay_minutes', Number($event))"
                                    />
                                    <span class="text-sm text-muted-foreground">{{ t('common.minutes') }}</span>
                                </div>
                            </div>

                            <div class="grid gap-1">
                                <Label class="text-xs text-muted-foreground">{{ t('alerts.escalation.via') }}</Label>
                                <Select
                                    :model-value="level.channel"
                                    @update:model-value="updateEscalationLevel(index, 'channel', $event)"
                                >
                                    <SelectTrigger class="w-32">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="ch in channels" :key="ch.value" :value="ch.value">
                                            {{ locale === 'ar' ? ch.label.ar : ch.label.en }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <Button
                            v-if="escalationConfig.levels.length > 1"
                            variant="ghost"
                            size="icon"
                            @click="removeEscalationLevel(index)"
                        >
                            <Trash2 class="size-4 text-muted-foreground" />
                        </Button>
                    </div>

                    <Button
                        v-if="escalationConfig.levels.length < 4"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        @click="addEscalationLevel"
                    >
                        <Plus class="me-2 size-4" />
                        {{ t('alerts.escalation.add_level') }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
