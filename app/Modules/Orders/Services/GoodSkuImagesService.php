<?php
/**
 * Created by PhpStorm.
 * User: rogers
 * Date: 18-10-15
 * Time: 下午3:54
 */

namespace App\Modules\Orders\Services;


use App\Modules\Orders\Repositories\GoodSkuImagesRepository;

class GoodSkuImagesService
{

    public static function getSkuImage($skuId)
    {
        return GoodSkuImagesRepository::getSkuImage($skuId);
    }
}