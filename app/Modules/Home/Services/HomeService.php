<?php

namespace App\Modules\Home\Services;

use App\Modules\Home\Repositories\BannerRepository;
use App\Modules\Home\Repositories\IconRepository;
use App\Modules\Users\Repositories\CurrencyRepository;

class HomeService
{
    /**
     * 获取并转换ICON
     * @return array
     */
    public function getIcons()
    {
        $icons = IconRepository::getAllIcon(['src', 'category_id', 'title']);
        $transformed = [];
        foreach ($icons as $k => $item) {
            $transformed[$k]['img'] = cdnUrl($item->src);
            $transformed[$k]['cate'] = intval($item->category_id);
            $transformed[$k]['title'] = $item->title;
        }
        return $transformed;
    }

    /**
     * 获取并转换Banner
     * @param int $type
     * @return array
     */
    public function getBanners($type = 2)
    {
        $banners = BannerRepository::getBanners(['src', 'link'], $type);
        $transformed = [];
        foreach ($banners as $k => $item) {
            $transformed[$k]['img'] = cdnUrl($item->src);
            $transformed[$k]['url'] = $item->link;
        }
        return $transformed;
    }

    /**
     * @param $currencyCode
     * @throws \Exception
     */
    public static function checkCurrency($currencyCode)
    {
        $currency = CurrencyRepository::getByCurrencyCode($currencyCode);
        if (!$currency) {
            throw new \Exception("Currency dose not exists");
        }
    }
}