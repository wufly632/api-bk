<?php
// +----------------------------------------------------------------------
// | CustomerOrderAddress.php
// +----------------------------------------------------------------------
// | Description:
// +----------------------------------------------------------------------
// | Time: 2018/12/7 下午2:45
// +----------------------------------------------------------------------
// | Author: wufly <wfxykzd@163.com>
// +----------------------------------------------------------------------

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class CustomerOrderAddress extends Model
{
    protected $table = 'customers_order_address';
    protected $fillable = ['order_id','firstname','lastname','country','state','city','street_address','suburb','postcode','phone','created_at', 'updated_at'];
}
