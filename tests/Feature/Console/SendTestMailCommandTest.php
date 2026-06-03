<?php

namespace Tests\Feature\Console;

use App\Mail\DeployTestMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestMailCommandTest extends TestCase
{
    public function test_mail_test_command_sends_to_recipient(): void
    {
        Mail::fake();

        $this->artisan('wenando:mail-test', ['email' => 'ops@wenando.com'])
            ->assertSuccessful();

        Mail::assertSent(DeployTestMail::class, function (DeployTestMail $mail): bool {
            return $mail->hasTo('ops@wenando.com');
        });
    }

    public function test_mail_test_command_rejects_invalid_email(): void
    {
        Mail::fake();

        $this->artisan('wenando:mail-test', ['email' => 'not-an-email'])
            ->assertFailed();

        Mail::assertNothingSent();
    }
}
