import { onMounted, onUnmounted } from 'vue';

const POPUP_WIDTH = 600;
const POPUP_HEIGHT = 700;

/**
 * Opens a platform's `/connect/{platform}` OAuth flow in a centered popup and
 * invokes `onSuccess` when the popup posts back the `social-oauth-callback`
 * message. The listener is wired to the calling component's lifecycle.
 */
export const useOAuthPopup = (onSuccess: () => void) => {
    const openOAuthPopup = (platform: string) => {
        const left = window.screenX + (window.outerWidth - POPUP_WIDTH) / 2;
        const top = window.screenY + (window.outerHeight - POPUP_HEIGHT) / 2;

        window.open(
            `/connect/${platform}`,
            'oauth-popup',
            `width=${POPUP_WIDTH},height=${POPUP_HEIGHT},left=${left},top=${top},scrollbars=yes,resizable=yes`,
        );
    };

    const handleMessage = (event: MessageEvent) => {
        if (event.origin !== window.location.origin) return;
        if (event.data?.type !== 'social-oauth-callback') return;

        onSuccess();
    };

    onMounted(() => window.addEventListener('message', handleMessage));
    onUnmounted(() => window.removeEventListener('message', handleMessage));

    return { openOAuthPopup };
};
