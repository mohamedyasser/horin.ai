<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Menu, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetClose,
} from '@/components/ui/sheet';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import { dashboard } from '@/routes';

const { t, locale } = useI18n();
const page = usePage();

const currentDir = computed(() => locale.value === 'ar' ? 'rtl' : 'ltr');
const mobileMenuOpen = ref(false);
const isAuthenticated = computed(() => !!page.props.auth?.user);

const login = () => '/login';
const register = () => '/register';

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
                    <LocalizedLink href="/" class="flex items-center gap-2">
                        <div class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon class="size-5 fill-current" />
                        </div>
                        <span class="text-lg font-semibold">Horin</span>
                    </LocalizedLink>

                    <!-- Navigation Links -->
                    <nav v-if="showNav" class="hidden sm:flex items-center gap-1">
                        <LocalizedLink
                            href="/predictions"
                            class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('predictions.nav.predictions') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/markets"
                            class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('predictions.nav.markets') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/sectors"
                            class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('predictions.nav.sectors') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/search"
                            class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('search.title') }}
                        </LocalizedLink>
                    </nav>
                </div>

                <nav class="flex items-center gap-2">
                    <LanguageSwitcher />
                    <template v-if="isAuthenticated">
                        <Button as-child class="hidden sm:inline-flex">
                            <Link :href="dashboard()">{{ t('dashboard.title') }}</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Link
                            v-if="canLogin"
                            :href="login()"
                            class="hidden sm:block text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                        >
                            {{ t('common.login') }}
                        </Link>
                        <Button v-if="canRegister" as-child class="hidden sm:inline-flex">
                            <Link :href="register()">{{ t('common.getStarted') }}</Link>
                        </Button>
                    </template>

                    <!-- Mobile menu button -->
                    <Button
                        v-if="showNav"
                        variant="ghost"
                        size="icon"
                        class="sm:hidden"
                        @click="mobileMenuOpen = true"
                    >
                        <Menu class="size-5" />
                        <span class="sr-only">Open menu</span>
                    </Button>
                </nav>
            </div>
        </header>

        <!-- Mobile Navigation Sheet -->
        <Sheet v-model:open="mobileMenuOpen">
            <SheetContent :side="locale === 'ar' ? 'right' : 'left'" class="w-[280px] sm:w-[320px]">
                <SheetHeader class="text-start">
                    <SheetTitle>
                        <LocalizedLink href="/" class="flex items-center gap-2" @click="mobileMenuOpen = false">
                            <div class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                <AppLogoIcon class="size-5 fill-current" />
                            </div>
                            <span class="text-lg font-semibold">Horin</span>
                        </LocalizedLink>
                    </SheetTitle>
                </SheetHeader>

                <nav class="mt-6 flex flex-col gap-1">
                    <LocalizedLink
                        href="/predictions"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('predictions.nav.predictions') }}
                    </LocalizedLink>
                    <LocalizedLink
                        href="/markets"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('predictions.nav.markets') }}
                    </LocalizedLink>
                    <LocalizedLink
                        href="/sectors"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('predictions.nav.sectors') }}
                    </LocalizedLink>
                    <LocalizedLink
                        href="/search"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('search.title') }}
                    </LocalizedLink>
                </nav>

                <div class="mt-6 border-t border-border pt-6 flex flex-col gap-2">
                    <template v-if="isAuthenticated">
                        <Button as-child class="w-full">
                            <Link :href="dashboard()" @click="mobileMenuOpen = false">{{ t('dashboard.title') }}</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button v-if="canLogin" variant="outline" as-child class="w-full">
                            <Link :href="login()" @click="mobileMenuOpen = false">{{ t('common.login') }}</Link>
                        </Button>
                        <Button v-if="canRegister" as-child class="w-full">
                            <Link :href="register()" @click="mobileMenuOpen = false">{{ t('common.getStarted') }}</Link>
                        </Button>
                    </template>
                </div>
            </SheetContent>
        </Sheet>

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
                    <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                        <LocalizedLink href="/about" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('about.title') }}
                        </LocalizedLink>
                        <LocalizedLink href="/faq" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('faq.title') }}
                        </LocalizedLink>
                        <LocalizedLink href="/methodology" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('methodology.title') }}
                        </LocalizedLink>
                        <LocalizedLink href="/privacy" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('privacy.title') }}
                        </LocalizedLink>
                        <LocalizedLink href="/terms" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('terms.title') }}
                        </LocalizedLink>
                        <LocalizedLink href="/contact" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('common.contact') }}
                        </LocalizedLink>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</template>
