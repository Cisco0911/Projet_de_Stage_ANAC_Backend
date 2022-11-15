<?php

namespace Tests\Feature\User;

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
        $response = $this->postJson('register',
            [
                'name' => 'Olamide',
                'snd_name' => 'Farid',
                'num_inspector' => '12345',
                'email' => 'ofarid@gmail.com',
                'password' => 'mikeKlaus001@',
                'password_confirmation' => 'mikeKlaus001@',
            ]
        );

        $response->assertStatus(201);
    }

}
