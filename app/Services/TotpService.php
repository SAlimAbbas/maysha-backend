<?php

namespace App\Services;

class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new random Base32 secret.
     */
    public function generateSecret(int $byteLength = 20): string
    {
        $randomBytes = random_bytes($byteLength);

        return $this->base32Encode($randomBytes);
    }

    /**
     * Generate an otpauth:// provisioning URI for QR code generation.
     */
    public function getOtpAuthUri(string $accountName, string $secret, string $issuer = 'Maysha'): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Verify a 6-digit TOTP code against a Base32 secret.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $secretBytes = $this->base32Decode($secret);
        if ($secretBytes === null) {
            return false;
        }

        $currentStep = (int) floor(time() / 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $currentStep + $offset;
            $calculatedCode = $this->calculateCode($secretBytes, $step);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate 10 random recovery codes.
     *
     * @return array{plain: string[], hashed: string[]}
     */
    public function generateRecoveryCodes(int $count = 10): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            $part1 = bin2hex(random_bytes(2));
            $part2 = bin2hex(random_bytes(2));
            $code = strtoupper("{$part1}-{$part2}");

            $plain[] = $code;
            $hashed[] = password_hash($code, PASSWORD_BCRYPT);
        }

        return [
            'plain' => $plain,
            'hashed' => $hashed,
        ];
    }

    /**
     * Verify and consume a recovery code from the hashed recovery codes list.
     *
     * @param  string[]  $hashedCodes
     * @return array{valid: bool, remainingHashed: string[]}
     */
    public function verifyAndConsumeRecoveryCode(string $plainCode, array $hashedCodes): array
    {
        $plainCode = strtoupper(trim($plainCode));

        foreach ($hashedCodes as $index => $hashedCode) {
            if (password_verify($plainCode, $hashedCode)) {
                unset($hashedCodes[$index]);

                return [
                    'valid' => true,
                    'remainingHashed' => array_values($hashedCodes),
                ];
            }
        }

        return [
            'valid' => false,
            'remainingHashed' => $hashedCodes,
        ];
    }

    /**
     * Calculate 6-digit code for a given timestamp step.
     */
    private function calculateCode(string $secretBytes, int $step): string
    {
        // Pack step into 8-byte big-endian binary string
        $binaryStep = pack('J', $step);

        $hash = hash_hmac('sha1', $binaryStep, $secretBytes, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord($hash[19]) & 0x0F;
        $binaryCode = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binaryCode % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 encoding.
     */
    private function base32Encode(string $binary): string
    {
        if (strlen($binary) === 0) {
            return '';
        }

        $chars = self::BASE32_CHARS;
        $bits = '';
        $output = '';

        for ($i = 0; $i < strlen($binary); $i++) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }

        $remainder = strlen($bits) % 5;
        if ($remainder > 0) {
            $bits .= str_repeat('0', 5 - $remainder);
        }

        $chunks = str_split($bits, 5);
        foreach ($chunks as $chunk) {
            $output .= $chars[bindec($chunk)];
        }

        return $output;
    }

    /**
     * Base32 decoding.
     */
    private function base32Decode(string $base32): ?string
    {
        $base32 = strtoupper(str_replace('=', '', trim($base32)));
        if ($base32 === '') {
            return '';
        }

        $chars = self::BASE32_CHARS;
        $bits = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $pos = strpos($chars, $base32[$i]);
            if ($pos === false) {
                return null;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = str_split($bits, 8);
        $output = '';
        foreach ($bytes as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }
}
