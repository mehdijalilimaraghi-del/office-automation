// Standalone browser regression runner; uses existing Vite/Vue dependencies.
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const browserPath = process.env.EDITOR_TEST_BROWSER || (process.platform === 'win32'
    ? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' : 'chromium');
const profile = await mkdtemp(resolve(tmpdir(), 'office-editor-images-'));
let server, browser, socket;
try {
    server = await createServer({ configFile: false, root, plugins: [vue()],
        resolve: { alias: { '@': resolve(root, 'resources/js') } },
        server: { host: '127.0.0.1', port: 5189 }, logLevel: 'error' });
    await server.listen();
    const address = server.httpServer.address();
    const pageUrl = 'http://127.0.0.1:' + address.port + '/tests/Browser/editor-images.html';
    browser = spawn(browserPath, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    const endpoint = await new Promise((resolve, reject) => {
        let output = '';
        const timeout = setTimeout(() => reject(new Error('Browser startup timed out')), 20000);
        browser.once('error', error => { clearTimeout(timeout); reject(error); });
        browser.stderr.on('data', chunk => {
            output += chunk;
            const match = output.match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if (match) { clearTimeout(timeout); resolve(match[1]); }
        });
    });
    const endpointUrl = new URL(endpoint);
    const target = await (await fetch('http://' + endpointUrl.host + '/json/new?' + encodeURIComponent(pageUrl), { method: 'PUT' })).json();
    socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = event => {
        const message = JSON.parse(event.data);
        if (pending.has(message.id)) { const { resolve, reject } = pending.get(message.id); pending.delete(message.id); message.error ? reject(message.error) : resolve(message.result); }
    };
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence; pending.set(id, { resolve, reject }); socket.send(JSON.stringify({ id, method, params }));
    });
    const deadline = Date.now() + 60000;
    for (;;) {
        const ready = await send('Runtime.evaluate', { expression: 'typeof window.editorImageTests !== "undefined"', returnByValue: true });
        if (ready.result.value) break;
        if (Date.now() > deadline) throw new Error('Test module did not initialize');
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    const result = await Promise.race([
        send('Runtime.evaluate', { expression: 'window.editorImageTests', awaitPromise: true, returnByValue: true }),
        new Promise((_, reject) => setTimeout(() => reject(new Error('Browser tests timed out')), 90000).unref()),
    ]);
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    console.log(JSON.stringify(result.result.value, null, 2));
} catch (error) {
    console.error(error); process.exitCode = 1;
} finally {
    socket?.close();
    if (browser && browser.exitCode === null) {
        browser.kill();
        await new Promise(resolve => { browser.once('exit', resolve); setTimeout(resolve, 3000); });
    }
    await server?.close();
    // Only remove the dedicated temporary browser profile created by mkdtemp above.
    if (!profile.startsWith(resolve(tmpdir(), 'office-editor-images-'))) throw new Error('Unsafe browser profile cleanup path');
    await rm(profile, { recursive: true, force: true, maxRetries: 3 }).catch(() => {});
}