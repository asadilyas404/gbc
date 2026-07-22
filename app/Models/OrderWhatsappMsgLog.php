<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderWhatsappMsgLog extends Model
{
    use HasFactory;

    protected $table = 'order_whatsapp_msg_log';

    protected $guarded = ['id'];
}
