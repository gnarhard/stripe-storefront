<!DOCTYPE html>
<html>
<head>
    <title>New Order Notification</title>
</head>
<body>
    <h1>New Order Received</h1>
    <p>Dear Store Owner,</p>
    <p>We are excited to inform you that a new order has been placed on your website.</p>
    <p>Order Details:</p>
    <ul>
        <li><strong>Product Name:</strong> {{ $productName }}</li>
        <li><strong>Quantity:</strong> {{ $quantity }}</li>
        <li><strong>Total Price:</strong> ${{ $totalPrice }}</li>
    </ul>
    <p>Please log into your Stripe admin panel to view more details about this order.</p>
</body>
</html>
