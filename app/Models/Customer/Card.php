<?php


namespace App\Models\Customer;


use App\Modules\Users\Services\UsersService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $table = 'customer_cards';

    protected $guarded = ['id'];

    protected $appends = ['hidden_card_number'];

    protected static function boot()
    {
        parent::boot();
        $userid = UsersService::getUserId();
        static::addGlobalScope('is_del', function (Builder $builder) use ($userid) {
            $builder->where('is_del', 0)->where('user_id', $userid);
        });
    }

    /**
     * @function 银行卡号隐藏显示
     * @return string
     */
    public function getHiddenCardNumberAttribute()
    {
        $card_num = $this->attributes['card_number'];
        //截取银行卡号前4位
        $prefix = substr($card_num, 0, 4);
        //截取银行卡号后4位
        $suffix = substr($card_num, -4, 4);

        $card_num = $prefix . " **** **** **** " . $suffix;
        return $card_num;
    }
}