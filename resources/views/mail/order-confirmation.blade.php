<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation</title>
</head>
<body>
    <h1>Thank you for your order!</h1>
    <p>Dear {{ $user->name }},</p>
    <p>We have received your order and it is currently being processed. Here are the details of your order:</p>
    <ul>
        <li>Order Number: {{ $order->id }}</li>
        <li>Order Date: {{ $order->created_at->format('F j, Y') }}</li>
        <li>Total Amount: ${{ number_format($order->total, 2) }}</li>
    </ul>
    <p>Thank you for shopping with us!</p>
</body>
</html>
