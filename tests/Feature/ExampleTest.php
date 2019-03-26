<?php

namespace Tests\Feature;

use App\Models\Product\Attribute;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function testWhereHas()
    {
        $attr = Attribute::with('attrbuteValue')->whereHas('attrbuteValue', function ($query) {
            $query->where('id', '<', '100');
        })->where('id','100')->get();
        dd($attr);
    }
}
