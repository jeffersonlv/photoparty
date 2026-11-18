<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso ao contato de privacidade de que há uma nova solicitação de titular.
 * Sem dados pessoais (nem e-mail nem link do requerente): só protocolo, tipo e o caminho do admin.
 */
class PrivacyRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $protocol, public string $type)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Photo Party] '.__('privacy.mail.subject', ['protocol' => $this->protocol]));
    }

    public function content(): Content
    {
        return new Content(text: 'privacy.mail', with: [
            'protocol' => $this->protocol,
            'type' => __('privacy.types.'.$this->type),
            'url' => config('photoparty.public_url').route('admin.privacy.requests.index', [], false),
        ]);
    }
}
