// Keep generated data URLs below the server sanitizer's 300,000-character limit.
export const EDITOR_IMAGE_LENGTH = 200_000;
export const EDITOR_BODY_LENGTH = 480_000;
const supportedImage = /^image\/(png|jpeg|webp|gif|bmp)$/i;

export async function optimizeEditorImage(blob: Blob, maxLength = EDITOR_IMAGE_LENGTH): Promise<string> {
    const url = URL.createObjectURL(blob);
    const image = new Image();
    try {
        await new Promise<void>((resolve, reject) => {
            image.onload = () => resolve();
            image.onerror = () => reject(new Error('تصویر قابل خواندن نیست. لطفاً نسخه PNG یا JPEG آن را انتخاب کنید.'));
            image.src = url;
        });
        if (/^image\/(png|jpeg|webp)$/i.test(blob.type)
            && Math.max(image.naturalWidth, image.naturalHeight) <= 1600
            && Math.ceil(blob.size / 3) * 4 + 50 <= maxLength) {
            return await new Promise<string>((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(String(reader.result));
                reader.onerror = () => reject(new Error('خواندن تصویر انجام نشد.'));
                reader.readAsDataURL(blob);
            });
        }
        const scale = Math.min(1, 1600 / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const context = canvas.getContext('2d');
        if (!context) throw new Error('مرورگر امکان آماده‌سازی تصویر را ندارد.');
        for (;;) {
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            for (const quality of [0.9, 0.8, 0.65, 0.5]) {
                const result = canvas.toDataURL('image/webp', quality);
                if (result.length <= maxLength) return result;
            }
            if (canvas.width === 1 && canvas.height === 1) {
                throw new Error('فضای متن نامه برای افزودن تصویر کافی نیست؛ بخشی از محتوا را به پیوست منتقل کنید.');
            }
            canvas.width = Math.max(1, Math.floor(canvas.width * 0.8));
            canvas.height = Math.max(1, Math.floor(canvas.height * 0.8));
        }
    } finally {
        URL.revokeObjectURL(url);
    }
}

// Word commonly supplies local file: references in HTML and the actual bitmap in RTF.
export function imagesFromRtf(rtf: string): Blob[] {
    const images: Blob[] = [];
    const starts = rtf.matchAll(/\{\\pict\b/g);
    for (const match of starts) {
        const start = match.index;
        let depth = 1;
        let end = start + 1;
        for (; end < rtf.length && depth; end++) {
            if (rtf[end] === '\\') { end++; continue; }
            if (rtf[end] === '{') depth++;
            if (rtf[end] === '}') depth--;
        }
        if (depth) continue;
        let group = rtf.slice(start + 1, end - 1);
        const mime = /\\pngblip\b/.test(group) ? 'image/png' : /\\jpegblip\b/.test(group) ? 'image/jpeg' : null;
        if (!mime || /\\bin\d/.test(group)) continue;
        // Drop nested metadata groups (blipuid, etc.) before decoding hex bytes.
        while (/\{[^{}]*\}/.test(group)) group = group.replace(/\{[^{}]*\}/g, ' ');
        const hex = group.replace(/\\[a-z]+-?\d*\s?/gi, '').replace(/\s/g, '');
        if (!/^(?:[a-f\d]{2})+$/i.test(hex)) continue;
        const bytes = Uint8Array.from(hex.match(/../g)!, byte => Number.parseInt(byte, 16));
        images.push(new Blob([bytes], { type: mime }));
    }
    return images;
}

function cleanClipboardHtml(html: string): HTMLBodyElement {
    const body = new DOMParser().parseFromString(html, 'text/html').body;
    body.querySelectorAll('script,style,iframe,object,embed,svg,math,link,meta').forEach(node => node.remove());
    body.querySelectorAll('*').forEach(node => {
        if (node.tagName.toLowerCase() === 'v:imagedata') {
            const image = document.createElement('img');
            image.setAttribute('src', node.getAttribute('src') || '');
            node.replaceWith(image);
        }
    });
    const allowed = new Set('p div br strong b em i u s strike sup sub ul ol li h1 h2 h3 blockquote table thead tbody tr th td span hr img a'.split(' '));
    body.querySelectorAll('*').forEach(node => {
        if (!allowed.has(node.tagName.toLowerCase())) { node.replaceWith(...node.childNodes); return; }
        for (const attribute of Array.from(node.attributes)) {
            const name = attribute.name.toLowerCase();
            if (!['src', 'alt', 'width', 'height', 'style', 'colspan', 'rowspan', 'dir', 'href'].includes(name)
                || (name === 'src' && node.tagName !== 'IMG')
                || (name === 'href' && (node.tagName !== 'A' || !/^(https?:|mailto:)/i.test(attribute.value)))
                || (name === 'style' && /url\s*\(|expression\s*\(|@import|behavior|-moz-binding/i.test(attribute.value))) {
                node.removeAttribute(attribute.name);
            }
        }
    });
    return body as HTMLBodyElement;
}

export interface ClipboardSnapshot { html: string; text: string; rtf: string; files: File[] }
export function snapshotClipboard(data: DataTransfer): ClipboardSnapshot {
    return {
        html: data.getData('text/html'), text: data.getData('text/plain'), rtf: data.getData('text/rtf'),
        files: Array.from(data.files).filter(file => supportedImage.test(file.type)),
    };
}

export async function prepareClipboardContent(data: ClipboardSnapshot, availableLength: number): Promise<{ html: string; missingImages: number }> {
    const body = cleanClipboardHtml(data.html);
    if (!data.html) body.textContent = data.text;
    if (!data.html) body.style.whiteSpace = 'pre-wrap';
    const nodes = Array.from(body.querySelectorAll('img'));
    const rtfImages = imagesFromRtf(data.rtf);
    const fallbacks: Blob[] = rtfImages.length ? rtfImages : data.files;
    // When HTML is present its image nodes own placement; files are alternate representations.
    if (!nodes.length) {
        for (const blob of fallbacks) {
            const node = document.createElement('img');
            body.append(node);
            nodes.push(node);
        }
    }
    if (!nodes.length && /\\pict\b/.test(data.rtf)) {
        const node = document.createElement('img');
        body.append(node);
        nodes.push(node);
    }
    const placeholders = nodes.filter(node => !/^(data:|blob:|https?:)/i.test(node.getAttribute('src') || ''));
    let missingImages = 0;
    // Exclude source URLs when calculating the remaining persisted-HTML budget.
    const budgetBody = body.cloneNode(true) as HTMLElement;
    budgetBody.querySelectorAll('img').forEach(node => node.removeAttribute('src'));
    const budget = Math.min(EDITOR_IMAGE_LENGTH, Math.floor((availableLength - budgetBody.innerHTML.length - nodes.length * 120) / Math.max(1, nodes.length)));
    if (nodes.length && budget < 200) throw new Error('فضای متن نامه برای تصاویر کافی نیست؛ بخشی از محتوا را به پیوست منتقل کنید.');
    for (let index = 0; index < nodes.length; index++) {
        const node = nodes[index];
        const source = node.getAttribute('src') || '';
        const fallback = fallbacks.length === nodes.length ? fallbacks[index]
            : fallbacks.length === placeholders.length ? fallbacks[placeholders.indexOf(node)] : undefined;
        let blob: Blob | undefined;
        try {
            if (/^(data:image\/(?:png|jpeg|webp|gif|bmp);base64,|blob:|https?:)/i.test(source)) {
                try {
                    const response = await fetch(source, { credentials: 'omit', signal: AbortSignal.timeout(10000) });
                    if (!response.ok) throw new Error('Image unavailable');
                    blob = await response.blob();
                    if (!supportedImage.test(blob.type)) throw new Error('Unsupported image');
                } catch (error) {
                    if (!fallback) throw error;
                    blob = fallback;
                }
            } else {
                // Never assign a single bitmap to several different Word placeholders.
                blob = fallback;
            }
            if (!blob) throw new Error('Missing clipboard image');
            node.src = await optimizeEditorImage(blob, budget);
            node.removeAttribute('srcset');
            node.style.maxWidth = '100%';
            if (!node.style.height) node.style.height = 'auto';
        } catch {
            node.replaceWith(document.createTextNode('[تصویر منتقل نشد]'));
            missingImages++;
        }
    }
    // Plain text line breaks must survive HTML insertion without losing other markup.
    if (!data.html) {
        for (const node of Array.from(body.childNodes)) {
            if (node.nodeType !== Node.TEXT_NODE) continue;
            const fragment = document.createDocumentFragment();
            (node.textContent || '').split(/\r?\n/).forEach((line, index) => {
                if (index) fragment.append(document.createElement('br'));
                fragment.append(document.createTextNode(line));
            });
            node.replaceWith(fragment);
        }
    }
    if (body.innerHTML.length > availableLength) throw new Error('حجم کل متن نامه بیش از ظرفیت ذخیره‌سازی است.');
    return { html: body.innerHTML, missingImages };
}