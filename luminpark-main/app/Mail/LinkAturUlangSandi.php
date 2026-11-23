<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email "lupa password": berisi tautan verifikasi sekali pakai untuk mengatur ulang
 * kata sandi. Tautan dibangun di sisi server supaya token tidak pernah diolah ulang
 * oleh peramban penerima.
 */
class LinkAturUlangSandi extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nama,
        public string $url,
        public int $menitKedaluwarsa,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tautan Atur Ulang Kata Sandi Luminpark',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.link-atur-ulang-sandi',
        );
    }
}
