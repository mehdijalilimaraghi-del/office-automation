import '/resources/css/app.css';
import { createApp, h, nextTick, shallowRef } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Dashboard from '/resources/js/Pages/Dashboard.vue';
import ExternalParties from '/resources/js/Pages/Core/ExternalParties/Index.vue';
import Departments from '/resources/js/Pages/Core/Departments/Index.vue';

window.editorImageTests = (async () => {
    const results = [];
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    const text = () => document.body.textContent;
    const click = async (label, root = document) => {
        const button = [...root.querySelectorAll('button')].find(item => item.textContent.trim().startsWith(label) || item.textContent.trim().endsWith(label));
        assert(button, 'Button missing: ' + label);
        button.click();
        await nextTick();
    };
    const view = shallowRef(Dashboard);
    const trend = Array.from({ length: 366 }, (_, index) => {
        const date = new Date(Date.UTC(2026, 10, 16) - (365 - index) * 86400000);
        return { date: date.toISOString().slice(0, 10), created: 1, completed: 0 };
    });
    const viewProps = shallowRef({
        trend, stats: {}, personalTasks: [], teamTasks: [], nextActivities: [], upcomingReminders: [],
        recentComments: [], projectsOverview: [], sectionsOverview: [], canViewTeam: false,
    });
    window.route = name => !name ? { current: () => false } : name === 'notifications.summary' ? '/__preview-test/notifications' : '/unused/' + name;
    await createInertiaApp({
        page: { component: 'TestSurface', props: { errors: {}, auth: { user: { id: 1, name: 'آزمایش', email: 'test@example.com' }, abilities: { departments_create: true } } }, url: location.pathname, version: 'test', clearHistory: false, encryptHistory: false },
        resolve: () => ({ setup: () => () => h(view.value, viewProps.value) }),
        setup({ el, App, props, plugin }) {
            const app = createApp({ render: () => h(App, props) }).use(plugin);
            app.config.globalProperties.route = window.route;
            app.mount(el);
        },
    });
    await new Promise(resolve => setTimeout(resolve, 500));
    const period = document.querySelector('select');
    assert(period.value === 'week' && text().includes('۸/۲۵'), 'Dashboard did not default to seven Jalali days');
    for (const [value, label] of [['month', '۲۲ تا ۲۵'], ['season', 'مهر'], ['year', 'فروردین']]) {
        const currentPeriod = document.querySelector('select');
        currentPeriod.value = value;
        currentPeriod.dispatchEvent(new Event('change', { bubbles: true }));
        const chartText = () => document.querySelector('select').closest('section').textContent;
        const deadline = Date.now() + 3000;
        while (!chartText().includes(label) && Date.now() < deadline) {
            await new Promise(resolve => setTimeout(resolve, 20));
        }
        assert(chartText().includes(label), 'Missing Jalali bucket for ' + value + ': ' + chartText());
    }
    assert(text().includes('۳۱/۰'), 'Monthly totals failed to aggregate');
    results.push('weekly default and Jalali month, season and year render with correct totals');

    const organization = (id, name) => ({ id, name, status: 'active', departments_count: 0, contacts_count: 0 });
    const orgs = [organization(1, 'سازمان الف'), organization(2, 'سازمان ب')];
    viewProps.value = {
        organizations: orgs,
        departments: [
            { id: 11, external_organization_id: 1, parent_id: null, name: 'واحد ریشه الف', organization: orgs[0] },
            { id: 12, external_organization_id: 1, parent_id: 11, name: 'واحد فرزند الف', organization: orgs[0] },
            { id: 21, external_organization_id: 2, parent_id: null, name: 'واحد ریشه ب', organization: orgs[1] },
        ],
        contacts: [
            { id: 1, external_organization_id: 1, external_department_id: 12, name: 'مخاطب فرزند' },
            { id: 2, external_organization_id: 1, external_department_id: null, name: 'مخاطب مستقیم' },
            { id: 3, external_organization_id: 2, external_department_id: 21, name: 'مخاطب بیرونی دیگر' },
        ],
        customFields: { organization: [], department: [], contact: [] },
    };
    view.value = ExternalParties;
    await nextTick();
    await click('ساختار واحدها');
    assert(text().includes('واحد ریشه الف') && text().includes('└─') && !text().includes('واحد ریشه ب'), 'Unit tree did not isolate the selected organization');
    await click('افراد');
    assert(text().includes('مخاطب فرزند') && text().includes('مخاطب مستقیم') && !text().includes('مخاطب بیرونی دیگر'), 'Contacts did not isolate or group by organization');
    results.push('external organization tree and contact groups respect the selected organization');

    viewProps.value = {
        departments: [], users: [],
        customFields: { position: [], department: Array.from({ length: 24 }, (_, index) => ({ id: index + 1, key: 'field' + index, label: 'فیلد ' + index, type: 'text', is_required: false, options: null })) },
    };
    view.value = Departments;
    await nextTick();
    await click('واحد جدید');
    const dialog = document.querySelector('[aria-labelledby="department-form-title"] form');
    assert(dialog && dialog.scrollHeight > dialog.clientHeight, 'Internal unit form did not scroll');
    dialog.scrollTop = dialog.scrollHeight;
    assert(dialog.scrollTop > 0 && dialog.getBoundingClientRect().bottom <= innerHeight, 'Unit form exceeded the viewport');
    results.push('internal unit form remains within the viewport with long custom fields');
    return { passed: results.length, results };
})();
