<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type DbCopyRun = {
    id: number;
    status: string;
    started_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
    duration_milliseconds: number | null;
    duration_human: string | null;
    create_dest_db_on_laravel_cloud: boolean;
    source_system_db_connection: string;
    source_system_db_name: string;
    source_admin_app_connection: string;
    source_admin_app_name: string;
    source_db_connection: string;
    dest_db_connections: string[];
    created_at: string;
    updated_at: string;
};

type DbCopy = {
    id: string;
    status: string;
    progress: number | null;
    source_connection: string;
    source_db: string;
    dest_connection: string;
    dest_db: string;
    started_at: string | null;
    finished_at: string | null;
    duration_human: string | null;
    last_error: string | null;
    created_at: string;
    updated_at: string;
};

type Props = {
    dbCopyRun: DbCopyRun;
    copies: DbCopy[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'DB Copy Runs',
        href: '/db-copy-runs',
    },
    {
        title: String(props.dbCopyRun.id),
        href: `/db-copy-runs/${props.dbCopyRun.id}`,
    },
];
</script>

<template>
    <Head :title="`DB Copy Run ${props.dbCopyRun.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h1 class="text-lg font-semibold">
                        DB Copy Run Details
                    </h1>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Copies associated with this run.
                    </p>
                </div>
                <Link
                    href="/db-copy-runs"
                    class="inline-flex items-center rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-sm hover:bg-muted"
                >
                    Back to runs
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="mb-3">
                        <div class="text-xs font-medium text-muted-foreground">
                            Run Status
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-100':
                                        props.dbCopyRun.status === 'queued' || props.dbCopyRun.status === 'running',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-100':
                                        props.dbCopyRun.status === 'succeeded',
                                    'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-100':
                                        props.dbCopyRun.status === 'failed',
                                }"
                            >
                                {{ props.dbCopyRun.status }}
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-muted-foreground">
                            Started: {{ props.dbCopyRun.started_at ?? '—' }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Finished: {{ props.dbCopyRun.finished_at ?? '—' }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Duration: {{ props.dbCopyRun.duration_human ?? '—' }}
                        </div>
                    </div>

                    <div class="text-xs font-medium text-muted-foreground">
                        Source System
                    </div>
                    <div class="mt-2 text-sm font-medium">
                        {{ props.dbCopyRun.source_system_db_name }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ props.dbCopyRun.source_system_db_connection }}
                    </div>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="text-xs font-medium text-muted-foreground">
                        Source Admin App
                    </div>
                    <div class="mt-2 text-sm font-medium">
                        {{ props.dbCopyRun.source_admin_app_name }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ props.dbCopyRun.source_admin_app_connection }}
                    </div>
                    <div class="mt-4 text-xs font-medium text-muted-foreground">
                        Source DB Connection
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ props.dbCopyRun.source_db_connection }}
                    </div>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="text-xs font-medium text-muted-foreground">
                        Destination Connections
                    </div>
                    <div class="mt-2 text-xs text-muted-foreground">
                        {{ props.dbCopyRun.dest_db_connections.join(', ') || '—' }}
                    </div>
                    <div class="mt-3 text-xs text-muted-foreground">
                        Create via Laravel Cloud: {{ props.dbCopyRun.create_dest_db_on_laravel_cloud ? 'Yes' : 'No' }}
                    </div>
                </div>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 text-sm font-medium dark:border-sidebar-border"
                >
                    <span>Copies in this run</span>
                    <span class="text-xs text-muted-foreground">
                        {{ props.copies.length }} total
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
                                    Copy
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
                                    Duration
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="copy in props.copies"
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
                                                copy.status === 'queued' || copy.status === 'running',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-100':
                                                copy.status === 'succeeded',
                                            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-100':
                                                copy.status === 'failed',
                                        }"
                                    >
                                        {{ copy.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ copy.duration_human ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="props.copies.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No copies found for this run.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
