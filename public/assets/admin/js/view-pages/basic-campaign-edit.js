
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_basic_campaign_edit_js");
__panel.globals({
"readURL": {get: () => readURL, set: value => { readURL = value; }},
"show_item": {get: () => show_item, set: value => { show_item = value; }}
});

$("#date_from").on("change", function () {
    $('#date_to').attr('min',$(this).val());
});

$("#date_to").on("change", function () {
    $('#date_from').attr('max',$(this).val());
});
function readURL(input) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();

        reader.onload = function (e) {
            $('#viewer').attr('src', e.target.result);
        }

        reader.readAsDataURL(input.files[0]);
    }
}

$("#customFileEg1").change(function () {
    readURL(this);
});

function show_item(type) {
    if (type === 'product') {
        $("#type-product").show();
        $("#type-category").hide();
    } else {
        $("#type-product").hide();
        $("#type-category").show();
    }
}

})();
