<?php

namespace App\Console\Commands;

use App\Mail\TestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSent;

class SendTestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-test-email {email} {--subject=Test Email} {--message=This is a test email from Laravel!}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email using Mailgun';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("--- Environment File ---");
        $this->info("Loaded .env file: " . app()->environmentPath() . '/' . app()->environmentFile());
        $this->info("----------------------\n");

        $this->info("--- Artisan Command Configuration ---");
        $this->info("Mailer: " . config('mail.default'));
        $this->info("Mailgun Domain: " . config('services.mailgun.domain'));
        $this->info("Mailgun Secret: " . config('services.mailgun.secret'));
        $this->info("Mailgun Endpoint: " . config('services.mailgun.endpoint'));
        $this->info("-------------------------------------\n");

        $email = $this->argument('email');
        $subject = $this->option('subject');
        $message = $this->option('message');

        $this->info('Sending test email to: ' . $email);

        Event::listen(MessageSent::class, function (MessageSent $event) {
            $this->info('Mailgun Message ID: ' . $event->sent->getMessageId());
        });

        try {
            Mail::to($email)->send(new TestEmail($subject, $message));
            $this->info('Email sent successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
