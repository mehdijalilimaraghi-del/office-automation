<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { DatePicker } from '@jalali-js/vue';
import '@jalali-js/vue/date-picker.css';

interface Department { id: number; name: string; }
interface ReplyOption { id: number; registration_number: string | null; subject: string; }
interface CreatedDepartment { id: number; name: string; }
type CompositionMode = 'editor' | 'file' | 'both';

const props = defineProps<{
    types: Array<{ value: string; label: string }>;
    classifications: Array<{ value: string; label: string }>;
    departments: Department[];
    createdDepartment: CreatedDepartment | null;
    createdDepartments: CreatedDepartment[];
    repliesTo: ReplyOption[];
}>();

const editor = ref<HTMLDivElement | null>(null);
const compositionModes: Array<{ value: CompositionMode; label: string; description: string }> = [
    { value: 'editor', label: 'ویرایشگر داخلی', description: 'نامه را داخل سامانه بنویسید.' },
    { value: 'file', label: 'پیوست فایل', description: 'فایل آماده نامه را بارگذاری کنید.' },
    { value: 'both', label: 'متن و فایل', description: 'متن نامه را بنویسید و فایل هم پیوست کنید.' },
];

const form = useForm({
    type: props.types[0]?.value ?? 'internal',
    created_department_id: props.createdDepartment?.id ?? '' as number | '',
    classification: 'ordinary',
    subject: '',
    composition_mode: 'editor' as CompositionMode,
    body: '',
    sender_name: '',
    recipient_name: '',
    received_at: '',
    sent_at: '',
    recipient_department_id: '' as number | '',
    reply_to_id: '' as number | '',
    attachments: [] as File[],
});


watch(() => form.type, (type) => {
    if (type !== 'internal') form.recipient_department_id = '';
    if (type !== 'incoming') {
        form.sender_name = '';
        form.received_at = '';
    }
    if (type !== 'outgoing') {
        form.recipient_name = '';
        form.sent_at = '';
    }
});

function syncBody(): void {
    form.body = editor.value?.innerHTML ?? '';
}

function format(command: string): void {
    editor.value?.focus();
    document.execCommand(command, false);
    syncBody();
}

function formatBlock(tag: 'p' | 'h2'): void {
    editor.value?.focus();
    document.execCommand('formatBlock', false, tag);
    syncBody();
}

function chooseFiles(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.attachments = Array.from(target.files ?? []);
}

function removeFile(index: number): void {
    form.attachments.splice(index, 1);
}
</script>

