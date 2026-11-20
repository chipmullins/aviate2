<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Passes only when the email's domain is exactly one of the allowed airline
 * domains. "pilot@delta.com" passes; "pilot@notdelta.com",
 * "pilot@delta.com.evil.net" and "pilot@ops.delta.com" do not.
 */
class AirlineEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            $fail('Enter a valid email address.');

            return;
        }

        $domain = Str::lower(Str::afterLast($value, '@'));

        if (! in_array($domain, config('aviate.allowed_email_domains'), true)) {
            $fail('Aviate is only open to airline employees. Use your work email ('.implode(', ', array_map(
                fn ($d) => '@'.$d,
                config('aviate.allowed_email_domains')
            )).').');
        }
    }
}
