<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

interface Item {
    id: string;
    name: string;
    code?: string;
}

interface Props {
    items: Item[];
    modelValue: string[];
    saveLabel?: string;
    savedLabel?: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    saveLabel: 'Save',
    savedLabel: 'Saved',
    loading: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
    save: [value: string[]];
}>();

const localSelected = ref<string[]>([...props.modelValue]);
const hasChanges = computed(() => {
    if (localSelected.value.length !== props.modelValue.length) return true;
    return !localSelected.value.every((id) => props.modelValue.includes(id));
});

watch(
    () => props.modelValue,
    (newVal) => {
        localSelected.value = [...newVal];
    },
    { immediate: true }
);

const toggleItem = (id: string, checked: boolean) => {
    const index = localSelected.value.indexOf(id);
    if (checked && index === -1) {
        localSelected.value.push(id);
    } else if (!checked && index !== -1) {
        localSelected.value.splice(index, 1);
    }
};

const handleSave = () => {
    emit('update:modelValue', localSelected.value);
    emit('save', localSelected.value);
};
</script>

<template>
    <div class="space-y-4">
        <div class="max-h-64 space-y-2 overflow-y-auto rounded-md border p-4">
            <div
                v-for="item in items"
                :key="item.id"
                class="flex items-center gap-3"
            >
                <Checkbox
                    :id="`item-${item.id}`"
                    :model-value="localSelected.includes(item.id)"
                    @update:model-value="(checked: boolean) => toggleItem(item.id, checked)"
                />
                <Label
                    :for="`item-${item.id}`"
                    class="cursor-pointer text-sm font-normal"
                >
                    {{ item.name }}
                    <span v-if="item.code" class="text-muted-foreground">
                        ({{ item.code }})
                    </span>
                </Label>
            </div>
        </div>

        <Button
            type="button"
            :disabled="!hasChanges || loading || localSelected.length === 0"
            @click="handleSave"
        >
            {{ loading ? 'Saving...' : hasChanges ? saveLabel : savedLabel }}
        </Button>
    </div>
</template>
