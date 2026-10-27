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

class ShipmentShippedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Shipment $shipment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Maysha Order {$this->order->reference} has been shipped!",
        );
    }

    public function content(): Content
    {
        $name = htmlspecialchars($this->order->shipping_address['name'] ?? 'Customer');
        $awb = htmlspecialchars($this->shipment->awb_code ?? 'Pending');
        $courier = htmlspecialchars($this->shipment->courier_name ?? 'Our courier partner');
        $trackUrl = htmlspecialchars($this->shipment->tracking_url ?? "https://mayshaindia.com/orders/{$this->order->reference}");

        return new Content(
            htmlString: "<h2>Good news, {$name}!</h2><p>Your order <strong>{$this->order->reference}</strong> has been shipped with {$courier}.</p><p>Tracking / AWB Number: <strong>{$awb}</strong></p><p><a href=\"{$trackUrl}\" style=\"background:#1a1a1a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;\">Track Your Shipment</a></p><p>Thank you for choosing Maysha.</p>",
        );
    }
}
