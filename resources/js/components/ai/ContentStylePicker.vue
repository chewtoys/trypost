<script setup lang="ts">
import { IconCheck } from '@tabler/icons-vue';

interface StyleOption {
    key: string;
    preview: string;
    name: string;
    description?: string;
}

const props = withDefaults(defineProps<{
    modelValue: string;
    styles: StyleOption[];
    compact?: boolean;
}>(), {
    compact: false,
});

const emit = defineEmits<{
    'update:modelValue': [string];
}>();

const select = (key: string) => {
    emit('update:modelValue', key);
};
</script>

<template>
    <div
        class="grid gap-2"
        :class="compact ? 'grid-cols-3' : 'gap-3 sm:grid-cols-3'"
    >
        <button
            v-for="style in props.styles"
            :key="style.key"
            type="button"
            :class="[
                compact
                    ? 'flex flex-col items-center gap-1.5 rounded-lg border p-2 text-left transition-colors'
                    : 'relative flex cursor-pointer flex-col overflow-hidden rounded-xl border-2 border-foreground bg-card text-left shadow-2xs transition-all hover:bg-foreground/5',
                compact
                    ? (modelValue === style.key
                        ? 'border-violet-500 bg-violet-50 dark:bg-violet-950/30'
                        : 'border-border hover:border-violet-300 hover:bg-muted/50')
                    : (modelValue === style.key
                        ? '!bg-violet-100 shadow-md'
                        : ''),
            ]"
            @click="select(style.key)"
        >
            <!-- Compact variant: simple thumbnail + name + active text color -->
            <template v-if="compact">
                <img
                    :src="style.preview"
                    :alt="style.name"
                    class="w-full rounded object-cover"
                />
                <span
                    class="text-center text-[10px] font-medium leading-tight"
                    :class="modelValue === style.key ? 'text-violet-700 dark:text-violet-300' : 'text-foreground/70'"
                >
                    {{ style.name }}
                </span>
            </template>

            <!-- Full variant: aspect-video thumbnail + name + description + check icon -->
            <template v-else>
                <div class="aspect-video w-full overflow-hidden bg-muted">
                    <img
                        :src="style.preview"
                        :alt="style.name"
                        class="size-full object-cover"
                    />
                </div>
                <div class="flex items-start gap-2 p-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-foreground">{{ style.name }}</p>
                        <p v-if="style.description" class="mt-0.5 text-xs leading-snug text-foreground/60">{{ style.description }}</p>
                    </div>
                    <IconCheck
                        v-if="modelValue === style.key"
                        class="mt-0.5 size-4 shrink-0 text-foreground"
                        stroke-width="3"
                    />
                </div>
            </template>
        </button>
    </div>
</template>
