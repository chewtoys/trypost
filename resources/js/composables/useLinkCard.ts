import { useHttp } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch, type Ref } from 'vue';

import debounce from '@/debounce';
import { linkPreview } from '@/routes/app/posts';
import type { MediaItem } from '@/types/media';

export interface LinkCard {
    uri: string;
    title: string;
    description: string;
    image: string | null;
}

const URL_RE = /(https?:\/\/[^\s]+)/;

const firstUrl = (text: string): string | null => {
    const match = text.match(URL_RE);
    if (!match) {
        return null;
    }

    let url = match[0];
    if (/[.,;:!?]$/.test(url)) {
        url = url.slice(0, -1);
    }
    if (url.endsWith(')') && !url.includes('(')) {
        url = url.slice(0, -1);
    }
    return url;
};

export const useLinkCard = (content: Ref<string>, media: Ref<MediaItem[]>) => {
    const card = ref<LinkCard | null>(null);
    const loading = ref(false);
    const lastAttemptedUrl = ref<string | null>(null);
    const http = useHttp<{ url: string }, LinkCard | null>({ url: '' });

    const fetchCard = async (url: string): Promise<void> => {
        lastAttemptedUrl.value = url;
        loading.value = true;
        try {
            http.url = url;
            const data = await http.post(linkPreview.url());
            card.value = data && data.uri ? data : null;
        } catch {
            card.value = null;
        } finally {
            loading.value = false;
        }
    };

    const debounced = debounce((url: string) => {
        void fetchCard(url);
    }, 400);

    watch(
        [content, media],
        () => {
            if (media.value.length > 0) {
                card.value = null;
                loading.value = false;
                lastAttemptedUrl.value = null;
                return;
            }

            const url = firstUrl(content.value);
            if (!url) {
                card.value = null;
                loading.value = false;
                lastAttemptedUrl.value = null;
                return;
            }

            if (url === lastAttemptedUrl.value) {
                return;
            }

            debounced(url);
        },
        { immediate: true },
    );

    onBeforeUnmount(() => debounced.cancel());

    return { card, loading };
};
