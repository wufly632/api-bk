<?php
/**
 * Created by PhpStorm.
 * User: wmj
 * Date: 2018/5/9
 * Time: 17:06
 */

namespace App\Modules\Users\Repositories;

use App\Models\Customer\Card;
use App\Models\Customer\CustomerRelationship;
use App\Models\User\PwresetToken;
use App\Models\User\Token;
use App\User;
use App\Models\Customer\Income as CustomerIncome;

class UserRepository
{
    /**
     * 获取用户信息
     * @param $user_id
     * @return mixed
     */
    public function getUserInfo($user_id)
    {
        return User::useWritePdo()
            ->find($user_id);
    }

    /**
     * 重置密码
     * @param $userId
     * @param $password
     * @return mixed
     */
    public static function resetPassword($userId, $password)
    {
        $user = User::find($userId);
        $user->password = Hash::make($password);
        return $user->save();
    }

    /**
     * 扣除积分
     * @param $userId
     * @param $integral
     * @return mixed
     */
    public static function subIntegral($userId, $integral)
    {
        return User::where('id', $userId)->decrement('integral', $integral);
    }

    /**
     * 增加积分
     * @param $userId
     * @param $integral
     * @return mixed
     */
    public static function addIntegral($userId, $integral)
    {
        return User::where('id', $userId)->increment('integral', $integral);
    }

    /**
     * 获取全部粉丝数量
     * @param $userId
     * @return mixed
     */
    public static function getFansCount($userId)
    {
        return CustomerRelationship::where('parent_id', $userId)->count(['id']);
    }

    /**
     * 批量获取用户信息
     * @param $userIds
     * @return mixed
     */
    public static function getUserInfoByIds($userIds)
    {
        return User::whereIn('id', $userIds)->get()->toArray();
    }

    /**
     * 获取粉丝列表
     * @param $userId
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getFansList($userId)
    {
        return CustomerRelationship::with('user')
            ->where('parent_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * 批量获取粉丝数量
     * @param array $userIds
     * @return mixed
     */
    public static function getUsersFansCount($userIds)
    {
        return CustomerRelationship::whereIn('parent_id', $userIds)
            ->selectRaw('count(id) as fansm, parent_id')
            ->groupBy(['parent_id'])
            ->get();
    }

    /**
     * 获取粉丝收益
     * @param $userIds
     * @return mixed
     */
    public static function getUsersIncomeSum($userIds)
    {
        return CustomerIncome::whereIn('from_user_id', $userIds)
            ->selectRaw('sum(amount) as income, from_user_id')
            ->groupBy(['from_user_id'])
            ->get()->toArray();
    }

    /**
     * 更新可以更新的用户信息
     * @param $userId
     * @param $userInfo
     * @return mixed
     */
    public static function update($userId, $userInfo)
    {
        return User::where('id', $userId)->update($userInfo);
    }

    /**
     * 根据邮箱查询用户信息
     * @param $email
     * @return mixed
     */
    public static function getUserINfoByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    /**
     * 获取重置密码token
     * @param $userId
     * @return mixed
     */
    public static function getPwresetToken($userId)
    {
        return PwresetToken::where('user_id', $userId)
            ->where('status', 1)
            ->orderBy('id', 'desc')->first();
    }

    /**
     * 更新重置密码token状态
     * @param $id
     * @param $data
     * @return mixed
     */
    public static function updatePwresetToken($id, $data)
    {
        return PwresetToken::where('id', $id)->update($data);
    }

    /**
     * 新建重置密码token
     * @param $data
     * @return mixed
     */
    public static function pwresetTokenAdd($data)
    {
        return PwresetToken::create($data);
    }

    /**
     * 根据token获取单条信息
     * @param $token
     * @return mixed
     */
    public static function getPwresetTokenByToken($token)
    {
        return PwresetToken::where('token', $token)
            ->where('status', 1)
            ->first();
    }

    /**
     * 获取token
     * @param $userId
     * @return mixed
     */
    public static function getToken($userId)
    {
        return Token::where('user_id', $userId)->first();
    }

    /**
     * 获取银行卡列表
     * @param $userId
     * @return mixed
     */
    public static function getCards($userId)
    {
        return Card::where('user_id', $userId)->get();
    }

    /**
     * 扣除余额
     * @param $userId
     * @param $balance
     * @return mixed
     */
    public static function subAmountMoney($userId, $balance)
    {
        return User::where('id', $userId)->decrement('amount_money', $balance);
    }

    /**
     * 增加余额，收益
     * @param $userId
     * @param $income
     */
    public static function addIncome($userId, $income)
    {
        $user = User::find($userId);
        $user->accumulated_income = $user->accumulated_income + $income;
        $user->amount_money = $user->amount_money + $income;
        $user->save();
        /*self::addAmountMoney($userId, $income);
        User::where('id', $userId)->increment('accumulated_income', $income);*/
    }

    /**
     * 增加余额
     * @param $userId
     * @param $balance
     * @return mixed
     */
    public static function addAmountMoney($userId, $balance)
    {
        User::where('id', $userId)->increment('amount_money', $balance);
    }

    public static function firstOrcreate($option, $extraOption)
    {
        return User::firstOrcreate($option, $extraOption);
    }

    public static function find($userId)
    {
        return User::find($userId);
    }

    public static function updateOrCreate($option, $extraOption)
    {
        return User::updateOrCreate($option, $extraOption);
    }
}