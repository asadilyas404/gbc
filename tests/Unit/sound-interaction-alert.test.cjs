const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../../public/assets/admin/js/sound-interaction-alert.js'), 'utf8');

function setup(notification = null) {
    const bar = { hidden: true };
    const events = {};
    const window = {};
    const document = {
        body: {},
        readyState: 'loading',
        getElementById: (id) => id === 'sound-enable-bar' ? bar : id === 'myAudio' ? notification : null,
        addEventListener: (name, callback) => { events[name] = callback; },
    };
    vm.runInNewContext(source, { window, document, console });
    return { api: window.soundInteractionAlert, bar, events };
}

test('blocked playback shows prompt, and a click retries and clears it', async () => {
    const { api, bar, events } = setup();
    let calls = 0;
    const audio = {
        isConnected: true,
        play() {
            calls++;
            return calls === 1
                ? Promise.reject({ name: 'NotAllowedError' })
                : Promise.resolve();
        },
    };
    await assert.rejects(api.play(audio));
    assert.equal(bar.hidden, false);
    events.click();
    await new Promise(setImmediate);
    assert.equal(calls, 2);
    assert.equal(bar.hidden, true);
});

test('ordinary pages detect blocked sound on load and clear the message after interaction', async () => {
    let calls = 0;
    let pauses = 0;
    const probe = {
        removeAttribute() {},
        play() {
            calls++;
            return calls === 1 ? Promise.reject({ name: 'NotAllowedError' }) : Promise.resolve();
        },
        pause() { pauses++; },
    };
    const { bar, events } = setup({ cloneNode: () => probe });
    events.DOMContentLoaded();
    await new Promise(setImmediate);
    assert.equal(bar.hidden, false);
    events.click();
    await new Promise(setImmediate);
    assert.equal(bar.hidden, true);
    assert.equal(pauses, 1);
    events['turbo:load']();
    assert.equal(calls, 2);
});

test('ordinary pages keep the message hidden when playback is allowed', async () => {
    const { bar, events } = setup({ cloneNode: () => ({
        removeAttribute() {},
        play: () => Promise.resolve(),
        pause() {},
    }) });
    events.DOMContentLoaded();
    await new Promise(setImmediate);
    assert.equal(bar.hidden, true);
});

test('unsupported or interrupted audio does not request user interaction', async () => {
    for (const name of ['NotSupportedError', 'AbortError']) {
        const { api, bar } = setup();
        await assert.rejects(api.play({ play: () => Promise.reject({ name }) }));
        assert.equal(bar.hidden, true);
    }
});

test('clearing one sound keeps prompt visible for other blocked sounds', () => {
    const { api, bar, events } = setup();
    let retried = false;
    api.requireInteraction('internet', () => api.clear('internet'));
    api.requireInteraction('orders', () => {
        retried = true;
        api.clear('orders');
    });
    api.clear('internet');
    assert.equal(bar.hidden, false);
    events.keydown();
    assert.equal(retried, true);
    assert.equal(bar.hidden, true);
});

test('navigation discards retries belonging to the previous page', () => {
    const { api, bar, events } = setup();
    api.requireInteraction('orders', () => assert.fail('stale retry'));
    events['turbo:before-cache']();
    events.click();
    events['turbo:load']();
    assert.equal(bar.hidden, true);
});

test('detached audio is cleared without replaying it', async () => {
    const { api, bar, events } = setup();
    let calls = 0;
    await assert.rejects(api.play({
        isConnected: false,
        play() {
            calls++;
            return Promise.reject({ name: 'NotAllowedError' });
        },
    }));
    events.click();
    assert.equal(calls, 1);
    assert.equal(bar.hidden, true);
});
