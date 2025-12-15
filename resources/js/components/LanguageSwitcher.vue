<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';

const { locale } = useI18n();
const page = usePage();

const languages = [
    { code: 'ar', name: 'العربية', dir: 'rtl' },
    { code: 'en', name: 'English', dir: 'ltr' },
] as const;

const currentLanguage = () => {
    return languages.find((lang) => lang.code === locale.value) || languages[0];
};

const switchLanguage = (code: string) => {
    const currentUrl = window.location.pathname;
    const currentLocale = (page.props.locale as string) || locale.value;

    // Update local state first
    locale.value = code;
    localStorage.setItem('locale', code);

    const lang = languages.find((l) => l.code === code);
    if (lang) {
        document.documentElement.lang = code;
        document.documentElement.dir = lang.dir;
    }

    // Check if current URL has a locale prefix (e.g., /ar/... or /en/...)
    const localePattern = new RegExp(`^/(ar|en)(/|$)`);

    if (localePattern.test(currentUrl)) {
        // Replace the locale prefix while preserving the rest of the path
        const newUrl = currentUrl.replace(localePattern, `/${code}$2`);
        router.visit(newUrl);
    } else {
        // No locale prefix (e.g., auth routes like /login, /register)
        // Just reload the current page to apply the new locale
        router.visit(currentUrl);
    }
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="sm" class="gap-1.5">
                <Languages class="size-4" />
                <span class="text-sm">{{ currentLanguage().name }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem
                v-for="lang in languages"
                :key="lang.code"
                @click="switchLanguage(lang.code)"
                :class="{ 'bg-muted': locale === lang.code }"
            >
                {{ lang.name }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
