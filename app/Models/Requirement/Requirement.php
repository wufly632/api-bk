<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/12/8
 * Time: 14:19
 */

namespace App\Models\Requirement;


use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    protected $table = 'requirements';
    protected $fillable = ['sku_id', 'num', 'type', 'is_push'];
    protected $guarded = ['id'];
}