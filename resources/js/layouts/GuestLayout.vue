<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { dashboard } from '@/routes';
import { telegram } from '@/routes/auth';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();
const page = usePage();

const currentDir = computed(() => (locale.value === 'ar' ? 'rtl' : 'ltr'));
const mobileMenuOpen = ref(false);
const scrolled = ref(false);
const onScroll = () => {
    scrolled.value = window.scrollY > 50;
};
onMounted(() => window.addEventListener('scroll', onScroll));
onUnmounted(() => window.removeEventListener('scroll', onScroll));
const isAuthenticated = computed(() => !!page.props.auth?.user);

const authUrl = () => telegram.url();

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
        <header
            class="fixed inset-x-0 top-0 z-30 transition-all duration-300"
            :class="
                scrolled
                    ? 'border-b border-border bg-background/80 backdrop-blur-lg'
                    : 'bg-transparent'
            "
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6"
            >
                <div class="flex items-center gap-6">
                    <LocalizedLink href="/" class="flex items-center gap-2">
                        <div
                            class="flex size-8 items-center justify-center rounded-md bg-foreground text-background"
                        >
                            <AppLogoIcon class="size-5 fill-current" />
                        </div>
                        <span class="text-lg font-bold tracking-tight"
                            >Horin</span
                        >
                    </LocalizedLink>

                    <!-- Navigation Links -->
                    <nav
                        v-if="showNav"
                        class="hidden items-center gap-1 sm:flex"
                    >
                        <LocalizedLink
                            href="/predictions"
                            class="px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('predictions.nav.predictions') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/recommendations"
                            class="px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('recommendations.title') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/markets"
                            class="px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('predictions.nav.markets') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/sectors"
                            class="px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('predictions.nav.sectors') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/search"
                            class="px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('search.title') }}
                        </LocalizedLink>
                        <LocalizedLink
                            v-if="locale === 'ar'"
                            href="/news"
                            class="px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('news.title') }}
                        </LocalizedLink>
                    </nav>
                </div>

                <nav class="flex items-center gap-2">
                    <LanguageSwitcher />
                    <template v-if="isAuthenticated">
                        <Button as-child class="hidden sm:inline-flex">
                            <Link :href="dashboard()">{{
                                t('dashboard.title')
                            }}</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            v-if="canLogin || canRegister"
                            as-child
                            class="hidden sm:inline-flex"
                        >
                            <Link :href="authUrl()">{{
                                t('common.getStarted')
                            }}</Link>
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
                        <span class="sr-only">{{ t('common.openMenu') }}</span>
                    </Button>
                </nav>
            </div>
        </header>

        <!-- Mobile Navigation Sheet -->
        <Sheet v-model:open="mobileMenuOpen">
            <SheetContent
                :side="locale === 'ar' ? 'right' : 'left'"
                class="w-[280px] sm:w-[320px]"
            >
                <SheetHeader class="text-start">
                    <SheetTitle>
                        <LocalizedLink
                            href="/"
                            class="flex items-center gap-2"
                            @click="mobileMenuOpen = false"
                        >
                            <div
                                class="flex size-8 items-center justify-center rounded-md bg-foreground text-background"
                            >
                                <AppLogoIcon class="size-5 fill-current" />
                            </div>
                            <span class="text-lg font-bold tracking-tight"
                                >Horin</span
                            >
                        </LocalizedLink>
                    </SheetTitle>
                </SheetHeader>

                <nav class="mt-6 flex flex-col gap-1">
                    <LocalizedLink
                        href="/predictions"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('predictions.nav.predictions') }}
                    </LocalizedLink>
                    <LocalizedLink
                        href="/recommendations"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('recommendations.title') }}
                    </LocalizedLink>
                    <LocalizedLink
                        href="/markets"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('predictions.nav.markets') }}
                    </LocalizedLink>
                    <LocalizedLink
                        href="/sectors"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('predictions.nav.sectors') }}
                    </LocalizedLink>
                    <LocalizedLink
                        href="/search"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('search.title') }}
                    </LocalizedLink>
                    <LocalizedLink
                        v-if="locale === 'ar'"
                        href="/news"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('news.title') }}
                    </LocalizedLink>
                </nav>

                <div
                    class="mt-6 flex flex-col gap-2 border-t border-border pt-6"
                >
                    <template v-if="isAuthenticated">
                        <Button as-child class="w-full">
                            <Link
                                :href="dashboard()"
                                @click="mobileMenuOpen = false"
                                >{{ t('dashboard.title') }}</Link
                            >
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            v-if="canLogin || canRegister"
                            as-child
                            class="w-full"
                        >
                            <Link
                                :href="authUrl()"
                                @click="mobileMenuOpen = false"
                                >{{ t('common.getStarted') }}</Link
                            >
                        </Button>
                    </template>
                </div>
            </SheetContent>
        </Sheet>

        <!-- Main Content -->
        <main class="pt-16">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-border">
            <div class="mx-auto max-w-7xl px-6 py-8">
                <div
                    class="flex flex-col items-center justify-between gap-4 sm:flex-row"
                >
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} Horin.
                        {{ t('common.allRightsReserved') }}
                    </p>
                    <nav
                        class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2"
                    >
                        <LocalizedLink
                            href="/about"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('about.title') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/faq"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('faq.title') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/methodology"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('methodology.title') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/privacy"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('privacy.title') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/terms"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('terms.title') }}
                        </LocalizedLink>
                        <LocalizedLink
                            href="/contact"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('common.contact') }}
                        </LocalizedLink>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</template>
