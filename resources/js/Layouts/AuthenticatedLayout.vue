<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';

const page = usePage();
const showingNavigationDropdown = ref(false);
const openDesktopMenu = ref<string | null>(null);
const expandedMobileMenus = ref<Record<string, boolean>>({
    work: false,
    organization: false,
    settings: false,
});

const abilities = computed(
    () => (page.props.auth.abilities ?? {}) as Record<string, boolean>,
);

const can = (ability: string): boolean => Boolean(abilities.value[ability]);

const hasWorkMenu = computed(() =>
    ['cartable_view', 'tasks_view', 'projects_view', 'notification_view'].some(can),
);

const hasOrganizationMenu = computed(() =>
    ['departments_view', 'users_view', 'roles_view', 'custom_fields_manage'].some(can),
);

const hasSettingsMenu = computed(() => can('settings_manage'));

function toggleDesktopMenu(menu: string): void {
    openDesktopMenu.value =
        openDesktopMenu.value === menu ? null : menu;
}

function toggleMobileMenu(menu: string): void {
    expandedMobileMenus.value[menu] = !expandedMobileMenus.value[menu];
}

function closeMobileNavigation(): void {
    showingNavigationDropdown.value = false;
}

onMounted(() => {
    if (
        route().current('cartable.*') ||
        route().current('notifications.*') ||
        route().current('tasks.*') ||
        route().current('projects.*')
    ) {
        expandedMobileMenus.value.work = true;
    }

    if (
        route().current('departments.*') ||
        route().current('users.*') ||
        route().current('roles.*') ||
        route().current('custom-fields.*')
    ) {
        expandedMobileMenus.value.organization = true;
    }

    if (route().current('settings.*')) {
        expandedMobileMenus.value.settings = true;
    }
});
</script>

