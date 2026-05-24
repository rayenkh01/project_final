<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestSmtpMail extends Command
{
    protected $signature = 'mail:test {email : Recipient email address}';

    protected $description = 'Send a test email using the configured mailer';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        try {
            Mail::raw(
                "Bonjour,\n\nCeci est un test SMTP depuis la plateforme VAS CDR.\n\nConfiguration mail operationnelle.",
                function ($message) use ($email): void {
                    $message
                        ->to($email)
                        ->subject('Test SMTP - VAS CDR');
                }
            );
        } catch (Throwable $e) {
            $this->error('Email not sent.');
            $this->error($e->getMessage());

            return 1;
        }

        $this->info("Test email sent to {$email}.");

        return 0;
    }
}
