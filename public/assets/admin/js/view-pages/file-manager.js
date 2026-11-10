
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_file_manager_js");
__panel.globals({
"readURL": {get: () => readURL, set: value => { readURL = value; }}
});

function readURL(input) {
    $('#files').html("");
    for( let i = 0; i<input.files.length; i++)
    {
        if (input.files && input.files[i]) {
            let reader = new FileReader();
            reader.onload = function (e) {
                $('#files').append('<div class="col-md-2 col-sm-4 m-1"><img class="initial-28" id="viewer" src="'+e.target.result+'"/></div>');
            }
            reader.readAsDataURL(input.files[i]);
        }
    }

}

$("#customFileUpload").change(function () {
    readURL(this);
});

$('#customZipFileUpload').change(function(e){
    let fileName = e.target.files[0].name;
    $('#zipFileLabel').html(fileName);
});

$('.copy-test').on('click', function (){
    let copyText = $(this).data('file-path');
    copy_test(copyText);
})

$('.form-submit-warning').on('submit', function (){
    form_submit_warrning(e)
})

})();
