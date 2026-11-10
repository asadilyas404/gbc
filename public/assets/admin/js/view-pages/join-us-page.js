
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_join_us_page_js");



__panel.onTarget(document, 'click', '.remove-input-fields-group', function () {
    let id = $(this).data('id');
    $('#'+id).remove();
    $('#file_rows_data_'+id).remove();
    $('#check_box_data'+id).remove();
    $('#check_box_data_main_'+id).remove();
    $('.delete_'+id).remove();
});


__panel.listen(document, 'change', function(event) {
    if (event.target.classList.contains('fieldTypeSelect')) {
        let data = event.target.value;
        let key = $(event.target).data('key');
        optionSelected(data , key);
    }
});

__panel.onTarget(document, 'click', '.remove-check-box', function () {

    let parent_id = $(this).data('parent-id');
    let child_id = $(this).data('child-id');
    $('#check_box_data_'+parent_id+'_'+child_id).remove();
});
__panel.onTarget(document, 'click', '.add-check-box', function () {
    let parent_id = $(this).data('id');
    add_check_box(parent_id);
});




})();
