<?php

declare(strict_types=1);

namespace App\Services\Firebase;

use App\Contracts\FirebaseAuthGateway;
use App\Support\Firebase\FirebaseAuthException;
use App\Support\Firebase\FirebaseIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class RestFirebaseAuthGateway implements FirebaseAuthGateway
{
    private const BASE_URL = 'https://identitytoolkit.googleapis.com/v1/';

    public function __construct(private readonly string $apiKey) {}

    public function signInWithPassword(string $email, string $password): FirebaseIdentity
    {
        $login = $this->post('accounts:signInWithPassword', ['email'=>$email,'password'=>$password,'returnSecureToken'=>true]);
        if (empty($login['idToken'])) throw new FirebaseAuthException(FirebaseAuthException::LAYANAN_GAGAL);
        return $this->verifikasiEmail((string)$login['idToken']);
    }

    public function verifikasiEmail(string $idToken): FirebaseIdentity
    {
        $result = $this->post('accounts:lookup', ['idToken' => $idToken]);
        $user = $result['users'][0] ?? null;
        if (!is_array($user) || empty($user['localId']) || empty($user['email'])) {
            throw new FirebaseAuthException(FirebaseAuthException::KREDENSIAL_SALAH);
        }
        return new FirebaseIdentity((string)$user['localId'], mb_strtolower((string)$user['email']), (bool)($user['emailVerified'] ?? false));
    }

    public function sendPasswordReset(string $email): void
    {
        $this->post('accounts:sendOobCode', ['requestType'=>'PASSWORD_RESET','email'=>$email]);
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $payload): array
    {
        if ($this->apiKey === '') throw new FirebaseAuthException(FirebaseAuthException::LAYANAN_GAGAL);
        try {
            $response = Http::acceptJson()->asJson()->timeout(8)->post(self::BASE_URL.$path.'?key='.urlencode($this->apiKey), $payload);
        } catch (ConnectionException $e) {
            throw new FirebaseAuthException(FirebaseAuthException::LAYANAN_GAGAL, $e);
        }
        if ($response->successful()) return (array)$response->json();
        $code = (string)data_get($response->json(), 'error.message', '');
        $known = ['INVALID_ID_TOKEN','TOKEN_EXPIRED','EMAIL_NOT_FOUND','INVALID_PASSWORD','INVALID_LOGIN_CREDENTIALS','EMAIL_NOT_FOUND','INVALID_EMAIL'];
        $authError = collect($known)->contains(fn($prefix) => str_starts_with($code, $prefix));
        throw new FirebaseAuthException($authError ? FirebaseAuthException::KREDENSIAL_SALAH : FirebaseAuthException::LAYANAN_GAGAL, kodeFirebase: $code);
    }
}
