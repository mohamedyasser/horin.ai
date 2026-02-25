<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItemType } from '@/types';
import type { AlertPreferences } from '@/types/alerts';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Bell,
    Clock,
    Mail,
    MessageCircle,
    Smartphone,
    Volume2,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    preferences: AlertPreferences;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: t('settings.title'), href: '/settings/profile' },
    { title: t('alerts.preferences.title'), href: '/settings/alerts' },
];

const form = useForm({
    default_channels: props.preferences.default_channels ?? ['in_app'],
    quiet_hours_start: props.preferences.quiet_hours_start ?? '',
    quiet_hours_end: props.preferences.quiet_hours_end ?? '',
    timezone: props.preferences.timezone ?? 'Africa/Cairo',
    max_alerts_per_hour: props.preferences.max_alerts_per_hour ?? 10,
    max_alerts_per_day: props.preferences.max_alerts_per_day ?? 50,
    digest_enabled: props.preferences.digest_enabled ?? false,
    digest_time: props.preferences.digest_time ?? '18:00',
    smart_defaults_enabled: props.preferences.smart_defaults_enabled ?? true,
});

const channelOptions = [
    { value: 'in_app', label: t('alerts.channels.in_app'), icon: Bell },
    { value: 'push', label: t('alerts.channels.push'), icon: Smartphone },
    { value: 'email', label: t('alerts.channels.email'), icon: Mail },
    {
        value: 'telegram',
        label: t('alerts.channels.telegram'),
        icon: MessageCircle,
    },
];

const timezones = [
    { value: 'Africa/Cairo', label: 'Cairo (EET/EEST)' },
    { value: 'Asia/Riyadh', label: 'Riyadh (AST)' },
    { value: 'Asia/Dubai', label: 'Dubai (GST)' },
    { value: 'Europe/London', label: 'London (GMT/BST)' },
];

const toggleChannel = (channel: string, checked: boolean) => {
    if (checked) {
        if (!form.default_channels.includes(channel)) {
            form.default_channels.push(channel);
        }
    } else {
        const index = form.default_channels.indexOf(channel);
        if (index !== -1) {
            form.default_channels.splice(index, 1);
        }
    }
};

const submit = () => {
    form.patch('/settings/alerts', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('alerts.preferences.title')" />

        <SettingsLayout>
            <HeadingSmall
                :title="t('alerts.preferences.title')"
                :description="t('alerts.preferences.description')"
            />

            <form @submit.prevent="submit" class="mt-6 space-y-6">
                <!-- Notification Channels -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Bell class="size-5" />
                            {{ t('alerts.preferences.channels.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('alerts.preferences.channels.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="channel in channelOptions"
                            :key="channel.value"
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="channel.icon"
                                    class="size-5 text-muted-foreground"
                                />
                                <Label :for="`channel-${channel.value}`">{{
                                    channel.label
                                }}</Label>
                            </div>
                            <Checkbox
                                :id="`channel-${channel.value}`"
                                :model-value="
                                    form.default_channels.includes(
                                        channel.value,
                                    )
                                "
                                @update:model-value="
                                    toggleChannel(channel.value, $event)
                                "
                            />
                        </div>
                        <InputError :message="form.errors.default_channels" />
                    </CardContent>
                </Card>

                <!-- Quiet Hours -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Volume2 class="size-5" />
                            {{ t('alerts.preferences.quiet_hours.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                t('alerts.preferences.quiet_hours.description')
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="quiet_hours_start">{{
                                    t('alerts.preferences.quiet_hours.start')
                                }}</Label>
                                <Input
                                    id="quiet_hours_start"
                                    v-model="form.quiet_hours_start"
                                    type="time"
                                />
                                <InputError
                                    :message="form.errors.quiet_hours_start"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="quiet_hours_end">{{
                                    t('alerts.preferences.quiet_hours.end')
                                }}</Label>
                                <Input
                                    id="quiet_hours_end"
                                    v-model="form.quiet_hours_end"
                                    type="time"
                                />
                                <InputError
                                    :message="form.errors.quiet_hours_end"
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="timezone">{{
                                t('alerts.preferences.timezone')
                            }}</Label>
                            <select
                                id="timezone"
                                v-model="form.timezone"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <option
                                    v-for="tz in timezones"
                                    :key="tz.value"
                                    :value="tz.value"
                                >
                                    {{ tz.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.timezone" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Rate Limits -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="size-5" />
                            {{ t('alerts.preferences.rate_limits.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                t('alerts.preferences.rate_limits.description')
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="max_alerts_per_hour">{{
                                    t('alerts.preferences.rate_limits.per_hour')
                                }}</Label>
                                <Input
                                    id="max_alerts_per_hour"
                                    v-model.number="form.max_alerts_per_hour"
                                    type="number"
                                    min="1"
                                    max="100"
                                />
                                <InputError
                                    :message="form.errors.max_alerts_per_hour"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="max_alerts_per_day">{{
                                    t('alerts.preferences.rate_limits.per_day')
                                }}</Label>
                                <Input
                                    id="max_alerts_per_day"
                                    v-model.number="form.max_alerts_per_day"
                                    type="number"
                                    min="1"
                                    max="500"
                                />
                                <InputError
                                    :message="form.errors.max_alerts_per_day"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Digest Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Mail class="size-5" />
                            {{ t('alerts.preferences.digest.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('alerts.preferences.digest.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <Label for="digest_enabled">{{
                                t('alerts.preferences.digest.enabled')
                            }}</Label>
                            <Checkbox
                                id="digest_enabled"
                                :model-value="form.digest_enabled"
                                @update:model-value="
                                    form.digest_enabled = $event
                                "
                            />
                        </div>
                        <div v-if="form.digest_enabled" class="space-y-2">
                            <Label for="digest_time">{{
                                t('alerts.preferences.digest.time')
                            }}</Label>
                            <Input
                                id="digest_time"
                                v-model="form.digest_time"
                                type="time"
                            />
                            <InputError :message="form.errors.digest_time" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Smart Defaults -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{
                            t('alerts.preferences.smart_defaults.title')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'alerts.preferences.smart_defaults.description',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <Label for="smart_defaults_enabled">{{
                                t('alerts.preferences.smart_defaults.enabled')
                            }}</Label>
                            <Checkbox
                                id="smart_defaults_enabled"
                                :model-value="form.smart_defaults_enabled"
                                @update:model-value="
                                    form.smart_defaults_enabled = $event
                                "
                            />
                        </div>
                    </CardContent>
                </Card>

                <Separator />

                <!-- Submit -->
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ t('common.save') }}
                    </Button>
                </div>
            </form>
        </SettingsLayout>
    </AppLayout>
</template>
