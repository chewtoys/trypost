<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { IconChartBar, IconLoader2, IconUsers } from '@tabler/icons-vue';
import { computed, onMounted, ref } from 'vue';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatNumberCompact } from '@/lib/utils';
import { metrics as metricsRoute } from '@/routes/app/posts/platforms';

interface Metric {
    label: string;
    value: number;
    kind?: string;
}

type MetricsResponse = Metric[] | { unsupported: true; reason: string };

interface Props {
    postId: string;
    postPlatformId: string;
}

const props = defineProps<Props>();

const loading = ref(true);
const metrics = ref<Metric[]>([]);

const stats = computed(() => metrics.value.filter((m) => !m.kind));
const subscribers = computed(() =>
    metrics.value.find((m) => m.kind === 'subscribers'),
);
const reactions = computed(() =>
    metrics.value.filter((m) => m.kind === 'reaction'),
);

const hasMetrics = computed(() => metrics.value.length > 0);

const http = useHttp<Record<string, never>, MetricsResponse>({});

onMounted(async () => {
    try {
        const response = await http.get(
            metricsRoute.url({
                post: props.postId,
                postPlatform: props.postPlatformId,
            }),
        );

        if (Array.isArray(response)) {
            metrics.value = response;
        }
    } catch {
        // Swallow — empty state hides itself.
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <!-- Loading: subtle inline indicator. -->
    <div
        v-if="loading"
        class="flex items-center gap-2 border-t px-4 py-3 text-xs text-muted-foreground"
    >
        <IconLoader2 class="h-3 w-3 animate-spin" />
        {{ $t('posts.show.metrics_loading') }}
    </div>

    <!-- Loaded with data: full metrics block. -->
    <div v-else-if="hasMetrics" class="border-t px-4 py-3">
        <div
            class="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
        >
            <IconChartBar class="h-3 w-3" />
            {{ $t('posts.show.metrics') }}
        </div>
        <div v-if="stats.length > 0" class="grid grid-cols-3 gap-2">
            <div
                v-for="metric in stats"
                :key="metric.label"
                class="rounded-md bg-muted/50 px-2.5 py-1.5"
            >
                <p
                    class="text-[10px] tracking-wider text-muted-foreground uppercase"
                >
                    {{ metric.label }}
                </p>
                <p class="text-sm font-semibold tabular-nums">
                    {{ formatNumberCompact(metric.value) }}
                </p>
            </div>
        </div>

        <div
            v-if="subscribers || reactions.length > 0"
            class="flex flex-wrap items-center gap-1.5"
            :class="{ 'mt-2': stats.length > 0 }"
        >
            <TooltipProvider v-if="subscribers">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs"
                        >
                            <IconUsers class="size-3.5 text-muted-foreground" />
                            <span class="font-semibold tabular-nums">{{
                                formatNumberCompact(subscribers.value)
                            }}</span>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>{{ subscribers.label }}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <span
                v-if="subscribers && reactions.length > 0"
                class="mx-0.5 h-4 w-px bg-border"
                aria-hidden="true"
            />

            <span
                v-for="reaction in reactions"
                :key="reaction.label"
                class="inline-flex items-center gap-1 rounded-full bg-muted/50 px-2 py-1 text-xs"
            >
                <span class="text-sm leading-none">{{ reaction.label }}</span>
                <span class="font-semibold tabular-nums">{{
                    formatNumberCompact(reaction.value)
                }}</span>
            </span>
        </div>
    </div>

    <!-- No data / unsupported: render nothing so the card stays clean. -->
</template>
