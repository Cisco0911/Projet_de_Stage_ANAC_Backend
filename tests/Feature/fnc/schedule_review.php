<?php

namespace Tests\Feature\fnc;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class schedule_review extends TestCase
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
            ->postJson('update_fnc',
            [
                'id' => 66,
                'update_object' => 'review_date',
                'new_value' => '2023/01/17 9:16'
            ]
        );

        $response->dump();
        $response->assertStatus(200);
    }

}
