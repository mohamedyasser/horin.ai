<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    PinInput,
    PinInputGroup,
    PinInputSlot,
} from '@/components/ui/pin-input';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface AuthConfigContent {
    title: string;
    description: string;
    toggleText: string;
}

const authConfigContent = computed<AuthConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: t('auth.twoFactor.recoveryLabel'),
            description: t('auth.twoFactor.description'),
            toggleText: t('auth.twoFactor.useCode'),
        };
    }

    return {
        title: t('auth.twoFactor.codeLabel'),
        description: t('auth.twoFactor.description'),
        toggleText: t('auth.twoFactor.useRecovery'),
    };
});

const showRecoveryInput = ref<boolean>(false);

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = [];
};

const code = ref<number[]>([]);
const codeValue = computed<string>(() => code.value.join(''));

const codeForm = useForm({
    code: codeValue,
});

const recoveryForm = useForm({
    recovery_code: '',
});

const submitCodeForm = () => {
    codeForm.transform((data) => ({
        ...data,
        code: codeValue.value,
    })).post('/two-factor-challenge', {
        onError: () => {
            code.value = [];
        },
    });
};

const submitRecoveryForm = () => {
    recoveryForm.post('/two-factor-challenge');
};
</script>

<template>
    <AuthLayout
        :title="authConfigContent.title"
        :description="authConfigContent.description"
    >
        <Head :title="t('auth.twoFactor.title')" />

        <div class="space-y-6">
            <template v-if="!showRecoveryInput">
                <form
                    @submit.prevent="submitCodeForm"
                    class="space-y-4"
                >
                    <div
                        class="flex flex-col items-center justify-center space-y-3 text-center"
                    >
                        <div class="flex w-full items-center justify-center">
                            <PinInput
                                id="otp"
                                placeholder="○"
                                v-model="code"
                                type="number"
                                otp
                            >
                                <PinInputGroup>
                                    <PinInputSlot
                                        v-for="(id, index) in 6"
                                        :key="id"
                                        :index="index"
                                        :disabled="codeForm.processing"
                                        autofocus
                                    />
                                </PinInputGroup>
                            </PinInput>
                        </div>
                        <InputError :message="codeForm.errors.code" />
                    </div>
                    <Button type="submit" class="w-full" :disabled="codeForm.processing">
                        {{ t('auth.twoFactor.submitButton') }}
                    </Button>
                    <div class="text-center text-sm text-muted-foreground">
                        <button
                            type="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            @click="() => toggleRecoveryMode(codeForm.clearErrors)"
                        >
                            {{ authConfigContent.toggleText }}
                        </button>
                    </div>
                </form>
            </template>

            <template v-else>
                <form
                    @submit.prevent="submitRecoveryForm"
                    class="space-y-4"
                >
                    <Input
                        v-model="recoveryForm.recovery_code"
                        type="text"
                        :placeholder="t('auth.twoFactor.recoveryPlaceholder')"
                        :autofocus="showRecoveryInput"
                        required
                    />
                    <InputError :message="recoveryForm.errors.recovery_code" />
                    <Button type="submit" class="w-full" :disabled="recoveryForm.processing">
                        {{ t('auth.twoFactor.submitButton') }}
                    </Button>

                    <div class="text-center text-sm text-muted-foreground">
                        <button
                            type="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            @click="() => toggleRecoveryMode(recoveryForm.clearErrors)"
                        >
                            {{ authConfigContent.toggleText }}
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </AuthLayout>
</template>
