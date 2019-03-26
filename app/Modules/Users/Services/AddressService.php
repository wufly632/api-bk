<?php
/**
 * Created by api.
 * User: Bruce.He
 * Date: 2018/4/27
 * Time: 22:28
 */

namespace App\Modules\Users\Services;

use App\Assistants\CLogger;
use App\Modules\Users\Repositories\AddressRepository;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /**
     * 创建或者更新address
     * @param $request
     * @return bool
     */
    public static function createOrUpdate($request)
    {
        $userId = UsersService::getUserId();
        $id = $request->input('address_id', 0);
        if ($id) {
            $addressInfo = AddressRepository::getAddressInfo($id);
            if (!$addressInfo || $addressInfo->user_id != $userId) return false;
        }
        $firstName = $request->input('firstname');
        $lastName = $request->input('lastname');
        $phone = $request->input('iphone');
        $country = $request->input('country');
        $state = $request->input('state');
        $city = $request->input('city');
        $streetAddress = $request->input('street');
        $suburb = $request->input('suburb', '');
        $postcode = $request->input('postalcode');
        $default = $request->input('default', false);
        $addressData = [
            'user_id' => $userId,
            'firstname' => $firstName,
            'lastname' => $lastName,
            'phone' => $phone,
            'country' => $country,
            'state' => $state,
            'city' => $city,
            'street_address' => $streetAddress,
            'suburb' => $suburb,
            'postcode' => $postcode,
            'is_default' => ($default == 'true') ? 1 : 0,
        ];
        try {
            DB::beginTransaction();
            if ($id) {
                AddressRepository::updateAddress($id, $addressData);
                $address = $id;
            } else {
                $address = AddressRepository::createAddress($addressData);
            }
            if ($address) {
                if ($addressData['is_default']) {
                    AddressRepository::changeDefault($address, $userId);
                }
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            CLogger::getLogger('address')->info($exception->getMessage());
            return false;
        }
    }

    public static function getList()
    {
        $userId = UsersService::getUserId();
        return AddressRepository::getList($userId);
    }

    public static function getAddressInfo($id)
    {
        return AddressRepository::getAddressInfo($id);
    }

    public static function deleteAddress($id)
    {
        return AddressRepository::deleteAddress($id);
    }

    public static function changeDefault($userId, $id)
    {
        try {
            DB::beginTransaction();
            AddressRepository::changeDefault($id, $userId);
            AddressRepository::updateAddress($id, ['is_default' => 1]);
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            return false;
        }
    }
}