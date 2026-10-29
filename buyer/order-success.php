
<?php
session_start();
require_once "../db.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "buyer"
) {
    header("Location: ../login.php");
    exit;
}

$order_id = (int) ($_SESSION["last_order_id"] ?? 0);
$buyer_id = (int) $_SESSION["user_id"];

if ($order_id < 1) {
    header("Location: ../index.php");
    exit;
}

// Retrieve the order belonging to this buyer only.
$stmt = $conn->prepare(
    "SELECT order_id, order_date, total_amount,
            delivery_location, status, payment_status
     FROM orders
     WHERE order_id = ? AND buyer_id = ?
     LIMIT 1"
);

$stmt->bind_param("ii", $order_id, $buyer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit("Order not found.");
}

// Retrieve the items in this order.
$stmt = $conn->prepare(
    "SELECT p.product_name, oi.quantity,
            oi.unit_price, oi.subtotal
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?"
);

$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

// Prepare safe values for display.
$order_status = ucfirst($order["status"]);
$payment_status = ucfirst($order["payment_status"]);

$payment_badge = match ($order["payment_status"]) {
    "paid" => "success",
    "failed" => "danger",
    default => "warning"
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Order Placed | AgriConnect</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f7faf4;
            font-family: Arial, sans-serif;
            color: #25372d;
        }

        .success-card {
            max-width: 760px;
            margin: 40px auto;
            padding: 30px;
            background: #ffffff;
            border: 1px solid #e2e9e1;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(20, 60, 35, 0.06);
        }

        h1 {
            color: #075b3d;
            font-weight: 800;
        }

        .success-icon {
            font-size: 55px;
        }

        .order-details {
            background: #f7faf4;
            border-radius: 12px;
            padding: 18px;
        }

        .payment-box {
            background: #fff9e6;
            border: 1px solid #f0df9c;
            border-radius: 12px;
            padding: 18px;
        }

        .btn-green {
            display: inline-block;
            background: #13804f;
            color: white;
            padding: 11px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
        }

        .btn-green:hover {
            background: #075b3d;
            color: white;
        }

        .total-amount {
            color: #075b3d;
            font-weight: 800;
        }

        @media (max-width: 576px) {
            .success-card {
                margin: 18px auto;
                padding: 20px;
            }
        }
    </style>
</head>

<body>
<main class="container">
    <section class="success-card">

        <div class="text-center mb-4">
            <div class="success-icon">✅</div>

            <h1 class="h2">Order Placed Successfully!</h1>

            <p class="text-secondary">
                Thank you for shopping with AgriConnect.
            </p>

            <p class="mb-1">Your order number is</p>

            <h2 class="h4">
                #<?= (int) $order["order_id"] ?>
            </h2>
        </div>

        <div class="order-details mb-4">
            <h2 class="h5 mb-3">Order Information</h2>

            <p>
                <strong>Date:</strong>
                <?= htmlspecialchars(
                    $order["order_date"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </p>

            <p>
                <strong>Order status:</strong>
                <?= htmlspecialchars(
                    $order_status,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </p>

            <p>
                <strong>Payment status:</strong>
                <span class="badge text-bg-<?= $payment_badge ?>">
                    <?= htmlspecialchars(
                        $payment_status,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </span>
            </p>

            <p class="mb-0">
                <strong>Delivery location:</strong>
                <?= htmlspecialchars(
                    $order["delivery_location"] ?? "Not provided",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </p>
        </div>

        <div class="payment-box mb-4">
            <h2 class="h5">💳 M-Pesa Payment Instructions</h2>

            <?php if ($order["payment_status"] === "paid"): ?>

                <p class="mb-0">
                    Your payment has been verified by the AgriConnect
                    administrator. Thank you for your payment.
                </p>

            <?php elseif ($order["payment_status"] === "failed"): ?>

                <p>
                    Your submitted payment has not been approved.
                    Please contact the AgriConnect administrator
                    before attempting another payment.
                </p>

            <?php else: ?>

                <p>
                    Your order has been recorded, but your payment
                    has not yet been verified.
                </p>

                <p>
                    The official M-Pesa recipient number has not yet
                    been configured.
                    <strong>Do not send money to an unconfirmed number.</strong>
                </p>

                <p class="mb-0">
                    Once the official payment details are available,
                    follow the instructions provided by AgriConnect.
                    Your payment will remain unpaid until an
                    administrator independently verifies the
                    transaction.
                </p>

            <?php endif; ?>
        </div>

        <h2 class="h5 mt-4 mb-3">Items Ordered</h2>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($items->num_rows > 0): ?>

                    <?php while ($item = $items->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars(
                                    $item["product_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $item["quantity"] ?>
                            </td>

                            <td>
                                KSh <?= number_format(
                                    (float) $item["unit_price"],
                                    2
                                ) ?>
                            </td>

                            <td>
                                KSh <?= number_format(
                                    (float) $item["subtotal"],
                                    2
                                ) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4" class="text-center">
                            No order items were found.
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="text-end mt-3">
            <h3 class="h4 total-amount">
                Total: KSh <?= number_format(
                    (float) $order["total_amount"],
                    2
                ) ?>
            </h3>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <a href="../produce-catalogue.php" class="btn-green">
                Continue Shopping
            </a>

            <a href="../index.php" class="btn btn-outline-success">
                Home
            </a>
        </div>

    </section>
</main>
</body>
</html>
<?php
$stmt->close();
?>
