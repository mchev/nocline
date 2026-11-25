<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BellRing,
    CalendarClock,
    FolderTree,
    Globe,
    KeyRound,
    LayoutGrid,
    Radar,
    Server,
    Siren,
    Tv,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain, { type NavSection } from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { index as channelsIndex } from '@/routes/alertes/channels';
import { index as checksIndex } from '@/routes/checks';
import { index as coffreIndex } from '@/routes/coffre';
import { index as groupsIndex } from '@/routes/groups';
import { index as hostsIndex } from '@/routes/hosts';
import { index as incidentsIndex } from '@/routes/incidents';
import { index as maintenanceIndex } from '@/routes/maintenance';
import { index as probesIndex } from '@/routes/probes';
import { index as renewalsIndex } from '@/routes/renewals';
import { index as statusPagesIndex } from '@/routes/status-pages';
import { index as wallboardsIndex } from '@/routes/wallboards';

const page = usePage();
const role = computed(() => page.props.auth.user.role);
const canManage = computed(() => role.value !== 'viewer');

const navSections = computed<NavSection[]>(() => {
    const sections: NavSection[] = [
        {
            items: [
                { title: t('Dashboard'), href: dashboard(), icon: LayoutGrid },
                { title: t('Monitors'), href: checksIndex(), icon: Activity },
                { title: t('Servers'), href: hostsIndex(), icon: Server },
                { title: t('Incidents'), href: incidentsIndex(), icon: Siren },
                {
                    title: t('Renewals'),
                    href: renewalsIndex(),
                    icon: CalendarClock,
                },
            ],
        },
        {
            title: t('Share'),
            items: [
                { title: t('Alerts'), href: channelsIndex(), icon: BellRing },
                {
                    title: t('Status pages'),
                    href: statusPagesIndex(),
                    icon: Globe,
                },
                {
                    title: t('Wallboards'),
                    href: wallboardsIndex(),
                    icon: Tv,
                },
            ],
        },
        {
            title: t('More'),
            items: [
                { title: t('Groups'), href: groupsIndex(), icon: FolderTree },
                {
                    title: t('Maintenance'),
                    href: maintenanceIndex(),
                    icon: Wrench,
                },
                { title: t('Probes'), href: probesIndex(), icon: Radar },
                ...(canManage.value
                    ? [
                          {
                              title: t('Vault'),
                              href: coffreIndex(),
                              icon: KeyRound,
                          },
                      ]
                    : []),
            ],
        },
    ];

    return sections;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :sections="navSections" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
