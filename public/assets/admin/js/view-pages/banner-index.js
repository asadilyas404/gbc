
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_banner_index_js");
__panel.globals({
"readURL": {get: () => readURL, set: value => { readURL = value; }}
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

})();
