<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const { t, locale } = useI18n();
const confirmationInput = useTemplateRef('confirmationInput');

const confirmationWord = () => locale.value === 'ar' ? 'حذف' : 'DELETE';
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall
            :title="t('settings.deleteAccount.heading')"
            :description="t('settings.deleteAccount.description')"
        />
        <div
            class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">{{ t('settings.deleteAccount.warningTitle') }}</p>
                <p class="text-sm">
                    {{ t('settings.deleteAccount.warningText') }}
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive" data-test="delete-user-button"
                        >{{ t('settings.deleteAccount.deleteButton') }}</Button
                    >
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => confirmationInput?.$el?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>{{ t('settings.deleteAccount.confirmTitle') }}</DialogTitle>
                            <DialogDescription>
                                {{ t('settings.deleteAccount.confirmDescription', { word: confirmationWord() }) }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="confirmation">
                                {{ t('settings.deleteAccount.confirmationLabel', { word: confirmationWord() }) }}
                            </Label>
                            <Input
                                id="confirmation"
                                type="text"
                                name="confirmation"
                                ref="confirmationInput"
                                :placeholder="confirmationWord()"
                                autocomplete="off"
                            />
                            <InputError :message="errors.confirmation" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    {{ t('common.cancel') }}
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                {{ t('settings.deleteAccount.deleteButton') }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
