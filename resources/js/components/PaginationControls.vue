<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

interface Props {
    currentPage: number;
    lastPage: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'page-change', page: number): void;
}>();

const { t } = useI18n();

const isPreviousDisabled = computed(() => props.currentPage <= 1);
const isNextDisabled = computed(() => props.currentPage >= props.lastPage);

const handlePrevious = () => {
    if (!isPreviousDisabled.value) {
        emit('page-change', props.currentPage - 1);
    }
};

const handleNext = () => {
    if (!isNextDisabled.value) {
        emit('page-change', props.currentPage + 1);
    }
};
</script>

<template>
    <div class="flex items-center justify-center gap-2">
        <Button
            variant="outline"
            size="sm"
            :disabled="isPreviousDisabled"
            @click="handlePrevious"
        >
            <ChevronLeft class="size-4" />
            <span class="sr-only">{{ t('common.previous') }}</span>
        </Button>

        <span class="text-sm text-muted-foreground">
            {{ t('common.pageIndicator', { current: currentPage, total: lastPage }) }}
        </span>

        <Button
            variant="outline"
            size="sm"
            :disabled="isNextDisabled"
            @click="handleNext"
        >
            <ChevronRight class="size-4" />
            <span class="sr-only">{{ t('common.next') }}</span>
        </Button>
    </div>
</template>