<template>
    <Head title="ایجاد مکاتبه" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-bold text-slate-900">ایجاد مکاتبه</h2>
                <p class="mt-1 text-sm text-slate-500">پیش‌نویس ایجاد می‌شود و شماره رسمی پس از ثبت نهایی اختصاص می‌یابد.</p>
            </div>
        </template>

        <div class="p-4 sm:p-6">
            <form
                class="mx-auto max-w-5xl space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
                enctype="multipart/form-data"
                @submit.prevent="form.post(route('correspondence.store'), { forceFormData: true })"
            >
                <section class="space-y-4">
                    <h3 class="text-base font-bold text-slate-900">مشخصات مکاتبه</h3>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="type" class="mb-1.5 block text-sm font-semibold text-slate-700">نوع مکاتبه <span class="text-rose-600">*</span></label>
                            <select id="type" v-model="form.type" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.type" />
                        </div>
                        <div>
                            <label for="classification" class="mb-1.5 block text-sm font-semibold text-slate-700">طبقه‌بندی دسترسی <span class="text-rose-600">*</span></label>
                            <select id="classification" v-model="form.classification" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="item in classifications" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">نامه عادی پس از ثبت نهایی برای کاربران دارای مجوز مشاهده قابل مشاهده است؛ نامه محرمانه فقط برای افراد مرتبط و دبیرخانه.</p>
                            <InputError class="mt-1" :message="form.errors.classification" />
                        </div>
                        <div>
                            <label for="created_department_id" class="mb-1.5 block text-sm font-semibold text-slate-700">واحد ایجادکننده <span v-if="createdDepartments.length > 1" class="text-rose-600">*</span></label>
                            <select
                                v-if="createdDepartments.length > 1"
                                id="created_department_id"
                                v-model="form.created_department_id"
                                required
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">انتخاب واحدی که از طرف آن نامه ایجاد می‌شود</option>
                                <option v-for="item in createdDepartments" :key="item.id" :value="item.id">{{ item.name }}</option>
                            </select>
                            <div v-else class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">{{ createdDepartment?.name ?? 'برای کاربر واحد فعالی تعیین نشده است' }}</div>
                            <p class="mt-1 text-xs text-slate-500">در صورت عضویت یا مدیریت چند واحد، واحد مربوط به این نامه را انتخاب کنید.</p>
                            <InputError class="mt-1" :message="form.errors.created_department_id" />
                        </div>
                        <div v-if="form.type === 'internal'">
                            <label for="recipient_department_id" class="mb-1.5 block text-sm font-semibold text-slate-700">واحد گیرنده <span class="text-rose-600">*</span></label>
                            <select id="recipient_department_id" v-model="form.recipient_department_id" required class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">انتخاب واحد گیرنده</option>
                                <option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.recipient_department_id" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="subject" class="mb-1.5 block text-sm font-semibold text-slate-700">موضوع <span class="text-rose-600">*</span></label>
                            <input id="subject" v-model="form.subject" required maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <InputError class="mt-1" :message="form.errors.subject" />
                        </div>
                        <div v-if="form.type === 'incoming'">
                            <label for="sender_name" class="mb-1.5 block text-sm font-semibold text-slate-700">فرستنده نامه وارده</label>
                            <input id="sender_name" v-model="form.sender_name" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <InputError class="mt-1" :message="form.errors.sender_name" />
                        </div>
                        <div v-if="form.type === 'outgoing'">
                            <label for="recipient_name" class="mb-1.5 block text-sm font-semibold text-slate-700">گیرنده بیرونی</label>
                            <input id="recipient_name" v-model="form.recipient_name" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <InputError class="mt-1" :message="form.errors.recipient_name" />
                        </div>
                        <div v-if="form.type === 'incoming'">
                            <label for="received_at" class="mb-1.5 block text-sm font-semibold text-slate-700">تاریخ دریافت</label>
                            <DatePicker v-model="form.received_at" system="jalali" locale="fa" value-format="gregorian-iso" />
                            <InputError class="mt-1" :message="form.errors.received_at" />
                        </div>
                        <div v-if="form.type === 'outgoing'">
                            <label for="sent_at" class="mb-1.5 block text-sm font-semibold text-slate-700">تاریخ ارسال</label>
                            <DatePicker v-model="form.sent_at" system="jalali" locale="fa" value-format="gregorian-iso" />
                            <InputError class="mt-1" :message="form.errors.sent_at" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="reply_to_id" class="mb-1.5 block text-sm font-semibold text-slate-700">پاسخ به مکاتبه ثبت‌شده</label>
                            <select id="reply_to_id" v-model="form.reply_to_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">مکاتبه‌ای انتخاب نشده</option>
                                <option v-for="item in repliesTo" :key="item.id" :value="item.id">{{ item.registration_number ?? 'بدون شماره' }} — {{ item.subject }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.reply_to_id" />
                        </div>
                    </div>
                </section>

                <section class="space-y-3 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">روش تهیه نامه</h3>
                        <p class="mt-1 text-sm text-slate-500">می‌توانید نامه را داخل سامانه بنویسید، فایل آماده را بارگذاری کنید یا هر دو را با هم داشته باشید.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label
                            v-for="mode in compositionModes"
                            :key="mode.value"
                            class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                            :class="form.composition_mode === mode.value ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-200' : 'border-slate-200 hover:border-slate-300'"
                        >
                            <input v-model="form.composition_mode" type="radio" name="composition_mode" :value="mode.value" class="mt-1 border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span>
                                <span class="block text-sm font-bold text-slate-900">{{ mode.label }}</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">{{ mode.description }}</span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.composition_mode" />
                </section>

                <section v-if="form.composition_mode !== 'file'" class="space-y-3 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">متن نامه</h3>
                        <p class="mt-1 text-sm text-slate-500">از نوار ابزار برای قالب‌بندی متن استفاده کنید. پیش‌نویس قبل از ثبت نهایی قابل ویرایش خواهد بود.</p>
                    </div>
                    <div class="overflow-hidden rounded-xl border border-slate-300 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                        <div class="flex flex-wrap gap-1 border-b border-slate-200 bg-slate-50 p-2" role="toolbar" aria-label="قالب‌بندی متن نامه">
                            <button type="button" title="پررنگ" aria-label="پررنگ" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold hover:bg-slate-100" @click="format('bold')">B</button>
                            <button type="button" title="مورب" aria-label="مورب" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm italic hover:bg-slate-100" @click="format('italic')">I</button>
                            <button type="button" title="زیرخط" aria-label="زیرخط" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm underline hover:bg-slate-100" @click="format('underline')">U</button>
                            <button type="button" title="حذف قالب‌بندی" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="format('removeFormat')">پاک‌کردن قالب</button>
                            <span class="mx-1 w-px bg-slate-200"></span>
                            <button type="button" title="راست‌چین" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="format('justifyRight')">راست‌چین</button>
                            <button type="button" title="وسط‌چین" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="format('justifyCenter')">وسط‌چین</button>
                            <button type="button" title="چپ‌چین" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="format('justifyLeft')">چپ‌چین</button>
                            <span class="mx-1 w-px bg-slate-200"></span>
                            <button type="button" title="عنوان بخش" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="formatBlock('h2')">عنوان</button>
                            <button type="button" title="پاراگراف معمولی" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="formatBlock('p')">پاراگراف</button>
                            <button type="button" title="فهرست نشانه‌دار" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="format('insertUnorderedList')">فهرست •</button>
                            <button type="button" title="فهرست شماره‌دار" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="format('insertOrderedList')">فهرست ۱.</button>
                            <span class="mx-1 w-px bg-slate-200"></span>
                            <button type="button" title="واگرد" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="format('undo')">واگرد</button>
                            <button type="button" title="ازنو" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm hover:bg-slate-100" @click="format('redo')">ازنو</button>
                        </div>
                        <div
                            ref="editor"
                            contenteditable="true"
                            role="textbox"
                            aria-label="متن نامه"
                            aria-multiline="true"
                            dir="rtl"
                            class="min-h-72 whitespace-normal p-4 text-sm leading-8 text-slate-800 outline-none"
                            data-placeholder="متن نامه را اینجا بنویسید..."
                            @input="syncBody"
                        ></div>
                    </div>
                    <InputError :message="form.errors.body" />
                </section>

                <section class="space-y-3 border-t border-slate-100 pt-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">فایل‌های پیوست</h3>
                        <p class="mt-1 text-sm text-slate-500">PDF، Word، Excel، PowerPoint، تصویر و فایل متنی؛ حداکثر ۲۰ مگابایت برای هر فایل و ۱۰ فایل.</p>
                    </div>
                    <label for="attachments" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-7 text-center transition hover:border-indigo-400 hover:bg-indigo-50/50">
                        <span class="text-sm font-semibold text-slate-800">انتخاب فایل از رایانه</span>
                        <span class="mt-1 text-xs text-slate-500">{{ form.composition_mode === 'editor' ? 'اختیاری' : 'برای این روش حداقل یک فایل لازم است' }}</span>
                        <input id="attachments" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" class="mt-3 block max-w-full text-sm text-slate-600 file:ml-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700" @change="chooseFiles" />
                    </label>
                    <InputError :message="form.errors.attachments" />
                    <InputError :message="form.errors['attachments.0']" />
                    <ul v-if="form.attachments.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                        <li v-for="(file, index) in form.attachments" :key="file.name + file.size + index" class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <span class="min-w-0 truncate text-sm text-slate-700">{{ file.name }} <span class="text-xs text-slate-400">({{ Math.ceil(file.size / 1024) }} KB)</span></span>
                            <button type="button" class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="removeFile(index)">حذف</button>
                        </li>
                    </ul>
                </section>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <Link :href="route('correspondence.index')" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">انصراف</Link>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ form.processing ? 'در حال ذخیره...' : 'ذخیره پیش‌نویس' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
