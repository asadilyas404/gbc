# WhatsApp Message Sending Setup Guide

This guide explains how to implement WhatsApp message sending functionality with PDF attachment support for purchase orders (or similar documents).

## Table of Contents
1. [Overview](#overview)
2. [Configuration Setup](#configuration-setup)
3. [Backend Implementation](#backend-implementation)
4. [Frontend Implementation](#frontend-implementation)
5. [Complete Example](#complete-example)

---

## Overview

This implementation allows sending WhatsApp messages with optional PDF attachments using the WhatsApp Intelligent API service. The system:
- Fetches contact phone numbers from the database
- Formats phone numbers (supports Oman and Pakistan formats)
- Generates PDF documents on-the-fly
- Sends WhatsApp messages with PDF attachments
- Logs sent messages for tracking

---

## Configuration Setup

### 1. Environment Variables

Add the following variables to your `.env` file:

```env
# WhatsApp Intelligent API Configuration
WHATSAPP_INTELLIGENT_API_URL=http://whatsintelligent.com/api/create-message
WHATSAPP_INTELLIGENT_APPKEY=your-app-key-here
WHATSAPP_INTELLIGENT_AUTHKEY=your-auth-key-here
WHATSAPP_INTELLIGENT_SANDBOX=false
```

**Note:** Replace `your-app-key-here` and `your-auth-key-here` with your actual credentials from WhatsApp Intelligent API.

### 2. Config File

Create or update `config/whatsapp.php`:

```php
<?php
    $config = [];

    // Meta/Facebook WhatsApp Business API Configuration (if needed)
    if(env('WHATSAPP_MODE' , NULL) == 'SANDBOX'){
        $config = [
            'verify_token' => env('VERIFY_TOKEN' , NULL),
            'phone_number_id' => env('PHONE_NUMBER_ID_SANDBOX' , NULL),
            'whatsapp_token' => env('WHATSAPP_TOKEN_SANDBOX' , NULL),
        ];
    } elseif(env('WHATSAPP_MODE', NULL) == 'LIVE'){
        $config = [
            'verify_token' => env('VERIFY_TOKEN' , NULL),
            'phone_number_id' => env('PHONE_NUMBER_ID' , NULL),
            'whatsapp_token'   => env('WHATSAPP_TOKEN' , NULL)
        ];
    }

    // WhatsApp Intelligent API Configuration
    $config['intelligent'] = [
        'api_url' => env('WHATSAPP_INTELLIGENT_API_URL', 'http://whatsintelligent.com/api/create-message'),
        'appkey' => env('WHATSAPP_INTELLIGENT_APPKEY', ''),
        'authkey' => env('WHATSAPP_INTELLIGENT_AUTHKEY', ''),
        'sandbox' => env('WHATSAPP_INTELLIGENT_SANDBOX', 'false'),
    ];

    return $config;
```

---

## Backend Implementation

### 1. Database Migration (if needed)

Create a migration for WhatsApp logs table:

```php
Schema::create('whatsapp_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('form_name');
    $table->string('entry_code');
    $table->timestamp('created_at');
});
```

### 2. Model (if needed)

Create `app/Models/Sale/WhatsappLog.php`:

```php
<?php

namespace App\Models\Sale;

use Illuminate\Database\Eloquent\Model;

class WhatsappLog extends Model
{
    protected $table = 'whatsapp_logs';
    protected $fillable = ['user_id', 'form_name', 'entry_code', 'created_at'];
    public $timestamps = false;
}
```

### 3. Controller Methods

Add these methods to your controller (e.g., `PurchaseOrderController.php`):

#### Method 1: Fetch Contact Info

```php
/**
 * Fetch contact phone number by ID
 * 
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function fetchContactInfo(Request $request)
{
    $contactCode = $request->query('contact_code');
    
    if (!$contactCode) {
        return response()->json(['error' => 'Contact code is required'], 400);
    }
    
    // Replace with your actual model and field names
    $contact = YourContactModel::where('contact_id', $contactCode)->first();
    
    if (!$contact) {
        return response()->json(['error' => 'Contact not found'], 404);
    }
    
    $contactPhone = $contact->phone_field; // Replace with your phone field name
    
    if (!$contactPhone) {
        return response()->json(['error' => 'Contact phone number not found'], 404);
    }

    return response()->json([
        'phone' => $contactPhone
    ]);
}
```

#### Method 2: Generate PDF for WhatsApp

```php
/**
 * Generate PDF document for WhatsApp attachment
 * 
 * @param Request $request
 * @param int $id Document ID
 * @return \Illuminate\Http\JsonResponse
 */
public function generatePdfForWhatsApp(Request $request, $id)
{
    // Load your document data
    $data['title'] = 'Your Document Title';
    $data['type'] = '1'; // Your print type
    $data['id'] = $id;
    
    // Load document with relationships
    $data['current'] = YourModel::with('relationships')->where('id', $id)->first();
    
    if (!$data['current']) {
        return response()->json(['error' => 'Document not found'], 404);
    }
    
    // Load additional data needed for PDF
    // $data['currency'] = ...
    // $data['payment_terms'] = ...
    
    // Generate PDF using your print view
    $view = view('prints.your_print_view', compact('data'))->render();
    
    $dompdf = new Dompdf();
    $options = $dompdf->getOptions();
    $options->set('dpi', 100);
    $options->set('isPhpEnabled', TRUE);
    $options->set('isHtml5ParserEnabled', TRUE);
    $options->setDefaultFont('roboto');
    $dompdf->setOptions($options);
    $dompdf->loadHtml($view, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape'); // Adjust as needed
    $dompdf->render();
    
    // Save PDF to public storage
    $filename = 'doc_' . $data['current']->document_code . '_' . time() . '.pdf';
    $directory = public_path('uploads/documents');
    
    if (!file_exists($directory)) {
        mkdir($directory, 0755, true);
    }
    
    $filePath = $directory . '/' . $filename;
    file_put_contents($filePath, $dompdf->output());
    
    // Return public URL
    $publicUrl = url('uploads/documents/' . $filename);
    
    return response()->json([
        'success' => true,
        'url' => $publicUrl,
        'filePath' => $publicUrl
    ]);
}
```

#### Method 3: Send WhatsApp Message

```php
/**
 * Send WhatsApp message via WhatsApp Intelligent API
 * 
 * @param Request $request
 * @return void (outputs JSON)
 */
public function sendWhatsappMsg(Request $request)
{
    $to = $request->to;
    $message = $request->message;
    $filePath = $request->filePath;
    $documentNumber = $request->invoiceNumber; // or documentNumber
    $title = $request->title;

    // Get configuration from config file
    $apiUrl = config('whatsapp.intelligent.api_url');
    $appkey = config('whatsapp.intelligent.appkey');
    $authkey = config('whatsapp.intelligent.authkey');
    $sandbox = config('whatsapp.intelligent.sandbox');

    $curl = curl_init();

    if($filePath == '' || $filePath == null) {
        // Send message without attachment
        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'appkey' => $appkey,
                'authkey' => $authkey,
                'to' => $to,
                'message' => $message,
                'sandbox' => $sandbox
            ),
        ));
    } else {
        // Send message with PDF attachment
        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'appkey' => $appkey,
                'authkey' => $authkey,
                'to' => $to,
                'message' => $message,
                'sandbox' => $sandbox,
                'file' => $filePath
            ),
        ));
    }

    $response = curl_exec($curl);
    curl_close($curl);

    $responseData = @json_decode($response, true);

    if ($responseData) {
        if (isset($responseData['message_status']) && $responseData['message_status'] == 'Success') {
            echo json_encode(['success' => 'Message sent successfully!']);

            // Log the message (adjust model name as needed)
            \App\Models\Sale\WhatsappLog::create([
                'user_id' => session('user_id'),
                'form_name' => $title,
                'entry_code' => $documentNumber,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } else {
            echo json_encode(['error' => 'Message sending failed. API returned: ' . $responseData['message_status']]);
        }
    } else {
        echo json_encode(['error' => 'Invalid JSON response or empty response.', 'raw_response' => $response]);
    }
}
```

### 4. Routes

Add these routes to `routes/web.php`:

```php
Route::prefix('your-module')->group(function () {
    // ... your existing routes ...
    
    Route::get('fetch-contact-info', 'YourController@fetchContactInfo');
    Route::get('generate-pdf-whatsapp/{id}', 'YourController@generatePdfForWhatsApp');
    Route::post('whatsapp-message-sending', 'YourController@sendWhatsappMsg');
});
```

---

## Frontend Implementation

### 1. Phone Number Formatting Functions

Add these JavaScript functions to your layout or common JS file:

```javascript
function formatPakPhoneNumber(phone) {
    phone = phone.replace(/[^0-9]/g, '');
    if (phone.startsWith('0')) {
        phone = '+92' + phone.slice(1);
    } else {
        phone = '+92' + phone;
    }
    return phone;
}

function formatOmanPhoneNumber(phone) {
    phone = phone.replace(/[^0-9]/g, '');
    phone = '+968' + phone;
    return phone;
}
```

### 2. WhatsApp Send Function

Add this function to your form view (e.g., `form.blade.php`):

```javascript
function sendWhatsAppMessage() {
    var button = document.getElementById("whatsappmessagebtn");
    if (button) {
        buttonIcons = button.innerHTML;
        button.disabled = true;
        button.textContent = 'Sending..';
    }

    // Get contact ID from your form
    var contactCode = $('#contact_id').val(); // Replace with your field ID
    var amount = $('#total_amount').val() || '0'; // Replace with your total field
    var title = 'Your Document Title'; // Or get from PHP: @json($page_data['title'])
    var documentCode = 'DOC-001'; // Get from your form or PHP variable
    var documentDate = $('input[name="document_date"]').val(); // Replace with your date field
    var formId = $('#form_id').val(); // Your document ID

    // Validation
    if (!contactCode) {
        toastr.error("Please select a contact first");
        if (button) {
            button.innerHTML = buttonIcons;
            button.disabled = false;
        }
        return;
    }

    if (!formId) {
        toastr.error("Please save the document first");
        if (button) {
            button.innerHTML = buttonIcons;
            button.disabled = false;
        }
        return;
    }

    // Fetch contact phone number
    $.ajax({
        url: '/your-module/fetch-contact-info',
        type: 'GET',
        data: {
            contact_code: contactCode
        },
        success: function(response) {
            const data = JSON.parse(response);

            if (!data || !data.phone) {
                toastr.error("Contact phone number not found");
                if (button) {
                    button.innerHTML = buttonIcons;
                    button.disabled = false;
                }
                return;
            }

            // Format phone number (choose appropriate format function)
            var to = formatOmanPhoneNumber(data.phone);
            // OR: var to = formatPakPhoneNumber(data.phone);

            // Generate PDF
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '/your-module/generate-pdf-whatsapp/' + formId,
                type: 'GET',
                success: function(pdfResponse) {
                    const pdfData = JSON.parse(pdfResponse);
                    var filePath = '';
                    
                    if (pdfData.success && pdfData.filePath) {
                        filePath = pdfData.filePath;
                    }

                    // Create message
                    const message = `Thank you for your valued order\n(*Document # ${documentCode}*, Dated ${documentDate}, Amount: OMR ${amount}).\n\nThank you and regards,\nwww.yourwebsite.com`;

                    // Send WhatsApp message
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: '/your-module/whatsapp-message-sending',
                        type: 'POST',
                        data: {
                            to: to,
                            message: message,
                            filePath: filePath,
                            invoiceNumber: documentCode,
                            title: title,
                        },
                        success: function(response) {
                            response = JSON.parse(response);
                            if (response.success) {
                                toastr.success("WhatsApp message sent at " + to);
                                if (button) {
                                    button.innerHTML = buttonIcons + '<i class="icon wb-check" aria-hidden="true"></i>';
                                    button.disabled = false;
                                }
                            } else {
                                toastr.error("Failed to send message check connection");
                                if (button) {
                                    button.innerHTML = buttonIcons;
                                    button.disabled = false;
                                }
                            }
                        },
                        error: function() {
                            toastr.error("Failed to send WhatsApp message.");
                            if (button) {
                                button.innerHTML = buttonIcons;
                                button.disabled = false;
                            }
                        }
                    });
                },
                error: function() {
                    toastr.error("Failed to generate PDF.");
                    if (button) {
                        button.innerHTML = buttonIcons;
                        button.disabled = false;
                    }
                }
            });
        },
        error: function() {
            toastr.error("Failed to fetch contact data.");
            if (button) {
                button.innerHTML = buttonIcons;
                button.disabled = false;
            }
        }
    });
}
```

### 3. Button in Page Header

The button should already exist in your page header. It calls `sendWhatsAppMessage()` when clicked:

```html
<button type="button" class="btn btn-success padding-3 margin-0 dropdown-toggle btn-sm" 
        id="whatsappmessagebtn" data-toggle="dropdown" aria-expanded="false">
    <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" 
         alt="WhatsApp" style="width:24px;height:22px;">
    <span class="caret"></span>
