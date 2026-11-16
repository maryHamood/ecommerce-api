<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {
    }

    public function build(): static
    {
        return $this
            ->subject('Order Confirmation #' . $this->order->id)
            ->view('emails.orders.confirmation');
    }
}
