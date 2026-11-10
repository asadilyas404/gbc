
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_product_index_js");
__panel.globals({
"element": {get: () => element, set: value => { element = value; }},
"count": {get: () => count, set: value => { count = value; }},
"countRow": {get: () => countRow, set: value => { countRow = value; }},
"parent_category_id": {get: () => parent_category_id, set: value => { parent_category_id = value; }},
"stock": {get: () => stock, set: value => { stock = value; }},
"show_min_max": {get: () => show_min_max, set: value => { show_min_max = value; }},
"hide_min_max": {get: () => hide_min_max, set: value => { hide_min_max = value; }},
"new_option_name": {get: () => new_option_name, set: value => { new_option_name = value; }},
"removeOption": {get: () => removeOption, set: value => { removeOption = value; }},
"deleteRow": {get: () => deleteRow, set: value => { deleteRow = value; }},
"readURL": {get: () => readURL, set: value => { readURL = value; }}
});

let element = "";
let count = 0;
let countRow = 0;
let parent_category_id = 0;
let stock = true;

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
    if($('.view_new_option').length <= 0){
        $('#item_stock').prop('readonly', false).prop('required', true);
    }
    updatestockCount();
});

function deleteRow(e) {
    element = $(e);
    element.parents('.add_new_view_row_class').remove();
}

__panel.onTarget(document, 'click', '.deleteRow', function () {
    let e = $(this);
    deleteRow(e);
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

function readURL(input) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();

        reader.onload = function(e) {
            $('#viewer').attr('src', e.target.result);
        }

        reader.readAsDataURL(input.files[0]);
    }
}

$("#customFileEg1").change(function() {
    readURL(this);
    $('#image-viewer-section').show(1000);
});

$('#category_id').on('change', function () {
    parent_category_id = $(this).val();
});

})();