</button>
<ul class="dropdown-menu pull-right" aria-labelledby="exampleIconDropdown1" role="menu">
    <li role="presentation">
        <a href="javascript:void(0)" role="menuitem" onclick="sendWhatsAppMessage()">Send</a>
    </li>
</ul>
```

---

## Complete Example

### Purchase Order Implementation

Here's a complete example for a Purchase Order module:

#### Controller: `PurchaseOrderController.php`

```php
use App\Models\TblPurcSupplier;
use App\Models\TblPurcPurchaseOrder;
use App\Models\Sale\WhatsappLog;
use Dompdf\Dompdf;

public function fetchSupplierInfo(Request $request)
{
    $supplierCode = $request->query('supplier_code');
    if (!$supplierCode) {
        return response()->json(['error' => 'Supplier code is required'], 400);
    }
    $supplier = TblPurcSupplier::where('supplier_id', $supplierCode)->first();
    if (!$supplier) {
        return response()->json(['error' => 'Supplier not found'], 404);
    }
    $supplierPhone = $supplier->supplier_phone_1;
    if (!$supplierPhone) {
        return response()->json(['error' => 'Supplier phone number not found'], 404);
    }
    return response()->json(['phone' => $supplierPhone]);
}

public function generatePdfForWhatsApp(Request $request, $id)
{
    $data['title'] = 'Purchase Order';
    $data['type'] = '1';
    $data['id'] = $id;
    
    $data['current'] = TblPurcPurchaseOrder::with('po_details','supplier')
        ->where('purchase_order_id', $id)->first();
    
    if (!$data['current']) {
        return response()->json(['error' => 'Purchase order not found'], 404);
    }
    
    // Load additional data
    $data['currency'] = TblDefiCurrency::where('currency_id', $data['current']->currency_id)->first();
    $data['payment_terms'] = TblAccoPaymentTerm::where('payment_term_id', $data['current']->payment_mode_id)->first();
    
    // Generate PDF
    $view = view('prints.purchase.purchase_order.purchase_order_print', compact('data'))->render();
    
    $dompdf = new Dompdf();
    $options = $dompdf->getOptions();
    $options->set('dpi', 100);
    $options->set('isPhpEnabled', TRUE);
    $options->set('isHtml5ParserEnabled', TRUE);
    $options->setDefaultFont('roboto');
    $dompdf->setOptions($options);
    $dompdf->loadHtml($view, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Save PDF
    $filename = 'po_' . $data['current']->purchase_order_code . '_' . time() . '.pdf';
    $directory = public_path('uploads/purchase_orders');
    
    if (!file_exists($directory)) {
        mkdir($directory, 0755, true);
    }
    
    $filePath = $directory . '/' . $filename;
    file_put_contents($filePath, $dompdf->output());
    
    $publicUrl = url('uploads/purchase_orders/' . $filename);
    
    return response()->json([
        'success' => true,
        'url' => $publicUrl,
        'filePath' => $publicUrl
    ]);
}

public function sendWhatsappMsg(Request $request)
{
    // ... (use the sendWhatsappMsg method from above)
}
```

#### Routes

```php
Route::prefix('purchase-order')->group(function () {
    Route::get('fetch-supplier-info', 'Purchase\PurchaseOrderController@fetchSupplierInfo');
    Route::get('generate-pdf-whatsapp/{id}', 'Purchase\PurchaseOrderController@generatePdfForWhatsApp');
    Route::post('whatsapp-message-sending', 'Purchase\PurchaseOrderController@sendWhatsappMsg');
});
```

---

## Testing Checklist

- [ ] Environment variables are set correctly
- [ ] Config file is properly configured
- [ ] Routes are registered
- [ ] Controller methods are implemented
- [ ] JavaScript function is added to the form
- [ ] Phone number formatting functions are available
- [ ] PDF generation works correctly
- [ ] WhatsApp API credentials are valid
- [ ] Test sending message without attachment
- [ ] Test sending message with PDF attachment
- [ ] Verify message logs are being saved

---

## Troubleshooting

### Issue: "Contact phone number not found"
- Check that the contact exists in the database
- Verify the phone field name matches your database column
- Ensure the contact_code parameter is being passed correctly

### Issue: "Failed to generate PDF"
- Check that the print view exists
- Verify all required data is loaded
- Check file permissions for the uploads directory
- Ensure DomPDF is properly installed

### Issue: "Message sending failed"
- Verify WhatsApp API credentials in `.env`
- Check API URL is correct
- Ensure phone number is properly formatted
- Verify sandbox mode setting

### Issue: PDF not attaching
- Check that filePath is a publicly accessible URL
- Verify the PDF file was created successfully
- Ensure the URL is accessible from the internet (not localhost)

---

## Notes

- Replace placeholder values (field names, model names, routes) with your actual implementation
- Adjust phone number formatting based on your country requirements
- Customize the message template as needed
- Ensure the uploads directory has proper write permissions
- Consider adding error handling and validation as needed
- For production, consider using queue jobs for PDF generation to avoid timeouts

---

## Support

For issues or questions, refer to:
- WhatsApp Intelligent API documentation
- Laravel DomPDF documentation
- Your project's codebase for similar implementations

