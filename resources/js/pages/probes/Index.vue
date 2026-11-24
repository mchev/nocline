<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Copy, Radar } from '@lucide/vue';
import { computed } from 'vue';
import ProbeController from '@/actions/App/Modules/Probes/Http/Controllers/ProbeController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusDot from '@/components/ui-kit/StatusDot.vue';
import { t } from '@/lib/i18n';

defineProps<{
    probes: {
        id: number;
        name: string;
        role: string;
        enabled: boolean;
        enrolled: boolean;
        online: boolean;
        capabilities: string[];
    }[];
}>();

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const hubUrl = typeof window === 'undefined' ? '' : window.location.origin;
const enrollment = computed(
    () => page.props.enrollment as { host: string; token: string } | null,
);
const select =
    'h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none';
const enrollCommand = computed(() =>
    enrollment.value
        ? `phare-agent enroll --hub ${hubUrl} --token ${enrollment.value.token}`
        : '',
);

function copyCommand() {
    if (enrollCommand.value) {
        navigator.clipboard?.writeText(enrollCommand.value);
    }
}

const statusOf = (p: { enrolled: boolean; online: boolean }) => {
    if (!p.enrolled) {
        return 'unknown' as const;
    }

    return p.online ? ('up' as const) : ('down' as const);
};

const statusLabel = (p: { enrolled: boolean; online: boolean }) => {
    if (!p.enrolled) {
        return t('not enrolled');
    }

    return p.online ? t('online') : t('offline');
};
</script>

<template>
    <Head :title="$t('Probes')" />

    <PageContainer>
        <PageHeader
            :title="$t('Probes')"
            :description="
                $t(
                    'Probes run checks from outside your network. Primary probes see your private side; external ones see the Internet view.',
                )
            "
        />

        <div
            v-if="enrollment"
            class="reveal surface border-warning/40 bg-warning/10 p-5 text-sm"
            role="status"
        >
            <p class="font-medium">
                {{
                    $t(
                        'Enrollment token for :host (shown once, valid for 24 h)',
                        { host: enrollment.host },
                    )
                }}
            </p>
            <div class="mt-3 flex items-start gap-2">
                <code
                    class="block flex-1 rounded-md bg-background/60 p-3 break-all select-all"
                >
                    {{ enrollCommand }}
                </code>
                <Button
                    size="icon"
                    variant="outline"
                    type="button"
                    :title="$t('Copy')"
                    :aria-label="$t('Copy')"
                    @click="copyCommand"
                >
                    <Copy class="size-4" />
                </Button>
            </div>
        </div>

        <Form
            v-if="isAdmin"
            v-bind="ProbeController.store.form()"
            class="form-panel reveal"
            aria-labelledby="probe-add-heading"
            reset-on-success
            v-slot="{ processing }"
        >
            <h2
                id="probe-add-heading"
                class="font-display text-lg font-semibold"
            >
                {{ $t('Add') }}
            </h2>
            <div class="grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                <div class="field-stack">
                    <Label for="probe-name" class="field-label">{{
                        $t('Name')
                    }}</Label>
                    <Input
                        id="probe-name"
                        name="name"
                        :placeholder="$t('Name (e.g. vps-eu-1)')"
                        required
                    />
                </div>
                <div class="field-stack">
                    <Label for="probe-role" class="field-label">{{
                        $t('Role')
                    }}</Label>
                    <select id="probe-role" name="role" :class="select">
                        <option value="primary">
                            {{ $t('primary (same network)') }}
                        </option>
                        <option value="external">
                            {{ $t('external (Internet view)') }}
                        </option>
                    </select>
                </div>
                <Button type="submit" :disabled="processing">
                    {{ $t('Add') }}
                </Button>
            </div>
        </Form>

        <EmptyState
            v-if="!probes.length"
            :icon="Radar"
            :title="$t('No probes.')"
            :description="
                $t(
                    'Add a probe and enroll the agent with the one-time token to start remote checks.',
                )
            "
        />

        <div
            v-else
            class="reveal reveal-delay-1 overflow-hidden surface"
            tabindex="0"
            role="region"
            :aria-label="$t('Probes')"
        >
            <table class="table-clean">
                <thead>
                    <tr>
                        <th scope="col">{{ $t('Name') }}</th>
                        <th scope="col">{{ $t('Role') }}</th>
                        <th scope="col">{{ $t('Status') }}</th>
                        <th scope="col" class="hidden md:table-cell">
                            {{ $t('Capabilities') }}
                        </th>
                        <th v-if="isAdmin" scope="col" class="text-right">
                            {{ $t('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in probes" :key="p.id">
                        <td class="font-medium">{{ p.name }}</td>
                        <td>
                            <span class="chip chip-info">{{ p.role }}</span>
                        </td>
                        <td>
                            <span class="inline-flex items-center gap-2">
                                <StatusDot
                                    :status="statusOf(p)"
                                    :pulse="p.enrolled && !p.online"
                                    :label="statusLabel(p)"
                                />
                                <span
                                    class="chip"
                                    :class="{
                                        'chip-success': p.enrolled && p.online,
                                        'chip-danger': p.enrolled && !p.online,
                                    }"
                                >
                                    {{ statusLabel(p) }}
                                </span>
                            </span>
                        </td>
                        <td
                            class="hidden text-xs text-muted-foreground md:table-cell"
                        >
                            {{ p.capabilities.join(', ') || '—' }}
                        </td>
                        <td v-if="isAdmin">
                            <div class="flex flex-wrap justify-end gap-1">
                                <Form
                                    v-bind="
                                        ProbeController.enrollmentToken.form(
                                            p.id,
                                        )
                                    "
                                >
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        {{ $t('Token') }}
                                    </Button>
                                </Form>
                                <Form
                                    v-if="p.enrolled"
                                    v-bind="ProbeController.revoke.form(p.id)"
                                >
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        {{ $t('Revoke') }}
                                    </Button>
                                </Form>
                                <Form
                                    v-bind="ProbeController.destroy.form(p.id)"
                                >
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        {{ $t('Delete') }}
                                    </Button>
                                </Form>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PageContainer>
</template>
