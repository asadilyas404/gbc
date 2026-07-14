<?php
    return [
        'image_source_base' => env('IMAGE_SOURCE_BASE', 'http://malikalpizza.royalerp.net/storage'),
        'branch_id' => env('BRANCH_ID'), // Branch ID
        'app_mode'  => env('APP_MODE', 'local'),
        'invoice_restaurant_name' => env('INVOICE_RESTAURANT_NAME', 'ملك البيتزا'),
        'invoice_branch_name' => env('INVOICE_BRANCH_NAME', 'المصنعة'),
        'branch_map_link' => env('BRANCH_MAP_LINK', 'https://maps.app.goo.gl/gv2XpfucXiyDxoqe9'),
        'invoice_slogan' => env('INVOICE_SLOGAN', 'لطلب الطعام من مالك البيتزا'),
        'cr_no' => env('CR_NO', '-'),
        'vat_no'    => env('VAT_NO', '-')
    ];