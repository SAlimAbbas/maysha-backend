<?php

namespace App\Mail\Logistics;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentNdrMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Shipment $shipment,
        public ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Delivery Update: Action needed for your Maysha Order {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        $name = htmlspecialchars($this->order->shipping_address['name'] ?? 'Customer');
        $reasonText = htmlspecialchars($this->reason ?: 'Delivery attempt was unsuccessful.');

        return new Content(
            htmlString: "<h2>Delivery Update</h2><p>Hello {$name}, our courier partner attempted delivery for order <strong>{$this->order->reference}</strong>, but was unable to complete it.</p><p>Reason: {$reasonText}</p><p>Please ensure your phone is reachable for re-attempt, or contact our support team at support@mayshaindia.com.</p>",
        );
    }
}
