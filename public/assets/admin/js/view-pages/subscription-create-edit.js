
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_subscription_create_edit_js");
__panel.globals({
"reset": {get: () => reset, set: value => { reset = value; }}
});

    $('#select-all').on('change', function(){
        if($(this).is(':checked')){
            $('.package-available-feature').prop('checked', true);
        }else{
            $('.package-available-feature').prop('checked', false);
        }
    })
    $('.package-available-feature').on('change', function(){
        if($(this).is(':checked')){
            if($('.package-available-feature').length == $('.package-available-feature:checked').length){
                $('#select-all').prop('checked', true);
            }
        }else{
            $('#select-all').prop('checked', false);
        }
    }).trigger('change');

    $('.limit-input').on('change', function() {

        var closestLimitItemCard = $(this).closest('.limit-item-card');
        var isChecked = $(this).is(':checked');
        if (isChecked) {
            if ($(this).val() == 'Use_Limit') {
                closestLimitItemCard.find('.custom-limit-box').show();
                closestLimitItemCard.find('.max_required').prop('required', true);
            } else {
                closestLimitItemCard.find('.custom-limit-box').hide();
                closestLimitItemCard.find('.max_required').removeAttr('required');
            }
        }
    }).trigger('change');



    __panel.onTarget(document, "click", "#reset_btn", function () {
    __panel.setTimeout(reset, 10);
    });

    function reset(){
    $('.limit-input').trigger('change');
    }

__panel.onTarget(document, "click", ".btn--reset", function () {
    $('.custom-limit-box').hide();
    $('.max_required').removeAttr('required');
});

})();
