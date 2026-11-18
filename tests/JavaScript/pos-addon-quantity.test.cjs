const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.join(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'public/assets/admin/js/view-pages/pos.js'), 'utf8');
const sync = source.slice(source.indexOf('function posAddonModeInput('), source.indexOf('$("#order_place")'));

for (const view of ['_quick-view-data', '_quick-view-cart-item']) {
    const template = fs.readFileSync(path.join(root, `resources/views/vendor-views/pos/${view}.blade.php`), 'utf8');
    const start = template.indexOf('    function calculateTotal(');
    const calculation = template.slice(start, template.indexOf('\n    }', start) + 6);
    test(`${view}: default quantities follow food and manual quantities stay independent`, () => {
        const quantity = { value: '1' };
        const buttons = [{ disabled: false }, { disabled: false }];
        const modes = {};
        const events = {};
        const form = {
            querySelector: () => quantity, querySelectorAll: () => addons,
            elements: { namedItem: name => modes[name] },
            appendChild: input => { modes[input.name] = input; }
        };
        const addons = [0.25, 0.5].map((price, index) => ({ price, value: '1', readOnly: true,
            name: index ? 'variation_addon_quantity[0][2]' : 'addon-quantity1',
            closest: selector => selector === 'form' ? form : { querySelectorAll: () => buttons }
        }));
        modes['variation_addon_mode[0][2]'] = { value: 'food' };
        const labels = {};
        const context = {
            document: { getElementById: () => form,
                createElement: () => ({}), addEventListener: (event, listener) => { events[event] = listener; } },
            $: selector => ({
                val: () => ({ '#base_price': '5', '#product_discount': '2',
                    '#product_discount_type': 'amount', '#add_new_product_quantity': quantity.value })[selector],
                text: value => { labels[selector] = value; }
            }),
            getCheckedPrice: () => ({ selectedOptionsTotal: 1,
                addonsTotal: addons.reduce((sum, addon) => sum + addon.price * Number(addon.value), 0) }),
            finalTotal: 0, actualPrice: 0
        };
        vm.createContext(context);
        vm.runInContext(sync + '\n' + calculation, context);
        for (const count of [1, 4, 2]) {
            quantity.value = String(count);
            vm.runInContext('calculateTotal()', context);
            assert.deepEqual(addons.map(addon => addon.value), [count, count]);
            assert.ok(addons.every(addon => !addon.readOnly));
            assert.ok(buttons.every(button => !button.disabled));
            assert.equal(parseFloat(labels['.actual_price']), 6.75 * count);
            assert.equal(parseFloat(labels['.chosen_price']), 4.75 * count);
        }

        // Two foods, but the customer wants one normal add-on.
        const button = { closest: () => ({ querySelector: () => addons[0] }) };
        events.click({ target: { closest: () => button } });
        addons[0].value = '1';
        vm.runInContext('calculateTotal()', context);
        assert.equal(Number(addons[0].value), 1);
        assert.equal(parseFloat(labels['.chosen_price']), 9.25);
        quantity.value = '4';
        vm.runInContext('calculateTotal()', context);
        assert.equal(Number(addons[0].value), 1);
        assert.equal(Number(addons[1].value), 4);
        assert.equal(parseFloat(labels['.chosen_price']), 18.25);

        // Typed variation add-on quantities also remain independent.
        addons[1].value = '1';
        events.input({ target: addons[1] });
        quantity.value = '2';
        vm.runInContext('calculateTotal()', context);
        assert.deepEqual(addons.map(addon => Number(addon.value)), [1, 1]);
        assert.equal(parseFloat(labels['.chosen_price']), 8.75);
    });
}
