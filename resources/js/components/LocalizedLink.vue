<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface Props {
    href: string;
    class?: HTMLAttributes['class'];
    preserveScroll?: boolean;
    preserveState?: boolean;
    replace?: boolean;
    only?: string[];
    except?: string[];
    headers?: Record<string, string>;
    queryStringArrayFormat?: 'brackets' | 'indices';
}

const props = withDefaults(defineProps<Props>(), {
    preserveScroll: false,
    preserveState: false,
    replace: false,
});

const { locale } = useI18n();

const localizedHref = computed(() => {
    // If href already starts with locale prefix, return as-is
    if (props.href.startsWith(`/${locale.value}/`) || props.href.startsWith(`/${locale.value}`)) {
        return props.href;
    }
    // If href is just '/', return locale home
    if (props.href === '/') {
        return `/${locale.value}`;
    }
    // Prepend locale to path
    return `/${locale.value}${props.href.startsWith('/') ? props.href : '/' + props.href}`;
});
</script>

<template>
    <Link
        :href="localizedHref"
        :class="props.class"
        :preserve-scroll="preserveScroll"
        :preserve-state="preserveState"
        :replace="replace"
        :only="only"
        :except="except"
        :headers="headers"
        :query-string-array-format="queryStringArrayFormat"
    >
        <slot />
    </Link>
</template>
