<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

const open = defineModel<boolean>('open', { default: false });

// The `{{ ... }}` snippets stay literal (they're code). Descriptions reuse the
// same `automations.expr.*` keys the `{{` autocomplete shows, so the guide and
// the editor hints never drift apart.
const expressionGroups = [
    {
        titleKey: 'automations.guide.groups.trigger_schedule',
        items: [
            { code: '{{ trigger.event }}', descKey: 'automations.expr.trigger_event' },
            { code: '{{ trigger.fired_at }}', descKey: 'automations.expr.trigger_fired_at' },
        ],
    },
    {
        titleKey: 'automations.guide.groups.trigger_post',
        items: [
            { code: '{{ trigger.post.id }}', descKey: 'automations.expr.trigger_post_id' },
            { code: '{{ trigger.post.content }}', descKey: 'automations.expr.trigger_post_content' },
            { code: '{{ trigger.post.status }}', descKey: 'automations.expr.trigger_post_status' },
            { code: '{{ trigger.post.scheduled_at }}', descKey: 'automations.expr.trigger_post_scheduled_at' },
            { code: '{{ trigger.post.published_at }}', descKey: 'automations.expr.trigger_post_published_at' },
        ],
    },
    {
        titleKey: 'automations.guide.groups.fetch_rss',
        items: [
            { code: '{{ fetched.title }}', descKey: 'automations.expr.fetched_title' },
            { code: '{{ fetched.link }}', descKey: 'automations.expr.fetched_link' },
            { code: '{{ fetched.description }}', descKey: 'automations.expr.fetched_description' },
            { code: '{{ fetched.pubDate }}', descKey: 'automations.expr.fetched_pubdate' },
        ],
    },
    {
        titleKey: 'automations.guide.groups.http',
        items: [{ code: '{{ fetched.<field> }}', descKey: 'automations.expr.fetched_http' }],
        noteKey: 'automations.guide.http_note',
    },
    {
        titleKey: 'automations.guide.groups.generate',
        items: [
            { code: '{{ generated.content }}', descKey: 'automations.expr.generated_content' },
            { code: '{{ generated.post_url }}', descKey: 'automations.expr.generated_post_url' },
        ],
    },
    {
        titleKey: 'automations.guide.groups.always',
        items: [
            { code: '{{ variables.API_KEY }}', descKey: 'automations.expr.variable' },
            { code: '{{ now }}', descKey: 'automations.expr.now' },
        ],
    },
];
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex w-full flex-col overflow-hidden sm:max-w-md">
            <SheetHeader>
                <SheetTitle>{{ $t('automations.guide.title') }}</SheetTitle>
                <SheetDescription>{{ $t('automations.guide.subtitle') }}</SheetDescription>
            </SheetHeader>

            <Tabs default-value="overview" class="flex min-h-0 flex-1 flex-col px-4 pb-6">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="overview">{{ $t('automations.guide.tabs.overview') }}</TabsTrigger>
                    <TabsTrigger value="expressions">{{ $t('automations.guide.tabs.expressions') }}</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="mt-4 min-h-0 flex-1 space-y-6 overflow-y-auto">
                    <section class="space-y-1.5">
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-foreground/50">
                            {{ $t('automations.guide.flow_title') }}
                        </h3>
                        <p class="text-sm text-foreground/70">{{ $t('automations.guide.flow_text') }}</p>
                    </section>

                    <section class="space-y-1.5">
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-foreground/50">
                            {{ $t('automations.guide.scope_title') }}
                        </h3>
                        <p class="text-sm text-foreground/70">{{ $t('automations.guide.scope_text') }}</p>
                    </section>

                    <section class="space-y-2">
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-foreground/50">
                            {{ $t('automations.guide.vars_title') }}
                        </h3>
                        <p class="text-sm text-foreground/70">{{ $t('automations.guide.vars_text') }}</p>
                    </section>

                    <section class="rounded-xl border-2 border-foreground bg-amber-50 p-3 shadow-[3px_3px_0_var(--foreground)]">
                        <p class="text-sm font-medium text-foreground">💡 {{ $t('automations.guide.tip_text') }}</p>
                    </section>
                </TabsContent>

                <TabsContent value="expressions" class="mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto">
                    <p class="text-sm text-foreground/70">{{ $t('automations.guide.data_text') }}</p>

                    <div v-for="group in expressionGroups" :key="group.titleKey" class="space-y-1.5">
                        <h4 class="text-xs font-bold text-foreground/70">{{ $t(group.titleKey) }}</h4>
                        <div
                            v-for="item in group.items"
                            :key="item.code"
                            class="flex flex-col gap-0.5 rounded-lg border-2 border-foreground/15 bg-card/50 p-2.5"
                        >
                            <code class="font-mono text-xs font-bold text-foreground">{{ item.code }}</code>
                            <span class="text-xs text-foreground/55">{{ $t(item.descKey) }}</span>
                        </div>
                        <p v-if="group.noteKey" class="text-xs text-foreground/55">{{ $t(group.noteKey) }}</p>
                    </div>
                </TabsContent>
            </Tabs>
        </SheetContent>
    </Sheet>
</template>
