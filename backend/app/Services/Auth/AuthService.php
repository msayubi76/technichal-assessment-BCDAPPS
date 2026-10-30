<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * A bcrypt hash of a random value that was thrown away, so nobody knows it.
     * It's built ahead of time with the same cost as real passwords (12), so
     * checking against it takes as long as checking a real password.
     */
    private const DECOY_HASH = '$2y$12$tmWvB9AidpRPW9Sloa2fr.Z16l6v478M8oG.yzL5HKVa3I8naIf3e';

    /**
     * Checks the email and password and hands back a new API token.
     *
     * @return array{token: string, user: User}
     */
    public function login(string $email, string $password, ?string $deviceName): array
    {
        $user = User::where('email', strtolower($email))->first();

        // Unknown emails are checked against the decoy hash, so every login runs
        // exactly one password check and takes the same time. That way the
        // response time doesn't reveal which emails have accounts.
        $hash = $user?->password ?? self::DECOY_HASH;

        if (! Hash::check($password, $hash) || $user === null) {
            Log::notice('auth.login_failed', ['email_hash' => hash('sha256', strtolower($email))]);

            throw new ApiException('invalid_credentials', 'The email or password is incorrect.', 401);
        }

        $token = $user->createToken($deviceName ?: 'web-app')->plainTextToken;

        Log::info('auth.login', ['user_id' => $user->id]);

        return ['token' => $token, 'user' => $user->load('driver.user')];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
