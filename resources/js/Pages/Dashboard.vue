<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface DashboardStats {
    my_tasks: number;
    my_today_tasks: number;
    my_overdue_tasks: number;
    my_review_tasks: number;
    team_active_tasks: number;
    team_overdue_tasks: number;
    team_review_tasks: number;
    team_completed_month: number;
    active_projects: number;
    projects_total: number;
    projects_planning: number;
    projects_completed: number;
    sections_total: number;
    tasks_total: number;
    tasks_todo: number;
    tasks_in_progress: number;
    tasks_completed: number;
    tasks_overdue: number;
    tasks_review: number;
}

interface DashboardTask {
    id: number;
    title: string;
    status: string;
    sub_status: string | null;
    priority: string;
    due_date: string | null;
    is_overdue: boolean;
    project: string | null;
    assignee: string | null;
}

interface TrendPoint { date: string; label: string; created: number; completed: number; }
interface ProjectOverview { id: number; name: string; code: string; status: string; sections_count: number; tasks_count: number; progress: number; }
interface SectionOverview { id: number; name: string; project_id: number; project_name: string | null; project_code: string | null; department: string | null; tasks_count: number; completed_tasks: number; progress: number; }

const props = defineProps<{
    canViewTeam: boolean;
    stats: DashboardStats;
    trend: TrendPoint[];
    personalTasks: DashboardTask[];
    teamTasks: DashboardTask[];
    projectsOverview: ProjectOverview[];
    sectionsOverview: SectionOverview[];
}>();

const statusLabels: Record<string, string> = {
    todo: 'در انتظار انجام',
    observed: 'دیده‌شده',
    in_progress: 'در حال انجام',
    completed: 'تکمیل‌شده',
    cancelled: 'لغوشده',
    cannot_continue: 'غیرقابل ادامه',
};
const priorityLabels: Record<string, string> = { low: 'کم', normal: 'عادی', high: 'زیاد', urgent: 'فوری' };
const priorityClasses: Record<string, string> = { low: 'text-slate-500', normal: 'text-slate-500', high: 'text-amber-700', urgent: 'font-bold text-rose-700' };
const projectStatusLabels: Record<string, string> = { planning: 'برنامه‌ریزی', active: 'فعال', completed: 'تکمیل‌شده', archived: 'بایگانی‌شده' };
const formatDate = (value: string | null) => value ? value.replaceAll('-', '/') : 'بدون مهلت';
const maxTrend = Math.max(1, ...props.trend.flatMap((item) => [item.created, item.completed]));

