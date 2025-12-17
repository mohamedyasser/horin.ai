<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { show } from '@/routes/two-factor';
import { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.twoFactor.title'),
        href: show.url(),
    },
]);

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

const enableForm = useForm({});
const disableForm = useForm({});

const handleEnable = () => {
    enableForm.post('/user/two-factor-authentication', {
        onSuccess: () => {
            showSetupModal.value = true;
        },
    });
};

const handleDisable = () => {
    disableForm.delete('/user/two-factor-authentication');
};

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.twoFactor.title')" />
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="t('settings.twoFactor.heading')"
                    :description="t('settings.twoFactor.description')"
                />

                <div
                    v-if="!twoFactorEnabled"
                    class="flex flex-col items-start justify-start space-y-4"
                >
                    <Badge variant="destructive">{{ t('settings.twoFactor.statusDisabled') }}</Badge>

                    <p class="text-muted-foreground">
                        {{ t('settings.twoFactor.disabledDescription') }}
                    </p>

                    <div>
                        <Button
                            v-if="hasSetupData"
                            @click="showSetupModal = true"
                        >
                            <ShieldCheck />{{ t('settings.twoFactor.continueSetup') }}
                        </Button>
                        <Button
                            v-else
                            @click="handleEnable"
                            :disabled="enableForm.processing"
                        >
                            <ShieldCheck />{{ t('settings.twoFactor.enableButton') }}
                        </Button>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-col items-start justify-start space-y-4"
                >
                    <Badge variant="default">{{ t('settings.twoFactor.statusEnabled') }}</Badge>

                    <p class="text-muted-foreground">
                        {{ t('settings.twoFactor.enabledDescription') }}
                    </p>

                    <TwoFactorRecoveryCodes />

                    <div class="relative inline">
                        <Button
                            variant="destructive"
                            @click="handleDisable"
                            :disabled="disableForm.processing"
                        >
                            <ShieldBan />
                            {{ t('settings.twoFactor.disableButton') }}
                        </Button>
                    </div>
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
