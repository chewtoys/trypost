import { useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch, type Ref } from 'vue';

import { linkPreview } from '@/routes/app/posts';
import type { MediaItem } from '@/types/media';

export interface LinkCard {
    uri: string;
    title: string;
    description: string;
    image: string | null;
}

/**
 * The first http(s) URL in `text`, or null. Trailing sentence punctuation and an
 * unmatched closing paren are trimmed so the detected URL matches the link's
 * rendered span — this mirrors the backend `UrlDetector`, keeping the preview
 * card's URL identical to the one the post actually links to.
 */
const firstUrl = (text: string): string | null => {
    const match = text.match(/https?:\/\/[^\s]+/);

    if (!match) {
        return null;
    }

    const url = match[0].replace(/[.,;:!?]$/, '');

    return url.endsWith(')') && !url.includes('(') ? url.slice(0, -1) : url;
};

/**
 * Live link-preview card for the composer. Resolves the first link in `content`
 * to its OpenGraph card, but only when there is no attached media (media
 * suppresses the link card on every platform). Returns the card and a loading
 * flag for the editor previews to render.
 */
export const useLinkCard = (content: Ref<string>, media: Ref<MediaItem[]>) => {
    const card = ref<LinkCard | null>(null);
    const loading = ref(false);
    const http = useHttp<{ url: string }, LinkCard | null>({ url: '' });

    const url = computed(() => (media.value.length > 0 ? null : firstUrl(content.value)));

    const fetchCard = async (target: string): Promise<void> => {
        loading.value = true;

        try {
            http.url = target;
            const data = await http.post(linkPreview.url());
            card.value = data?.uri ? data : null;
        } catch {
            card.value = null;
        } finally {
            loading.value = false;
        }
    };

    // The link went away (media attached, or URL removed) — drop the card at once.
    watch(url, (next) => {
        if (!next) {
            card.value = null;
            loading.value = false;
        }
    });

    // A new link appeared — fetch it. `watch` only fires on change, so the same
    // URL is never re-requested; the debounce keeps it off every keystroke.
    watchDebounced(
        url,
        (next) => {
            if (next) {
                void fetchCard(next);
            }
        },
        { debounce: 400, immediate: true },
    );

    return { card, loading };
};
