<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    Languages,
    LogOut,
    ScrollText,
    Settings,
    UserCog,
} from '@lucide/vue';
import { computed } from 'vue';
import LocaleController from '@/actions/App/Http/Controllers/LocaleController';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { index as auditIndex } from '@/routes/audit';
import { edit } from '@/routes/profile';
import { index as usersIndex } from '@/routes/users';
import type { User } from '@/types';

type Props = {
    user: User;
};

const page = usePage();
const locales = computed(
    () => (page.props.locales ?? {}) as Record<string, string>,
);
const currentLocale = computed(() => page.props.locale as string);
const isAdmin = computed(() => page.props.auth.user.role === 'admin');

const handleLogout = () => {
    router.flushAll();
};

const switchLocale = (locale: string) => {
    if (locale === currentLocale.value) {
        return;
    }

    router.post(
        LocaleController.update.url(),
        { locale },
        { preserveState: true, preserveScroll: true },
    );
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ $t('Settings') }}
            </Link>
        </DropdownMenuItem>
        <template v-if="isAdmin">
            <DropdownMenuItem :as-child="true">
                <Link
                    class="block w-full cursor-pointer"
                    :href="usersIndex()"
                    prefetch
                >
                    <UserCog class="mr-2 h-4 w-4" />
                    {{ $t('Users') }}
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem :as-child="true">
                <Link
                    class="block w-full cursor-pointer"
                    :href="auditIndex()"
                    prefetch
                >
                    <ScrollText class="mr-2 h-4 w-4" />
                    {{ $t('Activity log') }}
                </Link>
            </DropdownMenuItem>
        </template>
        <DropdownMenuSub v-if="Object.keys(locales).length > 1">
            <DropdownMenuSubTrigger data-test="language-menu">
                <Languages class="mr-2 h-4 w-4" />
                {{ $t('Language') }}
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                <DropdownMenuItem
                    v-for="(name, code) in locales"
                    :key="code"
                    class="cursor-pointer"
                    :data-test="`locale-${code}`"
                    @select="switchLocale(String(code))"
                >
                    <Check
                        class="mr-2 h-4 w-4"
                        :class="code === currentLocale ? '' : 'invisible'"
                    />
                    {{ name }}
                </DropdownMenuItem>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            as="button"
            data-test="logout-button"
            @click="handleLogout"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ $t('Log out') }}
        </Link>
    </DropdownMenuItem>
</template>
