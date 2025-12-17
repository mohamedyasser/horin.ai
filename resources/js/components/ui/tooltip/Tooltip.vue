<script setup lang="ts">
import { TooltipRoot, type TooltipRootEmits, type TooltipRootProps, useForwardPropsEmits } from 'reka-ui'
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'

const props = defineProps<TooltipRootProps>()
const emits = defineEmits<TooltipRootEmits>()

const { locale } = useI18n()
const direction = computed(() => locale.value === 'ar' ? 'rtl' : 'ltr')

const forwarded = useForwardPropsEmits(props, emits)
</script>

<template>
  <TooltipRoot
    data-slot="tooltip"
    :dir="direction"
    v-bind="forwarded"
  >
    <slot />
  </TooltipRoot>
</template>
