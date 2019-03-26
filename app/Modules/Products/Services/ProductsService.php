<?php

namespace App\Modules\Products\Services;


use App\Models\Category\Category;
use App\Models\Category\CategoryAttrvalueMapping;
use App\Models\Category\FrontCategory;
use App\Models\Currency;
use App\Models\Product\CodProduct;
use App\Models\Website\PcCategory;
use App\Models\Product\Products;
use App\Modules\Home\Services\CurrencyService;
use App\Modules\Home\Services\PcCategoryService;
use App\Modules\Products\Repositories\AuditProductsSkuRepository;
use App\Modules\Products\Repositories\CategoryRepository;
use App\Modules\Products\Repositories\ProductsRepository;
use App\Modules\Products\Repositories\ProductsSkuRepository;
use App\Modules\Promotions\Services\PromotionsService;
use App\Services\ApiResponse;
use App\Services\ElasticSearch;

class ProductsService
{
    public static function getList($request)
    {
        $currency_code = $request->input('currency_code', '');
        if (!$currency_code) {
            $currency_code = (new CurrencyService)->getDefaultCurrency();
        }
        $currency = Currency::where('currency_code', $currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $page = $request->input('page', 1) > 0 ? $request->input('page', 1) : 1;
        $perPage = $request->input('perPage', 20);

        // 新建查询构造器对象，设置只搜索上架商品，设置分页
        $builder = (new ElasticSearch())->onSale();

        // 置顶排序
        if ($top_ids = $request->input('top_ids', '')) {
            $builder->topOrder($top_ids);
        }

        // 关键词搜索
        if ($title = $request->input('title', '')) {
            $keywords = array_filter(explode(' ', $title));
            // 调用查询构造器的关键词筛选
            $builder->keywords($keywords);
        }

        if ($minPrice = $request->input('minprice')) {
            if (is_numeric($minPrice)) {
                // 转成美元
                $minPrice = round($minPrice / $currency->rate, 2);
                $builder->minPrice($minPrice);
            }
        }

        if ($maxPrice = $request->input('maxprice')) {
            if (is_numeric($maxPrice)) {
                // 转成美元
                $maxPrice = round($maxPrice / $currency->rate, 2);
                $builder->maxPrice($maxPrice);
            }
        }


        $category_one = [];
        // 类目ID搜索
        $cate_ids = $request->input('cate');
        if ($cate_ids) {
            $isFront = false;
            if (mb_strpos($cate_ids, 'front') !== false) {
                $isFront = true;
                $cate_ids_arr = explode(',', $cate_ids);
                array_walk($cate_ids_arr, function (&$item) {
                    $item = explode('-', $item)[0];
                });
                $categories = FrontCategory::whereIn('id', $cate_ids_arr)->get();
            } else {

                $categories = Category::whereIn('id', explode(',', $cate_ids))->get();
            }
            if ($categories) {
                // 调用查询构造器的类目筛选
                $builder->categories($categories, $isFront);
                $first_category = $categories[0] ?? '';
                $category_one = $categories->map(function ($item) {
                    return explode(',', $item->category_ids)[1] ?? $item->id;
                })->unique()->toArray();
            }
        }

        // 从用户请求参数获取 filters
        if ($filterString = $request->input('filter')) {
            $filterString = is_array($filterString) ? $filterString : [$filterString];
            $builder->propertyFilter($filterString);
        }

        $builder->aggregateProperties();
        $builder = $builder->paginate($perPage, $page);
        // 是否有提交 order 参数，如果有就赋值给 $order 变量
        // order 参数用来控制商品的排序规则
        $order_status = false;
        if ($order = $request->input('sort', '')) {
            // 是否是以 _asc 或者 _desc 结尾
            if (preg_match('/^(.+)-(asc|desc)$/', $order, $m)) {
                // 如果字符串的开头是这 3 个字符串之一，说明是一个合法的排序值(价格，销量，上新时间)
                if (in_array($m[1], ['price', 'orders', 'new'])) {
                    // 调用查询构造器的排序
                    $order_status = true;
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }
        if (!$order_status) {
            $builder->orderBy('sort', 'desc');
            $builder->orderBy('new', 'desc');
            $builder->orderBy('id', 'desc');
        }
        // dd($builder->getParams());
        $result = app('es')->search($builder->getParams());
        $elasticsearch_data = collect($result['hits']['hits']);
        // 获取所有的属性、属性值
        $elasticsearch_propertes = $result['aggregations']['properties']['properties']['buckets'];
        // 通过 collect 函数将返回结果转为集合，并通过集合的 pluck 方法取到返回的商品 ID 数组
        $productIds = collect($elasticsearch_data)->pluck('_id')->all();
        // 获取商品总数
        $product_total = $result['hits']['total'];
        // 通过 whereIn 方法从数据库中读取商品数据
        $products = Products::query()->byIds($productIds)->get();
        $goodData = [];
        foreach ($products as $good) {
            $tmp = [
                'id'           => $good->id,
                'name'         => $good->good_en_title,
                'img'          => $good->main_pic,
                'img_type'     => $good->pic_type,
                'origin_price' => round($good->origin_price * $currency->rate, $currency->digit),
                'price'        => round($good->price * $currency->rate, $currency->digit),
                'discount'     => round((1 - round($good->price / $good->origin_price,
                                4)) * 100) . '%',
                'stock'        => $good->good_stock,
                'rebate'       => round(round(($good->getProductSkus->max('price') * $currency->rate) * $good->rebate_level_one) / 100,
                    $currency->digit),
                // 'shelf_at'     => $good->shelf_at,
            ];
            $goodData[] = $tmp;
        }
        $nav = [];
        $title = $title ?: '';
        $describe = '';
        if ($cate_ids) {//mb_strpos(strtolower($request->path()), 'pc') !== false
            if (mb_strpos($cate_ids, 'front') !== false) {
                $nav = PcCategoryService::getPath(join(',', $cate_ids_arr));
            } else {
                $nav = PcCategoryService::getPath($cate_ids);
            }
            if ($nav) {
                $title = end($nav)['name'];
                $describe = '';
            }
        }
        // 查找相关搜索属性及属性值
        $cate_attr_map = self::getCateAttrMapping($elasticsearch_propertes);
        return ApiResponse::success([
            'goods'           => $goodData,
            'nav'             => $nav,
            'describe'        => $describe,
            'title'           => $title,
            'total_goods'     => $product_total,
            'currency_symbol' => $currency->symbol,
            'cate_attr_map'   => $cate_attr_map,
            'total_page'      => ceil($product_total / $perPage)
        ]);
    }

    public static function getProductInfo($productId)
    {
        return ProductsRepository::getProductInfo($productId);
    }

    public static function getSKuInfo($productId)
    {
        $skus = (object)ProductsSkuRepository::getSkus($productId);
        $skuImages = ProductsSkuRepository::getSkuImages($productId);
        foreach ($skus as $key => $sku) {
            $skus[$key]->icon = cdnUrl($skus[$key]->icon);
            $images = [];

            foreach ($skuImages as $skuImage) {
                if ($sku->id == $skuImage->sku_id) {
                    array_push($images, cdnUrl($skuImage->src));
                }
            }
            $skus[$key]->images = $images;
        }
        return $skus;
    }

    public static function getProductAttrs($productId, $categoryId)
    {
        return ProductsRepository::getProductAttrs($productId, $categoryId);
    }

    public static function getCateDetailAttr($cateId)
    {
        return CategoryRepository::getCateDetailAttr($cateId);
    }

    public static function detailCalculate(
        $productInfo,
        $productSkus,
        $promotions,
        $promotionSkus,
        $coupons,
        $productAttrs,
        $cateAttrs,
        $userCoupon = [],
        $currency
    ) {
        $result = [];
        $couponData = [];
        $promotionData = [];
        $propData = [];
        $result['code'] = $productInfo->good_code;
        $result['name'] = $productInfo->good_en_title;
        $result['summary'] = $productInfo->good_en_summary;
        $result['images'] = array_values(array_filter(json_decode($productInfo->content, true) ?: []));
        $result['img_type'] = $productInfo->pic_type;
        if ($result['images']) {
            foreach ($result['images'] as &$image) {
                $image = cdnUrl($image);
            }
        }
        $result['video'] = $productInfo->video;
        $result['max_integral'] = 0;
        $result['category_path'] = CategoryRepository::getCategoryPath($productInfo->category_id);
        $productSkuAttrs = [];
        $productAttrIds = array_pluck($productAttrs, 'attr_id');
        $productAttrValueIds = explode(',', implode(',', array_pluck($productAttrs, 'value_ids')));
        $productAttrInfos = ProductsRepository::getAttrsByIds($productAttrIds);
        $productAttrValueInfos = ProductsRepository::getAttrValuesByIds($productAttrValueIds);
        $productAttrTypes = array_pluck($productAttrInfos, 'type', 'id');
        $productAttrInfos = array_pluck($productAttrInfos, 'en_name', 'id');
        $productAttrValueInfos = array_pluck($productAttrValueInfos, 'en_name', 'id');
        $result['commission'] = 0;
        foreach ($productSkus as $key => $productSku) {

            if (empty($result['commission']) || $productSku->price > $result['commission']) {
                $result['commission'] = $productSku->price;
            }

            foreach ($productAttrs as $productAttr) {
                if ($productSku->id == $productAttr->sku_id) {
                    $productAttrTmp = [
                        'name'     => $productAttrInfos[$productAttr->attr_id],
                        'value'    => self::getAttributrValue($productAttr->value_ids, $productAttrValueInfos),
                        'value_id' => $productAttr->value_ids,
                        'sort'     => $productAttr->sort,
                    ];
                    $productSkuAttrValues = isset($productSkuAttrs[$productAttr->attr_id]) ? array_pluck($productSkuAttrs[$productAttr->attr_id],
                        'value_id') : [];
                    if (!in_array($productAttrTmp['value_id'], $productSkuAttrValues)) {
                        $productSkuAttrs[$productAttr->attr_id][] = $productAttrTmp;
                    }
                }
            }
        }
        $result['commission'] = round(($result['commission'] * $productInfo->rebate_level_two / 100 * $productInfo->rebate_level_one / 100) * $currency->rate,
            $currency->digit);
        $productSkuAttrs = array_merge($productSkuAttrs);
        $sale_attr = [];
        foreach ($productSkuAttrs as $productSkuAttr) {
            $sale_attr[] = array_pluck($productSkuAttr, 'value');
        }
        $skuData = [];
        foreach ($productSkuAttrs[0] as $productSkuAttr0) {
            $skuDataTmp0 = $productSkuAttr0;
            if (isset($productSkuAttrs[1])) {
                // sku有二级，遍历二级数据
                foreach (collect($productSkuAttrs[1])->sortByDesc('sort')->toArray() as $productSkuAttr1) {
                    $skuDataTmp1 = $productSkuAttr1;
                    if (isset($productSkuAttrs[2])) {
                        // 遍历三级销售属性
                        foreach ($productSkuAttrs[2] as $productSkuAttr2) {
                            foreach ($productSkus as $productSku) {
                                if ($productSku->value_ids == implode(',', [
                                        $productSkuAttr0['value_id'],
                                        $productSkuAttr1['value_id'],
                                        $productSkuAttr2['value_id']
                                    ])) {
                                    if (!isset($skuDataTmp0['id'])) {
                                        $skuDataTmp0['id'] = $skuDataTmp1['id'] = $productSku->id;
                                        $skuDataTmp0['icon'] = $skuDataTmp1['icon'] = $productSku->icon;
                                        $skuDataTmp0['img'] = $skuDataTmp1['img'] = array_unique($productSku->images);
                                        $skuDataTmp0['price'] = $skuDataTmp1['price'] = round($productSku->price * $currency->rate,
                                            $currency->digit);
                                        $skuDataTmp0['usd_price'] = $skuDataTmp1['usd_price'] = $productSku->price;
                                        $skuDataTmp0['origin_price'] = $skuDataTmp1['origin_price'] = round($productSku->origin_price * $currency->rate,
                                            $currency->digit);
                                        $skuDataTmp0['discount'] = $skuDataTmp1['discount'] = round((1 - round($productSku->price / $productSku->origin_price,
                                                        4)) * 100) . '%';
                                        $skuDataTmp0['prom_price'] = $skuDataTmp1['prom_price'] = 0;
                                        $skuDataTmp0['stock'] = $skuDataTmp1['stock'] = $productSku->good_stock;
                                        $skuDataTmp0['rebate'] = $skuDataTmp1['rebate'] = round($skuDataTmp0['price'] * $productInfo->rebate_level_one / 100,
                                            $currency->digit);

                                    }
                                    $skuDataTmp2 = [
                                        'id'           => $productSku->id,
                                        'name'         => $productSkuAttr2['name'],
                                        'value'        => $productSkuAttr2['value'],
                                        'icon'         => $productSku->icon,
                                        'img'          => array_unique($productSku->images),
                                        'price'        => round($productSku->price * $currency->rate, $currency->digit),
                                        'usd_price'    => $productSku->price,
                                        'origin_price' => round($productSku->origin_price * $currency->rate,
                                            $currency->digit),
                                        'discount'     => round((1 - round($productSku->price / $productSku->origin_price,
                                                        4)) * 100) . '%',
                                        'prom_price'   => 0,
                                        'stock'        => $productSku->good_stock,
                                        'sub'          => []
                                    ];
                                    if (!empty($promotionSkus)) {
                                        foreach ($promotionSkus as $promotionSku) {
                                            if ($productSku->id == $promotionSku->sku_id && $promotionSku->price < $productSku->price) {
                                                // $skuDataTmp2['origin_price'] = $skuDataTmp2['price'];
                                                $skuDataTmp2['price'] = round($promotionSku->price * $currency->rate,
                                                    $currency->digit);
                                                $skuDataTmp2['prom_price'] = round($promotionSku->price * $currency->rate,
                                                    $currency->digit);
                                                $skuDataTmp2['discount'] = round((1 - round($skuDataTmp2['price'] / $skuDataTmp2['origin_price'],
                                                                4)) * 100) . '%';
                                                if (empty($skuDataTmp0['prom_price'])) {
                                                    // $skuDataTmp0['origin_price'] = $skuDataTmp1['origin_price'] = $skuDataTmp2['origin_price'];
                                                    $skuDataTmp0['price'] = $skuDataTmp1['price'] = $skuDataTmp2['price'];
                                                    $skuDataTmp0['usd_price'] = $skuDataTmp1['usd_price'] = $skuDataTmp2['usd_price'];
                                                    $skuDataTmp0['prom_price'] = $skuDataTmp1['prom_price'] = $skuDataTmp2['prom_price'];
                                                    $skuDataTmp0['discount'] = $skuDataTmp1['discount'] = $skuDataTmp2['discount'];
                                                }
                                                break;
                                            }
                                        }
                                    }
                                    $skuDataTmp2['rebate'] = round($skuDataTmp2['price'] * $productInfo->rebate_level_one / 100,
                                        $currency->digit);
                                    $skuDataTmp1['sub'][] = $skuDataTmp2;
                                }
                            }
                            $skuDataTmp0['sub'][] = $skuDataTmp1;
                        }
                    } else {
                        // 没有三级，直接遍历二级
                        foreach ($productSkus as $key => $productSku) {
                            $productSkuValueIds = explode(',', $productSku->value_ids);
                            $productSkuAttrValueIds = [$productSkuAttr0['value_id'], $productSkuAttr1['value_id']];
                            sort($productSkuValueIds);
                            sort($productSkuAttrValueIds);
                            if ($productSkuValueIds == $productSkuAttrValueIds) {
                                if (!isset($skuDataTmp0['id'])) {
                                    $skuDataTmp0['id'] = $productSku->id;
                                    $skuDataTmp0['icon'] = $productSku->icon;
                                    $skuDataTmp0['img'] = array_unique($productSku->images);
                                    $skuDataTmp0['price'] = round($productSku->price * $currency->rate,
                                        $currency->digit);
                                    $skuDataTmp0['usd_price'] = $productSku->price;
                                    $skuDataTmp0['origin_price'] = round($productSku->origin_price * $currency->rate,
                                        $currency->digit);
                                    $skuDataTmp0['discount'] = round((1 - round($productSku->price / $productSku->origin_price,
                                                    4)) * 100) . '%';
                                    $skuDataTmp0['prom_price'] = 0;
                                    $skuDataTmp0['stock'] = $productSku->good_stock;
                                    $skuDataTmp0['rebate'] = round($skuDataTmp0['price'] * $productInfo->rebate_level_one / 100,
                                        $currency->digit);
                                }
                                $skuDataTmp1 = [
                                    'id'           => $productSku->id,
                                    'name'         => $productSkuAttr1['name'],
                                    'value'        => $productSkuAttr1['value'],
                                    'icon'         => $productSku->icon,
                                    'img'          => array_unique($productSku->images),
                                    'price'        => round($productSku->price * $currency->rate, $currency->digit),
                                    'usd_price'    => $productSku->price,
                                    'origin_price' => round($productSku->origin_price * $currency->rate,
                                        $currency->digit),
                                    'discount'     => round((1 - round($productSku->price / $productSku->origin_price,
                                                    4)) * 100) . '%',
                                    'prom_price'   => 0,
                                    'stock'        => $productSku->good_stock,
                                    'sub'          => []
                                ];
                                if (!empty($promotionSkus)) {
                                    foreach ($promotionSkus as $promotionSku) {
                                        if ($productSku->id == $promotionSku->sku_id && $promotionSku->price < $productSku->price) {
                                            // $skuDataTmp1['origin_price'] = $skuDataTmp1['price'];
                                            $skuDataTmp1['price'] = round($promotionSku->price * $currency->rate,
                                                $currency->digit);
                                            $skuDataTmp1['usd_price'] = $promotionSku->price;
                                            $skuDataTmp1['prom_price'] = round($promotionSku->price * $currency->rate,
                                                $currency->digit);
                                            $skuDataTmp1['discount'] = round((1 - round($skuDataTmp1['price'] / $skuDataTmp1['origin_price'],
                                                            4)) * 100) . '%';
                                            if (empty($skuDataTmp0['prom_price'])) {
                                                // $skuDataTmp0['origin_price'] = $skuDataTmp1['origin_price'];
                                                $skuDataTmp0['price'] = $skuDataTmp1['price'];
                                                $skuDataTmp0['usd_price'] = $skuDataTmp1['usd_price'];
                                                $skuDataTmp0['prom_price'] = $skuDataTmp1['prom_price'];
                                                $skuDataTmp0['discount'] = $skuDataTmp1['discount'];
                                            }
                                            break;
                                        }
                                    }
                                }
                                $skuDataTmp1['rebate'] = round($skuDataTmp1['price'] * $productInfo->rebate_level_one / 100,
                                    $currency->digit);
                                $skuDataTmp0['sub'][] = $skuDataTmp1;
                            }
                        }
                    }
                }
                if (count($skuDataTmp0['sub']) > 0) {
                    $skuDataTmp0['stock'] = max(array_pluck($skuDataTmp0['sub'], 'stock'));
                }
                $skuData[] = $skuDataTmp0;
            } else {
                // 如果sku只有一级，直接遍历sku数据
                foreach ($productSkus as $key => $productSku) {
                    if ($productSku->value_ids == $productSkuAttr0['value_id']) {
                        $skuDataTmp0 = [
                            'id'           => $productSku->id,
                            'name'         => $productSkuAttr0['name'],
                            'value'        => $productSkuAttr0['value'],
                            'icon'         => $productSku->icon,
                            'img'          => array_unique($productSku->images),
                            'price'        => round($productSku->price * $currency->rate, $currency->digit),
                            'usd_price'    => $productSku->price,
                            'origin_price' => round($productSku->origin_price * $currency->rate, $currency->digit),
                            'discount'     => round((1 - round($productSku->price / $productSku->origin_price,
                                            4)) * 100) . '%',
                            'prom_price'   => 0,
                            'stock'        => $productSku->good_stock,
                            'sub'          => []
                        ];
                        if (!empty($promotionSkus)) {
                            foreach ($promotionSkus as $promotionSku) {
                                if ($productSku->id == $promotionSku->sku_id && $promotionSku->price < $productSku->price) {
                                    // $skuDataTmp0['origin_price'] = $skuDataTmp0['price'];
                                    $skuDataTmp0['price'] = round($promotionSku->price * $currency->rate,
                                        $currency->digit);
                                    $skuDataTmp0['usd_price'] = $promotionSku->price;
                                    $skuDataTmp0['prom_price'] = round($promotionSku->price * $currency->rate,
                                        $currency->digit);
                                    $skuDataTmp0['discount'] = round((1 - round($skuDataTmp0['price'] / $skuDataTmp0['origin_price'],
                                                    4)) * 100) . '%';
                                    break;
                                }
                            }
                        }
                        $skuDataTmp0['rebate'] = round($skuDataTmp0['price'] * $productInfo->rebate_level_one / 100,
                            $currency->digit);
                        $skuData[] = $skuDataTmp0;
                    }
                }
            }
        }
        if ($promotions) {
            $promotionData['id'] = $promotions->id;
            $promotionData['role'] = PromotionsService::getPromotionMsg($promotions);
            $promotionData['start_at'] = $promotions->start_at;
            $promotionData['end_at'] = $promotions->end_at;
        }
        if ($coupons) {
            foreach ($coupons as $coupon) {
                $couponTmp = [
                    'id'              => $coupon->id,
                    'type'            => $coupon->rebate_type,
                    'currency_symbol' => Currency::getSymbolByCode($coupon->currency_code),
                    'price'           => $coupon->coupon_price,
                    'use_price'       => $coupon->coupon_use_price,
                    'startdate'       => $coupon->coupon_grant_startdate,
                    'enddate'         => $coupon->coupon_grant_enddate,
                    'datestatus'      => 1
                ];
                if ($coupon->coupon_number && $coupon->coupon_number <= $coupon->receive_number) {
                    $couponTmp['datestatus'] = 3;
                }
                if (in_array($coupon->id, $userCoupon)) {
                    $couponTmp['datestatus'] = 2;
                }
                $couponData[] = $couponTmp;
            }
        }
        if ($productInfo->pic) {
            $result['good_images'] = json_decode($productInfo->pic, true);
            foreach ($result['good_images'] as &$image) {
                $image = cdnUrl($image);
            }
        } else {
            $count_sku_image = [];
            foreach ($skuData as $datum) {
                $count_sku_image = array_merge($count_sku_image, $datum['img']);
            }
            $result['good_images'] = $count_sku_image;
        }
        if (isset($skuData[0]['img']) && $productInfo->main_pic) {
            //商品图临时解决方案
            $skuData[0]['img'] = array_merge($result['good_images'], $skuData[0]['img']);

            array_unshift($skuData[0]['img'], $productInfo->main_pic);
            $skuData[0]['img'] = array_values(array_unique($skuData[0]['img']));
        }
        foreach ($cateAttrs as $cateAttr) {
            if (isset($productAttrInfos[$cateAttr->attr_id])) {
                $attrName = $productAttrInfos[$cateAttr->attr_id];
                if (strtolower(trim($attrName)) == strtolower('Size Chart')) {
                    continue;
                }
                foreach ($productAttrs as $productAttr) {
                    $attrValue = [];
                    if ($productAttr->attr_id == $cateAttr->attr_id) {
                        if ($productAttrTypes[$cateAttr->attr_id] == 2) //非标准属性
                        {
                            $attrValue[] = $productAttr->value_name;
                        } else {
                            $attrValue[] = self::getAttributrValue($productAttr->value_ids, $productAttrValueInfos);
                            // $attrValue[] = $productAttrValueInfos[$productAttr->value_ids];
                        }
                        if (!empty($attrValue)) {
                            $propData[$attrName] = implode(',', $attrValue);
                        }
                        break;
                    }
                }
            }
        }
        $sizeChart = $productInfo->sizeChart;
        $cateInfo = CategoryRepository::getCateInfo($productInfo->category_id);
        $sizeChartData = [
            'type'       => 'none',
            'data'       => [
                'cm'   => '',
                'inch' => '',

            ],
            'other_desc' => ''
        ];
        if ($cateInfo->size_chart || $cateInfo->size_chart_inch) {
            $sizeChartData = [
                'type'       => 'image',
                'data'       => [
                    'cm'   => $cateInfo->size_chart,
                    'inch' => $cateInfo->size_chart_inch
                ],
                'other_desc' => $cateInfo->other_desc
            ];
        }
        if ($sizeChart) {
            $sizeAttrs = explode(',', $productInfo->sizeChart->attr);
            $sizeAttrInfos = ProductsRepository::getAttrValuesByIds($sizeAttrs);
            $sizeSize = explode(',', $productInfo->sizeChart->size);
            $sizeData = json_decode($sizeChart->size_chart);
            $dataIds = array_pluck($sizeAttrInfos, 'id');
            $dataNames = array_pluck($sizeAttrInfos, 'en_name');
            array_unshift($dataNames, 'Size');
            $resultData = [];
            $inchResultData = [];
            $proportion = 0.393700787;
            $popingKey = [];
            foreach ($sizeData as $key => $sizeDatum) {
                $lineData = [];
                $inchLineData = [];
                array_push($lineData, $sizeSize[$key]);
                array_push($inchLineData, $sizeSize[$key]);
                foreach ($sizeDatum as $innerKey => $value) {
                    $index = array_search($value->id, $dataIds);
                    if ($index === false) {
                        array_push($popingKey, $innerKey + 1);
                        // array_push($lineData, '');
                        // array_push($inchLineData, '');
                    } else {
                        if ($value->id == $dataIds[$index]) {
                            if (!floatval($value->value)) {
                                array_push($popingKey, $innerKey + 1);
                            } else {
                                array_push($lineData, $value->value);
                                array_push($inchLineData, strval(round((float)$value->value * $proportion, 1)));
                            }
                        }
                    }
                }
                array_push($resultData, $lineData);
                array_push($inchResultData, $inchLineData);
            }

            $popingKeyRev = array_reverse(array_unique($popingKey));
            foreach ($popingKeyRev as $revKey) {
                unset($dataNames[$revKey]);
            }
            array_unshift($resultData, $dataNames);
            array_unshift($inchResultData, $dataNames);
            if (count($dataNames) > 1) {
                $sizeChartData = [
                    'type'       => 'data',
                    'data'       => [
                        'cm'   => $resultData,
                        'inch' => $inchResultData,
                    ],
                    'other_desc' => $cateInfo->other_desc
                ];
            }
        }

        $result['recommend'] = self::getProductRecommend($productInfo->category_id, $productInfo->id, $currency);
        $result['promotion'] = $promotionData;
        $result['coupon'] = $couponData;
        $result['style'] = $skuData;
        $result['size_chart'] = $sizeChartData;
        $result['prop'] = $propData;
        $result['sale_attr'] = $sale_attr;
        $result['currency_symbol'] = $currency->symbol;
        return $result;
    }

    private static function getAttributrValue($value_ids, $productAttrValueInfos)
    {
        $value_id_arr = explode(',', $value_ids);
        $str = '';
        foreach ($value_id_arr as $value_id) {
            if (!isset($productAttrValueInfos[$value_id])) {
                continue;
            }
            $str .= $productAttrValueInfos[$value_id] . ', ';
        }
        return rtrim($str, ', ');
    }

    /**
     * @function 获取分类下所有商品
     * @param $cate_id
     * @param int $limit
     * @return array
     */
    public static function getCategoryGoods($cate_id, $limit = 5)
    {
        $category = CategoryRepository::getCateInfo($cate_id);
        $goods = [];
        if (!$category) {
            return $goods;
        }
        $model = Products::where(['status' => 1])
            ->selectRaw('id,good_en_title as name,main_pic as img,ROUND(price,2) as price,ROUND(origin_price,2) as origin_price,ROUND(price*rebate_level_one/100,2) as rebate,good_stock as stock')
            ->orderBy('sort', 'desc')
            ->orderBy('orders', 'desc')
            ->orderBy('shelf_at', 'desc')
            ->limit($limit);
        switch ($category->level) {
            case 1:
                $goods = $model->where('category_path', 'like', '0,' . $cate_id . ',%')->get();
                break;
            case 2:
                $category_ids = Category::where('parent_id', $cate_id)->pluck('id')->toArray();
                $goods = $model->whereIn('category_id', $category_ids)->get();
                break;
            case 3:
                $goods = $model->where('category_id', $cate_id)->get();
                break;
        }

        return $goods;
    }

    /**
     * @function 搜索结果的ropertes
     * @param $elasticsearch_propertes
     * @return array
     */
    public static function getCateAttrMapping($elasticsearch_propertes)
    {
        $result = [];
        foreach ($elasticsearch_propertes as $elasticsearch_properte) {
            $tmp['attr_name'] = $elasticsearch_properte['key'];
            $tmp['attr_values'] = array_filter(collect($elasticsearch_properte['value']['buckets'])->pluck('key')->toArray());
            sort($tmp['attr_values']);
            switch (strtolower($tmp['attr_name'])) {
                case 'color':
                    $tmp['attr_type'] = 1;
                    break;
                case 'size':
                    $tmp['attr_type'] = 2;
                    break;
                default:
                    $tmp['attr_type'] = 3;
                    break;
            }
            $result[] = $tmp;
        }
        return collect($result)->sortBy('attr_type')->toArray();
    }

    public static function getAddPrice()
    {
        $price = 0;
        if ($skuId = request()->input('sku_id')) {
            $skuInfo = ProductsSkuRepository::getSkuById($skuId);
            $num = request()->input('num', 0);
            $price = round($num * $skuInfo->price, 2);
        }
        return $price;
    }

    public static function getProductRecommend($categoryId, $productId, $currency, $limit = 5)
    {
        $recommends = [];
        $recommendGoods = Products::where(['category_id' => $categoryId])
            ->whereNotIn('id', [$productId])
            ->orderBy('sort', 'desc')
            ->orderBy('orders', 'desc')
            ->orderBy('shelf_at', 'desc')
            ->limit($limit)
            ->get();
        foreach ($recommendGoods as $item) {
            $tmp = [];
            $tmp['id'] = $item->id;
            $tmp['name'] = $item->good_en_title;
            $tmp['img'] = $item->main_pic;
            $tmp['price'] = round($item->price * $currency->rate, $currency->digit);
            $tmp['origin_price'] = round($item->origin_price * $currency->rate, $currency->digit);
            $tmp['rebate'] = round($item->price * $currency->rate * $item->rebate_level_one / 100, $currency->digit);
            $tmp['stock'] = $item->stock;
            $recommends[] = $tmp;
        }
        return $recommends;
    }

    /**
     * @function 组装商品详情页数据
     * @param $productInfo
     * @param $productSkus
     * @param $productAttrs
     * @param $cateAttrs
     * @param $currency
     * @return array
     */
    public static function codDetailCalculate($productInfo, $productSkus, $productAttrs, $cateAttrs, $currency)
    {
        $result = [];
        $propData = [];
        $result['code'] = $productInfo->good_code;
        $result['name'] = $productInfo->good_en_title;
        $result['summary'] = $productInfo->good_en_summary;
        $result['images'] = array_values(array_filter(json_decode($productInfo->content, true) ?: []));
        if ($result['images']) {
            foreach ($result['images'] as &$image) {
                $image = cdnUrl($image);
            }
        }
        $result['video'] = $productInfo->video;
        $result['max_integral'] = 0;
        $result['category_path'] = CategoryRepository::getCategoryPath($productInfo->category_id);
        $productSkuAttrs = [];
        $productAttrIds = array_pluck($productAttrs, 'attr_id');
        $productAttrValueIds = explode(',', implode(',', array_pluck($productAttrs, 'value_ids')));
        $productAttrInfos = ProductsRepository::getAttrsByIds($productAttrIds);
        $productAttrValueInfos = ProductsRepository::getAttrValuesByIds($productAttrValueIds);
        $productAttrTypes = array_pluck($productAttrInfos, 'type', 'id');
        $productAttrInfos = array_pluck($productAttrInfos, 'en_name', 'id');
        $productAttrValueInfos = array_pluck($productAttrValueInfos, 'en_name', 'id');
        $result['commission'] = 0;
        foreach ($productSkus as $key => $productSku) {
            if (empty($result['commission']) || $productSku->price > $result['commission']) {
                $result['commission'] = $productSku->price;
            }

            foreach ($productAttrs as $productAttr) {
                if ($productSku->id == $productAttr->sku_id) {
                    $productAttrTmp = [
                        'name'     => $productAttrInfos[$productAttr->attr_id],
                        'value'    => self::getAttributrValue($productAttr->value_ids, $productAttrValueInfos),
                        'value_id' => $productAttr->value_ids
                    ];
                    $productSkuAttrValues = isset($productSkuAttrs[$productAttr->attr_id]) ? array_pluck($productSkuAttrs[$productAttr->attr_id],
                        'value_id') : [];
                    if (!in_array($productAttrTmp['value_id'], $productSkuAttrValues)) {
                        $productSkuAttrs[$productAttr->attr_id][] = $productAttrTmp;
                    }
                }
            }
        }
        $result['commission'] = round(($result['commission'] * $productInfo->rebate_level_two / 100 * $productInfo->rebate_level_one / 100) * $currency->rate,
            $currency->digit);
        $productSkuAttrs = array_merge($productSkuAttrs);
        $sale_attr = [];
        foreach ($productSkuAttrs as $productSkuAttr) {
            $sale_attr[] = array_pluck($productSkuAttr, 'value');
        }
        $skuData = [];
        foreach ($productSkuAttrs[0] as $productSkuAttr0) {
            $skuDataTmp0 = $productSkuAttr0;
            if (isset($productSkuAttrs[1])) {
                // sku有二级，遍历二级数据
                foreach ($productSkuAttrs[1] as $productSkuAttr1) {
                    $skuDataTmp1 = $productSkuAttr1;
                    if (isset($productSkuAttrs[2])) {
                        // 遍历三级销售属性
                        foreach ($productSkuAttrs[2] as $productSkuAttr2) {
                            foreach ($productSkus as $productSku) {
                                if ($productSku->value_ids == implode(',', [
                                        $productSkuAttr0['value_id'],
                                        $productSkuAttr1['value_id'],
                                        $productSkuAttr2['value_id']
                                    ])) {
                                    if (!isset($skuDataTmp0['id'])) {
                                        $skuDataTmp0['id'] = $skuDataTmp1['id'] = $productSku->id;
                                        $skuDataTmp0['icon'] = $skuDataTmp1['icon'] = $productSku->icon;
                                        $skuDataTmp0['img'] = $skuDataTmp1['img'] = $productSku->images;
                                        $skuDataTmp0['price'] = $skuDataTmp1['price'] = round($productSku->price * $currency->rate,
                                            $currency->digit);
                                        $skuDataTmp0['usd_price'] = $skuDataTmp1['usd_price'] = $productSku->price;
                                        $skuDataTmp0['origin_price'] = $skuDataTmp1['origin_price'] = round($productSku->origin_price * $currency->rate,
                                            $currency->digit);
                                        $skuDataTmp0['discount'] = $skuDataTmp1['discount'] = round((1 - round($productSku->price / $productSku->origin_price,
                                                        4)) * 100) . '%';
                                        $skuDataTmp0['prom_price'] = $skuDataTmp1['prom_price'] = 0;
                                        $skuDataTmp0['stock'] = $skuDataTmp1['stock'] = $productSku->good_stock;
                                        $skuDataTmp0['rebate'] = $skuDataTmp1['rebate'] = round($skuDataTmp0['price'] * $productInfo->rebate_level_one / 100,
                                            $currency->digit);

                                    }
                                    $skuDataTmp2 = [
                                        'id'           => $productSku->id,
                                        'name'         => $productSkuAttr2['name'],
                                        'value'        => $productSkuAttr2['value'],
                                        'icon'         => $productSku->icon,
                                        'img'          => $productSku->images,
                                        'price'        => round($productSku->price * $currency->rate, $currency->digit),
                                        'usd_price'    => $productSku->price,
                                        'origin_price' => round($productSku->origin_price * $currency->rate,
                                            $currency->digit),
                                        'discount'     => round((1 - round($productSku->price / $productSku->origin_price,
                                                        4)) * 100) . '%',
                                        'prom_price'   => 0,
                                        'stock'        => $productSku->good_stock,
                                        'sub'          => []
                                    ];
                                    if (!empty($promotionSkus)) {
                                        foreach ($promotionSkus as $promotionSku) {
                                            if ($productSku->id == $promotionSku->sku_id && $promotionSku->price < $productSku->price) {
                                                // $skuDataTmp2['origin_price'] = $skuDataTmp2['price'];
                                                $skuDataTmp2['price'] = round($promotionSku->price * $currency->rate,
                                                    $currency->digit);
                                                $skuDataTmp2['prom_price'] = round($promotionSku->price * $currency->rate,
                                                    $currency->digit);
                                                $skuDataTmp2['discount'] = round((1 - round($skuDataTmp2['price'] / $skuDataTmp2['origin_price'],
                                                                4)) * 100) . '%';
                                                if (empty($skuDataTmp0['prom_price'])) {
                                                    // $skuDataTmp0['origin_price'] = $skuDataTmp1['origin_price'] = $skuDataTmp2['origin_price'];
                                                    $skuDataTmp0['price'] = $skuDataTmp1['price'] = $skuDataTmp2['price'];
                                                    $skuDataTmp0['usd_price'] = $skuDataTmp1['usd_price'] = $skuDataTmp2['usd_price'];
                                                    $skuDataTmp0['prom_price'] = $skuDataTmp1['prom_price'] = $skuDataTmp2['prom_price'];
                                                    $skuDataTmp0['discount'] = $skuDataTmp1['discount'] = $skuDataTmp2['discount'];
                                                }
                                                break;
                                            }
                                        }
                                    }
                                    $skuDataTmp2['rebate'] = round($skuDataTmp2['price'] * $productInfo->rebate_level_one / 100,
                                        $currency->digit);
                                    $skuDataTmp1['sub'][] = $skuDataTmp2;
                                }
                            }
                            $skuDataTmp0['sub'][] = $skuDataTmp1;
                        }
                    } else {
                        // 没有三级，直接遍历二级
                        foreach ($productSkus as $key => $productSku) {
                            $productSkuValueIds = explode(',', $productSku->value_ids);
                            $productSkuAttrValueIds = [$productSkuAttr0['value_id'], $productSkuAttr1['value_id']];
                            sort($productSkuValueIds);
                            sort($productSkuAttrValueIds);
                            if ($productSkuValueIds == $productSkuAttrValueIds) {
                                if (!isset($skuDataTmp0['id'])) {
                                    $skuDataTmp0['id'] = $productSku->id;
                                    $skuDataTmp0['icon'] = $productSku->icon;
                                    $skuDataTmp0['img'] = $productSku->images;
                                    $skuDataTmp0['price'] = round($productSku->price * $currency->rate,
                                        $currency->digit);
                                    $skuDataTmp0['usd_price'] = $productSku->price;
                                    $skuDataTmp0['origin_price'] = round($productSku->origin_price * $currency->rate,
                                        $currency->digit);
                                    $skuDataTmp0['discount'] = round((1 - round($productSku->price / $productSku->origin_price,
                                                    4)) * 100) . '%';
                                    $skuDataTmp0['prom_price'] = 0;
                                    $skuDataTmp0['stock'] = $productSku->good_stock;
                                    $skuDataTmp0['rebate'] = round($skuDataTmp0['price'] * $productInfo->rebate_level_one / 100,
                                        $currency->digit);
                                }
                                $skuDataTmp1 = [
                                    'id'           => $productSku->id,
                                    'name'         => $productSkuAttr1['name'],
                                    'value'        => $productSkuAttr1['value'],
                                    'icon'         => $productSku->icon,
                                    'img'          => $productSku->images,
                                    'price'        => round($productSku->price * $currency->rate, $currency->digit),
                                    'usd_price'    => $productSku->price,
                                    'origin_price' => round($productSku->origin_price * $currency->rate,
                                        $currency->digit),
                                    'discount'     => round((1 - round($productSku->price / $productSku->origin_price,
                                                    4)) * 100) . '%',
                                    'prom_price'   => 0,
                                    'stock'        => $productSku->good_stock,
                                    'sub'          => []
                                ];
                                if (!empty($promotionSkus)) {
                                    foreach ($promotionSkus as $promotionSku) {
                                        if ($productSku->id == $promotionSku->sku_id && $promotionSku->price < $productSku->price) {
                                            // $skuDataTmp1['origin_price'] = $skuDataTmp1['price'];
                                            $skuDataTmp1['price'] = round($promotionSku->price * $currency->rate,
                                                $currency->digit);
                                            $skuDataTmp1['usd_price'] = $promotionSku->price;
                                            $skuDataTmp1['prom_price'] = round($promotionSku->price * $currency->rate,
                                                $currency->digit);
                                            $skuDataTmp1['discount'] = round((1 - round($skuDataTmp1['price'] / $skuDataTmp1['origin_price'],
                                                            4)) * 100) . '%';
                                            if (empty($skuDataTmp0['prom_price'])) {
                                                // $skuDataTmp0['origin_price'] = $skuDataTmp1['origin_price'];
                                                $skuDataTmp0['price'] = $skuDataTmp1['price'];
                                                $skuDataTmp0['usd_price'] = $skuDataTmp1['usd_price'];
                                                $skuDataTmp0['prom_price'] = $skuDataTmp1['prom_price'];
                                                $skuDataTmp0['discount'] = $skuDataTmp1['discount'];
                                            }
                                            break;
                                        }
                                    }
                                }
                                $skuDataTmp1['rebate'] = round($skuDataTmp1['price'] * $productInfo->rebate_level_one / 100,
                                    $currency->digit);
                                $skuDataTmp0['sub'][] = $skuDataTmp1;
                            }
                        }
                    }
                }
                $skuData[] = $skuDataTmp0;
            } else {
                // 如果sku只有一级，直接遍历sku数据
                foreach ($productSkus as $key => $productSku) {
                    if ($productSku->value_ids == $productSkuAttr0['value_id']) {
                        $skuDataTmp0 = [
                            'id'           => $productSku->id,
                            'name'         => $productSkuAttr0['name'],
                            'value'        => $productSkuAttr0['value'],
                            'icon'         => $productSku->icon,
                            'img'          => $productSku->images,
                            'price'        => round($productSku->price * $currency->rate, $currency->digit),
                            'usd_price'    => $productSku->price,
                            'origin_price' => round($productSku->origin_price * $currency->rate, $currency->digit),
                            'discount'     => round((1 - round($productSku->price / $productSku->origin_price,
                                            4)) * 100) . '%',
                            'prom_price'   => 0,
                            'stock'        => $productSku->good_stock,
                            'sub'          => []
                        ];
                        if (!empty($promotionSkus)) {
                            foreach ($promotionSkus as $promotionSku) {
                                if ($productSku->id == $promotionSku->sku_id && $promotionSku->price < $productSku->price) {
                                    // $skuDataTmp0['origin_price'] = $skuDataTmp0['price'];
                                    $skuDataTmp0['price'] = round($promotionSku->price * $currency->rate,
                                        $currency->digit);
                                    $skuDataTmp0['usd_price'] = $promotionSku->price;
                                    $skuDataTmp0['prom_price'] = round($promotionSku->price * $currency->rate,
                                        $currency->digit);
                                    $skuDataTmp0['discount'] = round((1 - round($skuDataTmp0['price'] / $skuDataTmp0['origin_price'],
                                                    4)) * 100) . '%';
                                    break;
                                }
                            }
                        }
                        $skuDataTmp0['rebate'] = round($skuDataTmp0['price'] * $productInfo->rebate_level_one / 100,
                            $currency->digit);
                        $skuData[] = $skuDataTmp0;
                    }
                }
            }
        }

        foreach ($cateAttrs as $cateAttr) {
            if (isset($productAttrInfos[$cateAttr->attr_id])) {
                $attrName = $productAttrInfos[$cateAttr->attr_id];
                foreach ($productAttrs as $productAttr) {
                    $attrValue = [];
                    if ($productAttr->attr_id == $cateAttr->attr_id) {
                        if ($productAttrTypes[$cateAttr->attr_id] == 2) //非标准属性
                        {
                            $attrValue[] = $productAttr->value_name;
                        } else {
                            $attrValue[] = self::getAttributrValue($productAttr->value_ids, $productAttrValueInfos);
                            // $attrValue[] = $productAttrValueInfos[$productAttr->value_ids];
                        }
                        if (!empty($attrValue)) {
                            $propData[$attrName] = implode(',', $attrValue);
                        }
                        break;
                    }
                }
            }
        }
        $result['recommend'] = self::getProductRecommend($productInfo->category_id, $productInfo->id, $currency);
        $result['promotion'] = [];
        $result['coupon'] = [];
        $result['evaluation'] = self::getEvaluation();
        $result['style'] = $skuData;
        $result['prop'] = $propData;
        $result['sale_attr'] = $sale_attr;
        $result['currency_symbol'] = $currency->symbol;
        $result['google_num'] = $productInfo->codProduct->google_num ?? 1354;
        return $result;
    }

    /**
     * @function 评价
     * @return array
     */
    private static function getEvaluation()
    {
        $evaluation = [
            [
                'grade' => 5,
                'text'  => 'perfect',
                'img'   => 'https://images.waiwaimall.com/product/000032000002/5bc80664be552.jpg',
                'date'  => '2019-01-12 15:35:21',
                'phone' => '138****2458',
            ],
            [
                'grade' => 5,
                'text'  => 'perfect',
                'img'   => 'https://images.waiwaimall.com/product/000032000002/5bc80664be552.jpg',
                'date'  => '2019-01-10 09:35:21',
                'phone' => '184****5797',
            ],
        ];
        return $evaluation;
    }

    /**
     * @function 获取cod模式商品信息
     * @param $productId
     * @return mixed
     */
    public static function getCodProductInfo($productId)
    {
        return ProductsRepository::getCodProductInfo($productId);
    }

}