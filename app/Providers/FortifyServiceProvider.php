<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Hash;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\RateLimiter;
use App\Actions\Fortify\UpdateUserProfileInformation;

class FortifyServiceProvider extends ServiceProvider
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


        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('inspector_number', $request->num_inspector)->first();

            if ($user &&
            Hash::check($request->password, $user->password)) {
                return $user;
            }
        });

        ResetPassword::createUrlUsing(
            function ($notifiable, $token)
            {
                return env("FRONTEND_URL")."/reset_password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";
//                url(route('password.reset', [
//                    'token' => $this->token,
//                    'email' => $notifiable->getEmailForPasswordReset(),
//                ], false));
            }
        );


        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;

            return Limit::perMinute(5)->by($email.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

    }
}
