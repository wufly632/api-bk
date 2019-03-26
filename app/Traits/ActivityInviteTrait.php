<?php


namespace App\Traits;

use Illuminate\Support\Facades\Redis;

trait ActivityInviteTrait
{
    protected static $gainStr = ' invite <span>1</span> follower';

    protected static $incFansStr = ' has gained <span>৳200</span> reward !';

    /**
     * check activity status
     * @return bool
     */
    public static function checkActivityStatus()
    {
        if (!config('thirdparty.inviteStatus')) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * check invite code
     * @return bool
     */
    public static function checkInviteCode()
    {
        if (!request()->filled('invite_code')) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * check activity condition
     * @return bool
     */
    public static function checkCondition()
    {
        if (self::checkActivityStatus() && self::checkInviteCode()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user
     * @return mixed
     */
    public static function getUserName($user)
    {
        return $user->fullname ?: $user->email;
    }

    /**
     * add marquee cache;
     * @param $user
     * @param $marqueeEventStr
     * @param string $queen
     * @param int $expire
     */
    public static function addMarquee($user, $marqueeEventStr, $queen = 'invite_marquee')
    {
        $marquee = self::replaceNameOrEmail(self::getUserName($user)) . $marqueeEventStr;
        if (!Redis::lPushx($queen, $marquee)) {
            Redis::lPush($queen, $marquee);
        };
    }

    /**
     * 替换名字或者邮箱
     * @param $nameOrEmail
     * @return string
     */
    public static function replaceNameOrEmail($nameOrEmail)
    {
        $replaced = '';
        if (filter_var($nameOrEmail, FILTER_VALIDATE_EMAIL)) {
            $replaced = mb_substr($nameOrEmail, 0, mb_strpos($nameOrEmail, '@'));
        } else {
            $replaced = $nameOrEmail;
        }
        return self::replaceStr($replaced, '***');
    }

    /**
     * 替换字符串
     * @param $str
     * @param $replacement
     * @return string
     */
    public static function replaceStr($str, $replacement)
    {
        if (mb_strlen($str) < 5) {
            $str .= $replacement;
        } else {
            $left = mb_substr($str, 0, 2);
            $right = mb_substr($str, -2, 2);
            $str = $left . $replacement . $right;
        }
        return $str;
    }
}