
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_category_index_js");
__panel.globals({
"forms": {get: () => forms, set: value => { forms = value; }}
});

__panel.onTarget(document, 'ready', function () {

    $('.js-select2-custom').each(function () {
        let select2 = $.HSCore.components.HSSelect2.init($(this));
    });
});

$('#reset_btn').click(function(){
    $('#exampleFormControlSelect1').val(null).trigger('change');
})
let forms = document.querySelectorAll('.priority-form');

forms.forEach(function(form) {
    let select = form.querySelector('.priority-select');

    select.addEventListener('change', function() {
        form.submit();
    });
});

})();
