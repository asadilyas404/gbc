
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_category_import_export_js");

__panel.onTarget(document, 'ready', function (){
    $('#date_from').attr('max',(new Date()).toISOString().split('T')[0]);
    $('#date_to').attr('max',(new Date()).toISOString().split('T')[0]);
    $('.id_wise').hide();
    $('.date_wise').hide();
    $('#type').on('change', function()
    {
        $('.id_wise').hide();
        $('.date_wise').hide();
        $('.'+$(this).val()).show();
    })
});
$('#reset_btn').click(function(){
    $('#bulk__import').val(null);
})


__panel.onTarget(document, "click", ".submit_btn", function(e){
    e.preventDefault();
    var data = $(this).val();
    myFunction(data)
});

})();
