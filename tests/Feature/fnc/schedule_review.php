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
            ->postJson('schedule_review',
            [
                'id' => 6
            ]
        );

        $response->assertStatus(200);
    }

}
