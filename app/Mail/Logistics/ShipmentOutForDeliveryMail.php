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

class ShipmentOutForDeliveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Shipment $shipment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Maysha Order {$this->order->reference} is Out for Delivery!",
        );
    }

    public function content(): Content
    {
        $name = htmlspecialchars($this->order->shipping_address['name'] ?? 'Customer');
        $awb = htmlspecialchars($this->shipment->awb_code ?? '');

        return new Content(
            htmlString: "<h2>Your order is arriving today, {$name}!</h2><p>Your order <strong>{$this->order->reference}</strong> (AWB: {$awb}) is out for delivery with our courier partner.</p><p>Please ensure someone is available at your delivery address to receive the parcel.</p>",
        );
    }
}
