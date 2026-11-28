<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { HostMetrics } from '@/components/HostCard.vue';
import HostShowIncidentsPanel, {
    type HostIncidentRow,
} from '@/components/hosts/HostShowIncidentsPanel.vue';
import HostShowMetricsPanel from '@/components/hosts/HostShowMetricsPanel.vue';
import HostShowServicesPanel, {
    type AttachableCheckRow,
    type LinkedCheckRow,
} from '@/components/hosts/HostShowServicesPanel.vue';
import HostShowSettingsPanel from '@/components/hosts/HostShowSettingsPanel.vue';
import HostDeleteDialog from '@/components/hosts/HostDeleteDialog.vue';
import HostShowSummary from '@/components/hosts/HostShowSummary.vue';
import type { HostMetricPoint } from '@/components/HostMetricsChart.vue';
import HostController from '@/actions/App/Modules/Hosts/Http/Controllers/HostController';
import type { MetricAlertsConfig } from '@/components/HostMetricAlertsSection.vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import { t } from '@/lib/i18n';
import type { GroupOption } from '@/types/groups';

type HostPayload = {
    id: number;
    name: string;
    group_id: number | null;
    tags: string[];
    color: string | null;
    enrolled: boolean;
    online: boolean;
    last_seen_at: string | null;
    agent_version: string | null;
    agent_outdated: boolean;
    agent_auto_update: boolean;
    hub_version: string;
    metrics: HostMetrics;
    agent_check: {
        id: number;
        status: string;
        interval_sec: number;
        timeout_sec: number;
        failure_threshold: number;
    } | null;
    hostname: string | null;
    public_ipv4: string | null;
    distro: string | null;
    kernel: string | null;
    provider: string | null;
    provider_label: string | null;
    provider_region: string | null;
    stack: Record<string, string> | null;
    stack_warnings?: Record<string, string>;
    metric_alerts: MetricAlertsConfig;
    resource_alert: { active: boolean; message: string | null };
    reboot_required: boolean;
    reboot_required_since: string | null;
    reboot_required_message: string | null;
};

type MetricsChartPayload = {
    hours: number;
    bucket_seconds: number;
    end_at: string;
    points: HostMetricPoint[];
};

const props = defineProps<{
    host: HostPayload;
    linked_checks: LinkedCheckRow[];
    attachable_checks: AttachableCheckRow[];
    groups: GroupOption[];
    metrics_chart: MetricsChartPayload;
    recent_incidents: HostIncidentRow[];
}>();

const canManage = computed(
    () =>
        (usePage().props.auth as { user: { role: string } }).user.role !==
        'viewer',
);

const isAdmin = computed(
    () =>
        (usePage().props.auth as { user: { role: string } }).user.role ===
        'admin',
);

const deleteOpen = ref(false);
const deleteProcessing = ref(false);

function confirmDelete() {
    deleteProcessing.value = true;
    router.delete(HostController.destroy.url(props.host.id), {
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}

const chartHours = ref(props.metrics_chart.hours);
const chartPoints = ref<HostMetricPoint[]>([...props.metrics_chart.points]);
const chartEndAt = ref(props.metrics_chart.end_at);
const chartLoading = ref(false);

async function loadMetricsChart(hours: number) {
    chartHours.value = hours;
    chartLoading.value = true;

    try {
        const url = new URL(
            HostController.metricsChart.url(props.host.id),
            window.location.origin,
        );
        url.searchParams.set('hours', String(hours));

        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            return;
        }

        const data = (await res.json()) as MetricsChartPayload;
        chartPoints.value = data.points;
        chartEndAt.value = data.end_at;
    } finally {
        chartLoading.value = false;
    }
}

const tagsInput = ref(props.host.tags.join(', '));
const attachId = ref('' as string | number);

const form = useForm({
    name: props.host.name,
    group_id: props.host.group_id ?? ('' as string | number),
    tags: props.host.tags,
    color: props.host.color ?? '',
    interval_sec: props.host.agent_check?.interval_sec ?? 60,
    timeout_sec: props.host.agent_check?.timeout_sec ?? 10,
    failure_threshold: props.host.agent_check?.failure_threshold ?? 2,
});

const canSave = computed(() => !form.processing && canManage.value);

function save() {
    form.tags = tagsInput.value
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean);
    form.transform((d) => ({
        ...d,
        group_id: d.group_id === '' ? null : d.group_id,
        color: d.color || null,
    })).patch(HostController.update.url(props.host.id), {
        preserveScroll: true,
    });
}

