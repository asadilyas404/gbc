const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

function setup() {
    const listeners = {};
    const addEventListener = (name, fn) => (listeners[name] ||= []).push(fn);
    const emit = (name) => (listeners[name] || []).forEach(fn => fn());
    const yellow = { hidden: true };
    const red = { hidden: true, dataset: { muteLabel: 'Mute', unmuteLabel: 'Unmute' } };
    const mute = { addEventListener, setAttribute() {} };
    let context;
    class AudioContext {
        constructor() {
            context = this;
            this.state = 'suspended';
            this.resume = () => new Promise(() => {});
        }
        addEventListener(name, fn) { this.onStateChange = fn; }
    }
    const window = {
        addEventListener, AudioContext,
        matchMedia: () => ({ matches: true }),
        setInterval: () => 1,
        clearInterval() {},
    };
    const document = {
        body: {}, readyState: 'loading', hidden: false, addEventListener,
        getElementById: id => ({
            'sound-enable-bar': yellow,
            'internet-connection-alert': red,
            'internet-connection-alert-mute': mute,
        })[id] || null,
    };
    const sandbox = vm.createContext({ window, document, navigator: { onLine: false }, console });
    for (const file of ['sound-interaction-alert.js', 'internet-connection-alert.js']) {
        vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/assets/admin/js', file), 'utf8'), sandbox);
    }
    return { red, yellow, emit, context: () => context };
}

test('offline without unlocked Web Audio shows both alerts', () => {
    const { red, yellow } = setup();
    assert.equal(red.hidden, false);
    assert.equal(yellow.hidden, false);
});

test('pending resume keeps yellow alert visible until Web Audio is running', () => {
    const { red, yellow, emit, context } = setup();
    emit('pointerdown');
    assert.equal(yellow.hidden, false);
    context().state = 'running';
    context().onStateChange();
    assert.equal(yellow.hidden, true);
    assert.equal(red.hidden, false);
    context().state = 'suspended';
    context().onStateChange();
    assert.equal(yellow.hidden, false);
});

test('muting offline audio clears its interaction prompt', () => {
    const { red, yellow, emit } = setup();
    emit('click');
    assert.equal(yellow.hidden, true);
    assert.equal(red.hidden, false);
});

test('Turbo navigation restores the offline interaction prompt immediately', () => {
    const { yellow, emit } = setup();
    emit('turbo:before-cache');
    assert.equal(yellow.hidden, true);
    emit('turbo:load');
    assert.equal(yellow.hidden, false);
});
