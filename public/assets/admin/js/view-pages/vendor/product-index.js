
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_vendor_product_index_js");
__panel.globals({
"element": {get: () => element, set: value => { element = value; }},
"count": {get: () => count, set: value => { count = value; }},
"countRow": {get: () => countRow, set: value => { countRow = value; }},
"show_min_max": {get: () => show_min_max, set: value => { show_min_max = value; }},
"hide_min_max": {get: () => hide_min_max, set: value => { hide_min_max = value; }},
"new_option_name": {get: () => new_option_name, set: value => { new_option_name = value; }},
"removeOption": {get: () => removeOption, set: value => { removeOption = value; }},
"deleteRow": {get: () => deleteRow, set: value => { deleteRow = value; }},
"getRequest": {get: () => getRequest, set: value => { getRequest = value; }}
});


let element = "";
let count = 0;
let countRow = 0;
__panel.ready(function() {
    // INITIALIZATION OF SELECT2
    // =======================================================
    $('.js-select2-custom').each(function () {
        let select2 = $.HSCore.components.HSSelect2.init($(this));
    });
});

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

__panel.onTarget(document, 'change', '.get-request', function () {
    let val= $(this).val();
    let route= $(this).data('url')+val;
    let id= $(this).data('id');
    getRequest(route, id);
})

function getRequest(route, id) {
    __panel.request($.get({
        url: route,
        dataType: 'json',
        success: function (data) {
            $('#' + id).empty().append(data.options);
        },
    }));
}

$("#customFileEg1").change(function () {
    readURL(this);
    $('#image-viewer-section').show(1000)
});

})();
