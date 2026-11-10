// Run: node tests/Browser/panel-navigation.cjs <path-to-turbo-8.0.13.js> <path-to-chrome>
// Uses a local fixture server; no restaurant account, database or production writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const http = require('node:http');
const {spawn} = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const turbo = fs.readFileSync(process.argv[2], 'utf8');
const chromePath = process.argv[3];
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'panel-navigation-'));

function fixture(name) {
    return `<!DOCTYPE html><html><head><meta name="turbo-cache-control" content="no-cache">
      <script src="/panel-page.js"></script><script src="/turbo.js"></script><script>Turbo.session.drive = false;</script>
      <script src="/panel-navigation.js"></script><script src="/turbo-internal-links.js" defer></script></head><body data-screen="${name}">
      <audio id="myAudio" src="/test.wav" data-turbo-permanent></audio>
      <script src="/jquery.js" data-turbo-eval="false"></script>
      <script src="/vendor.js" data-turbo-eval="false"></script>
      <script src="/shared-library.js"></script>
      <script src="/js/view-pages/controls.js"></script>
      <button id="action" onclick="inlineAction()">Action</button>
      <a id="next" href="/${name === 'pos' ? 'orders' : 'pos'}">Next</a>
      <form id="search" method="get" action="/settings"><input name="query" value="test"></form>
      <div class="hs-unfold"><button id="menu-toggle" data-hs-unfold-options='{"target":"#menu"}'>Menu</button>
      <div id="menu" class="hs-unfold-content">Menu content</div></div>
      ${name === 'zones' ? '<script src="https://maps.googleapis.com/maps/api/js?libraries=places"></script>' : ''}
      <script>(() => {
        const page = PanelPage.create('fixture');
        const state = '${name}';
        if (state === 'zones' && !window.google) throw new Error('Maps dependency missing');
        page.globals({inlineAction: {get: () => inlineAction}, initMap: {get: () => initMap}});
        function inlineAction() {
          window.clicks = (window.clicks || 0) + 1;
          savedContext.resume();
          savedAudio.play().then(() => { window.audioAllowed = true; }).catch(() => { window.audioAllowed = false; });
        }
        function initMap() { window.mapScreens ||= []; window.mapScreens.push(state); }
        if (!window.savedAudio) window.savedAudio = document.getElementById('myAudio');
        if (!window.savedContext) window.savedContext = new AudioContext();
        page.ready(() => {
          if (!window.fixtureLibrary || window.controlsBody !== document.body) throw new Error('Script ordering');
          new HSUnfold($('#menu-toggle')).init();
          $(window).on('resize', () => { window.pluginResizes = (window.pluginResizes || 0) + 1; });
          window.addEventListener('resize', () => { window.nativeResizes = (window.nativeResizes || 0) + 1; });
          document.body.dataset.initialized = state;
        });
        page.listen(document, 'DOMContentLoaded', () => { window.readyCount = (window.readyCount || 0) + 1; });
        page.on('click', '#action', () => { window.delegated = (window.delegated || 0) + 1; });
        page.setInterval(() => { window.ticks ||= {}; window.ticks[state] = (window.ticks[state] || 0) + 1; }, 20);
      })();</script>
      ${['plain', 'zones'].includes(name) ? '' : '<script src="https://maps.googleapis.com/maps/api/js?callback=initMap"></script>'}
      </body></html>`;
}

