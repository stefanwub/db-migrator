<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
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
    copies_count: number;
    created_at: string;
    updated_at: string;
};

type Props = {
    availableConnections: string[];
    dbCopyRuns: {
        data: DbCopyRun[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
};

const props = defineProps<Props>();

const form = useForm({
    source_system_db_connection: '',
    source_system_db_name: '',
    source_admin_app_connection: '',
    source_admin_app_name: '',
    source_db_connection: '',
    dest_db_connections: [] as string[],
    threads: 8,
    recreateDestination: true,
    createDestDbOnLaravelCloud: false,
});

function toggleDestinationConnection(connection: string): void {
    if (form.dest_db_connections.includes(connection)) {
        form.dest_db_connections = form.dest_db_connections.filter((value) => value !== connection);

        return;
    }

    form.dest_db_connections = [...form.dest_db_connections, connection];
}

function submit(): void {
    form.post('/db-copy-runs');
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'DB Copy Runs',
        href: '/db-copy-runs',
    },
];
</script>

<template>
    <Head title="DB Copy Runs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <div class="mb-4">
                    <h2 class="text-sm font-semibold">
                        Create DB Copy Run
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Select source and destination connections from configured database connections.
                    </p>
                </div>

                <form
                    class="grid gap-4 md:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">
                            Source System DB Connection
                        </label>
                        <select
                            v-model="form.source_system_db_connection"
                            class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                        >
                            <option value="">
                                Select connection
                            </option>
                            <option
                                v-for="connection in props.availableConnections"
                                :key="`system-${connection}`"
                                :value="connection"
                            >
                                {{ connection }}
                            </option>
                        </select>
                        <InputError :message="form.errors.source_system_db_connection" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">
                            Source System DB Name
                        </label>
                        <input
                            v-model="form.source_system_db_name"
                            type="text"
                            class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                            placeholder="system_database"
                        />
                        <InputError :message="form.errors.source_system_db_name" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">
                            Source Admin App Connection
                        </label>
                        <select
                            v-model="form.source_admin_app_connection"
                            class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                        >
                            <option value="">
                                Select connection
                            </option>
                            <option
                                v-for="connection in props.availableConnections"
                                :key="`admin-${connection}`"
                                :value="connection"
                            >
                                {{ connection }}
                            </option>
                        </select>
                        <InputError :message="form.errors.source_admin_app_connection" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">
                            Source Admin App Database
                        </label>
                        <input
                            v-model="form.source_admin_app_name"
                            type="text"
                            class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                            placeholder="admin_app"
                        />
                        <InputError :message="form.errors.source_admin_app_name" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">
                            Source Cluster Connection
                        </label>
                        <select
                            v-model="form.source_db_connection"
                            class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                        >
                            <option value="">
                                Select connection
                            </option>
                            <option
                                v-for="connection in props.availableConnections"
                                :key="`source-${connection}`"
                                :value="connection"
                            >
                                {{ connection }}
                            </option>
                        </select>
                        <InputError :message="form.errors.source_db_connection" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">
                            Threads
                        </label>
                        <input
                            v-model.number="form.threads"
                            type="number"
                            min="1"
                            max="64"
                            class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.threads" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">
                            Destination Database Connections
                        </label>
                        <div class="grid gap-2 rounded-md border border-border p-3 md:grid-cols-3">
                            <label
                                v-for="connection in props.availableConnections"
                                :key="`dest-${connection}`"
                                class="inline-flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.dest_db_connections.includes(connection)"
                                    @change="toggleDestinationConnection(connection)"
                                />
                                <span>{{ connection }}</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.dest_db_connections" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input
                                v-model="form.createDestDbOnLaravelCloud"
                                type="checkbox"
                            />
                            <span>Create destination DB on Laravel Cloud API</span>
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input
                                v-model="form.recreateDestination"
                                type="checkbox"
                            />
                            <span>Recreate destination databases</span>
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            Create run
                        </button>
                    </div>
                </form>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 text-sm font-medium dark:border-sidebar-border"
                >
                    <span>Latest DB Copy Runs</span>
                    <span class="text-xs text-muted-foreground">
                        {{ props.dbCopyRuns.data.length }} shown
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
                                    Run
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
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Source System
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Source Admin App
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Source DB Connection
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Destination Connections
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Cloud Create
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Copies
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="run in props.dbCopyRuns.data"
                                :key="run.id"
                                class="hover:bg-muted/40"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="`/db-copy-runs/${run.id}`"
                                        class="rounded bg-muted px-2 py-0.5 text-xs font-mono hover:bg-muted/80"
                                    >
                                        {{ run.id }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-100':
                                                run.status === 'queued' || run.status === 'running',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-100':
                                                run.status === 'succeeded',
                                            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-100':
                                                run.status === 'failed',
                                        }"
                                    >
                                        {{ run.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ run.duration_human ?? '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    <div class="text-xs text-muted-foreground">
                                        {{ run.source_system_db_connection }}
                                    </div>
                                    <div class="font-medium">
                                        {{ run.source_system_db_name }}
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="text-xs text-muted-foreground">
                                        {{ run.source_admin_app_connection }}
                                    </div>
                                    <div class="font-medium">
                                        {{ run.source_admin_app_name }}
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ run.source_db_connection }}
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ run.dest_db_connections.join(', ') || '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ run.create_dest_db_on_laravel_cloud ? 'Yes' : 'No' }}
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    {{ run.copies_count }}
                                </td>
                            </tr>
                            <tr v-if="props.dbCopyRuns.data.length === 0">
                                <td
                                    colspan="9"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No DB copy runs yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
