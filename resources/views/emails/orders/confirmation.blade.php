<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
</head>
<body>
<h2>Thank you for your order!</h2>

<p>Hello {{ $order->user->name }},</p>

<p>Your order has been successfully created.</p>

<h3>Order Details</h3>

<p><strong>Order Number:</strong> #{{ $order->id }}</p>
<p><strong>Status:</strong> {{ $order->status }}</p>
<p><strong>Total:</strong> {{ number_format($order->total, 2) }}</p>

<h3>Items</h3>

<table border="1" cellpadding="8" cellspacing="0">
    <thead>
    <tr>
        <th>Product</th>
        <th>Quantity</th>
        <th>Unit Price</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($order->items as $item)
        <tr>
            <td>{{ $item->product->name ?? 'Product' }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ number_format($item->price, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p>We will notify you when your order status changes.</p>

<p>Thank you for shopping with us!</p>
</body>
</html>
