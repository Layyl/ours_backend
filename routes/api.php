<?php

use App\Http\Controllers\announcementController;
use App\Http\Controllers\referral\AuthenticationController;
use App\Http\Controllers\referral\ErCountController;
use App\Http\Controllers\referral\forgotPasswordController;
use App\Http\Controllers\referral\medixDB;
use App\Http\Controllers\referral\patientController;
use App\Http\Controllers\referral\printReferralFormController;
use App\Http\Controllers\referral\ResetPasswordController;
use App\Http\Controllers\referral\SetPasswordController;
use App\Http\Controllers\referral\testMailController;
use App\Http\Controllers\referral\uploadFilesController;
use App\Http\Controllers\referral\verificationController;
use App\Http\Controllers\referral\MessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function(){
    Route::controller(patientController::class)->group(function(){
        Route::get('fetchDashboardCensus','fetchDashboardCensus');
        Route::get('fetchReferrals','fetchReferrals');
        Route::get('fetchReferralData','fetchReferralData');
        Route::get('fetchReferralDataMasterfile','fetchReferralDataMasterfile');
        Route::get('fetchReferralMessages','fetchReferralMessages');
        Route::get('fetchInboundPatients','fetchInboundPatients');
        Route::get('fetchInboundPatientsOB','fetchInboundPatientsOB');
        Route::get('fetchOutboundPatients','fetchOutboundPatients');
        Route::get('fetchUnpostedReferrals','fetchUnpostedReferrals');
        Route::get('fetchMasterfile','fetchMasterfile');
        Route::get('fetchCivilStatus','fetchCivilStatus');
        Route::get('fetchNationality','fetchNationality');
        Route::get('fetchReferralReasons','fetchReferralReasons');
        Route::get('fetchProvince','fetchProvince');
        Route::get('fetchMunicipality','fetchMunicipality');
        Route::get('fetchBarangay','fetchBarangay');
        Route::get('fetchHealthCareInstitution','fetchHealthCareInstitution');
        Route::get('fetchPatientData','fetchPatientData');
        Route::get('searchPatients','searchPatients');
        Route::get('fetchServiceTypes','fetchServiceTypes');
        Route::get('fetchDoctors','fetchDoctors');
        Route::get('getDashboardStats','getDashboardStats');
        Route::get('countProcessing','countProcessing');
        Route::post('updatePatientData','updatePatientData');
        Route::post('updatePatientFiles','updatePatientFiles');
        Route::post('updateVitalSigns','updateVitalSigns');
        Route::post('createNewReferral','createNewReferral');
        Route::post('updateReferral','updateReferral');
        Route::post('createReferralSafru','createReferralSafru');
        Route::post('setToOngoing','setToOngoing');
        Route::post('acceptPatient','acceptPatient');
        Route::post('setDepartment','setDepartment');
        Route::post('deferPatient','deferPatient');
        Route::post('setToIntransit','setToIntransit');
        Route::post('cancelReferral','cancelReferral');
        Route::post('expiredPatient','expiredPatient');
        Route::post('setToExpired','setToExpired');
        Route::post('reopenReferral','reopenReferral');
        Route::post('transferToOtherHCI','transferToOtherHCI');
        Route::post('transferToOPCEN','transferToOPCEN');
        Route::post('transferToJBLMGHOPCEN','transferToJBLMGHOPCEN');
        Route::post('returnToJBLMGH','returnToJBLMGH');
        Route::post('createHCI','createHCI');
        Route::post('removeHCI','removeHCI'); 
        Route::post('OPCENToOtherHCI','OPCENToOtherHCI');
        Route::post('sendChat', [MessageController::class, 'sendChat']);
    });
    
    Route::controller(AuthenticationController::class)->group(function(){
        Route::get('fetchUsers','fetchUsers');
        Route::get('fetchNotifications','fetchNotifications');
        Route::get('fetchMessageNotifications','fetchMessageNotifications');
        Route::post('logout','logout');
        Route::post('createAccount','createAccount');
        Route::post('updatePassword','updatePassword');
        Route::post('updateEmail','updateEmail');
        Route::post('updateNumber','updateNumber');
        Route::post('removeUser','removeUser');
    
    });


    Route::controller(uploadFilesController::class)->group(function(){
        Route::post('upload','upload');
    });
    
    Route::controller(announcementController::class)->group(function(){
        Route::get('fetchAllAnnouncements','fetchAllAnnouncements');
        Route::get('fetchActiveAnnouncements','fetchActiveAnnouncements');
        Route::post('createNewAnnouncement','createNewAnnouncement');
        Route::post('setAnnouncementToExpired','setAnnouncementToExpired');
        Route::post('removeAnnouncement','removeAnnouncement');
    
    });

    Route::controller(ErCountController::class)->group(function(){
        Route::get('fetchERCount','fetchERCount');
    });

});

Route::controller(printReferralFormController::class)->group(function(){
    Route::get('getReferralForm','getReferralForm');
});

Route::controller(AuthenticationController::class)->group(function(){
    Route::post('login','login');
});

Route::group(['middleware' => 'api'], function () {
    Route::get('emailVerification', [VerificationController::class, 'verify'])->name('verification.verify');
});

Route::controller(forgotPasswordController::class)->group(function(){
    Route::post('sendResetLinkEmail','sendResetLinkEmail');
});

Route::group(['middleware' => 'api'], function () {
    Route::post('reset', [ResetPasswordController::class, 'reset'])->name('api.reset');
});

Route::group(['middleware' => 'api'], function () {
    Route::post('setPassword', [SetPasswordController::class, 'setPassword'])->name('api.setPassword');
});

Route::controller(verificationController::class)->group(function(){
    Route::post('resendVerificationEmail','resendVerificationEmail');
});

