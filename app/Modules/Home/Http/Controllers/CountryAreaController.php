<?php

namespace App\Modules\Home\Http\Controllers;

use App\Modules\Home\Services\CountryAreaService;
use App\Services\ApiResponse;
use Illuminate\Support\Facades\Cache;

class CountryAreaController
{
    /**
     * 获取国家列表
     * @return mixed
     * @throws \Exception
     */
    public function areaList()
    {
        $country = Cache::get('countryArea_areaList');
        if (!$country) {
            $country = CountryAreaService::getItems()->toArray();
            foreach ($country as $key => $item) {
                $areaList = CountryAreaService::getItems($item['id'])->toArray();
                $country[$key]['next_level'] = $areaList;
            }
            Cache::put('countryArea_areaList', $country, 60 * 24);
        }
        return ApiResponse::success($country);
    }

    /**
     * 获取地区列表
     * @return mixed
     */
    public function countryList()
    {
        if (!request()->filled('id')) {
            return ApiResponse::failure(g_API_ERROR, 'something was wrong with your request');
        }
        $id = request()->input('id');

        try {
            $areaList = CountryAreaService::getAreaList($id);

            return ApiResponse::success($areaList);
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'something was wrong with your request');
        }

    }

    public function nationalCode()
    {
        $nationalInfo = json_decode(CountryAreaService::COUNTRY_INFO, true);

        try {
            $nationalCode = CountryAreaService::getItems(0, 1, ['name', 'calling_code', 'standard_code'])->toArray();
            return ApiResponse::success(['national_code' => $nationalCode]);
        } catch (\Exception $e) {
            ding('获取区号失败-' . $e->getMessage());
            return ApiResponse::failure(g_API_ERROR, 'something was wrong with your request');
        }
    }
}