<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Languages } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';

const { locale } = useI18n();

const languages = [
    { code: 'ar', name: 'العربية', dir: 'rtl' },
    { code: 'en', name: 'English', dir: 'ltr' },
] as const;

const currentLanguage = () => {
    return languages.find((lang) => lang.code === locale.value) || languages[0];
};

const switchLanguage = (code: string) => {
    locale.value = code;
    localStorage.setItem('locale', code);

    const lang = languages.find((l) => l.code === code);
    if (lang) {
        document.documentElement.lang = code;
        document.documentElement.dir = lang.dir;
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
