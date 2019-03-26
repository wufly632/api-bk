<?php
/**
 * Created by PhpStorm.
 * User: rogers
 * Date: 18-11-22
 * Time: 上午11:24
 */

namespace App\Models\Home;


use Illuminate\Database\Eloquent\Model;

class MobileCard extends Model
{
    protected $table = 'website_mobile_homepage_cards';

    protected $visible = ['content', 'type'];

    protected $casts = [
        'content' => 'array'
    ];

    /**
     * @function cdn加速
     * @param $content
     * @return string
     */
    public function getContentAttribute($content)
    {
        $content = json_decode($content, true);
        foreach ($content as $key => &$item) {
            $item['src'] = cdnUrl($item['src']);
        }
        return $content;
    }
}