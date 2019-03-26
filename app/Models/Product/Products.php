<?php
// +----------------------------------------------------------------------
// | Products.php
// +----------------------------------------------------------------------
// | Description:
// +----------------------------------------------------------------------
// | Time: 2018/10/28 下午2:46
// +----------------------------------------------------------------------
// | Author: wufly <wfxykzd@163.com>
// +----------------------------------------------------------------------

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    protected $table = 'goods';


    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('status', function (Builder $builder) {
            $builder->where('status', 1);
        });
    }

    public function getProductSkus()
    {
        return $this->hasMany(ProductSku::class, 'good_id', 'id');
    }

    protected function getMainPicAttribute($item)
    {
        return cdnUrl($item);
    }

    public function scopeByIds($query, $ids)
    {
        return $query->whereIn('id', $ids)->orderByRaw(sprintf("FIND_IN_SET(id, '%s')", join(',', $ids)));
    }

    /**
     * @function cod模式商品
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function codProduct()
    {
        return $this->hasOne(CodProduct::class, 'good_id', 'id');
    }

    public function sizeChart()
    {
        return $this->hasOne(GoodSizeChart::class, 'good_id', 'id');
    }
}
