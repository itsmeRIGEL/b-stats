<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const page = usePage<{ appLogo?: string }>();
</script>

<template>
    <img :src="page.props.appLogo || '/logo.svg'" :class="className" v-bind="$attrs" alt="App logo" />
</template>
