<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('validating.{validator_id}', function ($user, $validator_id) {
    return (int) $user->id === (int) $validator_id;
});

Broadcast::channel('user.{validator_id}', function ($user, $validator_id) {
    return (int) $user->id === (int) $validator_id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
