export function correspondenceFixtures() {
    let tokens = 0;
    const posts = [];
    const templates = [];
    return {
        name: 'correspondence-preview-fixtures',
        configureServer(server) {
            server.middlewares.use('/__preview-test', async (request, response, next) => {
                const path = request.url.split('?')[0];
                if ((path === '/token' || path === '/preview-token')) {
                    response.setHeader('Content-Type', 'application/json');
                    response.setHeader('Cache-Control', 'no-store, private');
                    response.end(JSON.stringify({ token: 'current-token-' + ++tokens }));
                } else if ((path === '/missing-token' || path === '/missing-preview-token')) {
                    response.setHeader('Content-Type', 'application/json'); response.end('{}');
                } else if (path === '/preview' && request.method === 'POST') {
                    let body = ''; for await (const chunk of request) body += chunk;
                    const values = new URLSearchParams(body);
                    if (values.get('_token') !== 'current-token-' + tokens) { response.statusCode = 419; response.end('stale token'); return; }
                    posts.push(JSON.parse(values.get('preview')));
                    response.setHeader('Content-Type', 'text/html; charset=utf-8');
                    response.end('<!doctype html><html><body><h1 id="preview-result">Preview ready</h1></body></html>');
                } else if (path === '/body-templates') {
                    response.setHeader('Content-Type', 'application/json');
                    if (request.method === 'POST') {
                        let body = '';
                        for await (const chunk of request) body += chunk;
                        const { title, body: html } = JSON.parse(body);
                        const id = templates.length + 1;
                        templates.push({ id, title, body: html });
                        response.statusCode = 201;
                        response.end(JSON.stringify({ id, title }));
                    } else {
                        response.end(JSON.stringify(templates.map(({ id, title }) => ({ id, title }))));
                    }
                } else if (/^\/body-templates\/\d+$/.test(path)) {
                    response.setHeader('Content-Type', 'application/json');
                    response.end(JSON.stringify(templates.find(item => item.id === Number(path.split('/').at(-1)))));
                } else if (path === '/state') {
                    response.setHeader('Content-Type', 'application/json'); response.end(JSON.stringify({ tokens, posts }));
                } else if (path === '/notifications') {
                    response.setHeader('Content-Type', 'application/json'); response.end('{"unreadCount":0,"notifications":[]}');
                } else next();
            });
        },
    };
}