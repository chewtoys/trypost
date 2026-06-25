<script setup lang="ts">
import { IconCircleCheck, IconCircleX } from '@tabler/icons-vue';
import { onMounted } from 'vue';

import PopupLayout from '@/layouts/PopupLayout.vue';

const props = defineProps<{
    success: boolean;
    message: string;
    platform?: string | null;
}>();

const CLOSE_DELAY = 1500;

onMounted(() => {
    if (window.opener && !window.opener.closed) {
        try {
            window.opener.postMessage(
                {
                    type: 'social-oauth-callback',
                    success: props.success,
                    message: props.message,
                    platform: props.platform ?? null,
                },
                window.location.origin,
            );
        } catch {
            // Opener may be cross-origin or already gone; the timed close still applies.
        }
    }

    window.setTimeout(() => window.close(), CLOSE_DELAY);
});
</script>

<template>
    <PopupLayout :title="success ? $t('accounts.popup_callback.title_success') : $t('accounts.popup_callback.title_error')">
        <div class="flex flex-col items-center justify-center gap-3 py-16 text-center" dusk="popup-callback">
            <div
                class="flex h-14 w-14 items-center justify-center rounded-full"
                :class="
                    success
                        ? 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400'
                        : 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400'
                "
            >
                <IconCircleCheck v-if="success" class="h-7 w-7" />
                <IconCircleX v-else class="h-7 w-7" />
            </div>
            <p class="text-lg font-medium text-foreground">{{ message }}</p>
            <p class="text-sm text-muted-foreground">{{ $t('accounts.popup_callback.closing') }}</p>
        </div>
    </PopupLayout>
</template>
