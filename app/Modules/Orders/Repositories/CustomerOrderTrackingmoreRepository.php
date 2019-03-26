<?php

namespace App\Modules\Orders\Repositories;

use App\Models\Customer\OrderTrackingmore;
use Illuminate\Support\Facades\DB;

class CustomerOrderTrackingmoreRepository
{
    public static function addTableGetId($trackInfo)
    {
        return OrderTrackingmore::create($trackInfo)->id;
    }

    public static function updateTable($id, $trackInfo)
    {
        return OrderTrackingmore::where('id', $id)->update($trackInfo);
    }

    public static function getTable($orderId)
    {
        return OrderTrackingmore::where('order_id', $orderId)->first();
    }
}