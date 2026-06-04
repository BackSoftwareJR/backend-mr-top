<?php

namespace App\Console\Commands;

use App\Mail\DeployTestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SendTestMailCommand extends Command
{
    protected $signature = 'wenando:mail-test {email : Recipient address for the SMTP verification message}';

    protected $description = 'Send a test email via configured SMTP (Hostinger deploy verification)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email:rfc,dns']],
        );

        if ($validator->fails()) {
            $this->components->error('Invalid email address.');

            foreach ($validator->errors()->all() as $message) {
                $this->line("  {$message}");
            }

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $from = (string) config('mail.from.address');

        if ($mailer === 'log' || $mailer === 'array') {
            $this->components->warn("MAIL_MAILER={$mailer} — not suitable for production SMTP verification.");
        }

        if ($host === '' || $host === '127.0.0.1') {
            $this->components->warn('MAIL_HOST looks unset; check .env before sending.');
        }

        if ($from === '' || $from === 'hello@example.com') {
            $this->components->warn('MAIL_FROM_ADDRESS should be hola@wenando.com for alignment with Hostinger mailbox.');
        }

        $this->components->info("Sending test mail to {$email} via {$mailer} ({$host})…");

        try {
            Mail::to($email)->send(new DeployTestMail);

            $this->components->success('Test message accepted by the mail transport.');
            $this->line('  OtpMail is sent synchronously on login; WelcomeMail still uses the database queue (cron queue:work).');
            $this->line('  Check inbox and spam; verify SPF/DKIM/DMARC (docs/11_EMAIL_&_DELIVERABILITY.md).');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error('Mail send failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
