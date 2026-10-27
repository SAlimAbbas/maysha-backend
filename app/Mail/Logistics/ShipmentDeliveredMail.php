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

class ShipmentDeliveredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Shipment $shipment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Maysha Order {$this->order->reference} has been Delivered!",
        );
    }

    public function content(): Content
    {
        $name = htmlspecialchars($this->order->shipping_address['name'] ?? 'Customer');

        return new Content(
            htmlString: "<h2>Delivered!</h2><p>Hello {$name}, your Maysha package for order <strong>{$this->order->reference}</strong> has been successfully delivered.</p><p>We hope you love your products! Feel free to share your review on our store.</p>",
        );
    }
}
