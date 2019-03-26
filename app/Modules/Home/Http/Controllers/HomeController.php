<?php

namespace App\Modules\Home\Http\Controllers;

use App\Assistants\CLogger;
use App\Models\Currency;
use App\Models\Home\MobileCard;
use App\Models\Website\MobileCategory;
use App\Modules\Home\Services\CurrencyService;
use App\Models\Website\HomepageCard;
use App\Modules\Home\Services\HomeService;
use App\Modules\Home\Services\MobileCardService;
use App\Modules\Home\Services\MobileCategoryService;
use App\Modules\Home\Services\PcCategoryService;
use App\Modules\Products\Repositories\ProductsRepository;
use App\Modules\Products\Services\ProductsService;
use App\Modules\Promotions\Services\PromotionsService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\Error\Api;

class HomeController extends Controller
{

    protected $homeService;

    public function __construct(HomeService $homeService)
    {
        $this->homeService = $homeService;
    }


    public function index()
    {
        // $result = ProductsService::getList(\request());
        // if ($result['status'] != 200) {
        //     return $result;
        // }
        // $productList = $result['content'];
        $productList = ProductsRepository::getRecommandProducts();

        $resData = [];


        $currencyCode = \request()->input('currency_code', '');
        if (!$currencyCode) {
            $currencyCode = (new CurrencyService)->getDefaultCurrency();
        }
        $currency = Currency::where('currency_code', $currencyCode)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        try {
            $this->homeService::checkCurrency($currencyCode);
            $resData['goods'] = collect($productList->items())->each(function ($item) use ($currency) {
                $item->img = cdnUrl($item->img);
                $item->price = round($item->price * $currency->rate, $currency->digit);
                $item->origin_price = round($item->origin_price * $currency->rate, $currency->digit);
                $item->rebate = round($item->price * $item->rebate / 100, $currency->digit);
                $item->discount = round((1 - round($item->price / $item->origin_price,
                                4)) * 100) . '%';
                return $item;
            });
            $resData['total_page'] = $productList->lastPage();
            $resData['diypage'] = '';
            $resData['currency_symbol'] = $currency->symbol;
            $resData['banners'] = $this->homeService->getBanners();
            $resData['icons'] = $this->homeService->getIcons();
            $promotion = PromotionsService::getPromotion($currencyCode);
            if ($promotion) {
                $promotionGood = PromotionsService::getPromotionGood($promotion->id, $currencyCode);
                if ($promotion->activity_cycle != 0) {
                    $end_at_time = strtotime($promotion->end_at);
                    if (($end_at_time - time()) > $promotion->activity_cycle * 3600) {
                        $end_at = ($end_at_time - time()) % ($promotion->activity_cycle * 3600);
                    } else {
                        $end_at = $end_at_time - time();
                    }
                } else {
                    $end_at = strtotime($promotion->end_at) - time();
                }

                $resData['flash_sale'] = [
                    'promotion_id'   => $promotion->id,
                    'promotion_name' => $promotion->title,
                    'good_list'      => $promotionGood,
                    'end_at'         => $end_at,
                ];
            } else {
                $resData['flash_sale'] = [];
            }

            $resData['activities'] = MobileCardService::getActivities();
            return ApiResponse::success($resData);
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, $e->getMessage());
        }
    }

    /**
     * @function pc端首页
     * @param Request $request
     * @return mixed
     */
    public function pcIndex(Request $request)
    {
        $currency_code = isset($request->currency_code) ? $request->currency_code : (new CurrencyService)->getDefaultCurrency();
        $currency = Currency::where('currency_code', $currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        // 获取banner图
        $banners = $this->homeService->getBanners(1);
        $banners = collect($banners)->map(function ($item) {
            $item['img'] = cdnUrl($item['img']);
            return $item;
        });

        // 获取促销广告信息
        $promotion = HomepageCard::selectRaw('json_merge(left_image,center_image) as storey_left,title,link,product_category_id')
            ->where('id', '<', 5)
            ->get();
        $storey = [];
        foreach ($promotion as $item) {
            $storey_left = collect(json_decode($item->storey_left))->each(function ($item) {
                $item->img = cdnUrl($item->src);
                $item->url = $item->link;
                unset($item->src);
                unset($item->link);
                unset($item->show);
                return $item;
            })->toArray();
            $tmp['storey_title'] = $item->title;
            $tmp['storey_url'] = $item->link;
            $tmp['storey_left'] = $storey_left;
            // 获取分类下的商品
            $storey_right = ProductsService::getCategoryGoods($item->product_category_id);
            $storey_right = collect($storey_right)->each(function ($item) use ($currency) {
                $item->img = cdnUrl($item->img);
                $item->price = round($item->price * $currency->rate, $currency->digit);
                $item->origin_price = round($item->origin_price * $currency->rate, $currency->digit);
                $item->rebate = round($item->rebate * $currency->rate, $currency->digit);
                return $item;
            });
            $tmp['storey_right'] = $storey_right;
            $storey[] = $tmp;
        }
        $daily_goods = HomepageCard::selectRaw('left_image')
            ->where('id', '=', 5)
            ->first();
        $good_ids = $daily_goods->left_image ? json_decode($daily_goods->left_image) : [];
        $daily = ProductsRepository::getProductInfoByIds($good_ids);
        $daily = collect($daily)->each(function ($item) use ($currency) {
            $item->img = cdnUrl($item->img);
            $item->price = round($item->price * $currency->rate, $currency->digit);
            $item->origin_price = round($item->origin_price * $currency->rate, $currency->digit);
            $item->rebate = round($item->price * $item->rebate / 100, $currency->digit);
            $item->discount = round((1 - round($item->price / $item->origin_price,
                            4)) * 100) . '%';
            return $item;
        });
        $goods = ProductsRepository::getRecommandProducts();
        $total_page = $goods->lastPage();
        $goods = $goods->items();
        $goods = collect($goods)->each(function ($item) use ($currency) {
            $item->img = cdnUrl($item->img);
            $item->price = round($item->price * $currency->rate, $currency->digit);
            $item->origin_price = round($item->origin_price * $currency->rate, $currency->digit);
            $item->rebate = round($item->rebate * $currency->rate, $currency->digit);
            $item->discount = round((1 - round($item->price / $item->origin_price,
                            4)) * 100) . '%';
            return $item;
        });
        $currency_symbol = $currency->symbol;
        return ApiResponse::success(compact('banners', 'storey', 'daily', 'goods', 'total_page', 'currency_symbol'));
    }


    public function category()
    {
        $cates = MobileCategoryService::getAll();
        $childrenIds = array_pluck($cates, 'id');
        $childrens = MobileCategoryService::getChildrenByParentId($childrenIds);
        $data = [];

        foreach ($cates as $key => $cate) {
            $data[$key] = $cate->toArray();
            $data[$key]['sub'] = [];
            foreach ($childrens as $k => $children) {
                if ($children->parent_id == $cate->id) {
                    $childrenData = $children->toArray();
                    if ($childrenData['front']) {
                        $childrenData['category_id'] = $this->addFront($childrenData['category_id']);
                    }
                    array_push($data[$key]['sub'], [
                        'id'   => $childrenData['category_id'],
                        'name' => $childrenData['name'],
                        'img'  => $childrenData['image']
                    ]);
                }
            }
            $data[$key]['uni_icon'] = str_replace('&#x', '\U0000', $data[$key]['icon']);
            unset($data[$key]['parent_id']);
            unset($data[$key]['id']);
        }
        return ApiResponse::success(['cates' => $data]);
    }

    public function addFront($id_str)
    {
        $oldIds = explode(',', $id_str);
        array_walk($oldIds, function (&$item) {
            $item = $item . '-front';
        });
        return join($oldIds, ',');
    }

    public function pcCategory()
    {
        $cates = PcCategoryService::getAll();
        $childrenIds = array_pluck($cates, 'id');
        $childrens = PcCategoryService::getChildrenByParentId($childrenIds);
        $data = [];

        foreach ($cates as $key => $cate) {
            $data[$key]['id'] = $cate->category_id;
            if ($cate['front']) {
                $data[$key]['id'] = $this->addFront($cate->category_id);
            }
            $data[$key]['name'] = $cate->name;
            $data[$key]['sub'] = [];
            foreach ($childrens as $k => $children) {
                if ($children->parent_id == $cate->id) {
                    $childrenData = $children->toArray();
                    if ($childrenData['front']) {
                        $childrenData['category_id'] = $this->addFront($childrenData['category_id']);
                    }
                    array_push($data[$key]['sub'],
                        ['id' => $childrenData['category_id'], 'name' => $childrenData['name']]);
                }
            }
            unset($data[$key]['parent_id']);
        }
        return ApiResponse::success(['cates' => $data]);
    }

    public function getHotWords()
    {
        $data = [
            [
                "name" => "LED",
                "cate" => 1498
            ],
            [
                "name" => "Mommy & Me",
                "cate" => 1498
            ]
        ];
        return ApiResponse::success(['words' => $data]);
    }

    /**
     * @function 获取货币列表
     * @return mixed
     */
    public function currency()
    {
        $country_code = (new CurrencyService)->getDefaultCurrency();
        $currencys = Currency::all();
        $currency = [];
        foreach ($currencys as $key => $item) {
            $currency[$key]['currency_symbol'] = $item->symbol;
            $currency[$key]['currency_code'] = $item->currency_code;
            $currency[$key]['flag'] = cdnUrl($item->national_flag);
            $currency[$key]['threshold'] = $item->threshold;
            $currency[$key]['threshold_num'] = env('THRESHOLD_NUM', 3);
            $currency[$key]['fare'] = $item->fare;
            $currency[$key]['is_default'] = $item->currency_code == $country_code ? 1 : 0;
        }
        return ApiResponse::success(compact('currency'));
    }
}
