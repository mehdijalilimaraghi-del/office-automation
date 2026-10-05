<script setup lang="ts">
import { computed } from 'vue';
import { getDepartmentIcon } from '@/Utils/departmentIcons';

const props = withDefaults(defineProps<{
    icon?: string | null;
    size?: number;
    strokeWidth?: number;
}>(), {
    icon: 'building-2',
    size: 20,
    strokeWidth: 1.9,
});

const iconComponent = computed(() => getDepartmentIcon(props.icon));
</script>

<template>
    <component
        :is="iconComponent"
        :size="size"
        :stroke-width="strokeWidth"
        aria-hidden="true"
    />
</template>
