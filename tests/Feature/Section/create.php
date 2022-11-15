<?php

namespace Section;

use App\Models\User;
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

        $user = User::find(1);

        $response = $this
            ->actingAs($user)
            ->withSession(['banned' => false])
            ->postJson('add_ss',
            [
                'name' => 'AGA'
            ]
        );

        $response->assertStatus(200);
    }

}
