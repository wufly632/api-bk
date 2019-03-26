<?php
/**
 * Created by patpat.
 * User: zhijian.zhang
 * Date: 2018/5/8
 * Time: 11:33
 */

namespace App\Modules\Products\Repositories;


use App\Models\Product\AdminAttributeValue;
use App\Models\Product\Attribute;
use App\Models\Product\AttrValue;
use App\Models\Product\AuditGoods;
use App\Models\Product\AuditGoodSku;
use App\Models\Product\CodProduct;
use App\Models\Product\Products;
use App\Models\Product\ProductSku;

class ProductsRepository
{
    public static function getProductInfo($productId)
    {
        return Products::find($productId);
    }

    /**
     * @function 获取cod模式商品信息
     * @param $productId
     * @return mixed
     */
    public static function getCodProductInfo($productId)
    {
        return Products::withoutGlobalScopes()->find($productId);
    }

    public static function getProductAttrs($productId, $categoryId)
    {
        return AttrValue::leftJoin('admin_goods_category_attribute as agca', 'agca.attr_id', '='
            , 'goods_attr_value.attr_id')
            ->leftJoin('admin_attribute_value as aav', 'aav.id', '=', 'goods_attr_value.value_ids')
            ->selectRaw('goods_attr_value.*,aav.sort')
            ->where('goods_attr_value.good_id', $productId)
            ->where('agca.category_id', $categoryId)
            ->orderByDesc('agca.is_image')
            ->get();
    }

    public static function getAttrsByIds($attrIds)
    {
        return Attribute::whereIn('id', $attrIds)
            ->get();
    }

    public static function getAttrValuesByIds($valueIds)
    {
        return AdminAttributeValue::whereIn('id', $valueIds)
            ->get()->toArray();
    }

    public static function getProductByIds($goodIds)
    {
        return Products::query()->byIds($goodIds)->get();
    }

    public static function getAttrAndValuesByIds($valueIds)
    {
        return Attribute::join('admin_attribute_value', 'admin_attribute_value.attribute_id', '=', 'admin_attribute.id')
            ->selectRaw('admin_attribute.en_name as attr_name, admin_attribute_value.en_name as value_name')
            ->whereIn('admin_attribute_value.id', $valueIds)
            ->get();
    }

    public static function subProductStock($goodStocks)
    {
        foreach ($goodStocks as $key => $goodStock) {
            Products::where('id', $key)->decrement('good_stock', $goodStock);
        }
    }

    public static function subAuditProductStock($goodStocks)
    {
        foreach ($goodStocks as $key => $goodStock) {
            AuditGoods::where('id', $key)->decrement('good_stock', $goodStock);
        }
    }

    public static function subProductSkuStock($goodSkuStocks)
    {
        foreach ($goodSkuStocks as $key => $goodSkuStock) {
            ProductSku::where('id', $key)->decrement('good_stock', $goodSkuStock);
        }
    }

    public static function subAuditProductSkuStock($goodSkuStocks)
    {
        foreach ($goodSkuStocks as $key => $goodSkuStock) {
            AuditGoodSku::where('id', $key)->decrement('good_stock', $goodSkuStock);
        }
    }

    public static function getProductSkuInfoBySkus($skuIds)
    {
        return ProductSku::join('goods', 'goods.id', '=', 'good_skus.good_id')
            ->selectRaw('good_skus.id,goods.good_en_title,good_skus.icon,goods.pic_type')
            ->whereIn('good_skus.id', $skuIds)
            ->get();
    }

    public static function addProductStock($goodStocks)
    {
        foreach ($goodStocks as $key => $goodStock) {
            Products::where('id', $key)->increment('good_stock', $goodStock);
        }
    }

    public static function addAuditProductStock($goodStocks)
    {
        foreach ($goodStocks as $key => $goodStock) {
            AuditGoods::where('id', $key)->increment('good_stock', $goodStock);
        }
    }

    public static function addProductSkuStock($goodSkuStocks)
    {
        foreach ($goodSkuStocks as $key => $goodSkuStock) {
            ProductSku::where('id', $key)->increment('good_stock', $goodSkuStock);
        }
    }

    public static function addAuditProductSkuStock($goodSkuStocks)
    {
        foreach ($goodSkuStocks as $key => $goodSkuStock) {
            AuditGoodSku::where('id', $key)->increment('good_stock', $goodSkuStock);
        }
    }

    /**
     * @function 获取商品信息
     * @param $ids
     * @return array
     */
    public static function getProductInfoByIds($ids)
    {
        return Products::selectRaw('id, good_en_title as name,main_pic as img, pic_type as img_type, ROUND(price,2) as price, ROUND(origin_price,2) as origin_price,good_stock as stock,rebate_level_one as rebate')
            ->whereIn('id', $ids)
            ->limit(5)
            ->orderByDesc('sort')
            ->get();
    }

    /**
     * @function 获取推荐商品信息
     * @return array
     */
    public static function getRecommandProducts()
    {
        $where = [];
        $except_ids = [1, 172, 508, 798, 1019, 1721, 1540, 1258, 1300];
        foreach ($except_ids as $except_id) {
            $where[] = ['category_path', 'not like', '0,' . $except_id . ',%'];
        }
        return Products::selectRaw('id, good_en_title as name,main_pic as img,pic_type as img_type,ROUND(price,2) as price,ROUND(origin_price,2) as origin_price,good_stock as stock,TRUNCATE(rebate_level_one*price/100,2) as rebate')
            ->where($where)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20, ['id']);
    }

    /**
     * @function 获取下一条记录ID
     * @param $productId
     * @return mixed
     */
    public static function getNextCodProductId($productId)
    {
        $product_id = CodProduct::where('good_id', '>', $productId)->min('good_id');
        if (!$product_id) {
            $product_id = CodProduct::min('good_id');
        }
        return $product_id;
    }
}