<?php

namespace App\Models\Home;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'country';

    /**
     * @function cdn加速
     * @param $content
     * @return string
     */
    public function getNationalFlagAttribute($content)
    {
        return cdnUrl($content);
    }
}
