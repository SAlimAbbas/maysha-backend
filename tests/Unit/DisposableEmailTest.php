<?php

namespace Tests\Unit;

use App\Rules\NotDisposableEmail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DisposableEmailTest extends TestCase
{
    public function test_rejects_mx_mailsrv_bot_domain(): void
    {
        $validator = Validator::make(
            ['email' => 'morgan.morris46@mx-mailsrv.com'],
            ['email' => ['required', new NotDisposableEmail()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('Disposable or temporary email', $validator->errors()->first('email'));
    }

    public function test_rejects_common_disposable_domains(): void
    {
        $domains = ['test@mailinator.com', 'user@tempmail.com', 'bot@10minutemail.com', 'fake@trashmail.com'];
        foreach ($domains as $email) {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => ['required', new NotDisposableEmail()]]
            );
            $this->assertTrue($validator->fails(), "Failed to reject: {$email}");
        }
    }

    public function test_accepts_legitimate_email(): void
    {
        $validator = Validator::make(
            ['email' => 'customer@gmail.com'],
            ['email' => ['required', new NotDisposableEmail()]]
        );

        $this->assertTrue($validator->passes());
    }
}
