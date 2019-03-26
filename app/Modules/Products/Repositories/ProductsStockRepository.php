<?php
/**
 * Created by patpat.
 * User: zhijian.zhang
 * Date: 2018/5/7
 * Time: 14:27
 */

namespace  App\Modules\Products\Repositories;

use App\Models\Product\Products;
use App\Models\Product\ProductSku;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ProductsStockRepository
{
    use DispatchesJobs;

    public static function getSkuStock($skuId)
    {
        return ProductSku::where('id', $skuId)->value('good_stock');
    }

    public static function getProductsByIds($goodIds)
    {
        return Products::whereIn('id', $goodIds)->get();
    }

    public static function getSkuStockBySkuIds($skuIds)
    {
        return ProductSku::whereIn('id', $skuIds)->get();
    }

}