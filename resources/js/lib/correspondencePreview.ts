import axios from 'axios';

// Open synchronously while the user click still permits popups, then obtain
// the current session token independently of cached/history Inertia page props.
export async function openCorrespondencePreview(tokenUrl: string, previewUrl: string, payload: Record<string, unknown>): Promise<void> {
    const target = 'correspondence-preview-' + Date.now();
    const popup = window.open('', target);
    if (!popup) throw new Error('مرورگر اجازه باز کردن پیش‌نمایش را نداد. پنجره‌های بازشو را برای سامانه فعال کنید.');
    try {
        popup.document.title = 'آماده‌سازی پیش‌نمایش نامه';
        popup.document.body.dir = 'rtl';
        popup.document.body.textContent = 'در حال آماده‌سازی پیش‌نمایش نامه…';
        const response = await axios.get<{ token?: string }>(tokenUrl, {
            headers: { Accept: 'application/json' }, timeout: 10000,
        });
        const token = response.data.token;
        if (typeof token !== 'string' || !token) {
            throw new Error('دریافت اطلاعات امنیتی پیش‌نمایش انجام نشد. اعتبار ورود به سامانه را بررسی کنید؛ اطلاعات فرم شما حفظ شده است.');
        }
        if (popup.closed) throw new Error('پنجره پیش‌نمایش بسته شد. دوباره روی پیش‌نمایش کلیک کنید.');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = previewUrl;
        form.target = target;
        form.hidden = true;
        for (const [name, value] of Object.entries({ _token: token, preview: JSON.stringify(payload) })) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = name; input.value = value;
            form.append(input);
        }
        document.body.append(form);
        // Leave the form connected until the browser has dispatched navigation.
        try { form.submit(); } finally { window.setTimeout(() => form.remove(), 1000); }
    } catch (error) {
        popup.close();
        if (axios.isAxiosError(error)) {
            if (error.response?.status === 401 || error.response?.status === 419) {
                throw new Error('اعتبار ورود به سامانه پایان یافته است؛ اطلاعات فرم را حفظ و دوباره وارد سامانه شوید.');
            }
            throw new Error('آماده‌سازی پیش‌نمایش انجام نشد. اتصال به سامانه را بررسی و دوباره تلاش کنید.');
        }
        throw error;
    }
}