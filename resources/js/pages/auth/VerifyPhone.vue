<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    PinInput,
    PinInputGroup,
    PinInputSlot,
} from '@/components/ui/pin-input';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
    phone: string;
    status?: string;
}>();

const code = ref<number[]>([]);
const codeValue = computed<string>(() => code.value.join(''));
const resendCooldown = ref(0);
let cooldownInterval: ReturnType<typeof setInterval> | null = null;

const startCooldown = (): void => {
    resendCooldown.value = 60;
    cooldownInterval = setInterval(() => {
        resendCooldown.value--;
        if (resendCooldown.value <= 0 && cooldownInterval) {
            clearInterval(cooldownInterval);
        }
    }, 1000);
};

const resendCode = (): void => {
    router.post('/verify-phone/resend', {}, {
        onSuccess: () => startCooldown(),
    });
};

onMounted(() => {
    startCooldown();
});

onUnmounted(() => {
    if (cooldownInterval) {
        clearInterval(cooldownInterval);
    }
});
</script>

<template>
    <AuthLayout
        :title="t('auth.verifyPhone.title')"
        :description="t('auth.verifyPhone.description', { phone })"
    >
        <Head :title="t('auth.verifyPhone.title')" />

        <div
            v-if="status === 'verification-code-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ t('auth.verifyPhone.codeSent') }}
        </div>

        <Form
            action="/verify-phone"
            method="post"
            class="space-y-4"
            reset-on-error
            @error="code = []"
            #default="{ errors, processing }"
        >
            <input type="hidden" name="code" :value="codeValue" />
            <div class="flex flex-col items-center justify-center space-y-3 text-center">
                <div class="flex w-full items-center justify-center">
                    <PinInput
                        id="verification-code"
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
                                :disabled="processing"
                                autofocus
                            />
                        </PinInputGroup>
                    </PinInput>
                </div>
                <InputError :message="errors.code" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing || code.length !== 6"
            >
                <Spinner v-if="processing" />
                {{ t('auth.verifyPhone.submitButton') }}
            </Button>

            <div class="text-center text-sm text-muted-foreground">
                <button
                    type="button"
                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    :disabled="resendCooldown > 0"
                    @click="resendCode"
                >
                    {{ resendCooldown > 0
                        ? t('auth.verifyPhone.resendIn', { seconds: resendCooldown })
                        : t('auth.verifyPhone.resendButton')
                    }}
                </button>
            </div>
        </Form>
    </AuthLayout>
</template>
