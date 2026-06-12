<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CertificatesPreviewController;
use App\Http\Controllers\Admin\CertificatesReleaseController;
use App\Http\Controllers\Admin\CertificatesSendController;
use App\Http\Controllers\Admin\CollaboratorController;
use App\Http\Controllers\Admin\EditionController;
use App\Http\Controllers\Admin\EditionPlaceController;
use App\Http\Controllers\Admin\OrganizerController;
use App\Http\Controllers\Admin\ParticipantController;
use App\Http\Controllers\Admin\TalkController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Certified\CertificatesDownloadController;
use App\Http\Controllers\Certified\CertificatesSearchController;
use App\Http\Controllers\Subscription\EditionController as SubscriptionEditionController;
use App\Http\Controllers\Subscription\SpeakerPhotoUploadController;
use App\Http\Controllers\Subscription\SubscriptionCollaboratorController;
use App\Http\Controllers\Subscription\SubscriptionParticipantController;
use App\Http\Controllers\Subscription\SubscriptionSpeakerController;
use App\Http\Controllers\Subscription\TalkSlideUploadController;
use Illuminate\Support\Facades\Route;

// ── Admin ─────────────────────────────────────────────────────────────────────
//
// All administrative endpoints require Sanctum authentication except login.
// Rate limit: 120 requests/minute — keyed by user ID or IP.

Route::prefix('admin')
    ->middleware('throttle:admin')
    ->group(function () {

        // ── Auth (public) ─────────────────────────────────────────────────────
        Route::post('auth/login', [AuthController::class, 'login']);

        // ── Auth + all records (protected) ────────────────────────────────────
        Route::middleware('auth:sanctum')->group(function () {

            Route::post('auth/logout', [AuthController::class, 'logout']);
            Route::get('auth/me', [AuthController::class, 'me']);
            Route::put('auth/profile', [AuthController::class, 'updateProfile']);
            Route::put('auth/password', [AuthController::class, 'changePassword']);

            // Editions
            Route::apiResource('editions', EditionController::class);

            // Records
            Route::prefix('records')->name('records.')->group(function () {

                Route::apiResource('edition-places', EditionPlaceController::class);

                Route::apiResource('participants', ParticipantController::class);

                // Speaker photo and slide proxies — must come BEFORE apiResource
                // so the named routes resolve correctly.
                Route::get('talks/speaker-photo/{person}', [TalkController::class, 'speakerPhoto'])
                    ->name('talks.speaker-photo');
                Route::get('talks/{talk}/slide', [TalkController::class, 'slide'])
                    ->name('talks.slide');
                Route::apiResource('talks', TalkController::class);
                Route::patch('talks/{talk}/approve', [TalkController::class, 'approve'])
                    ->name('talks.approve');

                Route::get('collaborators/metadata', [CollaboratorController::class, 'metadata'])
                    ->name('collaborators.metadata');
                Route::apiResource('collaborators', CollaboratorController::class);
                Route::patch('collaborators/{collaborator}/approve', [CollaboratorController::class, 'approve'])
                    ->name('collaborators.approve');

                Route::apiResource('organizers', OrganizerController::class);

                Route::apiResource('users', UserController::class);
                Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])
                    ->name('users.reset-password');
            });

            // Attendance / check-in
            Route::prefix('attendance')->name('attendance.')->group(function () {
                Route::get('/', [AttendanceController::class, 'index'])->name('index');
                Route::patch('{kind}/{id}/check-in', [AttendanceController::class, 'toggleCheckIn'])->name('check-in');
            });

            // Certificates (admin-only operations)
            Route::prefix('certificates')->name('certificates.')->group(function () {
                Route::get('preview', [CertificatesPreviewController::class, 'index'])->name('preview');
                Route::get('preview.csv', [CertificatesPreviewController::class, 'csv'])->name('preview.csv');
                Route::get('release', [CertificatesReleaseController::class, 'execute'])->name('release');
                Route::get('{code}/send', [CertificatesSendController::class, 'execute'])->name('send');
            });
        });
    });

// ── Subscription ──────────────────────────────────────────────────────────────
//
// Public endpoints — no authentication required.
// Rate limit: 30 requests/minute per IP to slow down bots and bulk submissions.

Route::prefix('subscription')
    ->middleware('throttle:subscription')
    ->group(function () {

        // Editions (read-only)
        Route::get('editions', [SubscriptionEditionController::class, 'index']);
        Route::get('editions/active', [SubscriptionEditionController::class, 'active']);

        // Registrations
        Route::post('subscriptions/participants', [SubscriptionParticipantController::class, 'store']);
        Route::post('subscriptions/collaborators', [SubscriptionCollaboratorController::class, 'store']);
        Route::post('subscriptions/speakers', [SubscriptionSpeakerController::class, 'store']);

        // File uploads — step 2 of speaker registration
        Route::post('subscriptions/speakers/{speakerId}/photo', [SpeakerPhotoUploadController::class, 'store']);
        Route::post('subscriptions/talks/{talkId}/slide', [TalkSlideUploadController::class, 'store']);
    });

// ── Certified ─────────────────────────────────────────────────────────────────
//
// Public endpoints — no authentication required.
// Rate limit: 60 requests/minute per IP.
// Note: the {code}/download wildcard must come BEFORE {term} so that a code
// ending in "/download" is not swallowed by the search route.

Route::prefix('certified')
    ->middleware('throttle:certified')
    ->group(function () {
        Route::get('{code}/download', [CertificatesDownloadController::class, 'execute']);
        Route::get('{term}', [CertificatesSearchController::class, 'execute']);
    });
