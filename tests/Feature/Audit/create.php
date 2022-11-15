<?php

namespace Tests\Feature\Audit;

use App\Models\User;
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
            ->postJson('add_audit',
                [
                    'name' => 'audit test',
                    'section_id' => 1,
                    'ra_id' => 1,
                    'services' => '[{"value":1}]',
                ]
            );

        $response
            ->assertStatus(200)
            ->assertJson(
                [
                    'statue' => 'success',
                ]
            );
    }

}
