
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_product_import_js");
__panel.globals({
"count": {get: () => count, set: value => { count = value; }},
"countRow": {get: () => countRow, set: value => { countRow = value; }},
"element": {get: () => element, set: value => { element = value; }},
"show_min_max": {get: () => show_min_max, set: value => { show_min_max = value; }},
"hide_min_max": {get: () => hide_min_max, set: value => { hide_min_max = value; }},
"new_option_name": {get: () => new_option_name, set: value => { new_option_name = value; }},
"removeOption": {get: () => removeOption, set: value => { removeOption = value; }},
"deleteRow": {get: () => deleteRow, set: value => { deleteRow = value; }}
});

let count = 0;
let countRow = 0;
let element = 0;
function show_min_max(data) {
    $('#min_max1_' + data).removeAttr("readonly");
    $('#min_max2_' + data).removeAttr("readonly");
    $('#min_max1_' + data).attr("required", "true");
    $('#min_max2_' + data).attr("required", "true");
}

function hide_min_max(data) {
    $('#min_max1_' + data).val(null).trigger('change');
    $('#min_max2_' + data).val(null).trigger('change');
    $('#min_max1_' + data).attr("readonly", "true");
    $('#min_max2_' + data).attr("readonly", "true");
    $('#min_max1_' + data).attr("required", "false");
    $('#min_max2_' + data).attr("required", "false");
}

__panel.onTarget(document, 'change', '.show_min_max', function () {
    let data = $(this).data('count');
    show_min_max(data);
});

__panel.onTarget(document, 'change', '.hide_min_max', function () {
    let data = $(this).data('count');
    hide_min_max(data);
});

__panel.onTarget(document, 'click', '.add_new_row_button', function () {
    let data = $(this).data('count');
    add_new_row_button(data);
});

__panel.onTarget(document, 'keyup', '.new_option_name', function () {
    let data = $(this).data('count');
    let value = $(this).val();
    new_option_name(value, data);
});

$('#reset_btn').click(function(){
    $('#bulk__import').val(null);
})


__panel.onTarget(document, "click", ".submit_btn", function(e){
    e.preventDefault();
    let data = $(this).val();
    myFunction(data)
});




function new_option_name(value, data) {
    $("#new_option_name_" + data).empty();
    $("#new_option_name_" + data).text(value)
    console.log(value);
}

function removeOption(e) {
    element = $(e);
    element.parents('.view_new_option').remove();
}

__panel.onTarget(document, 'click', '.delete_input_button', function () {
    let e = $(this);
    removeOption(e);
});

function deleteRow(e) {
    element = $(e);
    element.parents('.add_new_view_row_class').remove();
}

__panel.onTarget(document, 'click', '.deleteRow', function () {
    let e = $(this);
    deleteRow(e);
});

})();