const statusTone = (status: string) => ({
    todo: 'bg-slate-100 text-slate-700',
    observed: 'bg-sky-50 text-sky-700',
    in_progress: 'bg-blue-50 text-blue-700',
    completed: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-rose-50 text-rose-700',
    cannot_continue: 'bg-rose-50 text-rose-700',
}[status] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <Head title="داشبورد مدیریت" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold tracking-wide text-teal-700">مرکز عملیات</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">داشبورد مدیریت</h1>
                    <p class="mt-1 text-sm text-slate-500">کارهای شخصی، وضعیت تیم و موارد نیازمند پیگیری در یک نگاه</p>
                </div>
            </div>
        </template>

        <div class="min-h-[calc(100vh-9rem)] bg-slate-50/80 py-6 sm:py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="relative overflow-hidden rounded-2xl bg-slate-950 px-5 py-6 text-white shadow-sm sm:px-8 sm:py-8">
                    <div class="pointer-events-none absolute -left-16 -top-24 h-64 w-64 rounded-full bg-teal-400/15 blur-3xl"></div>
                    <div class="pointer-events-none absolute -bottom-28 right-1/3 h-64 w-64 rounded-full bg-blue-400/10 blur-3xl"></div>
                    <div class="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div class="max-w-2xl">
                            <h2 class="mt-4 text-2xl font-bold leading-tight sm:text-3xl">تصویر روشن‌تری از کار امروز داشته باشید.</h2>
                            <p class="mt-2 text-sm leading-7 text-slate-300">ابتدا کارهای خودتان را ببینید؛ در صورت داشتن مسئولیت مدیریتی، عملکرد تیم و گلوگاه‌ها در بخشی جداگانه نمایش داده می‌شود.</p>
                        </div>
                        <div class="shrink-0 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                            <p class="text-xs text-slate-400">کارهای شخصی باز</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums">{{ stats.my_tasks }}</p>
                        </div>
                    </div>
                </section>

                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Link href="/tasks" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">کارهای باز من</span><span class="rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-bold text-teal-700">شخصی</span></div>
                        <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">{{ stats.my_tasks }}</p><p class="mt-2 text-xs text-slate-500">تسک‌های تخصیص‌یافته به شما</p>
                    </Link>
                    <Link href="/tasks" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">سررسید امروز من</span><span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">امروز</span></div>
                        <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">{{ stats.my_today_tasks }}</p><p class="mt-2 text-xs text-slate-500">کارهای باز با مهلت امروز</p>
                    </Link>
                    <Link href="/tasks" class="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">عقب‌افتاده‌های من</span><span class="rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">نیازمند اقدام</span></div>
                        <p class="mt-3 text-3xl font-bold tracking-tight text-rose-700">{{ stats.my_overdue_tasks }}</p><p class="mt-2 text-xs text-slate-500">مهلت گذشته و هنوز باز</p>
                    </Link>
                    <Link href="/tasks" class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">در انتظار بررسی من</span><span class="rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">بررسی</span></div>
                        <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">{{ stats.my_review_tasks }}</p><p class="mt-2 text-xs text-slate-500">کارهای ارسال‌شده برای بررسی مدیر</p>
                    </Link>
                </section>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,.85fr)]">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div><h2 class="font-bold text-slate-950">روند هفت‌روزه فعالیت</h2><p class="mt-1 text-xs text-slate-500">تسک‌های ایجادشده و تکمیل‌شده در هر روز</p></div>
                            <div class="flex gap-3 text-xs"><span class="inline-flex items-center gap-1.5 text-slate-600"><i class="h-2.5 w-2.5 rounded-sm bg-sky-500"></i>ایجادشده</span><span class="inline-flex items-center gap-1.5 text-slate-600"><i class="h-2.5 w-2.5 rounded-sm bg-teal-500"></i>تکمیل‌شده</span></div>
                        </div>
                        <div class="mt-6 grid grid-cols-7 gap-2 sm:gap-4">
                            <div v-for="point in trend" :key="point.date" class="flex min-w-0 flex-col items-center">
                                <div class="flex h-36 w-full items-end justify-center gap-1.5 border-b border-slate-100 pb-2">
                                    <div class="w-2.5 rounded-t-md bg-sky-500 sm:w-4" :style="{ height: Math.max(point.created ? 5 : 0, point.created / maxTrend * 100) + '%' }" :title="'ایجادشده: ' + point.created"></div>
                                    <div class="w-2.5 rounded-t-md bg-teal-500 sm:w-4" :style="{ height: Math.max(point.completed ? 5 : 0, point.completed / maxTrend * 100) + '%' }" :title="'تکمیل‌شده: ' + point.completed"></div>
                                </div>
                                <span class="mt-2 text-[10px] text-slate-500 sm:text-xs">{{ point.label }}</span>
                                <span class="mt-1 text-[10px] font-semibold tabular-nums text-slate-700">{{ point.created }}/{{ point.completed }}</span>
                            </div>
                        </div>
                        <p class="mt-3 text-[11px] text-slate-400">در هر ستون: تعداد ایجادشده / تعداد تکمیل‌شده</p>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex items-start justify-between gap-3"><div><h2 class="font-bold text-slate-950">وضعیت کارها</h2><p class="mt-1 text-xs text-slate-500">تسک‌های قابل مشاهده در پروژه‌ها</p></div><Link href="/tasks" class="text-sm font-semibold text-teal-700 hover:text-teal-800">جزئیات</Link></div>
                        <div class="mt-5 space-y-4">
                            <div v-for="item in [
                                { label: 'در انتظار انجام / دیده‌شده', value: stats.tasks_todo, tone: 'bg-slate-400' },
                                { label: 'در حال انجام', value: stats.tasks_in_progress, tone: 'bg-sky-500' },
                                { label: 'در انتظار بررسی مدیر', value: stats.tasks_review, tone: 'bg-amber-500' },
                                { label: 'تکمیل‌شده', value: stats.tasks_completed, tone: 'bg-teal-500' },
                                { label: 'عقب‌افتاده', value: stats.tasks_overdue, tone: 'bg-rose-500' },
                            ]" :key="item.label">
                                <div class="mb-1.5 flex items-center justify-between gap-3 text-sm"><span class="text-slate-600">{{ item.label }}</span><span class="font-bold tabular-nums text-slate-900">{{ item.value }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" :class="item.tone" :style="{ width: (stats.tasks_total ? Math.min(100, item.value / stats.tasks_total * 100) : 0) + '%' }"></div></div>
                            </div>
                        </div>
                    </section>
                </div>

                <section v-if="canViewTeam" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div><p class="text-xs font-bold text-violet-700">نمای مدیریتی</p><h2 class="mt-1 font-bold text-slate-950">عملکرد تیم و زیرمجموعه‌ها</h2><p class="mt-1 text-xs text-slate-500">این آمار جدا از کارهای شخصی شماست و بر اساس محدوده دسترسی مدیریتی محاسبه می‌شود.</p></div>
                            <Link href="/tasks" class="text-sm font-semibold text-violet-700 hover:text-violet-800">پیگیری کارهای تیم ←</Link>
                        </div>
                    </div>
                    <div class="grid gap-3 border-b border-slate-100 bg-slate-50/70 p-4 sm:grid-cols-2 xl:grid-cols-4 sm:p-5">
                        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">کارهای باز تیم</p><p class="mt-2 text-2xl font-bold text-slate-950">{{ stats.team_active_tasks }}</p></div>
                        <div class="rounded-xl border border-rose-100 bg-white p-4"><p class="text-xs text-slate-500">عقب‌افتاده‌های تیم</p><p class="mt-2 text-2xl font-bold text-rose-700">{{ stats.team_overdue_tasks }}</p></div>
                        <div class="rounded-xl border border-amber-100 bg-white p-4"><p class="text-xs text-slate-500">در انتظار بررسی</p><p class="mt-2 text-2xl font-bold text-amber-700">{{ stats.team_review_tasks }}</p></div>
                        <div class="rounded-xl border border-teal-100 bg-white p-4"><p class="text-xs text-slate-500">تکمیل‌شده در ماه جاری</p><p class="mt-2 text-2xl font-bold text-teal-700">{{ stats.team_completed_month }}</p></div>
                    </div>
                    <div v-if="teamTasks.length === 0" class="px-6 py-10 text-center"><p class="text-sm font-semibold text-slate-700">کار بازی برای نمایش وجود ندارد.</p><p class="mt-1 text-xs text-slate-500">با ایجاد یا تخصیص کار به اعضای تیم، موارد قابل پیگیری در این بخش نمایش داده می‌شود.</p></div>
                    <div v-else class="divide-y divide-slate-100">
                        <Link v-for="task in teamTasks" :key="task.id" :href="'/tasks/' + task.id" class="flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ task.title }}</p><p class="mt-1 text-xs text-slate-500">{{ task.project || 'بدون پروژه' }} · مسئول: {{ task.assignee || 'تخصیص‌نیافته' }}</p></div>
                            <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusTone(task.status)">{{ statusLabels[task.status] || task.status }}<span v-if="task.sub_status === 'manager_review'"> · در انتظار بررسی</span></span><span class="text-xs" :class="priorityClasses[task.priority] || 'text-slate-500'">اولویت {{ priorityLabels[task.priority] || task.priority }}</span><span class="text-xs text-slate-400">مهلت: {{ formatDate(task.due_date) }}</span></div>
                        </Link>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold text-slate-950">کارهای شخصی من</h2><p class="mt-1 text-xs text-slate-500">فقط تسک‌هایی که به شما تخصیص داده شده‌اند</p></div><Link href="/tasks" class="text-sm font-semibold text-teal-700">همه کارها ←</Link></div>
                        <div v-if="personalTasks.length === 0" class="px-6 py-10 text-center text-sm text-slate-500">در حال حاضر کار باز تخصیص‌یافته‌ای ندارید.</div>
                        <div v-else class="divide-y divide-slate-100">
                            <Link v-for="task in personalTasks" :key="task.id" :href="'/tasks/' + task.id" class="block px-5 py-4 transition hover:bg-slate-50">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ task.title }}</p><p class="mt-1 text-xs text-slate-500">{{ task.project || 'بدون پروژه' }}</p></div><span class="w-fit rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusTone(task.status)">{{ statusLabels[task.status] || task.status }}</span></div>
                                <div class="mt-2 flex items-center justify-between gap-3"><span class="text-xs" :class="priorityClasses[task.priority] || 'text-slate-500'">اولویت {{ priorityLabels[task.priority] || task.priority }}</span><span class="text-xs" :class="task.is_overdue ? 'font-semibold text-rose-700' : 'text-slate-400'">مهلت: {{ formatDate(task.due_date) }}</span></div>
                            </Link>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold text-slate-950">پیشرفت پروژه‌ها</h2><p class="mt-1 text-xs text-slate-500">درصد تکمیل بر اساس تسک‌های قابل مشاهده</p></div><Link href="/projects" class="text-sm font-semibold text-teal-700">همه پروژه‌ها ←</Link></div>
                        <div v-if="projectsOverview.length === 0" class="px-6 py-10 text-center text-sm text-slate-500">پروژه‌ای برای نمایش در دسترس نیست.</div>
                        <div v-else class="divide-y divide-slate-100">
                            <div v-for="project in projectsOverview.slice(0, 6)" :key="project.id" class="px-5 py-4">
                                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><Link :href="'/projects/' + project.id" class="truncate text-sm font-semibold text-slate-800 hover:text-teal-700">{{ project.name }}</Link><p class="mt-1 text-xs text-slate-500">{{ project.code }} · {{ projectStatusLabels[project.status] || project.status }} · {{ project.tasks_count }} تسک</p></div><span class="shrink-0 text-sm font-bold text-slate-700">{{ project.progress }}٪</span></div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-teal-500" :style="{ width: project.progress + '%' }"></div></div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6"><div><h2 class="font-bold text-slate-950">واحدهای نیازمند پیگیری</h2><p class="mt-1 text-xs text-slate-500">پیشرفت بر اساس تسک‌های قابل مشاهده</p></div><span class="text-xs text-slate-400">{{ sectionsOverview.length }} بخش</span></div>
                    <div v-if="sectionsOverview.length === 0" class="p-8 text-center text-sm text-slate-500">بخشی برای نمایش وجود ندارد.</div>
                    <div v-else class="grid gap-0 divide-y divide-slate-100 md:grid-cols-2 md:divide-y-0 md:divide-x md:divide-x-reverse">
                        <div v-for="section in sectionsOverview" :key="section.id" class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ section.name }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ section.project_name || 'پروژه' }} · {{ section.department || 'بدون واحد مسئول' }}</p></div><span class="shrink-0 text-sm font-bold text-slate-700">{{ section.progress }}٪</span></div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-500" :style="{ width: section.progress + '%' }"></div></div>
                            <p class="mt-2 text-xs text-slate-400">{{ section.completed_tasks }} از {{ section.tasks_count }} تسک تکمیل شده</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
