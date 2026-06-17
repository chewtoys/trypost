<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { IconChevronDown, IconChevronUp, IconPlus, IconTrash } from '@tabler/icons-vue';
import { computed, onUnmounted, ref } from 'vue';

import { restrictions as restrictionsRoute, subreddits as subredditsRoute } from '@/actions/App/Http/Controllers/App/RedditController';
import InputError from '@/components/InputError.vue';
import { Avatar } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { usePageErrors } from '@/composables/usePageErrors';
import { getPlatformLogo } from '@/composables/usePlatformLogo';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface SubredditResult {
    name: string;
    title: string;
    subscribers: number;
    over_18: boolean;
}

interface Flair {
    id: string;
    text: string;
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

const props = withDefaults(
    defineProps<{
        socialAccount: SocialAccount | null;
        meta: Record<string, any>;
        disabled?: boolean;
        previewOnly?: boolean;
    }>(),
    { disabled: false, previewOnly: false },
);

const emit = defineEmits<{ 'update:meta': [value: Record<string, any>] }>();

const open = ref(false);

const updateMeta = (patch: Record<string, any>) => emit('update:meta', { ...props.meta, ...patch });

const subreddits = computed<SubredditRow[]>(() =>
    Array.isArray(props.meta?.subreddits) ? (props.meta!.subreddits as SubredditRow[]) : [],
);

const updateSubreddits = (rows: SubredditRow[]) => updateMeta({ subreddits: rows });

const updateRow = (index: number, patch: Partial<SubredditRow>) =>
    updateSubreddits(subreddits.value.map((row, i) => (i === index ? { ...row, ...patch } : row)));

const removeRow = (index: number) =>
    updateSubreddits(subreddits.value.filter((_, i) => i !== index));

const addRow = () =>
    updateSubreddits([
        ...subreddits.value,
        { name: '', title: '', type: 'self', nsfw: false, spoiler: false },
    ]);

// --- Per-row flairs (local state, not persisted in meta) ---
const rowFlairs = ref<Record<number, Flair[]>>({});

const getFlairs = (index: number): Flair[] => rowFlairs.value[index] ?? [];

// --- Subreddit search (per row) ---
const searchQueries = ref<Record<number, string>>({});
const searchResults = ref<Record<number, SubredditResult[]>>({});
const searchLoading = ref<Record<number, boolean>>({});

const subredditsHttp = useHttp<Record<string, never>, { data: SubredditResult[] }>();
const restrictionsHttp = useHttp<Record<string, never>, { data: { allowed_types: string[]; flair_required: boolean; flairs: Flair[] } }>();

const searchTimers: Record<number, ReturnType<typeof setTimeout>> = {};

const searchSubreddits = (index: number, query: string) => {
    searchQueries.value[index] = query;
    clearTimeout(searchTimers[index]);

    if (!props.socialAccount || query.trim() === '') {
        searchResults.value[index] = [];
        return;
    }

    searchTimers[index] = setTimeout(async () => {
        searchLoading.value[index] = true;
        try {
            const { data } = await subredditsHttp.get(
                subredditsRoute.url(props.socialAccount!.id, { query: { q: query } }),
            );
            searchResults.value[index] = data;
        } catch {
            searchResults.value[index] = [];
        } finally {
            searchLoading.value[index] = false;
        }
    }, 250);
};

const loadRestrictions = async (index: number, name: string) => {
    if (!props.socialAccount || !name) {
        return;
    }

    try {
        const { data } = await restrictionsHttp.get(
            restrictionsRoute.url({ account: props.socialAccount.id, subreddit: name }),
        );

        rowFlairs.value[index] = data.flairs;

        const current = subreddits.value[index];
        const allowedTypes = data.allowed_types;
        const flairRequired = data.flair_required;
        const currentType = current?.type ?? 'self';
        const resolvedType = (allowedTypes.includes(currentType) ? currentType : allowedTypes[0] ?? 'self') as SubredditRow['type'];

        updateRow(index, {
            allowed_types: allowedTypes,
            flair_required: flairRequired,
            type: resolvedType,
        });
    } catch {
        // silently fail; user can still proceed
    }
};

const selectSubreddit = (index: number, result: SubredditResult) => {
    searchQueries.value[index] = '';
    searchResults.value[index] = [];

    const current = subreddits.value[index] ?? {};
    const title = current.title && current.title !== '' ? current.title : result.title;

    updateRow(index, {
        name: result.name,
        title,
        nsfw: result.over_18 ? true : (current.nsfw ?? false),
    });

    loadRestrictions(index, result.name);
};

const getAllowedTypes = (index: number): string[] => {
    const row = subreddits.value[index];
    return row?.allowed_types && row.allowed_types.length > 0 ? row.allowed_types : ['self', 'link', 'image'];
};

const typeLabels: Record<string, string> = {
    self: 'posts.form.reddit.type_self',
    link: 'posts.form.reddit.type_link',
    image: 'posts.form.reddit.type_image',
};

// --- Error surfacing ---
const errors = usePageErrors();

const subredditError = (index: number): string | undefined =>
    Object.entries(errors.value).find(([k]) => k.includes(`subreddits.${index}`) && !k.includes('.title') && !k.includes('.flair') && !k.includes('.url'))?.[1];

const titleError = (index: number): string | undefined =>
    Object.entries(errors.value).find(([k]) => k.includes(`subreddits.${index}.title`))?.[1];

const flairError = (index: number): string | undefined =>
    Object.entries(errors.value).find(([k]) => k.includes(`subreddits.${index}.flair`))?.[1];

const urlError = (index: number): string | undefined =>
    Object.entries(errors.value).find(([k]) => k.includes(`subreddits.${index}.url`))?.[1];

onUnmounted(() => {
    Object.values(searchTimers).forEach((t) => clearTimeout(t));
});
</script>

<template>
    <div class="rounded-xl border-2 border-foreground bg-card shadow-2xs">
        <button
            type="button"
            class="flex w-full cursor-pointer items-center justify-between gap-3 p-4 text-sm"
            @click="open = !open"
        >
            <span class="flex min-w-0 items-center gap-2">
                <span class="inline-flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs">
                    <img :src="getPlatformLogo('reddit')" alt="Reddit" class="size-full object-cover" />
                </span>
                <span class="truncate font-bold text-foreground">{{ $t('posts.form.reddit.settings') }}</span>
                <span v-if="socialAccount?.display_name" class="truncate font-medium text-foreground/60">·&nbsp;{{ socialAccount.display_name }}</span>
            </span>
            <IconChevronUp v-if="open" class="size-4 shrink-0 text-foreground/60" />
            <IconChevronDown v-else class="size-4 shrink-0 text-foreground/60" />
        </button>

