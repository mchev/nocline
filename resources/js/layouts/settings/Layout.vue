<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KeyRound, Palette, ShieldCheck, User } from '@lucide/vue';
import PageContainer from '@/components/ui-kit/PageContainer.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { index as apiTokens } from '@/routes/api-tokens';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const sidebarNavItems = [
    { title: 'Profile', href: editProfile(), icon: User },
    { title: 'Security', href: editSecurity(), icon: ShieldCheck },
    { title: 'Appearance', href: editAppearance(), icon: Palette },
    { title: 'API tokens', href: apiTokens(), icon: KeyRound },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <PageContainer size="narrow">
        <PageHeader
            :title="$t('Settings')"
            :description="$t('Manage your profile and account settings')"
        />

        <nav
            class="-mx-1 flex gap-1 overflow-x-auto px-1 py-1"
            :aria-label="$t('Settings')"
        >
            <Link
                v-for="item in sidebarNavItems"
                :key="toUrl(item.href)"
                :href="item.href"
                :aria-current="
                    isCurrentOrParentUrl(item.href) ? 'page' : undefined
                "
                class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border border-transparent px-3 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[current=page]:border-border aria-[current=page]:bg-muted aria-[current=page]:text-foreground"
            >
                <component :is="item.icon" class="size-4" aria-hidden="true" />
                {{ $t(item.title) }}
            </Link>
        </nav>

        <div class="flex flex-col gap-6">
            <slot />
        </div>
    </PageContainer>
</template>