const server = http.createServer((req, res) => {
    const routes = {
        '/turbo.js': turbo,
        '/panel-page.js': fs.readFileSync(path.join(root, 'public/assets/admin/js/panel-page.js')),
        '/panel-navigation.js': fs.readFileSync(path.join(root, 'public/assets/admin/js/panel-navigation.js')),
        '/turbo-internal-links.js': fs.readFileSync(path.join(root, 'public/assets/admin/js/turbo-internal-links.js')),
        '/jquery.js': fs.readFileSync(path.join(root, 'public/assets/admin/js/jquery.min.js')),
        '/vendor.js': fs.readFileSync(path.join(root, 'public/assets/admin/js/vendor.min.js')),
        '/shared-library.js': 'window.fixtureLibrary = true; window.libraryLoads = (window.libraryLoads || 0) + 1;',
        '/js/view-pages/controls.js': 'window.controlsBody = document.body; window.controlsLoads = (window.controlsLoads || 0) + 1;',
    };
    if (req.url === '/test.wav') {
        const wav = Buffer.alloc(44 + 800);
        wav.write('RIFF'); wav.writeUInt32LE(wav.length - 8, 4); wav.write('WAVEfmt ', 8);
        wav.writeUInt32LE(16, 16); wav.writeUInt16LE(1, 20); wav.writeUInt16LE(1, 22);
        wav.writeUInt32LE(8000, 24); wav.writeUInt32LE(8000, 28); wav.writeUInt16LE(1, 32); wav.writeUInt16LE(8, 34);
        wav.write('data', 36); wav.writeUInt32LE(800, 40); wav.fill(128, 44);
        res.setHeader('Content-Type', 'audio/wav'); res.end(wav);
    } else if (routes[req.url]) {
        res.setHeader('Content-Type', 'text/javascript');
        setTimeout(() => res.end(routes[req.url]), req.url.includes('controls') ? 50 : 0);
    } else {
        res.setHeader('Content-Type', 'text/html');
        res.end(fixture(new URL(req.url, 'http://local').pathname.slice(1) || 'pos'));
    }
});

