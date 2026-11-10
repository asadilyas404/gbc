const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/admin/js/panel-page.js'), 'utf8');

function setup(readyState = 'complete') {
    const document = new EventTarget();
    document.body = {};
    document.readyState = readyState;
    const handlers = new Map();
    const timers = new Map();
    const intervals = new Map();
    let nextId = 0;
    const jq = () => ({
        on(events, ...args) { events.split(' ').forEach(event => handlers.set(event, args.at(-1))); },
        off(namespace) { for (const key of handlers.keys()) if (key.endsWith(namespace)) handlers.delete(key); },
        remove() {},
    });
    jq.fn = {};
    const window = {
        jQuery: jq,
        setTimeout(fn) { timers.set(++nextId, fn); return nextId; },
        clearTimeout(id) { timers.delete(id); },
        setInterval(fn) { intervals.set(++nextId, fn); return nextId; },
        clearInterval(id) { intervals.delete(id); },
    };
    vm.runInNewContext(source, {window, document, console});
    const flush = () => { for (const [id, fn] of timers) { timers.delete(id); fn(); } };
    return {window, document, handlers, timers, intervals, flush, create: window.PanelPage.create};
}

test('ready runs for a Turbo body after DOMContentLoaded and after setup finishes', () => {
    const env = setup();
    let initialized = false;
    env.create('orders').ready(() => { initialized = true; });
    assert.equal(initialized, false);
    env.flush();
    assert.equal(initialized, true);
});

test('repeat visits clean document handlers, timers, native listeners and connections', () => {
    const env = setup();
    let clicks = 0;
    let disconnected = 0;
    let staleWork = 0;
    const page = env.create('orders');
    page.on('click keyup', '.quick-view-btn', () => clicks++);
    page.listen(env.document, 'custom-event', () => staleWork++);
    page.setTimeout(() => staleWork++, 100);
    page.setInterval(() => staleWork++, 100);
    page.cleanup(() => disconnected++);
    env.document.dispatchEvent(new Event('turbo:before-render'));
    env.document.dispatchEvent(new Event('custom-event'));
    env.flush();
    assert.equal(staleWork, 0);
    assert.equal(disconnected, 1);
    assert.equal(env.handlers.size, 0);
    assert.equal(env.intervals.size, 0);
    env.create('orders').on('click', '.quick-view-btn', () => clicks++);
    for (const handler of env.handlers.values()) handler();
    assert.equal(clicks, 1);
});

test('cached or superseded bodies cannot initialize against the next screen', () => {
    const env = setup('loading');
    let runs = 0;
    env.create('pos').ready(() => runs++);
    env.create('pos').ready(() => runs++);
    env.document.dispatchEvent(new Event('DOMContentLoaded'));
    assert.equal(runs, 1);
    env.document.dispatchEvent(new Event('turbo:before-render'));
    env.document.dispatchEvent(new Event('DOMContentLoaded'));
    assert.equal(runs, 1);
});

test('a failed Turbo request leaves the current screen working', () => {
    const env = setup();
    let clicks = 0;
    env.create('orders').on('click', '.quick-view-btn', () => clicks++);
    env.document.dispatchEvent(new Event('turbo:visit'));
    for (const handler of env.handlers.values()) handler();
    assert.equal(clicks, 1);
});

test('leaving a screen removes its globals without removing newer exports', () => {
    const env = setup();
    const first = env.create('kitchen');
    const sync = () => {};
    first.expose({syncKitchenOrders: sync});
    assert.equal(env.window.syncKitchenOrders, sync);
    env.document.dispatchEvent(new Event('turbo:before-render'));
    assert.equal(env.window.syncKitchenOrders, undefined);
    const second = env.create('pos');
    second.expose({updateCart: sync});
    env.window.updateCart = () => {};
    second.dispose();
    assert.equal(typeof env.window.updateCart, 'function');
});

test('page cleanup leaves the persistent audio permission and audio context intact', () => {
    const env = setup();
    const audio = {unlocked: true};
    const context = {state: 'running'};
    env.window.notificationAudio = audio;
    env.window.audioContext = context;
    env.create('orders');
    env.document.dispatchEvent(new Event('turbo:before-render'));
    assert.equal(env.window.notificationAudio, audio);
    assert.equal(env.window.audioContext, context);
});
