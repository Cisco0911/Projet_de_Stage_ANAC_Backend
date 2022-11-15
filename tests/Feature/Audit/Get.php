<?php

namespace Audit;

use App\Models\User;
use Tests\TestCase;

class Get extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_a_basic_request()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['banned' => false])
            ->get('/get_audits');

        $response->assertStatus(200);
    }

}
