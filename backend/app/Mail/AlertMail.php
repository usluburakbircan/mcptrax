<?php

namespace App\Mail;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Alert $alert,
        public bool $opened,
    ) {
    }

    public function envelope(): Envelope
    {
        $monitor = $this->alert->monitor;

        $subject = match (true) {
            $this->alert->kind === 'drift' => "⚠️ Tools changed on {$monitor->name}",
            $this->opened => "🔴 {$monitor->name} is DOWN",
            default => "🟢 {$monitor->name} recovered",
        };

        return new Envelope(subject: "[mcptrax] {$subject}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.alert',
            with: [
                'alert' => $this->alert,
                'monitor' => $this->alert->monitor,
                'opened' => $this->opened,
            ],
        );
    }
}
