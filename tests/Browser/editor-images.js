import { createApp, h, nextTick, ref } from 'vue';
import Editor from '/resources/js/Components/CorrespondenceRichTextEditor.vue';
import { optimizeEditorImage, prepareClipboardContent, imagesFromRtf } from '/resources/js/lib/editorImages.ts';

window.editorImageTests = (async () => {
    const results = [];
    const assert = (value, message) => { if (!value) throw new Error(message); };
    const test = async (name, action) => { await action(); results.push(name); };
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 700;
    const ctx = canvas.getContext('2d');
    const pixels = ctx.createImageData(700, 700);
    for (let i = 0; i < pixels.data.length; i += 4) {
        pixels.data[i] = (i * 17) % 251;
        pixels.data[i + 1] = (i * 31) % 253;
        pixels.data[i + 2] = (i * 47) % 255;
        pixels.data[i + 3] = (i % 16) ? 255 : 0;
    }
    ctx.putImageData(pixels, 0, 0);
    const png = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
    const file = new File([png], 'large.png', { type: 'image/png' });
    // Use deterministic noise if the browser's PNG encoder compresses the pattern heavily.
    if (file.size <= 350 * 1024) {
        let seed = 1234567;
        for (let i = 0; i < pixels.data.length; i += 4) {
            for (let k = 0; k < 3; k++) { seed = (seed * 1664525 + 1013904223) >>> 0; pixels.data[i + k] = seed >>> 24; }
        }
        ctx.putImageData(pixels, 0, 0);
    }
    ctx.clearRect(0, 0, 50, 50);
    const large = new File([await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))], 'large.png', { type: 'image/png' });
    let source;
    await test('large input is compressed automatically and retains transparency', async () => {
        assert(large.size > 350 * 1024, 'Fixture must exceed old input limit');
        source = await optimizeEditorImage(large);
        assert(source.length <= 200000, 'Image exceeds server-compatible budget');
        const image = new Image(); image.src = source; await image.decode();
        ctx.clearRect(0, 0, 700, 700); ctx.drawImage(image, 0, 0);
        assert(ctx.getImageData(0, 0, 1, 1).data[3] === 0, 'Transparency lost');
    });
    const snapshot = (html = '', files = [], rtf = '', text = '') => ({ html, files, rtf, text });
    await test('mixed HTML retains image order and removes executable markup', async () => {
        const result = await prepareClipboardContent(snapshot('<p>قبل</p><img src="' + source + '" onerror="alert(1)"><p>بعد</p><script>alert(2)</script>', [large]), 480000);
        const body = new DOMParser().parseFromString(result.html, 'text/html').body;
        assert(body.querySelectorAll('img').length === 1, 'Alternate file duplicated');
        assert(body.firstChild.textContent === 'قبل' && body.lastChild.textContent === 'بعد', 'Order changed');
        assert(!body.querySelector('script,[onerror]'), 'Unsafe markup survived');
        assert(result.missingImages === 0, 'Embedded image missing');
    });
    const small = document.createElement('canvas'); small.width = small.height = 12;
    const smallCtx = small.getContext('2d');
    smallCtx.fillStyle = 'red'; smallCtx.fillRect(0, 0, 12, 12);
    const smallBlob = await new Promise(resolve => small.toBlob(resolve, 'image/png'));
    const hex = Array.from(new Uint8Array(await smallBlob.arrayBuffer()), b => b.toString(16).padStart(2, '0')).join('');
    const rtf = '{\\rtf1{\\pict\\pngblip\\picw12\\pich12{\\*\\blipuid abcdef}' + hex + '}}';
    await test('Word RTF bitmap replaces local-file image at its text position', async () => {
        assert(imagesFromRtf(rtf).length === 1, 'RTF bitmap missing');
        const result = await prepareClipboardContent(snapshot('<p>اول</p><img src="file:///C:/Temp/image.png"><p>آخر</p>', [], rtf), 480000);
        assert(result.missingImages === 0 && /src="data:image\//.test(result.html), 'Local Word reference not replaced');
    });
    await test('Word VML images and clipboard bitmap fallback are retained', async () => {
        const result = await prepareClipboardContent(snapshot('<p>متن</p><v:shape><v:imagedata src="file:///image.png"></v:imagedata></v:shape>', [large]), 480000);
        assert(result.missingImages === 0 && /<img/.test(result.html), 'VML image missing');
    });
    await test('multiple images share the total document budget', async () => {
        const result = await prepareClipboardContent(snapshot('<img src="' + source + '"><p>بین</p><img src="' + source + '">'), 90000);
        assert(result.html.length <= 90000 && result.missingImages === 0, 'Document budget exceeded');
        assert((result.html.match(/<img/g) || []).length === 2, 'Image discarded');
    });
    await test('image-only paste and unavailable Word image have explicit outcomes', async () => {
        const imageOnly = await prepareClipboardContent(snapshot('', [large], '', 'اول\nآخر'), 480000);
        assert(/<br>/.test(imageOnly.html) && /<img/.test(imageOnly.html), 'Plain text/image missing');
        const missing = await prepareClipboardContent(snapshot('<p>متن</p><img src="file:///unavailable.png">'), 480000);
        assert(missing.missingImages === 1 && missing.html.includes('تصویر منتقل نشد'), 'Missing image silently removed');
    });
    await test('separate clipboard image survives text-only HTML and stale blob URLs', async () => {
        const separate = await prepareClipboardContent(snapshot('<p>متن</p>', [large]), 480000);
        assert(separate.missingImages === 0 && /<img/.test(separate.html), 'Separate bitmap dropped');
        const fallback = await prepareClipboardContent(snapshot('<p>متن</p><img src="blob:unavailable">', [large]), 480000);
        assert(fallback.missingImages === 0 && /src="data:image\//.test(fallback.html), 'Bitmap fallback not used');
    });
    await test('small images retain their original lossless bytes', async () => {
        const prepared = await optimizeEditorImage(smallBlob);
        assert(prepared === small.toDataURL('image/png'), 'Small image was unnecessarily recompressed');
    });
    await test('unsupported Word-only graphics are reported rather than silently lost', async () => {
        const unsupported = rtf.replace('\\pngblip', '\\wmetafile8');
        const result = await prepareClipboardContent(snapshot('<p>متن</p>', [], unsupported), 480000);
        assert(result.missingImages === 1 && result.html.includes('تصویر منتقل نشد'), 'Unsupported RTF image was silently lost');
    });
    let app;
    async function mount(value = '<p>قبل  بعد</p>') {
        app?.unmount();
        const model = ref(value);
        const processing = ref(false);
        app = createApp({ render: () => h(Editor, { modelValue: model.value, label: 'test', onProcessing: value => { processing.value = value; }, 'onUpdate:modelValue': value => { model.value = value; } }) });
        app.mount('#editor'); await nextTick();
        const editor = document.querySelector('.rte-content'); editor.focus();
        const range = document.createRange(); range.setStart(editor.querySelector('p').firstChild, 4); range.collapse(true);
        window.getSelection().removeAllRanges(); window.getSelection().addRange(range);
        return { editor, model, processing };
    }
    async function waitFor(action) {
        const deadline = Date.now() + 20000;
        while (!action()) { if (Date.now() > deadline) throw new Error('Timed out waiting for editor'); await new Promise(resolve => setTimeout(resolve, 20)); }
    }
    await test('actual Vue editor accepts large file selection', async () => {
        const { editor, model, processing } = await mount();
        Array.from(document.querySelectorAll('[role=tab]')).find(tab => tab.textContent === 'درج').click();
        await nextTick();
        assert(!document.querySelector('#editor').textContent.includes('۳۵۰'), 'Obsolete upload restriction still displayed');
        const input = document.querySelector('input[type=file]'); const data = new DataTransfer(); data.items.add(large);
        input.files = data.files; input.dispatchEvent(new Event('change', { bubbles: true }));
        await waitFor(() => editor.querySelector('img') && editor.getAttribute('aria-busy') === 'false');
        assert(model.value.includes('data:image/') && model.value.length <= 480000, 'Inserted image not emitted');
    });
    await test('actual Vue paste preserves caret position, image and surrounding text', async () => {
        const { editor, model, processing } = await mount();
        const data = new DataTransfer(); data.setData('text/html', '<strong>متن</strong><img src="file:///word.png">'); data.items.add(large);
        const event = new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true });
        editor.dispatchEvent(event);
        assert(event.defaultPrevented, 'Image paste was not intercepted');
        assert(processing.value, 'Form was not notified that images are being prepared');
        await waitFor(() => editor.querySelector('img') && editor.getAttribute('aria-busy') === 'false');
        assert(editor.textContent === 'قبل متن بعد', 'Insertion caret or text order changed: ' + editor.textContent);
        assert(model.value.includes('<strong>متن</strong>'), 'Rich text formatting lost');
        assert(!processing.value && document.activeElement === editor, 'Editor did not resume typing');
        document.querySelector('button[title="واگرد"]').click(); await nextTick();
        assert(!editor.querySelector('img') && editor.textContent === 'قبل  بعد', 'Undo did not restore original content');
        document.querySelector('button[title="ازنو"]').click(); await nextTick();
        assert(editor.querySelector('img') && model.value.includes('<strong>متن</strong>'), 'Redo lost pasted image');
    });
    app?.unmount();
    return { passed: results.length, results };
})();
window.editorImageTests.then(result => { document.querySelector('#results').textContent = JSON.stringify(result); }, error => { document.querySelector('#results').textContent = error.stack; });