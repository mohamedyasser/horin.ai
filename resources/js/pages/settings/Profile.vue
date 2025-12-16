<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

const { t, locale } = useI18n();

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.profile.title'),
        href: edit().url,
    },
]);

const page = usePage();
const user = page.props.auth.user as { name: string; email: string; email_verified_at: string | null; language: string | null };

const selectedLanguage = ref(user.language || locale.value);

const updateLanguage = (value: string) => {
    selectedLanguage.value = value;
    router.patch(ProfileController.update().url, { language: value }, {
        preserveScroll: true,
        onSuccess: () => {
            // Reload the page to apply the new locale
            window.location.reload();
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.profile.title')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.profile.heading')"
                    :description="t('settings.profile.description')"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">{{ t('settings.profile.nameLabel') }}</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            :placeholder="t('settings.profile.namePlaceholder')"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ t('settings.profile.emailLabel') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            :placeholder="t('settings.profile.emailPlaceholder')"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            {{ t('settings.profile.unverifiedEmail') }}
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ t('settings.profile.resendVerification') }}
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            {{ t('settings.profile.verificationSent') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >{{ t('common.save') }}</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                {{ t('common.saved') }}
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <!-- Language Preference Section -->
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.profile.languageHeading')"
                    :description="t('settings.profile.languageDescription')"
                />

                <div class="grid gap-2">
                    <Label>{{ t('settings.profile.languageLabel') }}</Label>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            :variant="selectedLanguage === 'ar' ? 'default' : 'outline'"
                            @click="updateLanguage('ar')"
                            class="min-w-[120px]"
                        >
                            {{ t('settings.profile.languageArabic') }}
                        </Button>
                        <Button
                            type="button"
                            :variant="selectedLanguage === 'en' ? 'default' : 'outline'"
                            @click="updateLanguage('en')"
                            class="min-w-[120px]"
                        >
                            {{ t('settings.profile.languageEnglish') }}
                        </Button>
                    </div>
                </div>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
