const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const template = fs.readFileSync(path.join(__dirname,
    '../../resources/views/vendor-views/pos/index-new.blade.php'), 'utf8');
const start = template.indexOf("$(document).on('submit', '#extra-discount-form'");
const end = template.indexOf("$(document).on('change', '#order_discount_type_id'", start);
assert.ok(start >= 0 && end > start, 'Discount submit handler exists');

function setup({ defaults = true, selected = '2', confirmed = '0', attached = true } = {}) {
    let handler;
    let resolvePopup;
    const confirmation = { value: confirmed };
    const state = { popups: [], submissions: 0, prevented: 0 };
    const form = {
        dataset: { hasDefaultDiscount: defaults ? '1' : '0' },
        closest: () => null,
        querySelector(selector) {
            return selector.includes('confirm_default_discount_removal') ? confirmation : { value: selected };
        }
    };
    vm.runInNewContext(template.slice(start, end), {
        $: () => ({ on: (_event, _selector, callback) => { handler = callback; } }),
        document: { body: {}, contains: candidate => attached && candidate === form },
        Swal: { fire: options => {
            state.popups.push(options);
            return { then: callback => { resolvePopup = callback; } };
        } },
        HTMLFormElement: { prototype: { submit() {
            assert.equal(this, form);
            state.submissions++;
        } } }
    });
    return { state, form, confirmation,
        submit: () => handler.call(form, { preventDefault: () => state.prevented++ }),
        resolve: result => resolvePopup(result) };
}

test('default discounts require the explicit Add extra discount confirmation', () => {
    const flow = setup();
    flow.submit();
    assert.equal(flow.state.prevented, 1);
    assert.equal(flow.state.submissions, 0);
    assert.equal(flow.confirmation.value, '0');
    assert.equal(flow.state.popups[0].confirmButtonText, 'Add extra discount');
    assert.equal(flow.state.popups[0].showCancelButton, true);
    flow.resolve({ isConfirmed: true });
    assert.equal(flow.confirmation.value, '1');
    assert.equal(flow.state.submissions, 1);
});

test('confirmation supports the installed legacy SweetAlert result', () => {
    const flow = setup();
    flow.submit();
    flow.resolve({ value: true });
    assert.equal(flow.confirmation.value, '1');
    assert.equal(flow.state.submissions, 1);
});

test('Cancel preserves the cart and allows another confirmation attempt', () => {
    const flow = setup();
    flow.submit();
    flow.resolve({ dismiss: 'cancel' });
    assert.equal(flow.confirmation.value, '0');
    assert.equal(flow.state.submissions, 0);
    flow.submit();
    assert.equal(flow.state.popups.length, 2);
});

test('repeated submits while the popup is open do not open another popup', () => {
    const flow = setup();
    flow.submit();
    flow.submit();
    assert.equal(flow.state.popups.length, 1);
    assert.equal(flow.state.submissions, 0);
});

for (const [name, options] of [
    ['cart has no default discounts', { defaults: false }],
    ['cashier removes extra discount', { selected: '' }],
    ['replacement is already confirmed', { confirmed: '1' }]
]) {
    test(`submit needs no popup when ${name}`, () => {
        const flow = setup(options);
        flow.submit();
        assert.equal(flow.state.prevented, 0);
        assert.equal(flow.state.popups.length, 0);
    });
}

test('a replaced cart form is not submitted after confirmation', () => {
    const flow = setup({ attached: false });
    flow.submit();
    flow.resolve({ isConfirmed: true });
    assert.equal(flow.state.submissions, 0);
});
