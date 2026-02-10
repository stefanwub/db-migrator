<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type DbCopyRow = {
    id: number;
    name: string;
    dump_file_path: string;
    status: string;
    error_message: string | null;
    source_row_count: number | null;
    dest_row_count: number | null;
    source_size: number | null;
    dest_size: number | null;
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
    callback_url: string;
    started_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
    duration_milliseconds: number | null;
    duration_human: string | null;
    last_error: string | null;
    created_at: string;
    updated_at: string;
};

type Props = {
    dbCopy: DbCopy;
    rows: DbCopyRow[];
    source_total_size_bytes: number;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'DB Copies',
        href: '/db-copies',
    },
    {
        title: props.dbCopy.id,
        href: `/db-copies/${props.dbCopy.id}`,
    },
];

const hasLongError = computed(() => {
    return (props.dbCopy.last_error?.length ?? 0) > 300;
});

const totalSourceSizeMb = computed(() => {
    if (!props.source_total_size_bytes) {
        return 0;
    }

    return Number((props.source_total_size_bytes / (1024 * 1024)).toFixed(1));
});

function formatSizeWithMb(bytes: number | null): string {
    if (!bytes) {
        return '—';
    }

    const mb = bytes / (1024 * 1024);

    return `${bytes} (${mb.toFixed(1)} MB)`;
}

const showErrorModal = ref(false);

const selectedRowError = ref<string | null>(null);
const showRowErrorModal = ref(false);

function openRowErrorModal(message: string | null) {
    if (!message) {
        return;
    }

    selectedRowError.value = message;
    showRowErrorModal.value = true;
}
</script>

<template>
    <Head :title="`DB Copy ${props.dbCopy.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h1 class="text-lg font-semibold">
                        DB Copy Details
                    </h1>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Status and row-level information for this copy.
                    </p>
                </div>
                <Link
                    href="/db-copies"
                    class="inline-flex items-center rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-sm hover:bg-muted"
                >
                    Back to list
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="text-xs font-medium text-muted-foreground">
                        Status
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-100':
                                    dbCopy.status === 'queued' || dbCopy.status === 'running',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-100':
                                    dbCopy.status === 'succeeded',
                                'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-100':
                                    dbCopy.status === 'failed',
                            }"
                        >
                            {{ dbCopy.status }}
                        </span>
                        <span
                            v-if="dbCopy.progress !== null"
                            class="text-xs text-muted-foreground"
                        >
                            {{ dbCopy.progress }}%
                        </span>
                    </div>
                    <div class="mt-3 text-xs text-muted-foreground">
                        Started: {{ dbCopy.started_at ?? '—' }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Finished: {{ dbCopy.finished_at ?? '—' }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        Duration: {{ dbCopy.duration_human ?? '—' }} ({{ dbCopy.duration_milliseconds }} ms)
                    </div>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="text-xs font-medium text-muted-foreground">
                        Source
                    </div>
                    <div class="mt-2 text-sm font-medium">
                        {{ dbCopy.source_db }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ dbCopy.source_connection }}
                    </div>
                    <div class="mt-4 text-xs font-medium text-muted-foreground">
                        Destination
                    </div>
                    <div class="mt-2 text-sm font-medium">
                        {{ dbCopy.dest_db }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ dbCopy.dest_connection }}
                    </div>
                    <div class="mt-4 text-xs font-medium text-muted-foreground">
                        Total source size
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ totalSourceSizeMb }} MB ({{ props.source_total_size_bytes }} bytes)
                    </div>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs font-medium text-muted-foreground">
                            Last error
                        </div>
                        <button
                            v-if="dbCopy.last_error && hasLongError"
                            type="button"
                            class="text-xs font-medium text-primary hover:underline"
                            @click="showErrorModal = true"
                        >
                            View full error
                        </button>
                    </div>

                    <div
                        v-if="dbCopy.last_error"
                        class="mt-2 max-h-40 overflow-auto rounded-md bg-red-50 p-2 text-xs font-mono text-red-800 dark:bg-red-950/60 dark:text-red-100"
                    >
                        <pre class="whitespace-pre-wrap break-all">
{{ hasLongError ? `${dbCopy.last_error.slice(0, 300)}…` : dbCopy.last_error }}
                        </pre>
                    </div>
                    <div
                        v-else
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        No error recorded for this copy.
                    </div>
                </div>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 text-sm font-medium dark:border-sidebar-border"
                >
                    <span>Rows</span>
                    <span class="text-xs text-muted-foreground">
                        {{ rows.length }} total
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
                                    Name
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
                                    Source (rows / size)
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Destination (rows / size)
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
                                v-for="row in rows"
                                :key="row.id"
                                class="hover:bg-muted/40"
                            >
                                <td class="px-4 py-2 text-xs font-mono">
                                    {{ row.name }}
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-100':
                                                row.status === 'pending' ||
                                                row.status === 'dumped',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-100':
                                                row.status === 'imported' ||
                                                row.status === 'verified',
                                            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-100':
                                                row.status === 'failed',
                                        }"
                                    >
                                        {{ row.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    <div>
                                        Rows:
                                        {{ row.source_row_count ?? '—' }}
                                    </div>
                                    <div>
                                        Size:
                                        {{ formatSizeWithMb(row.source_size) }}
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    <div>
                                        Rows:
                                        {{ row.dest_row_count ?? '—' }}
                                    </div>
                                    <div>
                                        Size:
                                        {{ formatSizeWithMb(row.dest_size) }}
                                    </div>
                                </td>
                                <td class="max-w-64 px-4 py-2 text-xs">
                                    <button
                                        v-if="row.error_message"
                                        type="button"
                                        class="line-clamp-2 text-left text-red-600 hover:underline"
                                        @click="openRowErrorModal(row.error_message)"
                                    >
                                        {{ row.error_message }}
                                    </button>
                                    <span
                                        v-else
                                        class="text-muted-foreground"
                                    >
                                        &mdash;
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No row-level data has been recorded for this copy yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Copy error modal -->
        <div
            v-if="showErrorModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        >
            <div class="w-full max-w-2xl rounded-lg bg-background p-4 shadow-lg">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">
                        Full error message
                    </h2>
                    <button
                        type="button"
                        class="text-xs text-muted-foreground hover:text-foreground"
                        @click="showErrorModal = false"
                    >
                        Close
                    </button>
                </div>
                <div class="max-h-[60vh] overflow-auto rounded-md bg-red-50 p-3 text-xs font-mono text-red-800 dark:bg-red-950/60 dark:text-red-100">
                    <pre class="whitespace-pre-wrap break-all">
{{ dbCopy.last_error }}
                    </pre>
                </div>
            </div>
        </div>

        <!-- Row error modal -->
        <div
            v-if="showRowErrorModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        >
            <div class="w-full max-w-2xl rounded-lg bg-background p-4 shadow-lg">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">
                        Row error
                    </h2>
                    <button
                        type="button"
                        class="text-xs text-muted-foreground hover:text-foreground"
                        @click="showRowErrorModal = false"
                    >
                        Close
                    </button>
                </div>
                <div class="max-h-[60vh] overflow-auto rounded-md bg-red-50 p-3 text-xs font-mono text-red-800 dark:bg-red-950/60 dark:text-red-100">
                    <pre class="whitespace-pre-wrap break-all">
{{ selectedRowError }}
                    </pre>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

