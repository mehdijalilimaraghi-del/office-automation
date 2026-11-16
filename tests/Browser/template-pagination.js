import '/resources/css/app.css';
import { createApp, h, nextTick, shallowRef } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Template from '/resources/js/Pages/Correspondence/Template.vue';
import Print from '/resources/js/Pages/Correspondence/Print.vue';
import { formatPageNumber } from '/resources/js/lib/pageNumbers.ts';

window.editorImageTests = true;
window.startPaginationTests = async () => {
    const results = [];
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    const test = async (name, action) => { await action(); results.push(name); };
    const size = new URL(location.href).searchParams.get('size') === 'A5' ? 'A5' : 'A4';
    const height = size === 'A5' ? 210 : 297;
    const newY = size === 'A5' ? 160 : 240;
    const body = { id: 'body', role: 'body', kind: 'text', label: 'متن', content: '', x: 8, y: 0, width: 84, spacing: 12, font_size: 12, font_family: 'default', page_format: 'number_of', bold: false, align: 'right', visible: true };
    const letter = { ...body, id: 'letter', role: 'page_number', label: 'شماره صفحه نامه', y: 92, width: 30, spacing: 0, page_format: 'slash', font_size: 9 };
    const appendix = { ...letter, id: 'appendix', role: 'appendix_page_number', label: 'شماره صفحه پیوست', x: 58, y: 80, page_format: 'short', font_size: 11, font_family: 'Tahoma', align: 'left' };
    const header = { ...body, id: 'header', role: 'header', label: 'سربرگ', content: 'سربرگ مشترک', y: 3 };
    const meta = { ...body, id: 'meta', role: 'meta', label: 'مشخصات', content: 'مشخصات مخصوص نامه', y: 10 };
    const issuer = { ...body, id: 'issuer', role: 'issuer', label: 'امضا', content: 'امضای مخصوص نامه' };
    const pageLayout = { top: 14, right: 18, bottom: 22, left: 18, header_height: 70, footer_height: 25 };
    const view = shallowRef(Template);
    const viewProps = shallowRef({
        elements: [letter, appendix, body, header, meta, issuer], pageLayout, pageSize: size, tokens: [],
        letterheadDataUri: null, stampDataUri: null, defaultFontFamily: 'Vazirmatn', previewValues: {}, previewBody: 'متن نمونه نامه',
    });
    window.route = name => !name ? { current: () => false } : name === 'notifications.summary' ? '/__preview-test/notifications' : '/unused/' + name;
    await createInertiaApp({
        page: { component: 'TestSurface', props: { auth: { user: { id: 1, name: 'Test', email: 'test@example.com' }, abilities: {} } }, url: location.pathname, version: 'test', clearHistory: false, encryptHistory: false },
        resolve: () => ({ setup: () => () => h(view.value, viewProps.value) }),
        setup({ el, App, props, plugin }) { const app = createApp({ render: () => h(App, props) }).use(plugin); app.config.globalProperties.route = window.route; app.mount(el); },
    });
    await nextTick(); await document.fonts.ready;
    const counter = role => document.querySelector('[data-page-number-role="' + role + '"]');
    const switchTo = async section => {
        Array.from(document.querySelectorAll('[aria-label="بخش پیش‌نمایش"] button')).find(button => button.textContent === section).click(); await nextTick();
    };
    const inputFor = (label, tag = 'input') => Array.from(document.querySelectorAll('aside label')).find(node => node.textContent.trim().startsWith(label)).querySelector(tag);
    await test('letter preview shows only letter numbering', async () => {
        assert(counter('page_number')?.textContent === '۱/۵', 'Letter format changed');
        assert(!counter('appendix_page_number'), 'Appendix counter leaked into letter');
    });
    await test('appendix preview uses independent format and hides letter-only elements', async () => {
        await switchTo('پیوست');
        assert(counter('appendix_page_number')?.textContent === 'پیوست ـ ص۱ از ۳', 'Appendix sample format incorrect');
        assert(!counter('page_number'), 'Letter counter leaked into appendix');
        const canvas = document.querySelector('[data-preview-section]');
        assert(!canvas.textContent.includes('مشخصات مخصوص نامه') && !canvas.textContent.includes('امضای مخصوص نامه'), 'Letter metadata/issuer leaked into appendix');
        assert(canvas.textContent.includes('سربرگ مشترک'), 'Repeating header missing');
    });
    let expectedTop;
    await test('appendix format, position and font edits persist when switching sections', async () => {
        const title = inputFor('عنوان بالای پیوست'); title.value = 'مدارک همراه'; title.dispatchEvent(new Event('input', { bubbles: true }));
        const prefix = inputFor('عبارت قبل از شماره صفحه'); prefix.value = 'ضمیمه'; prefix.dispatchEvent(new Event('input', { bubbles: true }));
        const format = inputFor('قالب شماره صفحه', 'select'); format.value = 'page'; format.dispatchEvent(new Event('change', { bubbles: true }));
        const y = inputFor('موقعیت از بالای صفحه'); y.value = String(newY); y.dispatchEvent(new Event('change', { bubbles: true }));
        const font = inputFor('اندازه قلم'); font.value = '13'; font.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();
        expectedTop = Math.round(newY / height * 10000) / 100;
        assert(counter('appendix_page_number').style.top === expectedTop + '%', 'Appendix position not applied');
        await switchTo('نامه');
        assert(counter('page_number').textContent === '۱/۵' && counter('page_number').style.top === '92%', 'Letter settings changed with appendix');
        await switchTo('پیوست');
        assert(counter('appendix_page_number').textContent === 'ضمیمه ـ صفحه ۱ از ۳' && document.querySelector('[data-preview-section] h2')?.textContent === 'مدارک همراه' && Number(inputFor('اندازه قلم').value) === 13, 'Appendix settings reset on toggle');
    });
    await test('presentation preview retains appendix section and unsaved edits', async () => {
        Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === 'پیش‌نمایش قالب').click(); await nextTick();
        assert(!document.querySelector('aside.space-y-4'), 'Designer controls remain visible in presentation preview');
        assert(counter('appendix_page_number').style.top === expectedTop + '%', 'Preview discarded appendix configuration');
        await switchTo('نامه'); assert(counter('page_number').textContent === '۱/۵', 'Letter preview unavailable');
        await switchTo('پیوست');
    });
    const designerPage = document.querySelector('[data-preview-section]').getBoundingClientRect();
    const designerTop = (counter('appendix_page_number').getBoundingClientRect().top - designerPage.top) / designerPage.height;
    const configuredAppendix = { ...appendix, page_format: 'page', page_prefix: 'ضمیمه', y: expectedTop, font_size: 13 };
    const printProps = {
        isPreview: true, previewNotice: null, appendixHeading: 'مدارک همراه', templateElements: [letter, configuredAppendix, body], pageLayout, pageSize: size,
        letter: { id: 0, subject: 'نامه', type: 'outgoing', body: '<p>متن نامه</p>', appendix_body: '<div style="height:1200px">متن پیوست بلند</div>', appendix_mode: 'editor', appendix_file_count: 0, attachment_pages: null, letter_number: null, registration_number: null, registered_at: null, sent_at: null, recipient_name: null, recipient_addressing: null, external_organization: null, external_department: null },
        organization: {}, creatorName: null, creatorUnitTitle: null, managerName: null, managerPosition: null, managerTitle: null, creatorDepartmentName: null,
        stampDataUri: null, signatureDataUri: null, letterheadDataUri: null, printOptions: { font_family: 'Vazirmatn', show_creator_identity: false },
    };
    viewProps.value = printProps; view.value = Print;
    await nextTick(); await document.fonts.ready;
    async function waitFor(action) {
        const deadline = Date.now() + 10000;
        while (!action()) { if (Date.now() > deadline) throw new Error('Print layout did not settle'); await new Promise(resolve => setTimeout(resolve, 30)); }
    }
    const appendixPages = size === 'A5' ? 3 : 2;
    await waitFor(() => document.querySelectorAll('.paper').length === appendixPages + 1);
    await test('rendered letter and appendix have independent totals and counter positions', async () => {
        const papers = document.querySelectorAll('.paper');
        assert(papers[0].querySelector('.page-number').textContent === '۱/۱', 'Main counter includes appendix pages');
        assert(!papers[0].querySelector('[data-page-number-role="appendix_page_number"]'), 'Appendix counter on letter');
        for (let i = 1; i < papers.length; i++) {
            const number = papers[i].querySelector('[data-page-number-role="appendix_page_number"]');
            assert(number.textContent === formatPageNumber('page', i, appendixPages, true, 'ضمیمه'), 'Appendix numbering did not restart independently');
            assert(!papers[i].querySelector('[data-page-number-role="page_number"]'), 'Letter counter on appendix');
            const rect = papers[i].getBoundingClientRect();
            const top = (number.getBoundingClientRect().top - rect.top) / rect.height;
            assert(Math.abs(top - designerTop) < 0.002, 'Designer and output counter positions differ');
            assert(getComputedStyle(number).fontFamily.includes('Tahoma'), 'Appendix font changed in output');
        }
    });
    await test('hiding the appendix counter does not hide letter numbering', async () => {
        viewProps.value = { ...printProps, templateElements: [letter, { ...configuredAppendix, visible: false }, body] };
        await nextTick();
        assert(!counter('appendix_page_number') && counter('page_number'), 'Counter visibility is coupled');
    });
    await test('custom heading and removable appendix label reach the output', async () => {
        assert(document.querySelector('.paper .appendix-heading')?.textContent === 'مدارک همراه', 'Custom heading absent');
        viewProps.value = { ...printProps, appendixHeading: '', templateElements: [letter, { ...configuredAppendix, page_prefix: null }, body] };
        await nextTick();
        assert(!document.querySelector('.appendix-heading'), 'Blank heading still rendered');
        assert(counter('appendix_page_number')?.textContent === formatPageNumber('page', 1, appendixPages), 'Blank prefix still rendered');
    });
    await test('all four numbering formats support independent appendix captions', async () => {
        assert(formatPageNumber('number_of', 1, 3, true) === 'پیوست ـ ۱ از ۳', 'number_of format');
        assert(formatPageNumber('slash', 1, 3, true) === 'پیوست ـ ۱/۳', 'slash format');
        assert(formatPageNumber('short', 1, 3, true) === 'پیوست ـ ص۱ از ۳', 'short format');
        assert(formatPageNumber('page', 1, 3, true) === 'پیوست ـ صفحه ۱ از ۳', 'page format');
    });
    return { passed: results.length, size, media: matchMedia('print').matches ? 'print' : 'screen', results };
};