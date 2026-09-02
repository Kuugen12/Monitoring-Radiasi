<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RadiationAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $detectorKey;
    public $rate;
    public $doseRate;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct($detectorKey, $rate, $doseRate, $user)
    {
        $this->detectorKey = $detectorKey;
        $this->rate = $rate;
        $this->doseRate = $doseRate;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $detectorLabels = [
            'detektor1' => 'Detektor 1 — Zona A (Gudang)',
            'detektor2' => 'Detektor 2 — Zona B (R. Kontrol)',
            'detektor3' => 'Detektor 3 — Zona C (Laboratorium)',
            'detektor4' => 'Detektor 4 — Zona D (R. Arsip)',
        ];
        $label = $detectorLabels[$this->detectorKey] ?? $this->detectorKey;

        return new Envelope(
            subject: '⚠️ PERINGATAN: Radiasi Tinggi Terdeteksi di ' . $label,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.radiation_alert',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
