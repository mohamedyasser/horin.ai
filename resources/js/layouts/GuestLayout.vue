<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { login, register } from '@/routes';

const { t, locale } = useI18n();
const page = usePage();

const currentDir = computed(() => locale.value === 'ar' ? 'rtl' : 'ltr');

// Get locale from page props or fallback to i18n locale
const currentLocale = computed(() => (page.props.locale as string) || locale.value);

// Helper to create localized URLs
const localizedUrl = (path: string) => `/${currentLocale.value}${path}`;

interface Props {
    canLogin?: boolean;
    canRegister?: boolean;
    showNav?: boolean;
}

withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
    showNav: true,
});
</script>

<template>
    <div class="min-h-screen bg-background" :dir="currentDir" :lang="locale">
        <!-- Header -->
        <header class="border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center gap-6">
                    <Link :href="localizedUrl('/')" class="flex items-center gap-2">
                        <div class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon class="size-5 fill-current" />
                        </div>
                        <span class="text-lg font-semibold">Horin</span>
                    </Link>

                    <!-- Navigation Links -->
                    <nav v-if="showNav" class="hidden sm:flex items-center gap-1">
                        <Link
                            :href="localizedUrl('/predictions')"
                            class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('predictions.nav.predictions') }}
                        </Link>
                        <Link
                            :href="localizedUrl('/markets')"
                            class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('predictions.nav.markets') }}
                        </Link>
                        <Link
                            :href="localizedUrl('/sectors')"
                            class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('predictions.nav.sectors') }}
                        </Link>
                        <Link
                            :href="localizedUrl('/search')"
                            class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('search.title') }}
                        </Link>
                    </nav>
                </div>

                <nav class="flex items-center gap-2">
                    <LanguageSwitcher />
                    <Link
                        v-if="canLogin"
                        :href="login()"
                        class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                    >
                        {{ t('common.login') }}
                    </Link>
                    <Button v-if="canRegister" as-child>
                        <Link :href="register()">{{ t('common.getStarted') }}</Link>
                    </Button>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main>
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-border/40 bg-muted/30">
            <div class="mx-auto max-w-7xl px-4 py-6">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} Horin. {{ t('common.allRightsReserved') }}
                    </p>
                    <nav class="flex items-center gap-4">
                        <Link :href="localizedUrl('/about')" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('common.about') }}
                        </Link>
                        <a href="mailto:contact@horin.com" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('common.contact') }}
                        </a>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</template>
