<?php

use App\Http\Controllers\UserController;
use App\Notifications\FncReviewNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;

use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NcController;
use App\Notifications\RemovalResponse;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\FichierController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Notification;
use App\Http\Controllers\CheckListController;
use App\Http\Controllers\ConformiteController;
use App\Http\Controllers\DossierPreuveController;
use App\Http\Controllers\DossierSimpleController;
use App\Http\Controllers\NonConformiteController;
use App\Http\Controllers\FichierDePreuveController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use App\Http\Controllers\OperationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\ProfileInformationController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Thomasjohnkane\Snooze\ScheduledNotification;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__.'/auth.php';

$limiter = config('fortify.limiters.login');
$twoFactorLimiter = config('fortify.limiters.two-factor');
$verificationLimiter = config('fortify.limiters.verification', '6,1');


Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware(array_filter([
                'guest:'.config('fortify.guard'),
                $limiter ? 'throttle:'.$limiter : null,
            ]));

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');


if (Features::enabled(Features::registration())) {

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware(['guest:'.config('fortify.guard')]);
}


Broadcast::routes(['middleware' => ['auth:sanctum']]);


Route::middleware('auth:sanctum')->group(
    function () {

        // Section
        Route::get('get_ss', [SectionController::class, 'get_ss']);
        Route::post('add_ss', [SectionController::class, 'add_ss']);

        // Audit
        Route::get('get_audits', [AuditController::class, 'get_audits']);
        Route::post('add_audit', [AuditController::class, 'add_audit']);
        Route::delete('del_audit', [AuditController::class, 'del_audit']);



        // checkList
        Route::get('get_checkLists', [CheckListController::class, 'get_checkLists']);
        Route::post('add_checkLists', [CheckListController::class, 'add_checkLists']);



        // Dossier Preuve
        Route::get('get_Dps', [DossierPreuveController::class, 'get_Dps']);
        Route::post('add_Dps', [DossierPreuveController::class, 'add_Dps']);



        // Nc
        Route::get('get_NonCs', [NcController::class, 'get_NonCs']);
        Route::post('add_Ncs', [NcController::class, 'add_Ncs']);



        // Non conformité
        Route::get('get_fncs', [NonConformiteController::class, 'get_fncs']);
        Route::post('add_fncs', [NonConformiteController::class, 'add_fncs']);
        Route::delete('del_fnc', [NonConformiteController::class, 'del_fnc']);
        Route::post('update_fnc', [NonConformiteController::class, 'update_fnc']);



        // Fichier
        Route::get('get_fs', [FichierController::class, 'get_fs']);
        Route::get('overview_of', [FichierController::class, 'overview_of']);
        Route::post('add_files', [FichierController::class, 'add_files']);
        Route::delete('del_file', [FichierController::class, 'del_file']);
        Route::post('move_file', [FichierController::class, 'move_file']);
        Route::post('copy_file', [FichierController::class, 'copy_file']);




        // Dossier Simple
        Route::get('get_ds', [DossierSimpleController::class, 'get_ds']);
        Route::post('add_folder', [DossierSimpleController::class, 'add_folder']);
        Route::delete('del_folder', [DossierSimpleController::class, 'del_folder']);
        Route::post('update_folder', [DossierSimpleController::class, 'update_folder']);
        Route::post('move_folder', [DossierSimpleController::class, 'move_folder']);        Route::post('move_folder', [DossierSimpleController::class, 'move_folder']);
        Route::post('copy_folder', [DossierSimpleController::class, 'copy_folder']);
        Route::get('test', [DossierSimpleController::class, 'test']);



        // user
         Route::get('get_users', [UserController::class, 'get_users']);



        // Fichier de preuve
        // Route::get('get_fdps', [FichierDePreuveController::class, 'get_fdps']);
        // Route::post('add_fdps', [FichierDePreuveController::class, 'add_fdps']);



        // Services
        Route::get('get_services', [ServiceController::class, 'get_services']);
        Route::post('add_service', [ServiceController::class, 'add_service']);



        // Notification
        Route::post('notify_response', [OperationNotificationController::class, 'notify_response']);
        Route::post('markAsRead', [UserController::class, 'markAsRead']);



        // Editor
        Route::post('handle_edit', [NodeController::class, 'handle_edit']);



        // Schedule Notification
        Route::post('schedule_review',
            function (Request $request)
            {
                ScheduledNotification::create(
                    Auth::user(), // Target
                    new FncReviewNotification($request->id), // Notification
                    Carbon::now()->addRealSeconds(10) // Send At
                );
            }
        );


        Route::post('getDatasByIds',
        function(Request $request)
            {

                function getData($request)
                {
                    # code...

                    $format = function($element)
                    {

                        $node = json_decode($element);

                        switch ($element->parent_type) {
                            case "App\Models\Audit":
                                $node->parent_type = 'audit';
                                break;
                            case "App\Models\checkList":
                                $node->parent_type = 'checkList';
                                break;
                            case "App\Models\DossierPreuve":
                                $node->parent_type = 'dp';
                                break;
                            case "App\Models\Nc":
                                $node->parent_type = 'nonC';
                                break;
                            case "App\Models\NonConformite":
                                $node->parent_type = 'fnc';
                                break;
                            case "App\Models\DossierSimple":
                                $node->parent_type = 'ds';
                                break;

                            default:
                                $node->parent_type = '';
                                break;
                        }

                        return $node;
                    };

                    switch ($request->type)
                    {
                        case 'audit':
                            # code...
                            $audit = AuditController::find($request->id);
                            $audit->front_type = 'audit';
                            return $audit;
                        case 'checkList':
                            # code...
                            $checkList = CheckListController::find($request->id);
                            $checkList->front_type = 'checkList';
                            return $checkList;
                        case 'dp':
                            # code...
                            $dp = DossierPreuveController::find($request->id);
                            $dp->front_type = 'dp';
                            return $dp;
                        case 'nonC':
                            # code...
                            $nonC = NcController::find($request->id);
                            $nonC->front_type = 'nonC';
                            return $nonC;
                        case 'fnc':
                            # code...
                            $fnc = NonConformiteController::find($request->id);
                            $fnc->front_type = 'fnc';
                            return $fnc;
                        case 'ds':
                            # code...
                            $ds = DossierSimpleController::find($request->id);
                            $ds->front_type = 'ds';
                            return $format($ds);
                        case 'f':
                            # code...
                            $f = FichierController::find($request->id);
                            $f->front_type = 'f';
                            return $format($f);

                        default:
                            # code...
                            break;
                    }
                    // return 'f';
                }

                $datas = [];

                foreach ($request->ids as $key => $value) {
                    # code...
                    $id_arr = explode('-', $value);

                    $new_request = json_decode('{}');
                    $new_request->id = $id_arr[0]; $new_request->type = $id_arr[1];

                    // $new_request->id = (int)$id_arr[0];
                    // $new_request->type = $id_arr[1];

                    array_push($datas, getData($new_request));

                }
                // if(count($datas) == 1) return $datas[0];
                return $datas;
            }
        );

    }
);


    Route::get('user', function()
    {
        $authUser = Auth::user();
        if ($authUser)
        {
            # code...

            $format = function($node)
            {

                switch ($node->operable_type) {
                    case "App\Models\Audit":
                        $node->front_type = 'audit';
                        $node->node_type = 'Audit';
                        break;
                    case "App\Models\checkList":
                        $node->front_type = 'checkList';
                        $node->node_type = 'CheckList';
                        break;
                    case "App\Models\DossierPreuve":
                        $node->front_type = 'dp';
                        $node->node_type = 'Dossier Preuve';
                        break;
                    case "App\Models\Nc":
                        $node->front_type = 'nonC';
                        $node->node_type = 'NC';
                        break;
                    case "App\Models\NonConformite":
                        $node->front_type = 'fnc';
                        $node->node_type = 'FNC';
                        break;
                    case "App\Models\DossierSimple":
                        $node->front_type = 'ds';
                        $node->node_type = 'Dossier';
                        break;
                    case "App\Models\Fichier":
                        $node->front_type = 'f';
                        $node->node_type = 'Fichier';
                        break;

                    default:;
                        break;
                }

                return json_decode($node);
            };



            $authUser->services;
            $authUser->operationNotifications;
            foreach ($authUser->operationNotifications as $key => $value) {
                # code...
                $authUser->operationNotifications[$key]->operable;
                $authUser->operationNotifications[$key]->from;
                $authUser->operationNotifications[$key] = $format($authUser->operationNotifications[$key]);
            }
            $authUser->unreadNotifications;
            $authUser->readNotifications;
        }

        return $authUser;
    }
);

