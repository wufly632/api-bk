<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryArea extends Model
{
    protected $guarded = ['id'];
    protected $visible = ['id', 'name', 'calling_code', 'standard_code'];
}