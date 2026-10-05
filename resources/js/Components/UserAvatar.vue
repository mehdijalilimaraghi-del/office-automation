<script setup lang="ts">
import { computed, ref } from 'vue';

type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl';

const props = withDefaults(defineProps<{
    name?: string | null;
    avatarPath?: string | null;
    size?: AvatarSize;
    alt?: string;
}>(), {
    name: '',
    avatarPath: null,
    size: 'md',
    alt: '',
});

const imageFailed = ref(false);

const imageSrc = computed(() => {
    if (!imageFailed.value && props.avatarPath) {
        return `/storage/${props.avatarPath}`;
    }

    return '/images/default-avatar.svg';
});

const sizeClasses: Record<AvatarSize, string> = {
    xs: 'h-6 w-6',
    sm: 'h-8 w-8',
    md: 'h-10 w-10',
    lg: 'h-12 w-12',
    xl: 'h-16 w-16',
};

function handleImageError(): void {
    imageFailed.value = true;
}
</script>

<template>
    <span
        class="inline-flex shrink-0 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200"
        :class="sizeClasses[size]"
    >
        <img
            :src="imageSrc"
            :alt="alt || `تصویر ${name || 'کاربر'}`"
            class="h-full w-full object-cover"
            loading="lazy"
            @error="handleImageError"
        />
    </span>
</template>
