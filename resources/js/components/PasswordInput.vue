<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        modelValue?: string;
        class?: string;
    }>(),
    { modelValue: '' },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const attrs = useAttrs();
const show = ref(false);
</script>

<template>
    <div class="relative">
        <Input
            v-bind="attrs"
            :type="show ? 'text' : 'password'"
            :model-value="modelValue"
            @update:model-value="(v) => emit('update:modelValue', String(v))"
            class="pr-10"
        />
        <button
            type="button"
            @click="show = !show"
            :aria-label="show ? 'Hide password' : 'Show password'"
            :title="show ? 'Hide password' : 'Show password'"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex h-full items-center px-3 text-neutral-500 transition-colors hover:text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:text-neutral-400 dark:hover:text-neutral-200"
        >
            <EyeOff v-if="show" class="h-4 w-4" />
            <Eye v-else class="h-4 w-4" />
        </button>
    </div>
</template>
