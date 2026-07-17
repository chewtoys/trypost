<script setup lang="ts">
type MobileView = 'compose' | 'channels' | 'preview' | 'comments';

const activeView = defineModel<MobileView>('activeView', { required: true });

const items: { key: MobileView; label: string }[] = [
    { key: 'compose', label: 'posts.edit.tabs.compose' },
    { key: 'channels', label: 'posts.edit.tabs.channels' },
    { key: 'preview', label: 'posts.edit.tabs.preview' },
    { key: 'comments', label: 'posts.edit.tabs.comments' },
];
</script>

<template>
    <div
        data-testid="editor-mobile-nav"
        class="flex shrink-0 gap-1 overflow-x-auto border-b-2 border-foreground bg-card px-2 py-2 lg:hidden [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
        <button
            v-for="item in items"
            :key="item.key"
            type="button"
            :data-testid="`editor-nav-${item.key}`"
            class="shrink-0 rounded-lg border-2 px-3 py-1.5 text-sm font-semibold transition-colors"
            :class="activeView === item.key
                ? 'border-foreground bg-violet-100 text-foreground'
                : 'border-transparent text-foreground/60 hover:text-foreground'"
            @click="activeView = item.key"
        >
            {{ $t(item.label) }}
        </button>
    </div>
</template>
