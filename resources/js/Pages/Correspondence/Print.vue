<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

interface AddressingSnapshot {
    organizational_title_prefix?: string | null;
    position?: string | null;
    personal_salutation?: string | null;
    professional_title?: string | null;
    name?: string | null;
    organization_name?: string | null;
    department_name?: string | null;
}

interface PrintLetter {
    id: number;
    type: 'incoming' | 'outgoing' | 'internal';
    subject: string;
    body: string | null;
    letter_number: string | null;
    registration_number: string | null;
    registered_at: string | null;
    sent_at: string | null;
    recipient_name: string | null;
    recipient_addressing: AddressingSnapshot | null;
    external_organization: string | null;
    external_department: string | null;
}

const props = defineProps<{
    letter: PrintLetter;
    organization: {
        name?: string | null;
        short_name?: string | null;
        address?: string | null;
        phone?: string | null;
        email?: string | null;
        website?: string | null;
    };
    creatorName: string | null;
    creatorUnitTitle: string | null;
    stampDataUri: string | null;
    signatureDataUri: string | null;
    printOptions: {
        show_creator_identity: boolean;
        show_stamp: boolean;
        show_department_signature: boolean;
        font_family: string;
    };
}>();

const withLetterhead = ref(true);
const typeLabel = computed(() => ({ incoming: 'نامه وارده', outgoing: 'نامه صادره', internal: 'نامه داخلی' })[props.letter.type]);
const recipientLines = computed(() => {
    const a = props.letter.recipient_addressing;
    if (!a) return [props.letter.recipient_name].filter(Boolean) as string[];
    return [
        [a.organizational_title_prefix, a.position].filter(Boolean).join(' '),
        [a.personal_salutation, a.professional_title, a.name].filter(Boolean).join(' '),
        [a.organization_name, a.department_name].filter(Boolean).join(' - '),
    ].filter(Boolean) as string[];
});
const letterDate = computed(() => {
    const value = props.letter.sent_at ?? props.letter.registered_at;
    return value ? new Intl.DateTimeFormat('fa-IR', { dateStyle: 'long' }).format(new Date(value)) : '—';
});
function printLetter(): void {
    window.print();
}
</script>

<template>
    <Head :title="'پیش‌نمایش چاپ - ' + letter.subject" />
    <main class="min-h-screen bg-slate-100 px-4 py-6 text-slate-900" dir="rtl">
        <div class="no-print mx-auto mb-4 flex max-w-[210mm] flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <p class="text-sm font-bold">پیش‌نمایش چاپ نامه</p>
                <p class="mt-1 text-xs text-slate-500">قالب A4 مشترک برای چاپ کاغذی و ذخیره به‌صورت PDF</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input v-model="withLetterhead" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
                    چاپ با سربرگ
                </label>
                <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" @click="printLetter">چاپ / ذخیره PDF</button>
                <Link :href="route('correspondence.show', letter.id)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">بازگشت به نامه</Link>
            </div>
        </div>

        <article class="paper mx-auto bg-white px-[18mm] py-[14mm] shadow-xl" :style="{ fontFamily: printOptions.font_family || 'Vazirmatn' }">
            <header class="letterhead-slot" :class="{ 'letterhead-hidden': !withLetterhead }">
                <div class="flex items-start justify-between gap-6 border-b-2 border-slate-700 pb-4">
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold">{{ organization.name }}</h1>
                        <p v-if="organization.short_name" class="mt-1 text-sm">{{ organization.short_name }}</p>
                        <div class="mt-3 space-y-1 text-xs leading-5 text-slate-600">
                            <p v-if="organization.address">{{ organization.address }}</p>
                            <p class="flex flex-wrap gap-x-4 gap-y-1">
                                <span v-if="organization.phone">تلفن: {{ organization.phone }}</span>
                                <span v-if="organization.email">{{ organization.email }}</span>
                                <span v-if="organization.website">{{ organization.website }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0 text-left text-xs leading-6">
                        <p>{{ typeLabel }}</p>
                        <p v-if="letter.letter_number">شماره: {{ letter.letter_number }}</p>
                        <p v-if="letter.registration_number">شماره ثبت: {{ letter.registration_number }}</p>
                        <p>تاریخ: {{ letterDate }}</p>
                    </div>
                </div>
            </header>

            <section class="mt-7">
                <div v-if="recipientLines.length" class="mb-7 space-y-1 text-sm font-semibold leading-7">
                    <p v-for="(line, index) in recipientLines" :key="index">{{ line }}</p>
                </div>
                <h2 class="mb-6 text-center text-base font-bold leading-8">{{ letter.subject }}</h2>
                <div class="letter-body text-justify text-sm leading-8" v-html="letter.body || ''"></div>
            </section>

            <footer v-if="printOptions.show_creator_identity || (printOptions.show_department_signature && signatureDataUri) || (printOptions.show_stamp && stampDataUri)" class="signature-area mt-12 flex items-end justify-between gap-8">
                <div v-if="printOptions.show_creator_identity" class="min-w-0 text-center text-sm leading-7">
                    <p v-if="creatorUnitTitle" class="font-semibold">{{ creatorUnitTitle }}</p>
                    <p v-if="creatorName" class="font-bold">{{ creatorName }}</p>
                </div>
                <div v-if="printOptions.show_department_signature && signatureDataUri" class="text-center">
                    <img :src="signatureDataUri" alt="امضای مدیر واحد" class="mx-auto h-20 max-w-44 object-contain" />
                </div>
                <div v-if="printOptions.show_stamp && stampDataUri" class="mr-auto text-center">
                    <img :src="stampDataUri" alt="مهر سازمان" class="h-28 w-28 object-contain" />
                </div>
            </footer>
        </article>
    </main>
</template>

<style>
@page {
    size: A4;
    margin: 14mm 0 18mm;
    @bottom-center {
        content: "صفحه " counter(page) " از " counter(pages);
        font-family: Tahoma, sans-serif;
        font-size: 9pt;
        color: #475569;
    }
}
.paper {
    box-sizing: border-box;
    width: 210mm;
    min-height: 269mm;
    color: #111827;
}
.letterhead-slot {
    min-height: 38mm;
}
.letterhead-hidden {
    visibility: hidden;
}
.letter-body p {
    margin: 0 0 0.75rem;
}
.letter-body img {
    max-width: 100%;
    height: auto;
}
.signature-area {
    break-inside: avoid;
    page-break-inside: avoid;
}
@media print {
    html, body {
        background: #fff !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    main {
        min-height: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    .no-print {
        display: none !important;
    }
    .paper {
        width: auto;
        min-height: 0;
        margin: 0;
        padding: 0 18mm;
        box-shadow: none;
        overflow: visible;
    }
}
@media screen and (max-width: 800px) {
    .paper {
        width: 100%;
        min-height: 0;
        padding: 24px;
    }
}
</style>
