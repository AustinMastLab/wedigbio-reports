<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Source;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IngestionOutageAlert extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Event $event,
        public Source $source,
        public CarbonInterface $firstFailedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ingestion API unavailable: {$this->source->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.ingestion-outage-alert',
        );
    }
}
