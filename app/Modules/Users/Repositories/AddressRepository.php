<?php
/**
 * Created by PhpStorm.
 * User: wmj
 * Date: 2018/5/9
 * Time: 17:06
 */

namespace App\Modules\Users\Repositories;

use App\Models\Customer\Address;

class AddressRepository
{
    public static function createAddress($address)
    {
        $address = Address::create($address);
        return $address->id;
    }

    public static function changeDefault($address, $userId)
    {
        return Address::where('user_id', $userId)->whereNotIn('id', [$address])->update(['is_default' => 0]);
    }

    public static function getAddressInfo($id)
    {
        return Address::find($id);
    }

    public static function updateAddress($id, $addressData)
    {
        return Address::where('id', $id)->update($addressData);
    }

    public static function getList($userId)
    {
        return Address::where('user_id', $userId)
            ->get();
    }

    public static function deleteAddress($id)
    {
        return Address::destroy($id);
    }
}