<?php

namespace App\Modules\Carts\Repositories;

use App\Models\Customer\Carts;
use App\Modules\Products\Repositories\ProductsStockRepository;
use CartStatus;

class CartsRepository
{
    /**
     * 获取当前购物车记录
     * @param $user_id
     * @return \Illuminate\Support\Collection
     */
    public static function getCarts($user_id)
    {
        return Carts::useWritePdo()
            ->where('user_id', $user_id)
            ->where('valid_status', CartStatus::NOTPAY)
            ->orderBy('id', 'desc')
            ->get();
    }

    public static function updateOrCreate($userId, $goodId, $skuId, $num, $localTime, $type = 'update')
    {
        $model = Carts::where('valid_status', CartStatus::NOTPAY)
            ->where('user_id', $userId)
            ->where('good_id', $goodId)
            ->where('sku_id', $skuId);
        if ($cart = $model->first()) {
            if ($num == 0) {
                if ($model->update(['valid_status' => CartStatus::AUTHORIZED])) {
                    return true;
                }
                return false;
            } else {
                if ($type == 'update') {
                    $sku_num = $num;
                    if ($model->first()->num == $num) {
                        return true;
                    }
                } elseif ($type == 'add') {
                    $sku_num = $cart->num + $num;
                    if (!ProductsStockRepository::getSkuStock($skuId) >= $sku_num) {
                        return false;
                    }
                }
                if ($model->update(['num' => $sku_num])) {
                    return true;
                }
                return false;
            }
        } else {
            $cartModel = Carts::create([
                'user_id' => $userId,
                'good_id' => $goodId,
                'sku_id' => $skuId,
                'num' => $num,
                'user_local_time' => $localTime,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return $cartModel->id;
        }
    }

    public static function updateAttr($id, $cartData)
    {
        return Carts::where('id', $id)->update($cartData);
    }

    public static function delete($carts)
    {
        return Carts::destroy($carts);
    }

    public static function getCartById($cartId)
    {
        return Carts::where('id', $cartId)->where('valid_status', CartStatus::NOTPAY)->first();
    }

    public static function getCartProductsCounts($userId)
    {
        return Carts::where('user_id', $userId)->where('valid_status', CartStatus::NOTPAY)->sum('num');
    }

    public static function destroy($id)
    {
        return Carts::destroy($id);
    }

}