        <div v-if="open" class="space-y-5 border-t-2 border-foreground/10 px-4 pb-4 pt-4">
            <div v-if="socialAccount" class="flex items-center gap-3 rounded-lg bg-foreground/5 p-3">
                <Avatar
                    :src="socialAccount.avatar_url"
                    :name="socialAccount.display_name"
                    class="size-9 shrink-0 rounded-full border-2 border-foreground shadow-2xs"
                />
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">{{ $t('posts.form.discord.posting_to') }}</p>
                    <p class="truncate text-sm font-bold text-foreground">{{ socialAccount.display_name }}</p>
                </div>
            </div>

            <!-- Subreddit rows -->
            <div class="space-y-4">
                <div
                    v-for="(row, index) in subreddits"
                    :key="index"
                    class="space-y-3 rounded-lg border-2 border-foreground/20 p-3"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-black uppercase tracking-widest text-foreground/50">
                            {{ $t('posts.form.reddit.subreddit') }} {{ index + 1 }}
                        </span>
                        <button
                            type="button"
                            :disabled="disabled"
                            class="text-foreground/50 hover:text-rose-600 disabled:opacity-40"
                            @click="removeRow(index)"
                        >
                            <IconTrash class="size-3.5" />
                        </button>
                    </div>

                    <!-- Subreddit search -->
                    <div class="space-y-1.5">
                        <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">{{ $t('posts.form.reddit.subreddit') }}</p>
                        <div class="relative">
                            <Input
                                :model-value="row.name !== '' ? (searchQueries[index] !== undefined ? searchQueries[index] : `r/${row.name}`) : (searchQueries[index] ?? '')"
                                :disabled="disabled"
                                :placeholder="$t('posts.form.reddit.search_subreddit')"
                                @update:model-value="searchSubreddits(index, String($event))"
                                @focus="searchQueries[index] = ''"
                            />
                            <ul
                                v-if="searchResults[index] && searchResults[index].length > 0"
                                class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border-2 border-foreground bg-card shadow-2xs"
                            >
                                <li v-for="result in searchResults[index]" :key="result.name">
                                    <button
                                        type="button"
                                        class="flex w-full cursor-pointer flex-col px-3 py-2 text-left hover:bg-foreground/5"
                                        @click="selectSubreddit(index, result)"
                                    >
                                        <span class="text-sm font-semibold text-foreground">r/{{ result.name }}</span>
                                        <span class="text-xs text-foreground/60">{{ result.title }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <InputError :message="subredditError(index)" />
                    </div>

                    <!-- Title -->
                    <div class="space-y-1.5">
                        <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">{{ $t('posts.form.reddit.title') }}</p>
                        <Input
                            :model-value="row.title"
                            :disabled="disabled"
                            :placeholder="$t('posts.form.reddit.title')"
                            @update:model-value="updateRow(index, { title: String($event) })"
                        />
                        <InputError :message="titleError(index)" />
                    </div>

                    <!-- Post type -->
                    <div class="space-y-1.5">
                        <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">{{ $t('posts.form.reddit.post_type') }}</p>
                        <div class="flex gap-2">
                            <button
                                v-for="type in getAllowedTypes(index)"
                                :key="type"
                                type="button"
                                :disabled="disabled"
                                class="rounded-lg border-2 px-3 py-1.5 text-xs font-semibold transition-colors disabled:opacity-50"
                                :class="row.type === type
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-foreground/20 text-foreground/70 hover:border-foreground/50'"
                                @click="updateRow(index, { type: type as SubredditRow['type'] })"
                            >
                                {{ $t(typeLabels[type] ?? type) }}
                            </button>
                        </div>
                    </div>

                    <!-- URL (link type only) -->
                    <div v-if="row.type === 'link'" class="space-y-1.5">
                        <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">{{ $t('posts.form.reddit.url') }}</p>
                        <Input
                            :model-value="row.url ?? ''"
                            :disabled="disabled"
                            :placeholder="$t('posts.form.reddit.url')"
                            @update:model-value="updateRow(index, { url: String($event) })"
                        />
                        <InputError :message="urlError(index)" />
                    </div>

                    <!-- Flair -->
                    <div v-if="getFlairs(index).length > 0 || row.flair_required" class="space-y-1.5">
                        <p
                            class="text-[11px] font-black uppercase tracking-widest"
                            :class="row.flair_required ? 'text-foreground' : 'text-foreground/60'"
                        >
                            {{ $t('posts.form.reddit.flair') }}
                            <span v-if="row.flair_required" class="text-rose-500">*</span>
                        </p>
                        <select
                            :value="row.flair_id ?? ''"
                            :disabled="disabled"
                            class="w-full rounded-lg border-2 bg-card px-3 py-2 text-sm transition-colors hover:border-foreground focus:border-foreground focus:outline-none disabled:opacity-50"
                            :class="flairError(index) ? 'border-rose-500' : (row.flair_required && !row.flair_id ? 'border-amber-400' : 'border-foreground/30')"
                            @change="updateRow(index, {
                                flair_id: ($event.target as HTMLSelectElement).value || undefined,
                                flair_text: getFlairs(index).find((f) => f.id === ($event.target as HTMLSelectElement).value)?.text,
                            })"
                        >
                            <option value="">{{ $t('posts.form.reddit.no_flair') }}</option>
                            <option v-for="flair in getFlairs(index)" :key="flair.id" :value="flair.id">
                                {{ flair.text }}
                            </option>
                        </select>
                        <InputError :message="flairError(index)" />
                    </div>

                    <!-- NSFW + Spoiler -->
                    <div class="flex gap-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-foreground/80">
                            <input
                                type="checkbox"
                                :checked="row.nsfw ?? false"
                                :disabled="disabled"
                                class="rounded border-2 border-foreground/30"
                                @change="updateRow(index, { nsfw: ($event.target as HTMLInputElement).checked })"
                            />
                            {{ $t('posts.form.reddit.nsfw') }}
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-foreground/80">
                            <input
                                type="checkbox"
                                :checked="row.spoiler ?? false"
                                :disabled="disabled"
                                class="rounded border-2 border-foreground/30"
                                @change="updateRow(index, { spoiler: ($event.target as HTMLInputElement).checked })"
                            />
                            {{ $t('posts.form.reddit.spoiler') }}
                        </label>
                    </div>
                </div>

                <!-- Add subreddit -->
                <button
                    type="button"
                    :disabled="disabled"
                    class="inline-flex cursor-pointer items-center gap-1 text-xs font-bold text-foreground/70 hover:text-foreground disabled:opacity-50"
                    @click="addRow"
                >
                    <IconPlus class="size-3.5" />
                    {{ $t('posts.form.reddit.add_subreddit') }}
                </button>
            </div>
        </div>
    </div>
</template>
