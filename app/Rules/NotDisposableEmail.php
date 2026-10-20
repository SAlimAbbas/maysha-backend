<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotDisposableEmail implements ValidationRule
{
    /**
     * Known disposable, burner, and spambot email domains.
     * Includes the mx-mailsrv.com bot network discovered in subscriber logs.
     */
    protected static array $disposableDomains = [
        // Bot network domains
        'mx-mailsrv.com',
        'mailsrv.com',
        'mx-mail.com',
        'mailsrv.net',
        'mailsrv.org',
        'mail-server.org',
        'mx-server.net',

        // Popular disposable & temporary email services
        'mailinator.com',
        'tempmail.com',
        'temp-mail.org',
        'tempmail.net',
        'tempmail.io',
        '10minutemail.com',
        '10minutemail.net',
        '10minutemail.org',
        'guerrillamail.com',
        'guerrillamail.net',
        'guerrillamail.org',
        'guerrillamail.biz',
        'guerrillamailblock.com',
        'sharklasers.com',
        'grr.la',
        'dispostable.com',
        'throwawaymail.com',
        'yopmail.com',
        'yopmail.net',
        'yopmail.fr',
        'trashmail.com',
        'trashmail.net',
        'trashmail.me',
        'trashmail.org',
        'getairmail.com',
        'mytemp.email',
        'fakeinbox.com',
        'mohmal.com',
        'inboxkitten.com',
        'maildrop.cc',
        'burnermail.io',
        'crazymailing.com',
        'emailondeck.com',
        'mailnesia.com',
        'discard.email',
        'generator.email',
        'tempinbox.com',
        'spambog.com',
        'mailsac.com',
        'dropmail.me',
        'getnada.com',
        'nada.ltd',
        'fakemailgenerator.com',
        'armyspy.com',
        'cuvox.de',
        'dayrep.com',
        'einrot.com',
        'fleckens.hu',
        'gustr.com',
        'jourrapide.com',
        'rhyta.com',
        'superrito.com',
        'teleworm.us',
        'trbvm.com',
    ];

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('The :attribute must be a valid email address.');
            return;
        }

        $parts = explode('@', strtolower(trim($value)));
        if (count($parts) !== 2) {
            $fail('The :attribute format is invalid.');
            return;
        }

        $domain = $parts[1];

        // 1. Check exact match in blocked disposable domains list
        if (in_array($domain, self::$disposableDomains, true)) {
            $fail('Disposable or temporary email addresses are not permitted.');
            return;
        }

        // 2. Check domain pattern for burner / bot keywords
        $suspiciousPatterns = ['mailsrv', 'tempmail', 'trashmail', 'throwaway', 'burnermail', 'disposable'];
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($domain, $pattern)) {
                $fail('Disposable or temporary email addresses are not permitted.');
                return;
            }
        }

        // 3. In testing environment, bypass external DNS resolution to allow offline test suite to pass
        if (app()->environment('testing')) {
            return;
        }

        // Verify domain has valid DNS MX or A records (with fail-open on network issues)
        if (function_exists('checkdnsrr')) {
            try {
                // Check if host DNS is operational before testing domain
                static $dnsAvailable = null;
                if ($dnsAvailable === null) {
                    $dnsAvailable = @checkdnsrr('google.com', 'A');
                }
                if ($dnsAvailable) {
                    $hasMx = @checkdnsrr($domain, 'MX');
                    $hasA = $hasMx ? true : @checkdnsrr($domain, 'A');
                    if (! $hasMx && ! $hasA) {
                        $fail('The domain of the provided email address does not exist or cannot receive mail.');

                        return;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore transient DNS lookup errors to avoid blocking valid users during DNS glitch
            }
        }
    }
}
