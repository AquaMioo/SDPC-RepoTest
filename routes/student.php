<?php

use App\Http\Controllers\Student\ClientDirectoryController;
use App\Http\Controllers\Student\PortfolioItemController;
use App\Http\Controllers\Student\ProjectBoardController;
use App\Http\Controllers\Student\ProjectProcessController;
use App\Http\Controllers\Student\StudentEducationController;
use App\Http\Controllers\Student\StudentLanguageController;
use App\Http\Controllers\Student\StudentPhotoController;
use App\Http\Controllers\Student\StudentProfileController;
use App\Http\Controllers\Student\StudentVerificationController;
use App\Http\Controllers\Student\WorkflowController;
use App\Http\Middleware\EnsureAccountIsNotMonitored;
use App\Http\Middleware\EnsureAccountIsVerified;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureUserIsStudent;
use Illuminate\Support\Facades\Route;

/*
 * Student Module.
 *
 * Mounted on the same `{current_team}` prefix as the client module, but on
 * paths that module does not use — the two groups are separated by role
 * middleware, not by URL space, so an overlapping path would be resolved by
 * registration order and silently 403 whichever role lost.
 */
/*
 * The optional third-party enrolment check.
 *
 * Off the `{current_team}` prefix because the button that reaches it lives in
 * settings, which is not team-scoped either. Both routes 404 unless SheerID is
 * actually configured — see config/sheerid.php — and nothing anywhere on the
 * platform gates on the answer.
 */
Route::prefix('settings')
    ->middleware(['auth', 'verified', EnsureUserIsStudent::class])
    ->group(function () {
        Route::post('student-verification', [StudentVerificationController::class, 'store'])
            ->name('student.verification.store');
        Route::get('student-verification/return', [StudentVerificationController::class, 'update'])
            ->name('student.verification.return');
    });

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class, EnsureUserIsStudent::class])
    ->group(function () {
        /*
         * Browsing is open to any student, the same way the client module lets
         * an unverified business look around. Applying is what waits for an
         * administrator to accept the credential — and for the account not
         * being under monitoring, which is the same question asked from the
         * other direction: proved, and still trusted.
         */
        $verified = [EnsureAccountIsVerified::class, EnsureAccountIsNotMonitored::class];

        Route::get('find-clients', [ProjectBoardController::class, 'index'])->name('student.board.index');
        Route::get('find-clients/{project}', [ProjectBoardController::class, 'show'])->name('student.board.show');
        Route::post('find-clients/{project}/apply', [ProjectBoardController::class, 'apply'])
            ->middleware($verified)
            ->name('student.board.apply');

        Route::get('workflow', WorkflowController::class)->name('student.workflow');
        Route::get('process', ProjectProcessController::class)->name('student.process');
        Route::delete('my-applications/{application}', [ProjectBoardController::class, 'withdraw'])
            ->name('student.applications.withdraw');

        /*
         * An invitation is answered by the student it was sent to, so these
         * carry the same verification gate as applying: taking work on is the
         * thing that waits on a proved credential, not browsing for it.
         */
        Route::post('my-applications/{application}/accept', [ProjectBoardController::class, 'acceptInvitation'])
            ->middleware($verified)
            ->name('student.applications.accept');
        Route::post('my-applications/{application}/decline', [ProjectBoardController::class, 'declineInvitation'])
            ->middleware($verified)
            ->name('student.applications.decline');

        /*
         * Client List. Browsing businesses is open to any student, the same
         * way browsing postings is — what waits on verification is applying.
         */
        Route::get('clients', [ClientDirectoryController::class, 'index'])->name('student.clients.index');
        Route::get('clients/{business}', [ClientDirectoryController::class, 'show'])->name('student.clients.show');

        /*
         * The student's own profile and the work behind it. Open to any
         * student, verified or not: a profile is what an administrator reads
         * when deciding, so gating it behind verification would close the door
         * on the person trying to walk through it.
         */
        Route::get('my-profile', [StudentProfileController::class, 'edit'])->name('student.profile.edit');
        Route::patch('my-profile', [StudentProfileController::class, 'update'])->name('student.profile.update');

        /*
         * The profile's own sections, each edited in its own dialog rather
         * than through one page-wide save. Every one of them resolves the row
         * through the signed-in student's profile, so there is no policy on
         * any of these — somebody else's id is a 404.
         */
        Route::post('my-profile/photo', [StudentPhotoController::class, 'update'])
            ->name('student.photo.update');
        Route::delete('my-profile/photo', [StudentPhotoController::class, 'destroy'])
            ->name('student.photo.destroy');

        Route::post('my-profile/education', [StudentEducationController::class, 'store'])
            ->name('student.education.store');
        Route::patch('my-profile/education/{education}', [StudentEducationController::class, 'update'])
            ->name('student.education.update');
        Route::delete('my-profile/education/{education}', [StudentEducationController::class, 'destroy'])
            ->name('student.education.destroy');

        Route::post('my-profile/languages', [StudentLanguageController::class, 'store'])
            ->name('student.languages.store');
        Route::patch('my-profile/languages/{language}', [StudentLanguageController::class, 'update'])
            ->name('student.languages.update');
        Route::delete('my-profile/languages/{language}', [StudentLanguageController::class, 'destroy'])
            ->name('student.languages.destroy');

        Route::post('my-profile/portfolio', [PortfolioItemController::class, 'store'])
            ->name('student.portfolio.store');
        Route::patch('my-profile/portfolio/{portfolioItem}', [PortfolioItemController::class, 'update'])
            ->name('student.portfolio.update');
        Route::delete('my-profile/portfolio/{portfolioItem}', [PortfolioItemController::class, 'destroy'])
            ->name('student.portfolio.destroy');
    });
