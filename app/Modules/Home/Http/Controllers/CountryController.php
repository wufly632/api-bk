<?php

namespace App\Modules\Home\Http\Controllers;

use App\Modules\Home\Services\CountryService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $country = CountryService::getAll();
        $countryResult = [];
        foreach ($country as $countryItem)
        {
            $countryTmp = [
                'name' => $countryItem->name,
                'abbreviation' => $countryItem->abbreviation,
                'flag' => $countryItem->national_flag
            ];
            $countryResult[] = $countryTmp;
        }
        return ApiResponse::success($countryResult);
    }
}
