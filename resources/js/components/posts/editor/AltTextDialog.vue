<script setup lang="ts">
import { ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { MediaItem } from '@/types/media';

const props = defineProps<{
    mediaItem: MediaItem | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    (e: 'save', value: string): void;
}>();

const value = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        value.value = props.mediaItem?.meta?.alt_text ?? '';
    }
});

const save = () => {
    emit('save', value.value);
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl" data-testid="alt-text-dialog">
            <DialogHeader>
                <DialogTitle>{{ $t('posts.edit.alt_text.edit') }}</DialogTitle>
                <DialogDescription>{{ $t('posts.edit.alt_text.hint') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <Label for="alt-text-input">{{ $t('posts.edit.alt_text.label') }}</Label>
                <Textarea
                    id="alt-text-input"
                    v-model="value"
                    :placeholder="$t('posts.edit.alt_text.placeholder')"
                    rows="4"
                    data-testid="alt-text-input"
                />
                <p class="text-right text-xs tabular-nums text-foreground/60">{{ value.length }}</p>
            </div>

            <DialogFooter>
                <Button data-testid="alt-text-save" @click="save">
                    {{ $t('posts.edit.alt_text.save') }}
                </Button>
                <Button variant="outline" @click="open = false">
                    {{ $t('posts.edit.cancel') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