<template>
    <div dir="rtl">
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 items-center justify-between">
                        <!-- سمت راست: لوگو و منو -->
                        <div class="flex h-full items-center">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <div
                                class="hidden h-full items-center gap-1 sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    داشبورد
                                </NavLink>

                                <div v-if="hasWorkMenu" class="relative h-full">
                                    <button
                                        type="button"
                                        class="inline-flex h-full items-center gap-1 border-b-2 px-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2"
                                        :class="
                                            route().current('cartable.*') ||
                                            route().current('notifications.*') ||
                                            route().current('tasks.*') ||
                                            route().current('projects.*')
                                                ? 'border-indigo-400 text-gray-900'
                                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                        "
                                        aria-haspopup="menu"
                                        :aria-expanded="openDesktopMenu === 'work'"
                                        @click="toggleDesktopMenu('work')"
                                        @keydown.escape="openDesktopMenu = null"
                                    >
                                        کار و عملیات
                                        <ChevronDown
                                            class="h-4 w-4 transition-transform"
                                            :class="{ 'rotate-180': openDesktopMenu === 'work' }"
                                        />
                                    </button>

                                    <div
                                        v-if="openDesktopMenu === 'work'"
                                        class="absolute right-0 z-50 mt-1 w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-200/50"
                                        role="menu"
                                    >
                                        <NavLink
                                            v-if="can('cartable_view')"
                                            :href="route('cartable.index')"
                                            :active="route().current('cartable.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            کارتابل
                                        </NavLink>
                                        <NavLink
                                            v-if="can('tasks_view')"
                                            :href="route('tasks.index')"
                                            :active="route().current('tasks.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            تسک‌ها
                                        </NavLink>
                                        <NavLink
                                            v-if="can('projects_view')"
                                            :href="route('projects.index')"
                                            :active="route().current('projects.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            پروژه‌ها
                                        </NavLink>
                                        <NavLink
                                            v-if="can('notification_view')"
                                            :href="route('notifications.index')"
                                            :active="route().current('notifications.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            اعلان‌ها
                                        </NavLink>
                                    </div>
                                </div>

                                <div v-if="hasOrganizationMenu" class="relative h-full">
                                    <button
                                        type="button"
                                        class="inline-flex h-full items-center gap-1 border-b-2 px-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2"
                                        :class="
                                            route().current('departments.*') ||
                                            route().current('users.*') ||
                                            route().current('roles.*') ||
                                            route().current('custom-fields.*')
                                                ? 'border-indigo-400 text-gray-900'
                                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                        "
                                        aria-haspopup="menu"
                                        :aria-expanded="openDesktopMenu === 'organization'"
                                        @click="toggleDesktopMenu('organization')"
                                        @keydown.escape="openDesktopMenu = null"
                                    >
                                        سازمان داخلی
                                        <ChevronDown
                                            class="h-4 w-4 transition-transform"
                                            :class="{ 'rotate-180': openDesktopMenu === 'organization' }"
                                        />
                                    </button>

                                    <div
                                        v-if="openDesktopMenu === 'organization'"
                                        class="absolute right-0 z-50 mt-1 w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-200/50"
                                        role="menu"
                                    >
                                        <NavLink
                                            v-if="can('departments_view')"
                                            :href="route('departments.index')"
                                            :active="route().current('departments.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            سازمان
                                        </NavLink>
                                        <NavLink
                                            v-if="can('users_view')"
                                            :href="route('users.index')"
                                            :active="route().current('users.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            کاربران
                                        </NavLink>
                                        <NavLink
                                            v-if="can('roles_view')"
                                            :href="route('roles.index')"
                                            :active="route().current('roles.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            نقش‌ها
                                        </NavLink>
                                        <NavLink
                                            v-if="can('custom_fields_manage')"
                                            :href="route('custom-fields.index')"
                                            :active="route().current('custom-fields.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            فیلدهای سفارشی
                                        </NavLink>
                                    </div>
                                </div>

                                <NavLink
                                    v-if="can('external_parties_view')"
                                    :href="route('external-parties.index')"
                                    :active="route().current('external-parties.*')"
                                >
                                    برون‌سازمانی
                                </NavLink>

                                <div v-if="hasSettingsMenu" class="relative h-full">
                                    <button
                                        type="button"
                                        class="inline-flex h-full items-center gap-1 border-b-2 px-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2"
                                        :class="
                                            route().current('settings.*')
                                                ? 'border-indigo-400 text-gray-900'
                                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                        "
                                        aria-haspopup="menu"
                                        :aria-expanded="openDesktopMenu === 'settings'"
                                        @click="toggleDesktopMenu('settings')"
                                        @keydown.escape="openDesktopMenu = null"
                                    >
                                        تنظیمات
                                        <ChevronDown
                                            class="h-4 w-4 transition-transform"
                                            :class="{ 'rotate-180': openDesktopMenu === 'settings' }"
                                        />
                                    </button>

                                    <div
                                        v-if="openDesktopMenu === 'settings'"
                                        class="absolute right-0 z-50 mt-1 w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-200/50"
                                        role="menu"
                                    >
                                        <NavLink
                                            :href="route('settings.organization.edit')"
                                            :active="route().current('settings.*')"
                                            class="w-full justify-start rounded-lg border-0 px-3 py-2 text-right"
                                        >
                                            تنظیمات سازمان
                                        </NavLink>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- سمت چپ: کاربر -->
                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="left" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                                            >
                                                <UserAvatar
                                                    :name="$page.props.auth.user.name"
                                                    :avatar-path="$page.props.auth.user.profile?.avatar_path"
                                                    size="sm"
                                                />
                                                <span>{{ $page.props.auth.user.name }}</span>
                                                <svg
                                                    class="-ms-0.5 me-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            پروفایل
                                        </DropdownLink>

                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            خروج
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- منوی موبایل -->
                        <div class="-ms-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                                :aria-expanded="showingNavigationDropdown"
                                aria-label="باز و بسته کردن منو"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- منوی واکنش‌گرا -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="border-t border-slate-100 bg-white sm:hidden"
                >
                    <div class="space-y-1 p-3">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                            @click="closeMobileNavigation"
                        >
                            داشبورد
                        </ResponsiveNavLink>

                        <div v-if="hasWorkMenu" class="rounded-xl bg-slate-50">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl px-4 py-3 text-right text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                                :class="
                                    route().current('cartable.*') ||
                                    route().current('notifications.*') ||
                                    route().current('tasks.*') ||
                                    route().current('projects.*')
                                        ? 'text-slate-900'
                                        : 'text-slate-600'
                                "
                                :aria-expanded="expandedMobileMenus.work"
                                @click="toggleMobileMenu('work')"
                            >
                                <span>کار و عملیات</span>
                                <ChevronDown
                                    class="h-4 w-4 transition-transform"
                                    :class="{ 'rotate-180': expandedMobileMenus.work }"
                                />
                            </button>

                            <div v-show="expandedMobileMenus.work" class="space-y-1 px-2 pb-2">
                                <ResponsiveNavLink
                                    v-if="can('cartable_view')"
                                    :href="route('cartable.index')"
                                    :active="route().current('cartable.*')"
                                    @click="closeMobileNavigation"
                                >
                                    کارتابل
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    v-if="can('tasks_view')"
                                    :href="route('tasks.index')"
                                    :active="route().current('tasks.*')"
                                    @click="closeMobileNavigation"
                                >
                                    تسک‌ها
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    v-if="can('projects_view')"
                                    :href="route('projects.index')"
                                    :active="route().current('projects.*')"
                                    @click="closeMobileNavigation"
                                >
                                    پروژه‌ها
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    v-if="can('notification_view')"
                                    :href="route('notifications.index')"
                                    :active="route().current('notifications.*')"
                                    @click="closeMobileNavigation"
                                >
                                    اعلان‌ها
                                </ResponsiveNavLink>
                            </div>
                        </div>

                        <div v-if="hasOrganizationMenu" class="rounded-xl bg-slate-50">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl px-4 py-3 text-right text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                                :class="
                                    route().current('departments.*') ||
                                    route().current('users.*') ||
                                    route().current('roles.*') ||
                                    route().current('custom-fields.*')
                                        ? 'text-slate-900'
                                        : 'text-slate-600'
                                "
                                :aria-expanded="expandedMobileMenus.organization"
                                @click="toggleMobileMenu('organization')"
                            >
                                <span>سازمان داخلی</span>
                                <ChevronDown
                                    class="h-4 w-4 transition-transform"
                                    :class="{ 'rotate-180': expandedMobileMenus.organization }"
                                />
                            </button>

                            <div
                                v-show="expandedMobileMenus.organization"
                                class="space-y-1 px-2 pb-2"
                            >
                                <ResponsiveNavLink
                                    v-if="can('departments_view')"
                                    :href="route('departments.index')"
                                    :active="route().current('departments.*')"
                                    @click="closeMobileNavigation"
                                >
                                    سازمان
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    v-if="can('users_view')"
                                    :href="route('users.index')"
                                    :active="route().current('users.*')"
                                    @click="closeMobileNavigation"
                                >
                                    کاربران
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    v-if="can('roles_view')"
                                    :href="route('roles.index')"
                                    :active="route().current('roles.*')"
                                    @click="closeMobileNavigation"
                                >
                                    نقش‌ها
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    v-if="can('custom_fields_manage')"
                                    :href="route('custom-fields.index')"
                                    :active="route().current('custom-fields.*')"
                                    @click="closeMobileNavigation"
                                >
                                    فیلدهای سفارشی
                                </ResponsiveNavLink>
                            </div>
                        </div>

                        <ResponsiveNavLink
                            v-if="can('external_parties_view')"
                            :href="route('external-parties.index')"
                            :active="route().current('external-parties.*')"
                            @click="closeMobileNavigation"
                        >
                            برون‌سازمانی
                        </ResponsiveNavLink>

                        <div v-if="hasSettingsMenu" class="rounded-xl bg-slate-50">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl px-4 py-3 text-right text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                                :class="
                                    route().current('settings.*')
                                        ? 'text-slate-900'
                                        : 'text-slate-600'
                                "
                                :aria-expanded="expandedMobileMenus.settings"
                                @click="toggleMobileMenu('settings')"
                            >
                                <span>تنظیمات</span>
                                <ChevronDown
                                    class="h-4 w-4 transition-transform"
                                    :class="{ 'rotate-180': expandedMobileMenus.settings }"
                                />
                            </button>

                            <div v-show="expandedMobileMenus.settings" class="space-y-1 px-2 pb-2">
                                <ResponsiveNavLink
                                    :href="route('settings.organization.edit')"
                                    :active="route().current('settings.*')"
                                    @click="closeMobileNavigation"
                                >
                                    تنظیمات سازمان
                                </ResponsiveNavLink>
                            </div>
                        </div>
                    </div>

                    <!-- اطلاعات کاربر -->
                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ $page.props.auth.user.name }}
                            </div>

                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink
                                :href="route('profile.edit')"
                                @click="closeMobileNavigation"
                            >
                                پروفایل
                            </ResponsiveNavLink>

                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                @click="closeMobileNavigation"
                            >
                                خروج
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- عنوان صفحه -->
            <header
                v-if="$slots.header"
                class="bg-white shadow"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- محتوای صفحه -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
