
<?php
session_start();
require_once "../db.php";

/* AgriConnect Order Confirmation and Official Receipt */

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function money($amount) {
    return "KSh " . number_format((float)$amount, 2);
}

/* Check buyer login */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$buyer_id = (int)$_SESSION["user_id"];
$order_id = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT);

if (!$order_id || $order_id < 1) {
    http_response_code(400);
    exit("Invalid order number.");
}

/* Fetch the order belonging to this buyer */
$sql = "
    SELECT
        o.order_id,
        o.buyer_id,
        o.order_date,
        o.total_amount,
        o.delivery_location,
        o.status AS order_status,
        o.fulfillment_method,
        o.pickup_point_id,
        o.payment_method,
        o.payment_status,
        o.mpesa_receipt_number AS order_mpesa_receipt,
        u.full_name AS buyer_name,
        u.email AS buyer_email,
        u.phone AS buyer_phone,
        pp.point_name,
        pp.address AS pickup_address,
        pp.phone AS pickup_phone,
        pp.description AS pickup_description
    FROM orders o
    INNER JOIN users u ON o.buyer_id = u.user_id
    LEFT JOIN pickup_points pp
        ON o.pickup_point_id = pp.pickup_point_id
    WHERE o.order_id = ? AND o.buyer_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to prepare the order query. Check your database columns.");
}

$stmt->bind_param("ii", $order_id, $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit("Order not found, or you do not have permission to view it.");
}

/* Fetch purchased items */
$item_sql = "
    SELECT
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        p.product_name,
        p.unit
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id ASC
";

$item_stmt = $conn->prepare($item_sql);

if (!$item_stmt) {
    http_response_code(500);
    exit("Unable to retrieve order items.");
}

$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();

$items = [];

while ($item = $item_result->fetch_assoc()) {
    $items[] = $item;
}

$item_stmt->close();

/* Fetch a payment record that has been verified by administration */
$payment_sql = "
    SELECT
        payment_id,
        phone_number,
        amount,
        mpesa_receipt_number,
        result_code,
        result_description,
        payment_status,
        created_at,
        updated_at
    FROM mpesa_payments
    WHERE order_id = ?
      AND payment_status = 'paid'
      AND mpesa_receipt_number IS NOT NULL
      AND mpesa_receipt_number <> ''
    ORDER BY payment_id DESC
    LIMIT 1
";

$payment_stmt = $conn->prepare($payment_sql);

if (!$payment_stmt) {
    http_response_code(500);
    exit("Unable to retrieve payment information.");
}

