<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    //

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'num_inspector' => ['required', 'string', 'max:255'],
            'pwd' => ['required', 'string', 'max:255'],
        ]);

        if (! Auth::attempt($credentials)) {

            throw ValidationException::withMessages([
                'num_inspector' => __('auth.failed'),
            ]);
        }

        return $request->user();
    }

}
