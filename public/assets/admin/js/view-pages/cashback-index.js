
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("public_assets_admin_js_view_pages_cashback_index_js");

__panel.ready(function() {
    check_function();
    $('#cashback_type').on('change', function() {
        if($('#cashback_type').val() == 'amount')
        {
            $('#max_discount').attr("readonly","true");
            $('#max_discount').removeAttr("required");
            $('#max_discount').val(null);
            $('#percentage').addClass('d-none');
            $('#cuttency_symbol').removeClass('d-none');
            $('#Cash_back_amount').attr('max',99999999999);
        }
        else
        {
            $('#max_discount').removeAttr("readonly");
            $('#max_discount').attr("required","true");
            $('#percentage').removeClass('d-none');
            $('#cuttency_symbol').addClass('d-none');
            $('#Cash_back_amount').attr('max',100);

        }
    });

    $('#date_from').attr('min',(new Date()).toISOString().split('T')[0]);
    $('#date_to').attr('min',(new Date()).toISOString().split('T')[0]);

    // INITIALIZATION OF SELECT2
    // =======================================================
    $('.js-select2-custom').each(function () {
        let select2 = $.HSCore.components.HSSelect2.init($(this));
    });
    $('#reset_btn').click(function(){
        __panel.setTimeout(reset_select, 100);
    })
    function reset_select(){
        $('#date_from').val('');
        $('#date_from').attr('placeholder', 'mm/dd/yyyy');
        $('#date_from').attr('max', '');
        $('#select_customer').trigger('change');
        check_function();
    }

   function check_function(){
        if($('#cashback_type').val() == 'amount')
        {
            $('#max_discount').attr("readonly","true");
            $('#max_discount').removeAttr("required");
            $('#percentage').addClass('d-none');
            $('#cuttency_symbol').removeClass('d-none');
            $('#Cash_back_amount').attr('max',99999999999);
        }else{
            $('#max_discount').removeAttr("readonly");
            $('#max_discount').attr("required","true");
            $('#percentage').removeClass('d-none');
            $('#cuttency_symbol').addClass('d-none');
            $('#Cash_back_amount').attr('max',100);
        }
    }
});

$("#date_from").on("change", function () {
    $('#date_to').attr('min',$(this).val());
});

$("#date_to").on("change", function () {
    $('#date_from').attr('max',$(this).val());
});
$('#reset_btn').click(function(){
    $('#select_customer').val(null).trigger('change');

})

})();
