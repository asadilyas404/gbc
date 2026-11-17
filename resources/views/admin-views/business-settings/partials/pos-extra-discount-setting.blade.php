@php($posExtraDiscountEnabled = (new \App\Services\PosDiscountService())->isEnabled())
<div class="col-sm-6 col-lg-4">
    <div class="form-group mb-0">
        <label for="pos_extra_discount_enabled" class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
            <span class="pr-1 d-flex align-items-center switch--label">
                <span class="line--limit-1">POS Extra Discount</span>
            </span>
            <input type="hidden" name="pos_extra_discount_enabled" value="0">
            <input type="checkbox" class="toggle-switch-input" name="pos_extra_discount_enabled"
                id="pos_extra_discount_enabled" value="1" aria-describedby="pos_extra_discount_help"
                {{ $posExtraDiscountEnabled ? 'checked' : '' }}>
            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
        </label>
        <small id="pos_extra_discount_help" class="form-text text-muted">Allow extra discounts on POS orders. Default food offers remain available.</small>
    </div>
</div>
