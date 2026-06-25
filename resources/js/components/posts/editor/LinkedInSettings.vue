<script setup lang="ts">
import { IconAlertTriangle, IconChevronDown, IconChevronUp } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { Avatar } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { getMediaValidationWarning, isDocumentMedia } from '@/composables/useMedia';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import type { MediaItem } from '@/types/media';
import { Platform } from '@/types/platform';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface Props {
    socialAccount: SocialAccount | null;
    platform: string;
    contentType: string;
    media: MediaItem[];
    meta?: Record<string, any>;
    disabled?: boolean;
    previewOnly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    meta: () => ({}),
    disabled: false,
    previewOnly: false,
});

const emit = defineEmits<{
    'update:meta': [value: Record<string, any>];
}>();

const open = ref(false);

const isPage = computed(() => props.platform === Platform.LinkedInPage);

// The publish format follows the media; the only thing to configure is the
// title shown on a document (PDF) post, so we only expose it when a PDF is set.
const hasPdf = computed(() => props.media.some((item) => isDocumentMedia(item)));

const documentTitle = computed({
    get: () => (props.meta?.document_title as string | undefined) ?? '',
    set: (value: string) => emit('update:meta', { ...props.meta, document_title: value || null }),
});

const warning = computed(() => getMediaValidationWarning(props.contentType, props.media));
</script>

<template>
    <div class="rounded-xl border-2 border-foreground bg-card shadow-2xs">
        <button
            type="button"
            class="flex w-full cursor-pointer items-center justify-between gap-3 p-4 text-sm"
            @click="open = !open"
        >
            <span class="flex min-w-0 items-center gap-2">
                <span class="inline-flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs">
                    <img :src="getPlatformLogo(platform)" alt="LinkedIn" class="size-full object-cover" />
                </span>
                <span class="truncate font-bold text-foreground">{{ isPage ? $t('posts.form.linkedin.settings_page') : $t('posts.form.linkedin.settings') }}</span>
                <span v-if="socialAccount?.username" class="truncate font-medium text-foreground/60">·&nbsp;@{{ socialAccount.username }}</span>
            </span>
            <IconChevronUp v-if="open" class="size-4 shrink-0 text-foreground/60" />
            <IconChevronDown v-else class="size-4 shrink-0 text-foreground/60" />
        </button>

        <div v-if="open" class="space-y-5 border-t-2 border-foreground/10 px-4 pb-4 pt-4">
            <div v-if="socialAccount" class="flex items-center gap-3 rounded-lg bg-foreground/5 p-3">
                <Avatar
                    :src="socialAccount.avatar_url"
                    :name="socialAccount.display_name"
                    class="size-9 shrink-0 rounded-full border-2 border-foreground shadow-2xs"
                />
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">{{ $t('posts.form.linkedin.posting_to') }}</p>
                    <p class="truncate text-sm">
                        <span class="font-bold text-foreground">{{ socialAccount.display_name }}</span>
                        <span v-if="socialAccount?.username" class="font-medium text-foreground/60">&nbsp;@{{ socialAccount.username }}</span>
                    </p>
                </div>
            </div>

            <div v-if="hasPdf" class="space-y-2">
                <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">{{ $t('posts.form.linkedin.document_title') }}</p>
                <Input
                    v-model="documentTitle"
                    type="text"
                    :placeholder="$t('posts.form.linkedin.document_title_placeholder')"
                    :disabled="disabled || previewOnly"
                />
            </div>

            <p
                v-if="warning && !previewOnly"
                class="flex items-start gap-2 rounded-lg border-2 border-foreground bg-rose-50 p-2 text-xs font-semibold text-rose-700"
            >
                <IconAlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                {{ $t(`posts.form.warnings.${warning.key}`, warning.params) }}
            </p>
        </div>
    </div>
</template>
