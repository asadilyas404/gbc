
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_business_settings_payment_page_js");
__panel.globals({
"readURL": {get: () => readURL, set: value => { readURL = value; }},
"checkedFunc": {get: () => checkedFunc, set: value => { checkedFunc = value; }}
});



function readURL(input, gatewayName) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = function (e) {
            $('#' + gatewayName + '-image-preview').attr('src', e.target.result);
        }
        reader.readAsDataURL(input.files[0]);
    }
}

__panel.onTarget(document, 'change', 'input[name="gateway_image"]', function () {
    let gatewayName = $(this).attr('id').replace('-image', '');
    readURL(this, gatewayName);
});
function checkedFunc() {
    $('.switch--custom-label .toggle-switch-input').each( function() {
        if(this.checked) {
            $(this).closest('.switch--custom-label').addClass('checked')
        }else {
            $(this).closest('.switch--custom-label').removeClass('checked')
        }
    })
}
checkedFunc()
$('.switch--custom-label .toggle-switch-input').on('change', checkedFunc)



})();
