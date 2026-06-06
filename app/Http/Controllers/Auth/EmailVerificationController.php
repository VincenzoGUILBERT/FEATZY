<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Verify the email from the signed link clicked in a mail client.
     *
     * No `auth:sanctum`/`signed` middleware: the link is opened without a
     * session and an invalid/expired signature must redirect back to the SPA
     * with a status rather than abort 403. The user is resolved from the route
     * `{id}` and the signature/hash are validated manually here.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->redirectToSpa('invalid');
        }

        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->redirectToSpa('invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->redirectToSpa('already');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->redirectToSpa('success');
    }

    /**
     * Resend the verification link without requiring an authenticated session.
     *
     * Returns the same generic response regardless of whether the account
     * exists or is already verified, to prevent account enumeration.
     */
    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Si un compte non vérifié correspond à cette adresse, un lien de vérification a été envoyé.',
        ]);
    }

    /**
     * Redirect to the SPA email-verification result page with a status flag.
     */
    private function redirectToSpa(string $status): RedirectResponse
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($frontend.'/email/verified?'.http_build_query(['status' => $status]));
    }
}
