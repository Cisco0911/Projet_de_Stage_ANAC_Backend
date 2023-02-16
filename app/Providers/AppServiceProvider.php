<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $admin = User::find(0);

        if (empty($admin))
        {
            $admin = User::create([
                'name' => "ANAC_FILE_MANAGER",
                'second_name' => "ADMINISTRATOR",
                'inspector_number' => "000000",
                'email' => "anac.togo.file.manager@gmail.com",
                'password' => Hash::make("Administrator0000@"),
            ]);

            $admin->id = 0;
            $admin->push();
            $admin->refresh();

            $admin_token = $admin->createToken("Access token of ADMINISTRATOR");
        }
    }
}
