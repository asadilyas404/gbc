@php($posExtraDiscountEnabled = (new \App\Services\PosDiscountService())->isEnabled())
@php($billFooterTextEn = \App\CentralLogics\Helpers::get_business_settings('bill_footer_text_en', false) ?? '')
@php($billFooterTextAr = \App\CentralLogics\Helpers::get_business_settings('bill_footer_text_ar', false) ?? '')
<div class="col-sm-6 col-lg-4">
    <div class="form-group mb-0">
        <label for="pos_extra_discount_enabled" class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
            <span class="pr-1 d-flex align-items-center switch--label">
                <span class="line--limit-1">POS Extra Discount</span>
                <span class="form-label-secondary text-danger d-flex"
                    data-toggle="tooltip" data-placement="right"
                    data-original-title="Allow extra discounts on POS orders. Default food offers remain available."><img
                        src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}"
                        alt="Allow extra discounts on POS orders. Default food offers remain available."> *
                </span>
            </span>
            <input type="hidden" name="pos_extra_discount_enabled" value="0">
            <input type="checkbox" class="toggle-switch-input" name="pos_extra_discount_enabled"
                id="pos_extra_discount_enabled" value="1" aria-describedby="pos_extra_discount_help"
                {{ $posExtraDiscountEnabled ? 'checked' : '' }}>
            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
        </label>
    </div>
</div>

<div class="col-12 mt-3">
    <h5 class="mb-1">Bill Footer Text</h5>
    <small class="text-muted d-block mb-3">This text will appear at the bottom of the printed bill and bill PDF.</small>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="bill_footer_text_en" class="form-label">English</label>
            <textarea name="bill_footer_text_en" id="bill_footer_text_en" class="form-control" rows="3"
                maxlength="1000" placeholder="Thank you for visiting us.">{{ old('bill_footer_text_en', $billFooterTextEn) }}</textarea>
        </div>
        <div class="col-md-6">
            <label for="bill_footer_text_ar" class="form-label">Arabic</label>
            <textarea name="bill_footer_text_ar" id="bill_footer_text_ar" class="form-control" rows="3"
                maxlength="1000" dir="rtl" placeholder="شكراً لزيارتكم لنا">{{ old('bill_footer_text_ar', $billFooterTextAr) }}</textarea>
        </div>
    </div>
</div>
