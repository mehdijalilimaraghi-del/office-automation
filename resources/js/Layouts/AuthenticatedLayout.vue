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
    ['cartable_view', 'tasks_view', 'projects_view'].some(can) || Boolean(page.props.auth.user),
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
        <div class="app-shell min-h-screen">
            <nav class="app-topbar sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 shadow-[0_1px_0_rgba(15,23,42,0.02)] backdrop-blur-xl">
                <div class="mx-auto max-w-[1600px] px-4 sm:px-6 xl:px-8">
                    <div class="flex h-16 items-center justify-between">
                        <!-- سمت راست: لوگو و منو -->
                        <div class="flex h-full items-center">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="group flex items-center gap-3 rounded-xl py-1.5 pl-3 pr-1 transition hover:bg-slate-50">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-[15px] ring-1 ring-black/5 transition group-hover:scale-[1.03]"><ApplicationLogo class="block h-10 w-10" /></span>
                                    <span class="hidden min-w-[8.5rem] lg:block"><span class="block text-sm font-extrabold tracking-tight text-slate-900">اتوماسیون اداری</span><span class="mt-0.5 block text-[10px] font-medium tracking-wide text-slate-400">فضای کاری</span></span>
                                </Link>
                            </div>

                            <div
                                class="hidden h-full items-center gap-1 sm:ms-10 sm:flex xl:hidden"
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
                                        class="inline-flex h-10 items-center gap-2 rounded-xl border border-transparent px-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                                        :class="
                                            route().current('cartable.*') ||
                                            route().current('notifications.*') ||
                                            route().current('tasks.*') ||
                                            route().current('projects.*')
                                                ? 'border-indigo-100 bg-indigo-50 text-indigo-700 shadow-sm'
                                                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'
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
                                        class="absolute right-0 z-50 mt-2 w-56 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-xl shadow-slate-900/10 ring-1 ring-black/[0.02]"
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
                                            دستورکارها
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
                                        class="inline-flex h-10 items-center gap-2 rounded-xl border border-transparent px-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                                        :class="
                                            route().current('departments.*') ||
                                            route().current('users.*') ||
                                            route().current('roles.*') ||
                                            route().current('custom-fields.*')
                                                ? 'border-indigo-100 bg-indigo-50 text-indigo-700 shadow-sm'
                                                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'
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
                                        class="absolute right-0 z-50 mt-2 w-56 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-xl shadow-slate-900/10 ring-1 ring-black/[0.02]"
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
                                            نقش‌های کاربری
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
                                        class="inline-flex h-10 items-center gap-2 rounded-xl border border-transparent px-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                                        :class="
                                            route().current('settings.*')
                                                ? 'border-indigo-100 bg-indigo-50 text-indigo-700 shadow-sm'
                                                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'
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
                                        class="absolute right-0 z-50 mt-2 w-56 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-xl shadow-slate-900/10 ring-1 ring-black/[0.02]"
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
                                    دستورکارها
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
                                    نقش‌های کاربری
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

            <aside class="fixed bottom-0 right-0 top-16 z-30 hidden w-64 flex-col border-l border-slate-200/80 bg-white px-4 py-5 xl:flex">
                <div class="mb-5 px-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">فضای کاری</p>
                    <p class="mt-1 text-sm font-bold text-slate-800">فضای کاری</p>
                </div>
                <nav class="flex-1 space-y-1" aria-label="منوی اصلی">
                    <Link :href="route('dashboard')" :class="route().current('dashboard') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                        داشبورد
                    </Link>
                    <p class="px-3 pb-1 pt-5 text-[11px] font-bold text-slate-400">کار و عملیات</p>
                    <Link v-if="can('cartable_view')" :href="route('cartable.index')" :class="route().current('cartable.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h5M8 16h6"/></svg>کارتابل
                    </Link>
                    <Link v-if="can('tasks_view')" :href="route('tasks.index')" :class="route().current('tasks.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M9 6h11M9 12h11M9 18h11"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></svg>دستورکارها
                    </Link>
                    <Link v-if="can('projects_view')" :href="route('projects.index')" :class="route().current('projects.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 4V2M16 4V2M3 9h18"/></svg>پروژه‌ها
                    </Link>
                    <Link v-if="can('notification_view')" :href="route('notifications.index')" :class="route().current('notifications.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>اعلان‌ها
                    </Link>
                    <p v-if="hasOrganizationMenu" class="px-3 pb-1 pt-5 text-[11px] font-bold text-slate-400">مدیریت سازمان</p>
                    <Link v-if="can('departments_view')" :href="route('departments.index')" :class="route().current('departments.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="18" height="7" rx="1"/></svg>سازمان
                    </Link>
                    <Link v-if="can('users_view')" :href="route('users.index')" :class="route().current('users.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>کاربران
                    </Link>
                    <Link v-if="can('roles_view')" :href="route('roles.index')" :class="route().current('roles.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M12 3 4 7v5c0 5 8 9 8 9s8-4 8-9V7z"/><path d="m9 12 2 2 4-4"/></svg>نقش‌های کاربری
                    </Link>
                    <Link v-if="can('custom_fields_manage')" :href="route('custom-fields.index')" :class="route().current('custom-fields.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M4 5h16M4 12h16M4 19h16"/><circle cx="8" cy="5" r="2" fill="currentColor"/><circle cx="15" cy="12" r="2" fill="currentColor"/><circle cx="10" cy="19" r="2" fill="currentColor"/></svg>فیلدهای سفارشی
                    </Link>
                    <Link v-if="can('external_parties_view')" :href="route('external-parties.index')" :class="route().current('external-parties.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>برون‌سازمانی
                    </Link>
                    <Link v-if="hasSettingsMenu" :href="route('settings.organization.edit')" :class="route().current('settings.*') ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'" class="mt-1 flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1a2 2 0 1 1-2.8 2.8l-.1-.1a2 2 0 0 0-3.4 1.4v.3a2 2 0 1 1-4 0v-.2A2 2 0 0 0 5.8 17l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A2 2 0 0 0 1.6 11H1.5a2 2 0 1 1 0-4h.2A2 2 0 0 0 3 3.6l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A2 2 0 0 0 9.2 2.2V2a2 2 0 1 1 4 0v.2a2 2 0 0 0 3.4 1.4l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A2 2 0 0 0 20.8 10h.2a2 2 0 1 1 0 4h-.2a2 2 0 0 0-1.4 1z"/></svg>تنظیمات سازمان
                    </Link>
                </nav>
                <div class="mt-5 rounded-2xl bg-slate-50 p-3">
                    <p class="text-xs font-bold text-slate-700">{{ $page.props.auth.user.name }}</p>
                    <p class="mt-1 truncate text-[11px] text-slate-400">{{ $page.props.auth.user.email }}</p>
                </div>
            </aside>

            <!-- عنوان صفحه -->
            <header
                v-if="$slots.header"
                class="app-page-header xl:mr-64"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- محتوای صفحه -->
            <main class="app-main xl:mr-64">
                <slot />
            </main>
        </div>
    </div>
</template>
