<?php


namespace App\Modules\Activity\Services;


use App\Models\Currency;
use App\Modules\Orders\Repositories\CustomerInviteRankRepository;
use App\Modules\Products\Repositories\ProductsRepository;
use App\Modules\Products\Services\ProductsService;
use App\Modules\Users\Repositories\CustomerIncomeRepository;
use App\Modules\Users\Services\UsersService;
use App\Traits\ActivityInviteTrait;
use Illuminate\Support\Facades\Redis;

class ActivityService
{

    use ActivityInviteTrait;

    const FAKER = [
        "-1" => "Xzavier Ernser",
        "-2" => "June Hand",
        "-3" => "Dr. Nikolas Christiansen",
        "-4" => "Adrien O'Connell",
        "-5" => "Prof. Dortha Gerlach",
        "-6" => "Mrs. Stephany Hauck",
        "-7" => "Frieda Lang MD",
        "-8" => "Gail Hane",
        "-9" => "Loren Brekke",
        "-10" => "Dr. Justine Dooley"
    ];

    public static function getHeader()
    {
        return [
            "-1" => "https://images.waiwaimall.com/headers/1.png",
            "-2" => "https://images.waiwaimall.com/headers/2.png",
            "-3" => "https://images.waiwaimall.com/headers/3.png",
            "-4" => "https://images.waiwaimall.com/headers/4.png",
            "-5" => "https://images.waiwaimall.com/headers/5.png",
            "-6" => "https://images.waiwaimall.com/headers/6.png",
            "-7" => "https://images.waiwaimall.com/headers/7.png",
            "-8" => "https://images.waiwaimall.com/headers/8.png",
            "-9" => "https://images.waiwaimall.com/headers/9.png",
            "-10" => "https://images.waiwaimall.com/headers/10.png"
        ];
    }

    public static function getDefaultHeader()
    {
        return 'https://images.waiwaimall.com/headers/default.png';
    }

    public static function goodList()
    {
        return explode(',', config('thirdparty.cashBackGoodList'));
    }

    public static function getMarquee()
    {
        $data = [];

        if (self::checkActivityStatus()) {
            if (Redis::exists('invite_marquee_show')) {
                $marquee = Redis::lRange('invite_marquee_show', 0, -1);
            } else {
                $count = Redis::lLen('invite_marquee');
                $strArr = [
                    self::$incFansStr,
                    self::$gainStr
                ];
                $mincount = min(20, $count);
                if ($mincount > 1) {
                    foreach (range(1, $mincount) as $item) {
                        $getItem = Redis::rPop('invite_marquee');
                        Redis::lPush('invite_marquee_show', $getItem);
                    }
                }
                $MarqueeFaker = config('thirdparty.marqueeFaker', false);
                if ($MarqueeFaker && $mincount < 20) {
                    $faker = \Faker\Factory::create();
                    foreach (range(1, 20 - $mincount) as $value) {
                        self::addMarquee((object)['fullname' => $faker->name], $strArr[mt_rand(0, 1)], 'invite_marquee_show');
                    }
                }
                Redis::expire('invite_marquee_show', 120);
                $marquee = Redis::lRange('invite_marquee_show', 0, -1);
            }
            foreach ($marquee as $key => $item) {
                $data[$key]['image'] = array_values(self::getHeader())[mt_rand(0, 9)];
                $data[$key]['activityEvent'] = $item;
            }
        }
        return ['marquee' => $data];
    }

    public static function getTopTen()
    {
        $currency = Currency::where('currency_code', 'BDT')->first();
        $userId = UsersService::getUserId();
        if ($userId) {
            $fansCountModel = CustomerInviteRankRepository::get($userId);
            $fans = $fansCountModel ? $fansCountModel->count : 0;
            $reward = CustomerIncomeRepository::getInviteGet($userId);
        } else {
            $fans = 0;
            $reward = 0;
        }
        $topTen = Redis::zRevRange('invite_top_user_union', 0, 9, 'WITHSCORES');
        $topTenWithDetail = [];
        $i = 0;
        foreach ($topTen as $key => $item) {
            $user = self::transformUser($key);
            $topTenWithDetail[$i]['username'] = self::replaceNameOrEmail(self::getUserName($user));
            $topTenWithDetail[$i]['image'] = $user->logo;
            $topTenWithDetail[$i++]['fans'] = intval(explode('.', $item)[0]);
        }
        $goodListConfig = self::goodList();
        $goodList = [];
        if ($goodListConfig) {
            $good_list = ProductsRepository::getProductByIds($goodListConfig);
            foreach ($good_list as $key => $value) {
                $goodList[$key]['price'] = $value['price'];
                $goodList[$key]['image'] = cdnUrl($value['main_pic']);
                $goodList[$key]['goodId'] = $value['id'];
            }
        }
        $data = [
            'goodList' => $goodList,
            'fans' => $fans,
            'reward' => round($reward*$currency->rate, $currency->digit),
            'topTen' => $topTenWithDetail
        ];
        return $data;
    }

    public static function transformUser($userId)
    {
        if ($userId < 0) {
            return (object)['fullname' => self::FAKER[$userId], 'logo' => self::getHeader()[$userId]];
        } else {
            $user = UsersService::getUserInfo($userId);
            $user->logo = $user->logo ?: self::getDefaultHeader();
            return $user;
        }
    }
}
