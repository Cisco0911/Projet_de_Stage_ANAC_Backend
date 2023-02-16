<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\NewUserNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array  $input
     * @return \App\Models\User
     */
    public function create(array $input)
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'snd_name' => ['required', 'string', 'max:255'],
            'num_inspector' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        // $request->validate([
        //     'name' => ['required', 'string', 'max:255'],
        //     'snd_name' => ['required', 'string', 'max:255'],
        //     'inspector_number' => ['required', 'string', 'max:255'],
        //     'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        //     'password' => ['required', 'string', 'max:255'],
        // ]);


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

        $user = User::create([
            'name' => $input['name'],
            'second_name' => $input['snd_name'],
            'inspector_number' => $input['num_inspector'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        $id = 2;
        if ($user->id == 1)
        {
            while ( !empty( User::find($id) ) ) $id++;

            $user->id = $id;
            $user->push();
            $user->refresh();
        }

        $token = $user->createToken("Access token of ".$input['name']);

        $admin->notify( new NewUserNotification($user) );

        return $user;
    }
}
