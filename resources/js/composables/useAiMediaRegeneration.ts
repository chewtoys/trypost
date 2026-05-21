import { useHttp } from '@inertiajs/vue3';
import { echo } from '@laravel/echo-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import { regenerateMedia as regeneratePostAiMedia } from '@/routes/app/posts/ai';
import type { MediaItem } from '@/types/media';

export interface RegenerationPayload {
    media: MediaItem;
    targetMediaId: string;
}

type RegenerationStatus = 'idle' | 'starting' | 'processing';

interface RegenerationStartResponse {
    channel?: string;
}

interface RegenerationEvent {
    media: MediaItem | null;
    error?: string | null;
}

interface UseAiMediaRegenerationOptions {
    postId: string;
    getMediaItem: () => MediaItem | null;
    onRegenerated: (payload: RegenerationPayload) => void;
    onCompleted?: () => void;
}

const REGENERATION_TIMEOUT_MS = 180_000;

export const useAiMediaRegeneration = (options: UseAiMediaRegenerationOptions) => {
    const instruction = ref('');
    const errorMessage = ref<string | null>(null);
    const status = ref<RegenerationStatus>('idle');

    const httpRegenerate = useHttp<{ instruction: string }>({
        instruction: '',
    });

    let subscribedChannel: string | null = null;
    let regenerationTimeout: ReturnType<typeof setTimeout> | null = null;

    const isBusy = computed(() => status.value !== 'idle');
    const isProcessing = computed(() => status.value === 'processing');
    const normalizedInstruction = computed(() => instruction.value.trim());
    const canSubmit = computed(() => normalizedInstruction.value.length > 0 && !isBusy.value);

    const unsubscribe = () => {
        if (subscribedChannel) {
            echo().leave(`private-${subscribedChannel}`);
            subscribedChannel = null;
        }
    };

    const clearRegenerationTimeout = () => {
        if (regenerationTimeout !== null) {
            clearTimeout(regenerationTimeout);
            regenerationTimeout = null;
        }
    };

    const setIdleWithError = (message: string) => {
        errorMessage.value = message;
        status.value = 'idle';
    };

    const resetState = () => {
        instruction.value = '';
        errorMessage.value = null;
        status.value = 'idle';
        clearRegenerationTimeout();
        unsubscribe();
    };

    const blockDismissWhileBusy = (event: Event) => {
        if (isBusy.value) {
            event.preventDefault();
        }
    };

    const handleRegenerationResult = (event: RegenerationEvent) => {
        clearRegenerationTimeout();

        const mediaItem = options.getMediaItem();
        if (event.error || !event.media || !mediaItem) {
            setIdleWithError(event.error ?? trans('posts.ai.image_regenerate.errors.unavailable'));
            unsubscribe();
            return;
        }

        toast.success(trans('posts.ai.image_regenerate.success'));

        options.onRegenerated({
            media: event.media,
            targetMediaId: mediaItem.id,
        });

        resetState();
        options.onCompleted?.();
    };

    const subscribe = (channel: string) => {
        subscribedChannel = channel;
        status.value = 'processing';

        clearRegenerationTimeout();
        regenerationTimeout = setTimeout(() => {
            setIdleWithError(trans('posts.ai.image_regenerate.errors.timeout'));
            unsubscribe();
        }, REGENERATION_TIMEOUT_MS);

        echo()
            .private(channel)
            .listen('.ai.media.regenerated', (event: RegenerationEvent) => handleRegenerationResult(event));
    };

    const submit = async () => {
        const mediaItem = options.getMediaItem();
        const instructionValue = normalizedInstruction.value;

        if (!mediaItem) {
            return;
        }

        if (!instructionValue) {
            setIdleWithError(trans('posts.ai.image_regenerate.errors.required'));
            return;
        }

        errorMessage.value = null;
        status.value = 'starting';
        httpRegenerate.instruction = instructionValue;

        try {
            const response = (await httpRegenerate.post(
                regeneratePostAiMedia.url({ post: options.postId, mediaId: mediaItem.id }),
            )) as RegenerationStartResponse;
            const channel = String(response.channel ?? '');

            if (!channel) {
                throw new Error('Missing channel in regeneration response.');
            }

            subscribe(channel);
        } catch (error: unknown) {
            const responseMessage = (error as { response?: { data?: { message?: string } } })?.response?.data?.message;
            setIdleWithError(responseMessage ?? trans('posts.ai.image_regenerate.errors.start_failed'));
        }
    };

    onUnmounted(() => {
        clearRegenerationTimeout();
        unsubscribe();
    });

    return {
        instruction,
        errorMessage,
        status,
        isBusy,
        isProcessing,
        canSubmit,
        submit,
        resetState,
        blockDismissWhileBusy,
    };
};

