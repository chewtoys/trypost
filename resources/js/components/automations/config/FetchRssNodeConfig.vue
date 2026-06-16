<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { IconLoader2, IconRefresh } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { inject, ref, watch } from 'vue';

import { inspectFeed } from '@/actions/App/Http/Controllers/App/AutomationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface DiscoveredField {
    path: string;
    sample: string;
}

interface FetchRssConfig {
    feed_url: string;
    discovered_fields: DiscoveredField[];
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const automationId = inject<string>('automationId', '');

const local = ref<FetchRssConfig>({
    feed_url: (props.data.feed_url as string) ?? '',
    discovered_fields: (props.data.discovered_fields as DiscoveredField[]) ?? [],
});

watch(local, (val) => emit('update', val), { deep: true });

// Changing the feed invalidates the discovered fields — they belong to the old
// feed and would otherwise keep being offered as stale autocomplete suggestions.
watch(
    () => local.value.feed_url,
    () => {
        local.value.discovered_fields = [];
    },
);

const inspectHttp = useHttp<{ feed_url: string }, { fields: DiscoveredField[] }>({ feed_url: '' });
const isInspecting = ref(false);
const inspectError = ref<string | null>(null);

const expression = (path: string): string => `{{ ${path} }}`;

const inspect = async () => {
    if (isInspecting.value || local.value.feed_url.trim() === '') {
        return;
    }

    isInspecting.value = true;
    inspectError.value = null;

    try {
        inspectHttp.feed_url = local.value.feed_url;
        const { fields } = await inspectHttp.post(inspectFeed.url(automationId));
        local.value.discovered_fields = fields;
    } catch {
        inspectError.value = trans('automations.config.fetch_rss.inspect_error');
    } finally {
        isInspecting.value = false;
    }
};
</script>

<template>
    <div class="space-y-4">
        <div>
            <Label class="mb-1 block">{{ $t('automations.config.fetch_rss.feed_url') }}</Label>
            <Input v-model="local.feed_url" placeholder="https://example.com/feed.xml" />
            <InputError :message="errors?.feed_url" class="mt-1" />
            <p class="mt-1 text-xs text-foreground/50">{{ $t('automations.config.fetch_rss.feed_url_hint') }}</p>
        </div>

        <div>
            <Button type="button" variant="outline" size="sm" :disabled="isInspecting || local.feed_url.trim() === ''" @click="inspect">
                <IconLoader2 v-if="isInspecting" class="size-4 animate-spin" />
                <IconRefresh v-else class="size-4" />
                {{ isInspecting ? $t('automations.config.fetch_rss.inspecting') : $t('automations.config.fetch_rss.inspect') }}
            </Button>
            <p class="mt-1 text-xs text-foreground/50">{{ $t('automations.config.fetch_rss.inspect_hint') }}</p>
            <InputError v-if="inspectError" :message="inspectError" class="mt-1" />
        </div>

        <div v-if="local.discovered_fields.length > 0">
            <Label class="mb-1 block">{{ $t('automations.config.fetch_rss.discovered_fields') }}</Label>
            <ul class="max-h-48 space-y-1.5 overflow-y-auto rounded-md border border-border p-2">
                <li v-for="field in local.discovered_fields" :key="field.path" class="flex flex-col gap-0.5">
                    <code class="text-xs text-foreground">{{ expression(field.path) }}</code>
                    <span v-if="field.sample" class="truncate text-xs text-foreground/50">{{ field.sample }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
