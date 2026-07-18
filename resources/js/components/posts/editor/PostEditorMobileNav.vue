<script setup lang="ts">
import { IconCircleCheck, IconLoader2 } from '@tabler/icons-vue';
import { computed } from 'vue';

import { PostStatus } from '@/types/post';

type MobileView = 'compose' | 'channels' | 'preview' | 'comments';

const props = defineProps<{
    isSaving: boolean;
    showSaved: boolean;
    status: string;
}>();

const activeView = defineModel<MobileView>('activeView', { required: true });

const items: { key: MobileView; label: string }[] = [
    { key: 'compose', label: 'posts.edit.tabs.compose' },
    { key: 'channels', label: 'posts.edit.tabs.channels' },
    { key: 'preview', label: 'posts.edit.tabs.preview' },
    { key: 'comments', label: 'posts.edit.tabs.comments' },
];

const PUBLISHED_STATUSES: readonly string[] = [PostStatus.Published, PostStatus.PartiallyPublished];
const isPublished = computed(() => PUBLISHED_STATUSES.includes(props.status));
// When not scheduled the header is hidden on mobile, so this nav is the top bar:
// it shows the status chip and must clear the floating hamburger.
const headerHidden = computed(() => props.status !== PostStatus.Scheduled);
</script>

<template>
    <div
        data-testid="editor-mobile-nav"
        class="flex shrink-0 items-center gap-2 border-b-2 border-foreground bg-card px-2 py-2 lg:hidden"
    >
        <div
            class="flex flex-1 gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            :class="{ 'pl-14': headerHidden }"
        >
            <button
                v-for="item in items"
                :key="item.key"
                type="button"
                :data-testid="`editor-nav-${item.key}`"
                class="inline-flex h-10 shrink-0 items-center rounded-lg border-2 px-3 text-sm font-semibold transition-colors"
                :class="activeView === item.key
                    ? 'border-foreground bg-violet-100 text-foreground'
                    : 'border-transparent text-foreground/60 hover:text-foreground'"
                @click="activeView = item.key"
            >
                {{ $t(item.label) }}
            </button>
        </div>

        <div v-if="headerHidden" class="shrink-0 pr-1">
            <span v-if="isSaving" class="flex items-center gap-1 text-xs font-semibold text-foreground/70">
                <IconLoader2 class="size-3.5 animate-spin" />
                {{ $t('posts.edit.saving') }}
            </span>
            <span v-else-if="showSaved" class="flex items-center gap-1 text-xs font-semibold text-emerald-700">
                <IconCircleCheck class="size-3.5" stroke-width="2.5" />
                {{ $t('posts.edit.saved') }}
            </span>
            <span v-else-if="isPublished" class="flex items-center gap-1 text-xs font-semibold text-emerald-700">
                <IconCircleCheck class="size-3.5" stroke-width="2.5" />
                {{ $t('posts.edit.status.published') }}
            </span>
            <span v-else class="flex items-center gap-1.5 text-xs font-semibold text-foreground/60">
                <span class="size-2 rounded-full bg-foreground/40" />
                {{ $t('posts.edit.draft') }}
            </span>
        </div>
    </div>
</template>
