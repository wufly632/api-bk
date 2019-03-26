<?php

namespace App\Modules\Users\Http\Controllers;

use App\Modules\Users\Services\AddressService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AddressController extends Controller
{
    public function index()
    {
        $addressList = AddressService::getList();
        $addressData = [];
        foreach ($addressList as $addressItem) {
            $addressTmp = [
                'id' => $addressItem->id,
                'recipients' => "{$addressItem->firstname} {$addressItem->lastname}",
                'address' => "{$addressItem->country} {$addressItem->state} {$addressItem->city} {$addressItem->street_address} {$addressItem->suburb}",
                'iphone' => $addressItem->phone,
                'is_default' => $addressItem->is_default
            ];
            $addressData[] = $addressTmp;
        }
        return ApiResponse::success($addressData);
    }

    public function add(Request $request)
    {
        if (AddressService::createOrUpdate($request)) {
            return $this->index();
        }
        return ApiResponse::failure(g_API_ERROR, '地址添加失败');
    }

    public function edit(Request $request)
    {
        if ($result = self::addressValidator($request)) return $result;
        // dd(AddressService::createOrUpdate($request));
        if (AddressService::createOrUpdate($request)) {
            return $this->index();
        }
        return ApiResponse::failure(g_API_ERROR, '地址修改失败');
    }

    public function delete(Request $request)
    {
        if ($result = self::addressValidator($request)) return $result;
        $id = $request->input('address_id');
        if (AddressService::deleteAddress($id)) {
            return $this->index();
        }
        return ApiResponse::failure(g_API_ERROR, 'Address delete failed');
    }

    public function getInfo(Request $request)
    {
        if ($result = self::addressValidator($request)) return $result;
        $id = $request->input('address_id');
        $addressInfo = AddressService::getAddressInfo($id);
        $addressData = [
            'address_id' => $addressInfo->id,
            'firstname' => $addressInfo->firstname,
            'lastname' => $addressInfo->lastname,
            'iphone' => $addressInfo->phone,
            'country' => $addressInfo->country,
            'state' => $addressInfo->state,
            'city' => $addressInfo->city,
            'street' => $addressInfo->street_address,
            'suburb' => $addressInfo->suburb,
            'postalcode' => $addressInfo->postcode,
            'default' => $addressInfo->is_default ? true : false
        ];
        return ApiResponse::success($addressData);
    }

    public function setDefault(Request $request)
    {
        if ($result = self::addressValidator($request)) return $result;
        $id = $request->input('address_id');
        $userId = UsersService::getUserId();
        if (AddressService::changeDefault($userId, $id)) {
            return $this->index();
        }
        return ApiResponse::failure(g_API_ERROR, 'Set default Address failed');
    }

    private function addressValidator(Request $request)
    {
        $id = $request->input('address_id', 0);
        if (!$id) return ApiResponse::failure(g_API_ERROR, 'Address can not be null');
        $addressInfo = AddressService::getAddressInfo($id);
        $userId = UsersService::getUserId();
        if (!$addressInfo || $addressInfo->user_id != $userId) return ApiResponse::failure(g_API_ERROR, 'Address dose not exists');
        return false;
    }
}