$payment_stmt->bind_param("i", $order_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment = $payment_result->fetch_assoc();
$payment_stmt->close();

/*
 * An official receipt requires:
 * 1. The order is marked paid.
 * 2. Administration has marked an M-Pesa payment as paid.
 * 3. The verified M-Pesa receipt number matches the order record.
 */
$order_receipt = trim((string)($order["order_mpesa_receipt"] ?? ""));
$payment_receipt = trim((string)($payment["mpesa_receipt_number"] ?? ""));

$is_paid = (
    strtolower((string)$order["payment_status"]) === "paid"
    && $payment !== null
    && $order_receipt !== ""
    && $payment_receipt !== ""
    && $order_receipt === $payment_receipt
);

$document_title = $is_paid ? "ORDER RECEIPT" : "ORDER CONFIRMATION";

$document_number = "AC-ORD-" . str_pad(
    (string)$order["order_id"],
    6,
    "0",
    STR_PAD_LEFT
);

$fulfillment = strtolower(trim((string)$order["fulfillment_method"]));

$is_pickup = (
    strpos($fulfillment, "pickup") !== false ||
    strpos($fulfillment, "pick-up") !== false
);

$total_amount = (float)$order["total_amount"];
$paid_amount = $is_paid ? $total_amount : 0;
$balance = max(0, $total_amount - $paid_amount);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= h($document_title) ?> <?= h($document_number) ?> | AgriConnect</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f2f5f2;
            color: #25332b;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }

        .receipt {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
            padding: 38px;
            background: #fff;
            border: 1px solid #e1e8e2;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.06);
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding-bottom: 22px;
            border-bottom: 3px solid #167447;
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 75px;
            height: 75px;
            object-fit: contain;
        }

        .brand {
            margin: 0;
            color: #145b38;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .brand span {
            color: #75a843;
        }

        .issuer {
            margin: 5px 0 0;
            color: #64746a;
            font-size: 12px;
        }

        .receipt-heading {
            text-align: right;
        }

        .receipt-heading h2 {
            margin: 0 0 5px;
            color: #145b38;
            font-size: 21px;
        }

        .receipt-number {
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .section {
            margin-top: 25px;
        }

        .section h3 {
            margin: 0 0 12px;
            padding-bottom: 7px;
            border-bottom: 1px solid #e3e9e4;
            color: #145b38;
            font-size: 15px;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 25px;
        }

        .detail-label {
            display: block;
            color: #758078;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .detail-value {
            display: block;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background: #edf5ee;
            color: #24583b;
            text-align: left;
            font-size: 12px;
        }

        th, td {
            padding: 11px 9px;
            border-bottom: 1px solid #e5eae6;
            vertical-align: top;
        }

        .number {
            text-align: right;
            white-space: nowrap;
        }

        .totals {
            width: 100%;
            max-width: 340px;
            margin: 18px 0 0 auto;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 7px 0;
        }

        .grand-total {
            margin-top: 5px;
            padding-top: 13px;
            border-top: 2px solid #167447;
            color: #145b38;
            font-size: 18px;
            font-weight: 800;
        }

        .status {
            display: inline-block;
            padding: 7px 11px;
            border: 1px solid #e2b86c;
            border-radius: 5px;
            background: #fff6df;
            color: #7a4b00;
            font-weight: 700;
            font-size: 12px;
        }

        .status.paid {
            border-color: #9bc9a7;
            background: #e9f7ec;
            color: #176534;
        }

        .notice {
            margin-top: 18px;
            padding: 12px 14px;
            border-left: 4px solid #d9a441;
            background: #fff9eb;
            font-size: 12px;
        }

        .notice.verified {
            border-left-color: #167447;
            background: #edf7ef;
        }

        .footer {
            margin-top: 32px;
            padding-top: 17px;
            border-top: 1px solid #e3e9e4;
            color: #64746a;
            text-align: center;
            font-size: 12px;
        }

        .actions {
            max-width: 850px;
            margin: 18px auto 0;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .button {
            display: inline-block;
            padding: 12px 20px;
            border: 0;
            border-radius: 7px;
            background: #167447;
            color: white;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
        }

        .button.secondary {
            background: #59675e;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .receipt {
                padding: 20px 14px;
            }

            .top {
                flex-direction: column;
            }

            .receipt-heading {
                text-align: left;
            }

            .details {
                grid-template-columns: 1fr;
            }

            th, td {
                padding: 8px 5px;
                font-size: 12px;
            }

            .actions {
                flex-wrap: wrap;
            }

            .brand {
                font-size: 24px;
            }

            .logo {
                width: 60px;
                height: 60px;
            }
        }

        @page {
            size: A4;
            margin: 12mm;
        }

        @media print {
            body {
                padding: 0;
                background: white;
                font-size: 11px;
            }

            .receipt {
                max-width: none;
                padding: 0;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }

            .actions {
                display: none !important;
            }

            .section {
                break-inside: avoid;
            }

            table {
                break-inside: auto;
            }

            tr, .top {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

<main class="receipt">

    <header class="top">
        <div>
            <div class="brand-area">
                <img
                    src="../images/logo.png"
                    alt="AgriConnect Logo"
                    class="logo"
                >

                <div>
                    <h1 class="brand">Agri<span>Connect</span></h1>
                    <p class="issuer">
                        Issued by: AgriConnect Administration<br>
                        Connecting farmers to the marketplace
                    </p>
                </div>
            </div>
        </div>

        <div class="receipt-heading">
            <h2><?= h($document_title) ?></h2>

            <div class="receipt-number">
                <?= h($document_number) ?>
            </div>

            <div>
                Order date:
                <?= h(date("d M Y, h:i A", strtotime($order["order_date"]))) ?>
            </div>

            <div>
                Printed:
                <?= h(date("d M Y, h:i A")) ?>
            </div>
        </div>
    </header>

    <section class="section">
        <h3>Buyer Information</h3>

        <div class="details">
            <div>
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><?= h($order["buyer_name"]) ?></span>
            </div>

            <div>
                <span class="detail-label">Phone Number</span>
                <span class="detail-value"><?= h($order["buyer_phone"]) ?></span>
            </div>

            <div>
                <span class="detail-label">Email Address</span>
                <span class="detail-value"><?= h($order["buyer_email"]) ?></span>
            </div>

            <div>
                <span class="detail-label">Order Number</span>
                <span class="detail-value">#<?= h($order["order_id"]) ?></span>
            </div>
        </div>
    </section>

    <section class="section">
        <h3>Items Ordered</h3>

        <?php if (count($items) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="number">Quantity</th>
                        <th class="number">Unit Price</th>
                        <th class="number">Subtotal</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?= h($item["product_name"] ?: "Product #" . $item["product_id"]) ?>
                            </td>

                            <td class="number">
                                <?= h(rtrim(rtrim(number_format((float)$item["quantity"], 2, ".", ""), "0"), ".")) ?>
                                <?= h($item["unit"] ?? "") ?>
                            </td>

                            <td class="number">
                                <?= money($item["unit_price"]) ?>
                            </td>

                            <td class="number">
                                <?= money($item["subtotal"]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No order items were found for this order.</p>
        <?php endif; ?>

        <div class="totals">
            <div class="total-row">
                <span>Order Total</span>
                <strong><?= money($total_amount) ?></strong>
            </div>

            <?php if ($is_paid): ?>
                <div class="total-row">
                    <span>Amount Paid</span>
                    <strong><?= money($paid_amount) ?></strong>
                </div>

                <div class="total-row">
                    <span>Balance</span>
                    <strong><?= money($balance) ?></strong>
                </div>

                <div class="total-row grand-total">
                    <span>Total Paid</span>
                    <span><?= money($paid_amount) ?></span>
                </div>
            <?php else: ?>
                <div class="total-row grand-total">
                    <span>Amount Due</span>
                    <span><?= money($total_amount) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section">
        <h3>Payment Information</h3>

        <div class="details">
            <div>
                <span class="detail-label">Payment Method</span>
                <span class="detail-value">
                    <?= h(ucwords(str_replace("_", " ", $order["payment_method"] ?? "Not specified"))) ?>
                </span>
            </div>

            <div>
                <span class="detail-label">Payment Status</span>
                <span class="detail-value">
                    <?php if ($is_paid): ?>
                        <span class="status paid">PAID - PAYMENT VERIFIED</span>
                    <?php else: ?>
                        <span class="status">AWAITING PAYMENT VERIFICATION</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($is_paid): ?>
                <div>
                    <span class="detail-label">Verified M-Pesa Receipt Number</span>
                    <span class="detail-value">
                        <?= h($order_receipt) ?>
                    </span>
                </div>

                <div>
                    <span class="detail-label">Payment Phone Number</span>
                    <span class="detail-value">
                        <?= h($payment["phone_number"] ?: "Not recorded") ?>
                    </span>
                </div>

                <div>
                    <span class="detail-label">Verified Payment Amount</span>
                    <span class="detail-value">
                        <?= money($payment["amount"]) ?>
                    </span>
                </div>

                <div>
                    <span class="detail-label">Payment Verification Date</span>
                    <span class="detail-value">
                        <?php
                        if (!empty($payment["updated_at"])) {
                            echo h(date("d M Y, h:i A", strtotime($payment["updated_at"])));
                        } else {
                            echo "Not recorded";
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_paid): ?>
            <div class="notice verified">
                <strong>Payment verified.</strong>
                AgriConnect Administration has confirmed this payment.
                This is your official AgriConnect payment receipt.
                Keep it for your records.
            </div>
        <?php else: ?>
            <div class="notice">
                <strong>Order confirmation only.</strong>
                Your payment has not yet been verified by AgriConnect Administration.
                This document is not an official payment receipt. An official receipt
                will be available after successful payment verification.
            </div>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Fulfilment Information</h3>

        <div class="details">
            <div>
                <span class="detail-label">Fulfilment Method</span>
                <span class="detail-value">
                    <?= h(ucwords(str_replace("_", " ", $order["fulfillment_method"] ?? "Not specified"))) ?>
                </span>
            </div>

            <div>
                <span class="detail-label">Order Status</span>
                <span class="detail-value">
                    <?= h(ucfirst($order["order_status"] ?? "Not specified")) ?>
                </span>
            </div>

            <?php if ($is_pickup): ?>
                <div>
                    <span class="detail-label">Pickup Point</span>
                    <span class="detail-value">
                        <?= h($order["point_name"] ?? "Pickup point not assigned") ?>
                    </span>
                </div>

                <div>
                    <span class="detail-label">Pickup Address</span>
                    <span class="detail-value">
                        <?= h($order["pickup_address"] ?? "Not provided") ?>
                    </span>
                </div>

                <div>
                    <span class="detail-label">Pickup Phone</span>
                    <span class="detail-value">
                        <?= h($order["pickup_phone"] ?: "Not provided") ?>
                    </span>
                </div>

                <div>
                    <span class="detail-label">Pickup Description</span>
                    <span class="detail-value">
                        <?= h($order["pickup_description"] ?: "Not provided") ?>
                    </span>
                </div>
            <?php else: ?>
                <div>
                    <span class="detail-label">Delivery Location</span>
                    <span class="detail-value">
                        <?= h($order["delivery_location"] ?? "Not provided") ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">
        <strong>Thank you for choosing AgriConnect!</strong><br>
        Connecting farmers to buyers through a trusted marketplace.<br>
        This document was generated electronically by AgriConnect.
    </footer>

</main>

<div class="actions">
    <button class="button" onclick="window.print()">
        Print / Save as PDF
    </button>

    <a class="button secondary" href="orders.php">
        Back to My Orders
    </a>
</div>

</body>
</html>
