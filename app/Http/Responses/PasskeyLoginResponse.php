<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    use RedirectsToCurrentTeam;

    public function toResponse($request): Response
    {
        $this->forgetHistoryFromBeforeSignIn();

        $redirect = $this->destinationAfterSignIn($request, $this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));

        /*
         * Absolute, as it always was: redirect()->intended()->getTargetUrl()
         * returned a full URL and the passkey client navigates to whatever it
         * is handed. url() leaves an already-absolute intended URL alone.
         */
        return $request->wantsJson()
            ? new JsonResponse(['redirect' => url($redirect)], 200)
            : redirect()->to($redirect);
    }
}
