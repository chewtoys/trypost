<script setup lang="ts">
import { IconZoomIn, IconZoomOut } from '@tabler/icons-vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    centerTransform,
    clampTransform,
    type CropTransform,
    viewportToSource,
    zoomTransform,
} from '@/lib/imageCrop';

type Props = {
    open: boolean;
    src: string | null;
    fileName?: string;
    mimeType?: string;
    shape?: 'circle' | 'square';
    outputSize?: number;
};

const props = withDefaults(defineProps<Props>(), {
    fileName: 'image.png',
    mimeType: 'image/png',
    shape: 'circle',
    outputSize: 512,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'cropped', file: File): void;
}>();

const viewportEl = ref<HTMLElement | null>(null);
const imageEl = ref<HTMLImageElement | null>(null);
const viewportSize = ref(0);
const natural = ref({ width: 0, height: 0 });
const transform = ref<CropTransform>({ scale: 1, x: 0, y: 0 });
const processing = ref(false);
const initialized = ref(false);
const imageError = ref(false);

let dragPointerId: number | null = null;
let dragStart = { pointerX: 0, pointerY: 0, x: 0, y: 0 };
let resizeObserver: ResizeObserver | null = null;

const encodableMimes = ['image/jpeg', 'image/png', 'image/webp'];
const extensions: Record<string, string> = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };

const ready = computed(() => viewportSize.value > 0 && natural.value.width > 0);

const maskClass = computed(() => (props.shape === 'square' ? 'rounded-lg' : 'rounded-full'));

const outputMime = computed(() => (encodableMimes.includes(props.mimeType) ? props.mimeType : 'image/png'));

const outputFileName = computed(
    () => `${props.fileName.replace(/\.[^./]+$/, '') || 'image'}.${extensions[outputMime.value]}`,
);

const imageStyle = computed(() => ({
    width: `${natural.value.width * transform.value.scale}px`,
    height: `${natural.value.height * transform.value.scale}px`,
    transform: `translate(${transform.value.x}px, ${transform.value.y}px)`,
}));

const maybeInitialize = () => {
    if (!ready.value || initialized.value) {
        return;
    }

    transform.value = centerTransform(natural.value.width, natural.value.height, viewportSize.value);
    initialized.value = true;
};

const measure = () => {
    const el = viewportEl.value;

    if (!el) {
        return;
    }

    viewportSize.value = el.clientWidth;

    if (initialized.value) {
        transform.value = clampTransform(transform.value, natural.value.width, natural.value.height, viewportSize.value);
    } else {
        maybeInitialize();
    }
};

const onImageLoad = () => {
    const img = imageEl.value;

    if (!img) {
        return;
    }

    if (img.naturalWidth === 0 || img.naturalHeight === 0) {
        imageError.value = true;

        return;
    }

    natural.value = { width: img.naturalWidth, height: img.naturalHeight };
    maybeInitialize();
};

const onImageError = () => {
    imageError.value = true;
};

const onPointerDown = (event: PointerEvent) => {
    if (!ready.value) {
        return;
    }

    dragPointerId = event.pointerId;
    dragStart = { pointerX: event.clientX, pointerY: event.clientY, x: transform.value.x, y: transform.value.y };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
};

const onPointerMove = (event: PointerEvent) => {
    if (dragPointerId !== event.pointerId) {
        return;
    }

    transform.value = clampTransform(
        {
            scale: transform.value.scale,
            x: dragStart.x + (event.clientX - dragStart.pointerX),
            y: dragStart.y + (event.clientY - dragStart.pointerY),
        },
        natural.value.width,
        natural.value.height,
        viewportSize.value,
    );
};

const onPointerUp = (event: PointerEvent) => {
    if (dragPointerId === event.pointerId) {
        dragPointerId = null;
    }
};

const onWheel = (event: WheelEvent) => {
    if (!ready.value) {
        return;
    }

    event.preventDefault();
    transform.value = zoomTransform(
        transform.value,
        event.deltaY < 0 ? 1.06 : 0.94,
        natural.value.width,
        natural.value.height,
        viewportSize.value,
    );
};

const zoomBy = (factor: number) => {
    if (!ready.value) {
        return;
    }

    transform.value = zoomTransform(transform.value, factor, natural.value.width, natural.value.height, viewportSize.value);
};

const close = () => {
    emit('update:open', false);
};

const save = () => {
    const img = imageEl.value;

    if (!img || !ready.value) {
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = props.outputSize;
    canvas.height = props.outputSize;

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    const rect = viewportToSource(transform.value, viewportSize.value);
    context.drawImage(img, rect.sx, rect.sy, rect.sw, rect.sh, 0, 0, props.outputSize, props.outputSize);

    processing.value = true;
    canvas.toBlob(
        (blob) => {
            processing.value = false;

            if (!blob) {
                return;
            }

            emit('cropped', new File([blob], outputFileName.value, { type: outputMime.value }));
            close();
        },
        outputMime.value,
        0.92,
    );
};

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            initialized.value = false;
            processing.value = false;
            imageError.value = false;
            await nextTick();
            measure();

            if (viewportEl.value && !resizeObserver) {
                resizeObserver = new ResizeObserver(() => measure());
                resizeObserver.observe(viewportEl.value);
            }
        } else {
            resizeObserver?.disconnect();
            resizeObserver = null;
        }
    },
);

watch(
    () => props.src,
    () => {
        initialized.value = false;
        imageError.value = false;
        natural.value = { width: 0, height: 0 };
    },
);

onBeforeUnmount(() => resizeObserver?.disconnect());
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('common.photo_upload.crop_title') }}</DialogTitle>
                <DialogDescription>{{ $t('common.photo_upload.crop_description') }}</DialogDescription>
            </DialogHeader>

            <div
                ref="viewportEl"
                class="relative aspect-square w-full cursor-grab touch-none select-none overflow-hidden rounded-xl border-2 border-foreground bg-muted active:cursor-grabbing"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @wheel="onWheel"
            >
                <img
                    v-if="src && !imageError"
                    ref="imageEl"
                    :src="src"
                    alt=""
                    draggable="false"
                    class="absolute left-0 top-0 max-w-none"
                    :style="imageStyle"
                    @load="onImageLoad"
                    @error="onImageError"
                />
                <div
                    v-if="imageError"
                    class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-muted-foreground"
                >
                    {{ $t('common.photo_upload.crop_error') }}
                </div>
                <div
                    v-else
                    class="pointer-events-none absolute inset-0"
                    :class="maskClass"
                    style="box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5), inset 0 0 0 2px rgba(255, 255, 255, 0.7)"
                />
            </div>

            <div class="flex items-center justify-center gap-3">
                <Button type="button" variant="outline" size="icon" class="size-9" @click="zoomBy(0.9)">
                    <IconZoomOut class="size-4" />
                </Button>
                <span class="text-xs text-muted-foreground">{{ $t('common.photo_upload.crop_hint') }}</span>
                <Button type="button" variant="outline" size="icon" class="size-9" @click="zoomBy(1.1)">
                    <IconZoomIn class="size-4" />
                </Button>
            </div>

            <DialogFooter>
                <Button type="button" data-testid="crop-save" :disabled="processing || !ready" @click="save">
                    {{ $t('common.photo_upload.crop_save') }}
                </Button>
                <Button type="button" variant="outline" @click="close">
                    {{ $t('common.photo_upload.crop_cancel') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