async function main() {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const address = `http://127.0.0.1:${server.address().port}`;
    const chrome = spawn(chromePath, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], {windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
    let socket;
    try {
        const endpoint = await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('Chrome startup timed out')), 15000);
            chrome.stderr.on('data', data => {
                const match = data.toString().match(/DevTools listening on (ws:\/\/[^\s]+)/);
                if (match) { clearTimeout(timer); resolve(match[1]); }
            });
            chrome.on('error', reject);
        });
        socket = new WebSocket(endpoint);
        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('Chrome debugging connection timed out')), 10000);
            socket.addEventListener('open', () => { clearTimeout(timer); resolve(); }, {once: true});
            socket.addEventListener('error', () => { clearTimeout(timer); reject(new Error('Chrome debugging connection failed')); }, {once: true});
        });
        let id = 0;
        const pending = new Map();
        const errors = [];
        const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
            const requestId = ++id;
            const timer = setTimeout(() => { pending.delete(requestId); reject(new Error('CDP timed out: ' + method)); }, 15000);
            pending.set(requestId, {resolve: value => { clearTimeout(timer); resolve(value); }, reject: error => { clearTimeout(timer); reject(error); }});
            socket.send(JSON.stringify({id: requestId, method, params, ...(sessionId ? {sessionId} : {})}));
        });
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.id) {
                const request = pending.get(message.id);
                pending.delete(message.id);
                if (!request) return;
                if (message.error) request.reject(new Error(message.error.message));
                else request.resolve(message.result);
            } else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails);
            else if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') {
                errors.push(message.params.args.map(arg => arg.description || arg.value).join(' '));
            }
            else if (message.method === 'Fetch.requestPaused') {
                const callback = new URL(message.params.request.url).searchParams.get('callback');
                send('Fetch.fulfillRequest', {requestId: message.params.requestId, responseCode: 200,
                    responseHeaders: [{name: 'Content-Type', value: 'text/javascript'}],
                    body: Buffer.from('window.google = {maps: {importLibrary: () => Promise.resolve()}}; window.mapLoads = (window.mapLoads || 0) + 1;' + (callback ? callback + '();' : '')).toString('base64')}, message.sessionId).catch(error => errors.push(error.message));
            }
        });
        const target = await send('Target.createTarget', {url: 'about:blank'});
        const {sessionId} = await send('Target.attachToTarget', {targetId: target.targetId, flatten: true});
        const command = (method, params) => send(method, params, sessionId);
        await command('Runtime.enable');
        await command('Page.enable');
        await command('Fetch.enable', {patterns: [{urlPattern: 'https://maps.googleapis.com/*'}]});
        const evaluate = async expression => {
            const response = await command('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
            if (response.exceptionDetails) throw new Error(JSON.stringify(response.exceptionDetails));
            return response.result.value;
        };
        const waitFor = async expression => {
            const deadline = Date.now() + 12000;
            while (!await evaluate(expression)) {
                if (Date.now() > deadline) throw new Error('Timed out: ' + expression + '\n' + JSON.stringify(errors));
                await new Promise(resolve => setTimeout(resolve, 25));
            }
        };
        await command('Page.navigate', {url: address + '/pos'});
        await waitFor('document.body?.dataset.initialized === "pos" && window.mapScreens?.length === 1');
        await evaluate('window.savedDocument = document; window.savedContextIdentity = savedContext');
        const point = await evaluate('(() => {const r = document.getElementById("action").getBoundingClientRect(); return {x: r.x + r.width / 2, y: r.y + r.height / 2}})()');
        await command('Input.dispatchMouseEvent', {...point, type: 'mousePressed', button: 'left', clickCount: 1});
        await command('Input.dispatchMouseEvent', {...point, type: 'mouseReleased', button: 'left', clickCount: 1});
        await waitFor('window.audioAllowed === true');
        for (const screen of ['orders', 'kitchen', 'settings', 'pos', 'orders', 'pos']) {
            const previous = await evaluate('document.body.dataset.screen');
            await evaluate(`document.getElementById('next').href = ${JSON.stringify(address + '/' + screen)}; document.getElementById('next').click()`);
            await waitFor(`document.body?.dataset.initialized === ${JSON.stringify(screen)}`);
            await new Promise(resolve => setTimeout(resolve, 80));
            assert.equal(await evaluate('savedDocument === document'), true, 'Turbo retained the document');
            assert.equal(await evaluate('savedAudio === document.getElementById("myAudio")'), true, 'audio element retained');
            assert.equal(await evaluate('savedContext === savedContextIdentity'), true, 'audio context retained');
            assert.equal(await evaluate('libraryLoads'), 1, 'shared library loaded once');
            assert.equal(await evaluate('mapLoads'), 1, 'maps API loaded once');
            const clicks = await evaluate('window.clicks || 0');
            const delegated = await evaluate('window.delegated || 0');
            await evaluate('document.getElementById("action").click()');
            assert.equal(await evaluate('clicks'), clicks + 1, 'inline handler works');
            assert.equal(await evaluate('delegated'), delegated + 1, 'one delegated handler per visit');
            const resizes = await evaluate('window.pluginResizes || 0');
            const nativeResizes = await evaluate('window.nativeResizes || 0');
            await evaluate('window.dispatchEvent(new Event("resize"))');
            assert.equal(await evaluate('pluginResizes'), resizes + 1, 'legacy plugin window handler is cleaned');
            assert.equal(await evaluate('nativeResizes'), nativeResizes + 1, 'legacy native window handler is cleaned');
            await waitFor('window.audioAllowed === true');
            const oldTicks = await evaluate(`window.ticks[${JSON.stringify(previous)}] || 0`);
            await new Promise(resolve => setTimeout(resolve, 65));
            assert.equal(await evaluate(`window.ticks[${JSON.stringify(previous)}] || 0`), oldTicks, 'old page timer stopped');
        }
        assert.equal(await evaluate('readyCount'), 7, 'DOMContentLoaded-compatible callback runs on every visit');
        assert.equal(await evaluate('controlsLoads'), 7, 'page control script runs after each render');
        assert.deepEqual(await evaluate('mapScreens'), ['pos', 'orders', 'kitchen', 'settings', 'pos', 'orders', 'pos']);
        await evaluate('document.getElementById("search").requestSubmit()');
        await waitFor('document.body?.dataset.initialized === "settings"');
        assert.equal(await evaluate('savedDocument === document'), true, 'GET search form uses Turbo');
        await evaluate('history.back()');
        await waitFor('document.body?.dataset.initialized === "pos"');
        assert.equal(await evaluate('savedAudio === document.getElementById("myAudio")'), true, 'history preserves audio');
        assert.equal(await evaluate('readyCount'), 9, 'search and history both initialize their page');
        await command('Page.navigate', {url: address + '/plain'});
        await waitFor('document.body?.dataset.initialized === "plain"');
        await evaluate('Turbo.visit("/zones")');
        await waitFor('document.body?.dataset.initialized === "zones"');
        assert.equal(await evaluate('mapLoads'), 1, 'callback-free Maps dependency loads before page code');
        assert.deepEqual(errors, [], 'no JavaScript exceptions');
        console.log('PASS: nine Turbo visits including links, GET search and history; ordered scripts, single handlers, timer cleanup, inline globals, shared maps and enabled audio.');
    } finally {
        if (socket) socket.close();
        chrome.kill();
        server.close();
    }
}
main().catch(error => { console.error(error); process.exitCode = 1; server.close(); });
