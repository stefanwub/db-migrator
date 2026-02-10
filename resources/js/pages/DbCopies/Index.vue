<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type DbCopy = {
    id: string;
    status: string;
    progress: number | null;
    source_connection: string;
    source_db: string;
    dest_connection: string;
    dest_db: string;
    callback_url: string;
    started_at: string | null;
    finished_at: string | null;
    last_error: string | null;
    created_at: string;
    updated_at: string;
};

type Props = {
    dbCopies: {
        data: DbCopy[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'DB Copies',
        href: '/db-copies',
    },
];

const statusCounts = computed(() => {
    const counts: Record<string, number> = {
        queued: 0,
        running: 0,
        succeeded: 0,
        failed: 0,
    };

    for (const copy of props.dbCopies.data) {
        const status = copy.status as keyof typeof counts;

        if (status in counts) {
            counts[status] += 1;
        }
    }

    return counts;
});
</script>

<template>
    <Head title="DB Copies" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="grid gap-4 md:grid-cols-4">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="text-xs font-medium text-muted-foreground">
                        Queued
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ statusCounts.queued }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="text-xs font-medium text-muted-foreground">
                        Running
                    </div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ statusCounts.running }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="text-xs font-medium text-muted-foreground">
                        Succeeded
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-emerald-600">
                        {{ statusCounts.succeeded }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="text-xs font-medium text-muted-foreground">
                        Failed
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-red-600">
                        {{ statusCounts.failed }}
                    </div>
                </div>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 text-sm font-medium dark:border-sidebar-border"
                >
                    <span>Recent DB Copies</span>
                    <span class="text-xs text-muted-foreground">
                        {{ props.dbCopies.data.length }} shown
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/30">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    ID
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Source
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Destination
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Progress
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Started
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Finished
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Error
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="copy in props.dbCopies.data"
                                :key="copy.id"
                                class="hover:bg-muted/40"
                            >
                                <td class="max-w-40 truncate px-4 py-2">
                                    <Link
                                        :href="`/db-copies/${copy.id}`"
                                        class="rounded bg-muted px-2 py-0.5 text-xs font-mono hover:bg-muted/80"
                                    >
                                        {{ copy.id }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="text-xs text-muted-foreground">
                                        {{ copy.source_connection }}
                                    </div>
                                    <div class="font-medium">
                                        {{ copy.source_db }}
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="text-xs text-muted-foreground">
                                        {{ copy.dest_connection }}
                                    </div>
                                    <div class="font-medium">
                                        {{ copy.dest_db }}
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-100':
                                                copy.status === 'queued' ||
                                                copy.status === 'running',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-100':
                                                copy.status === 'succeeded',
                                            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-100':
                                                copy.status === 'failed',
                                        }"
                                    >
                                        {{ copy.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        v-if="copy.progress !== null"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ copy.progress }}%
                                    </span>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        &mdash;
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ copy.started_at ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ copy.finished_at ?? '—' }}
                                </td>
                                <td class="max-w-48 px-4 py-2 text-xs text-red-600">
                                    <span class="line-clamp-2">
                                        {{ copy.last_error ?? '' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="props.dbCopies.data.length === 0">
                                <td
                                    colspan="8"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No DB copies yet. Trigger a copy via the API
                                    to see it appear here.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

