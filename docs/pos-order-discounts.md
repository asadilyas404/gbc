# POS product and overall-bill discounts

ERP discount types with `is_order_discount = 0` (or null on legacy rows) appear
in the product modals. Active types with `is_order_discount = 1` appear in the
Extra Discount modal. The ERP `default_discount` is the percentage; `max_discount`
is a currency amount, with three decimal places. A zero maximum allows zero
discount. An overall-bill type must have a percentage between 0 and 100 and a
nonnegative maximum amount.

The bill discount is `min(eligible subtotal * percentage / 100, max_discount)`.
When applying extra discount to a cart containing default food discounts, a
SweetAlert asks the cashier to **Add extra discount** or **Cancel**. Confirmation
removes the default discounts from active items and includes their full prices
and add-ons in the extra discount base. Cancel leaves the cart unchanged. The
server requires the confirmation flag and saves the replacement atomically in
the session only after validation succeeds. Canceled items, delivery fees, tax
and other charges are excluded from the base.
The cart and checkout recalculate it after quantity changes, additions and
removals. Checkout revalidates the selected type against the current ERP data.

An active product with a manually selected positive discount prevents an extra
discount. Adding or editing a product to have a manual discount is also blocked
while an extra discount is selected. New and edited items receive no default
food discount while an extra discount is selected, including when a stale modal
submits the old default. The product modals display zero product discount and
disable product-discount selection during this time. Removing extra discount
allows defaults on subsequently added items; it does not restore the removed
discounts on existing lines.
Canceled product discounts do not block extra discount.
Choosing **No extra discount** clears the overall discount, including legacy
discounts without a catalogue ID.

Orders store the applied amount in `restaurant_discount_amount` and the selected
type in `order_discount_type_id`. Both draft loading paths restore the ID, so a
draft's amount is recalculated using the current catalogue rate and cap rather
than treating the previously capped amount as a fixed discount. The applied
product discount source and its original rate/type are preserved inside the
existing `order_details.food_details` JSON snapshot. Legacy snapshots infer a
default only when the saved amount matches the food's configured default and
the selection was empty or the old default ID `1`. Explicit manual selections
remain manual, even if their percentage happens to match a food default.
The legacy default report category (ID `1`) is retained for new default discounts
when that category is still an active per-product type. The snapshot source,
rather than the category ID alone, distinguishes a default from a manual choice.
When no extra discount is selected, foods with a default discount select Offer
in the add modal, and saved default offers stay selected in the edit modal. The
form identifies that selection as the default offer, and the server verifies
its amount against the food default or saved default rate before accepting it.
Previously saved drafts that contain both discounts keep their saved item
discounts until the cashier reapplies extra discount and confirms replacement;
those legacy default-discount lines remain excluded from the bill discount base.

When Business Setup disables Extra Discount, the cart's edit button remains
disabled, including on drafts with a saved discount. The cart and discount modal
show no disabled-setting warning or error. Server validation still rejects
applying or saving extra discount while the setting is disabled.

## POS totals and add-on quantities

Adding/editing an item, changing cart quantity, and saving a draft or final order
validate nonnegative prices and totals. A product discount is checked against the
**unit** food/variation price, even when quantity is greater than one. Invalid
quantities, prices, taxes, delivery fees, and excess discounts are rejected;
valid 100% discounts can still produce a zero-value sale. Checkout also checks
the calculated tax, charges, adjustment and final amount before saving. Rejected
checkout changes roll back customer, stock and order writes in the transaction.

Normal and variation add-ons follow food quantity by default. Cashiers can adjust
each add-on independently: two pizzas with one cheese add-on is valid. Explicit
quantities remain unchanged when food quantity changes and when saving/reloading
the order. The quantity mode is stored in the existing add-on JSON; legacy
quantities that differ from food quantity remain independent. Totals and stock
checks use the selected quantities and catalogue prices. Stock checks aggregate
repeated add-on IDs across variation groups. No new migration is required.

## Deployment

Run this migration on the central restaurant database and each branch database
before deploying the POS changes or syncing the new fields:

```sh
php artisan migrate --path=database/migrations/2026_11_17_000001_add_pos_order_discount_fields.php
```

The migration safely skips existing columns, including the catalogue columns
added by the ERP migration. It adds `orders.order_discount_type_id` to the central
database too, so existing order sync endpoints can accept it. Order sync already
transfers all order columns, and catalogue sync transfers all discount columns.
No new API field mapping is required. After migrating, sync ERP discount types
to the branches before using overall-bill discounts.

The migration has been tested on isolated SQLite databases. It has not been run
against the configured Oracle databases as part of this change.
