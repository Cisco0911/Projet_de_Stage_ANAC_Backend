<?php

namespace Tests\Feature\fnc;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class create extends TestCase
{


    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_a_basic_request()
    {
        $response = $this->postJson('add_fncs');

        $response->assertStatus(200);
    }

}
