<script setup lang="ts">
import { computed } from 'vue';

import VideoPreview from '@/components/posts/previews/VideoPreview.vue';
import { isVideoMedia } from '@/composables/useMedia';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import type { MediaItem } from '@/types/media';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface SubredditRow {
    name: string;
    title: string;
    type: 'self' | 'link' | 'image';
    url?: string;
    flair_id?: string;
    flair_text?: string;
    flair_required?: boolean;
    allowed_types?: string[];
    nsfw?: boolean;
    spoiler?: boolean;
}

const props = defineProps<{
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    meta?: Record<string, any>;
}>();

const subreddits = computed<SubredditRow[]>(() =>
    Array.isArray(props.meta?.subreddits) ? (props.meta!.subreddits as SubredditRow[]) : [],
);

const firstSubreddit = computed<SubredditRow | null>(() => subreddits.value[0] ?? null);

const subredditLabel = computed<string>(() => {
    const first = firstSubreddit.value;
    if (!first?.name) {
        return 'r/subreddit';
    }
    const extra = subreddits.value.length - 1;
    return extra > 0 ? `r/${first.name} +${extra} more` : `r/${first.name}`;
});

const postTitle = computed<string>(() => firstSubreddit.value?.title ?? '');

const showNsfw = computed<boolean>(() => firstSubreddit.value?.nsfw === true);
const showSpoiler = computed<boolean>(() => firstSubreddit.value?.spoiler === true);
const flairText = computed<string>(() => firstSubreddit.value?.flair_text ?? '');

const imageMedia = computed<MediaItem[]>(() => props.media.filter((item) => !isVideoMedia(item)));
const videoMedia = computed<MediaItem | null>(() => props.media.find((item) => isVideoMedia(item)) ?? null);

// Fix 3: only show uploaded media for image-type posts; self/link posts must not render images.
const showMedia = computed<boolean>(() => firstSubreddit.value?.type === 'image' && props.media.length > 0);

// Fix 3: render the link URL for link-type posts.
const linkUrl = computed<string | null>(() =>
    firstSubreddit.value?.type === 'link' && firstSubreddit.value?.url ? firstSubreddit.value.url : null,
);
</script>

<template>
    <div class="flex h-full w-full flex-col overflow-hidden bg-[#dae0e6]">
        <!-- Subreddit header -->
        <div class="flex items-center gap-2 bg-white px-4 py-2.5 shadow-sm">
            <img :src="getPlatformLogo('reddit')" alt="Reddit" class="h-6 w-6 rounded-full object-cover" />
            <span class="truncate text-[13px] font-bold text-[#FF4500]">{{ subredditLabel }}</span>
        </div>

        <!-- Post card -->
        <div class="flex-1 overflow-y-auto p-3">
            <div class="overflow-hidden rounded-md bg-white shadow-sm">
                <!-- Vote rail + content -->
                <div class="flex gap-0">
                    <!-- Vote column -->
                    <div class="flex w-10 shrink-0 flex-col items-center gap-1 bg-[#f8f9fa] px-2 py-3">
                        <div class="text-[#FF4500]">▲</div>
                        <span class="text-[11px] font-bold text-[#1c1c1c]">0</span>
                        <div class="text-[#878a8c]">▼</div>
                    </div>

                    <!-- Main content -->
                    <div class="min-w-0 flex-1 px-3 py-2">
                        <!-- Meta row: author -->
                        <div class="mb-1.5 flex items-center gap-1.5 text-[11px] text-[#878a8c]">
                            <img
                                v-if="socialAccount.avatar_url"
                                :src="socialAccount.avatar_url"
                                :alt="socialAccount.display_name"
                                class="h-4 w-4 rounded-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#FF4500] text-[8px] font-bold text-white"
                            >
                                {{ socialAccount.display_name?.charAt(0) }}
                            </div>
                            <span>u/{{ socialAccount.username || socialAccount.display_name || 'user' }}</span>
                        </div>

                        <!-- Title -->
                        <p v-if="postTitle" class="mb-1.5 text-[15px] font-semibold leading-snug text-[#222222]">{{ postTitle }}</p>
                        <p v-else class="mb-1.5 text-[15px] font-semibold italic leading-snug text-[#878a8c]">{{ $t('posts.form.reddit.title') }}</p>

                        <!-- Tags row: NSFW, Spoiler, Flair -->
                        <div v-if="showNsfw || showSpoiler || flairText" class="mb-1.5 flex flex-wrap items-center gap-1.5">
                            <span
                                v-if="showNsfw"
                                class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                                style="background-color: #ff585b"
                            >NSFW</span>
                            <span
                                v-if="showSpoiler"
                                class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                                style="background-color: #7193ff"
                            >Spoiler</span>
                            <span
                                v-if="flairText"
                                class="rounded-full border border-[#FF4500] px-2 py-0.5 text-[10px] font-semibold text-[#FF4500]"
                            >{{ flairText }}</span>
                        </div>

                        <!-- Body text -->
                        <p v-if="content" class="whitespace-pre-wrap text-[14px] leading-[1.4] text-[#3c3c3c]">{{ content }}</p>

                        <!-- Link URL pill (link-type posts only, Fix 3) -->
                        <a
                            v-if="linkUrl"
                            :href="linkUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-flex max-w-full items-center truncate rounded border border-[#878a8c]/40 bg-[#f8f9fa] px-2 py-0.5 text-[11px] text-[#0079d3] hover:underline"
                        >{{ linkUrl }}</a>

                        <!-- Media (image type only, Fix 3) -->
                        <div v-if="showMedia" class="mt-2 overflow-hidden rounded">
                            <div v-if="imageMedia.length > 0">
                                <div
                                    class="overflow-hidden"
                                    :class="imageMedia.length >= 2 ? 'grid grid-cols-2 gap-0.5' : ''"
                                >
                                    <div
                                        v-for="(item, index) in imageMedia.slice(0, 4)"
                                        :key="item.id"
                                        class="relative overflow-hidden"
                                        :class="imageMedia.length === 1 ? 'aspect-[4/3]' : 'aspect-square'"
                                    >
                                        <img
                                            :src="item.url"
                                            :alt="item.original_filename"
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            v-if="imageMedia.length > 4 && index === 3"
                                            class="absolute inset-0 flex items-center justify-center bg-black/60"
                                        >
                                            <span class="text-xl font-semibold text-white">+{{ imageMedia.length - 4 }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <VideoPreview
                                v-else-if="videoMedia"
                                :src="videoMedia.url"
                                video-class="w-full max-h-72 object-cover bg-black"
                            />
                        </div>

                        <!-- Action bar -->
                        <div class="mt-2 flex items-center gap-3 text-[11px] font-bold text-[#878a8c]">
                            <span>💬 0 Comments</span>
                            <span>↗ Share</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
