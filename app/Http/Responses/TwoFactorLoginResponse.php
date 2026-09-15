<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    use RedirectsToCurrentTeam;

    public function toResponse($request): Response
    {
        $this->forgetHistoryFromBeforeSignIn();

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->to($this->destinationAfterSignIn($request, $this->redirectPathForCurrentTeam($request, Fortify::redirects('login'))));
    }
}
