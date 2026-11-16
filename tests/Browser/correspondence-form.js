import { createApp, h, nextTick } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Create from '/resources/js/Pages/Correspondence/Create.vue';
import { openCorrespondencePreview } from '/resources/js/lib/correspondencePreview.ts';

window.editorImageTests = (async () => {
    const results = [];
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    const test = async (name, action) => { await action(); results.push(name); };
    const state = () => fetch('/__preview-test/state').then(response => response.json());
    const originalOpen = window.open.bind(window);
    const popups = [];
    window.open = (...args) => { const popup = originalOpen(...args); popups.push(popup); return popup; };
    async function waitFor(action) {
        const deadline = Date.now() + 15000;
        while (!await action()) { if (Date.now() > deadline) throw new Error('Browser assertion timed out'); await new Promise(resolve => setTimeout(resolve, 25)); }
    }
    try {
        await test('native preview POST navigates the opened tab using a fresh token', async () => {
            await openCorrespondencePreview('/__preview-test/token', '/__preview-test/preview', { subject: 'first unsaved letter' });
            await waitFor(async () => (await state()).posts.length === 1);
            await waitFor(() => popups[0].document.querySelector('#preview-result'));
            assert((await state()).posts[0].subject === 'first unsaved letter', 'Unsaved payload lost');
        });
        await test('second preview obtains a new token instead of reading page props', async () => {
            await openCorrespondencePreview('/__preview-test/token', '/__preview-test/preview', { subject: 'second letter' });
            await waitFor(async () => (await state()).posts.length === 2);
            assert((await state()).tokens === 2, 'Token was cached');
        });
        await test('missing token and blocked popups have explicit failures', async () => {
            let failed = false;
            try { await openCorrespondencePreview('/__preview-test/missing-token', '/__preview-test/preview', {}); } catch { failed = true; }
            assert(failed && popups[popups.length - 1].closed, 'Missing-token popup was left blank');
            const open = window.open; window.open = () => null; failed = false;
            try { await openCorrespondencePreview('/__preview-test/token', '/__preview-test/preview', {}); } catch { failed = true; } finally { window.open = open; }
            assert(failed, 'Blocked popup was not reported');
        });
        window.route = (name, params) => {
            if (name === 'correspondence.body-templates.show') return '/__preview-test/body-templates/' + params.template;
            if (name?.startsWith('correspondence.body-templates.')) return '/__preview-test/body-templates';
            if (!name) return { current: () => false };
            return ({ 'correspondence.preview': '/__preview-test/preview', 'notifications.summary': '/__preview-test/notifications' })[name] || '/__unused/' + name;
        };
        const props = {
            auth: { user: { id: 1, name: 'آزمایش', email: 'test@example.com', profile: null }, abilities: {} },
            types: [{ value: 'outgoing', label: 'صادره' }], classifications: [{ value: 'ordinary', label: 'عادی' }],
            departments: [], createdDepartment: null, createdDepartments: [],
            externalOrganizations: [{ id: 1, name: 'سازمان آزمون' }],
            externalDepartments: [{ id: 1, external_organization_id: 1, parent_id: null, name: 'واحد بدون مدیر', manager_contact_id: null }, { id: 2, external_organization_id: 1, parent_id: null, name: 'واحد دارای مدیر', manager_contact_id: '10' }],
            externalContacts: [{ id: 10, external_organization_id: 1, external_department_id: 2, name: 'مدیر آزمون' }],
            correspondenceFontFamily: 'Vazirmatn', approvalSettings: {}, isEditing: true,
            correspondence: { id: 77, type: 'outgoing', classification: 'ordinary', subject: 'نامه داخل فرم', body: '<p>متن فعلی نامه</p>',
                external_organization_id: 1, external_department_id: 1, external_contact_id: null,
                related_letters: [], copy_department_ids: [], original_documents: [], appendix_documents: [], appendix_mode: 'none', appendix_body: '' },
        };
        // Deliberately omit csrfToken: reproduce a cached form without that prop.
        await createInertiaApp({
            page: { component: 'Correspondence/Create', props, url: location.pathname, version: 'test', clearHistory: false, encryptHistory: false },
            resolve: () => Create,
            setup({ el, App, props, plugin }) { const app = createApp({ render: () => h(App, props) }).use(plugin); app.config.globalProperties.route = window.route; app.mount(el); },
        });
        await nextTick();
        await test('actual form warns when the selected external department has no manager', async () => {
            assert(document.querySelector('[role=status]')?.textContent.includes('مدیر تعیین نشده'), 'Missing-manager notice was not rendered');
        });
        await test('actual form recognizes a valid string-valued SQL Server manager id', async () => {
            document.querySelector('#external_department_id button').click();
            await nextTick();
            Array.from(document.querySelectorAll('[role=treeitem] button')).find(button => button.textContent.includes('واحد دارای مدیر')).click();
            await nextTick();
            await waitFor(() => !document.body.textContent.includes('مدیر تعیین نشده'));
            assert(!document.body.textContent.includes('مدیر تعیین نشده'), 'Valid manager still reported missing');
            assert(document.querySelector('#external_contact_id').textContent.includes('مدیر آزمون'), 'Manager was not selected automatically');
        });
        await test('actual form preview opens without a csrfToken page prop and sends unsaved content', async () => {
            const button = Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === 'پیش‌نمایش نامه');
            assert(button, 'Preview button missing'); button.click();
            await waitFor(async () => (await state()).posts.length === 3);
            assert((await state()).posts[2].subject === 'نامه داخل فرم', 'Form content not sent');
            assert(!document.querySelector('[role=alert]'), 'Form displayed an unexpected preview error');
        });
        await test('actual form preserves content after preview failure and permits a successful retry', async () => {
            const currentRoute = window.route;
            window.route = (name, ...args) => name === 'correspondence.preview' ? '/__preview-test/missing-preview' : currentRoute(name, ...args);
            const button = Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === 'پیش‌نمایش نامه');
            button.click();
            await waitFor(() => document.querySelector('[role=alert]'));
            assert(document.querySelector('#subject').value === 'نامه داخل فرم', 'Preview failure cleared unsaved input');
            window.route = currentRoute;
            button.click();
            await waitFor(async () => (await state()).posts.length === 4);
            assert(!document.querySelector('[role=alert]'), 'Retry did not clear the prior error');
        });
        await test('letter editor saves and reapplies a named rich-text template', async () => {
            const button = label => [...document.querySelectorAll('button')].find(item => item.textContent.trim() === label);
            button('قالب‌های متن نامه').click();
            const panel = await (async () => { await nextTick(); return document.querySelector('#letter-body-templates'); })();
            await waitFor(() => panel.getAttribute('aria-busy') === 'false');
            const title = panel.querySelector('input');
            title.value = 'قالب پیگیری';
            title.dispatchEvent(new Event('input', { bubbles: true }));
            await nextTick();
            button('ذخیره متن فعلی به‌عنوان قالب').click();
            await waitFor(() => panel.textContent.includes('قالب متن ذخیره شد.'));
            const editor = document.querySelector('.rte-content');
            editor.innerHTML = '<p>متن جایگزین آزمایشی</p>';
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            const confirm = window.confirm;
            window.confirm = () => true;
            try {
                button('استفاده از قالب').click();
                await waitFor(() => editor.textContent.includes('متن فعلی نامه'));
            } finally {
                window.confirm = confirm;
            }
        });
        return { passed: results.length, results };
    } finally {
        window.open = originalOpen;
        popups.forEach(popup => { if (popup && !popup.closed) popup.close(); });
    }
})();
window.editorImageTests.then(result => { document.querySelector('#results').textContent = JSON.stringify(result); }, error => { document.querySelector('#results').textContent = error.stack; });