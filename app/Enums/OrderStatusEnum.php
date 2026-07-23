<?php

namespace App\Enums;

enum OrderStatusEnum:string
{
    case PROCESSING = 'processing';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case ON_ITS_WAY = 'on_its_way';
}
