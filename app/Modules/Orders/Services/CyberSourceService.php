<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/10/11
 * Time: 下午7:47
 */

namespace App\Modules\Orders\Services;

use App\Assistants\CLogger;
use App\Modules\Products\Repositories\CategoryRepository;

class CyberSourceService
{
    public static function transact($userInfo, $orderInfo, $cards, $address, $amount, $shipAddressInfo, $orderGoodsInfo, $oldOrders, $currency)
    {
        // 商户风控跟踪号（订单号）
        $client = new \CybsSoapClient();
        // dd($client);
        $request = $client->createRequest($orderInfo->order_id);
        // 是否测试
        $afsService = new \stdClass();
        $afsService->run = 'true';
        $request->afsService = $afsService;
        // 设备指纹
        $request->deviceFingerprintID = \Cache::get(CYBS_PAY_SESSION_ID . '_' . $userInfo->id);

        // 账单地址信息
        $billTo = new \stdClass();
        $billTo->firstName = $address->firstname;
        $billTo->lastName = $address->lastname;
        $billTo->street1 = $address->street_address;
        $billTo->city = $address->city;
        $billTo->state = $address->state;
        $billTo->postalCode = $address->postcode;
        $billTo->country = $address->country;
        $billTo->email = $address->email;
        $billTo->ipAddress = request()->getClientIp();
        $billTo->phoneNumber = $address->phone;
        $request->billTo = $billTo;
        // 银行卡信息
        $card = new \stdClass();
        $card->accountNumber = $cards->number;
        $card->expirationMonth = $cards->expm;
        $card->expirationYear = $cards->expy;
        // $card->cardType = $cards->brand;
        $request->card = $card;

        // 支付金额信息
        $purchaseTotals = new \stdClass();
        $purchaseTotals->currency = $currency->currency_code;
        $purchaseTotals->grandTotalAmount = $amount;
        $request->purchaseTotals = $purchaseTotals;

        // 收货地址信息
        $shipTo = new \stdClass();
        $shipTo->city = $shipAddressInfo->city;
        $shipTo->country = $shipAddressInfo->country;
        $shipTo->firstName = $shipAddressInfo->firstname;
        $shipTo->lastName = $shipAddressInfo->lastname;
        $shipTo->phoneNumber = $shipAddressInfo->phone;
        $shipTo->state = $shipAddressInfo->state;
        $shipTo->street1 = $shipAddressInfo->street_address;
        if ($shipAddressInfo->suburb) $shipTo->street2 = $shipAddressInfo->suburb;
        $request->shipTo = $shipTo;

        // 订单商品信息
        $request->item = [];
        $cateId = '';
        if ($orderGoodsInfo) {
            foreach ($orderGoodsInfo as $index => $item) {
                $tmp = new \stdClass();
                $tmp->productName = $item->good_en_title;
                $tmp->quantity = $item->num;
                $tmp->unitPrice = $item->unit_price;
                $tmp->productSKU = $item->sku_id;
                $tmp->id = $index;
                $request->item[] = $tmp;
                if (!$cateId) $cateId = $item->category_path;
            }
        }
        $cateId = explode(',', $cateId)[1];

        // 附加字段
        $merchantDefinedData = new \stdClass();
        $request->merchantDefinedData_mddField_1 = in_array($orderInfo->from_type, [1, 2]) ? 'Web' : 'App';
        $mddField1 = ['id' => 1, '_' => in_array($orderInfo->from_type, [1, 2]) ? 'Web' : 'App'];
        $datetime1 = date_create(date('Y-m-d'));
        $datetime2 = date_create(date('Y-m-d', strtotime($userInfo->created_at)));
        $diff = date_diff($datetime1, $datetime2);
        $mddField2 = ['id' => 2, '_' => $diff->y * 365.25 + $diff->m * 30 + $diff->d];
        $mddField3 = ['id' => 3, '_' => $userInfo->email];
        $mddField4 = ['id' => 4, '_' => explode('@', $userInfo->email)[1]];
        $mddField5 = ['id' => 5, '_' => 'N'];
        $mddField6 = ['id' => 6, '_' => 'Mix'];
        $mddField8 = ['id' => 8, '_' => $orderInfo->code_id ? 'Y' : 'N'];
        $mddField9 = ['id' => 9, '_' => $userInfo->fullname ? $userInfo->fullname : $address->firstname ? "{$address->firstname} {$address->lastname}" : "{$shipAddressInfo->firstname} {$shipAddressInfo->lastname}"];
        $mddField10 = ['id' => 10, '_' => substr($cards->number, 0, 6)];
        $mddField11 = ['id' => 11, '_' => $oldOrders ? 'Y' : 'N'];
        $mddField12 = ['id' => 12, '_' => CategoryRepository::getCateInfo($cateId)->en_name];
        $mddField13 = ['id' => 13, '_' => $currency->abbreviation];
        $mddField14 = ['id' => 14, '_' => $orderInfo->fare ? 'Y' : 'N'];
        $mddField15 = ['id' => 15, '_' => 'DHL'];
        $merchantDefinedData->mddField = array($mddField1, $mddField2, $mddField3, $mddField4, $mddField5, $mddField6, $mddField8, $mddField9, $mddField10, $mddField11, $mddField12, $mddField13, $mddField14, $mddField15);
        $request->merchantDefinedData = $merchantDefinedData;
        CLogger::getLogger('cybs-info', 'cybs')->info(json_encode($request));
        // dd($request);
        $reply = $client->runTransaction($request);
        CLogger::getLogger('cybs-info', 'cybs')->info(json_encode($reply));
        // dd($client, $reply);
        return $reply;
    }
}