function attach() {
    if (attachId.value === '') {
        return;
    }

    router.post(
        HostController.attachCheck.url(props.host.id),
        { check_id: attachId.value },
        { preserveScroll: true, onSuccess: () => (attachId.value = '') },
    );
}

function detach(check: LinkedCheckRow) {
    if (confirm(t('Unlink “:name” from this server?', { name: check.name }))) {
        router.delete(
            HostController.detachCheck.url({
                host: props.host.id,
                check: check.id,
            }),
            { preserveScroll: true },
        );
    }
}

function newToken() {
    router.post(
        HostController.enrollmentToken.url(props.host.id),
        {},
        { preserveScroll: true },
    );
}

function revoke() {
    if (
        confirm(
            t('Revoke the agent token of ":name"?', { name: props.host.name }),
        )
    ) {
        router.delete(HostController.revoke.url(props.host.id), {
            preserveScroll: true,
        });
    }
}

const installHref = computed(() => `/monitor/install/${props.host.id}`);
</script>

<template>
    <Head :title="host.name" />

    <PageContainer size="wide" class="space-y-6">
        <HostShowSummary
            :name="host.name"
            :enrolled="host.enrolled"
            :online="host.online"
            :last-seen-at="host.last_seen_at"
            :metrics="host.metrics"
            :metric-alerts="host.metric_alerts"
            :agent-version="host.agent_version"
            :agent-outdated="host.agent_outdated"
            :accent-color="host.color"
            :hostname="host.hostname"
            :distro="host.distro"
            :kernel="host.kernel"
            :public-ipv4="host.public_ipv4"
            :provider-label="host.provider_label"
            :provider-region="host.provider_region"
            :stack="host.stack"
            :stack-warnings="host.stack_warnings"
            :resource-alert-active="host.resource_alert.active"
            :resource-alert-message="host.resource_alert.message"
            :reboot-required="host.reboot_required"
            :reboot-required-message="host.reboot_required_message"
            :can-manage="canManage"
            :is-admin="isAdmin"
            :install-href="installHref"
            @new-token="newToken"
            @revoke="revoke"
            @delete="deleteOpen = true"
        />

        <HostDeleteDialog
            v-model:open="deleteOpen"
            :host-name="host.name"
            :enrolled="host.enrolled"
            :processing="deleteProcessing"
            @confirm="confirmDelete"
        />

        <div
            class="grid items-start gap-6 lg:grid-cols-[minmax(0,19rem)_minmax(0,1fr)] xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]"
        >
            <HostShowSettingsPanel
                v-model:tags-input="tagsInput"
                :form="form"
                :groups="groups"
                :has-agent-check="host.agent_check !== null"
                :can-save="canSave"
                :can-manage="canManage"
                :host="{
                    id: host.id,
                    name: host.name,
                    enrolled: host.enrolled,
                    agent_auto_update: host.agent_auto_update,
                    agent_version: host.agent_version,
                    agent_outdated: host.agent_outdated,
                    hub_version: host.hub_version,
                    metric_alerts: host.metric_alerts,
                    resource_alert: host.resource_alert,
                }"
                @submit="save"
            />

            <div class="flex min-w-0 flex-col gap-6">
                <HostShowMetricsPanel
                    :enrolled="host.enrolled"
                    :chart-hours="chartHours"
                    :chart-loading="chartLoading"
                    :chart-points="chartPoints"
                    :chart-end-at="chartEndAt"
                    @hours-change="loadMetricsChart"
                />

                <HostShowIncidentsPanel :incidents="recent_incidents" />

                <HostShowServicesPanel
                    v-model:attach-id="attachId"
                    :linked-checks="linked_checks"
                    :attachable-checks="attachable_checks"
                    :can-manage="canManage"
                    @attach="attach"
                    @detach="detach"
                />
            </div>
        </div>
    </PageContainer>
</template>
