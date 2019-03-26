<?php

namespace App\Modules\Users\Repositories;

use App\Models\Customer\CustomerRelationship;

class CustomerRelationshipRepository
{
    /**
     * 根据用户ID获取上级
     * @param $userId
     * @return mixed
     */
    public static function getParentByUserId($userId)
    {
        return CustomerRelationship::where('user_id', $userId)->first();
    }

    public static function updateStatusById($id)
    {
        CustomerRelationship::where('id', $id)->update(['status' => 1]);
    }

    public static function create($data)
    {
        return CustomerRelationship::create($data);
    }
}