<?php
session_start();

require_once "../db.php";
require_once "../payment-config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "buyer"
) {
    header("Location: login.php");
    exit;
}

$buyer_id = (int) $_SESSION["user_id"];
$message = "";
$message_type = "";
$order = null;
$existing_payment = null;

if (empty($_SESSION["payment_csrf"])) {
    $_SESSION["payment_csrf"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$order_id = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT);

if (!$order_id) {
    $order_id = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
}

if (!$order_id || $order_id < 1) {
    http_response_code(400);
    exit("Invalid order number. Open this page from your order details.");
}

/*
 * Only allow the logged-in buyer to access their own order.
 */
$stmt = $conn->prepare(
    "SELECT order_id, total_amount, status, payment_method,
            payment_status, mpesa_receipt_number
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
    exit("Order not found, or you do not have permission to access it.");
}

/*
 * Handle receipt submission.
 * A submitted receipt is PENDING until the administrator verifies it.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $phone = trim($_POST["phone_number"] ?? "");
    $receipt = strtoupper(trim($_POST["receipt_number"] ?? ""));

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["payment_csrf"], $token)
    ) {
        $message = "Security check failed. Refresh the page and try again.";
        $message_type = "danger";
    } elseif ($order["payment_status"] === "paid") {
        $message = "This order has already been marked as paid.";
        $message_type = "success";
    } elseif (
        !preg_match('/^(?:0[17][0-9]{8}|254[17][0-9]{8}|\+254[17][0-9]{8})$/', $phone)
    ) {
        $message = "Enter a valid Kenyan M-Pesa phone number.";
        $message_type = "danger";
    } elseif (!preg_match('/^[A-Z0-9]{8,20}$/', $receipt)) {
        $message = "Enter a valid M-Pesa transaction code.";
        $message_type = "danger";
    } else {
        try {
            $conn->begin_transaction();

            /*
             * Lock the order and check its latest payment status.
             */
            $lock = $conn->prepare(
                "SELECT payment_status
                 FROM orders
                 WHERE order_id = ? AND buyer_id = ?
                 FOR UPDATE"
            );
            $lock->bind_param("ii", $order_id, $buyer_id);
            $lock->execute();
            $latest = $lock->get_result()->fetch_assoc();
            $lock->close();

            if (!$latest) {
                throw new RuntimeException("Order not found.");
            }

            if ($latest["payment_status"] === "paid") {
                throw new RuntimeException(
                    "This order has already been marked as paid."
                );
            }

            /*
             * Prevent duplicate submissions for this order while a
             * receipt is already awaiting verification.
             */
            $check = $conn->prepare(
                "SELECT payment_id
                 FROM mpesa_payments
                 WHERE order_id = ?
                   AND payment_status = 'pending'
                 LIMIT 1
                 FOR UPDATE"
            );
            $check->bind_param("i", $order_id);
            $check->execute();
            $pending = $check->get_result()->fetch_assoc();
            $check->close();

            if ($pending) {
                throw new RuntimeException(
                    "A payment receipt is already awaiting admin verification for this order."
                );
            }

            /*
             * Store the submitted receipt as pending.
             * Do NOT mark the order as paid here.
             */
            $payment_status = "pending";
            $description =
                "Buyer submitted a receipt. Awaiting independent admin verification.";

            $insert = $conn->prepare(
                "INSERT INTO mpesa_payments
                    (order_id, phone_number, amount,
                     mpesa_receipt_number, result_description,
                     payment_status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $amount = (float) $order["total_amount"];

            $insert->bind_param(
                "isdsss",
                $order_id,
                $phone,
                $amount,
                $receipt,
                $description,
                $payment_status
            );

            $insert->execute();
            $insert->close();

            $conn->commit();

            $message =
                "Your M-Pesa receipt has been submitted. " .
                "Payment is still pending until the administrator verifies the transaction.";

            $message_type = "success";

        } catch (Throwable $ex) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
                // No active transaction to roll back.
            }

            if (
                $ex instanceof RuntimeException
            ) {
                $message = $ex->getMessage();
            } elseif (
                $conn->errno === 1062 ||
                str_contains(strtolower($ex->getMessage()), "duplicate entry")
            ) {
                $message =
                    "This transaction code has already been submitted. " .
                    "Please contact the administrator if you believe this is an error.";
            } else {
                $message =
                    "We could not submit the receipt. Please try again or contact the administrator.";
            }

            $message_type = "danger";
        }

        /*
         * Reload the order and any submitted payment after processing.
         */
        $stmt = $conn->prepare(
            "SELECT order_id, total_amount, status, payment_method,
                    payment_status, mpesa_receipt_number
             FROM orders
             WHERE order_id = ? AND buyer_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("ii", $order_id, $buyer_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

/*
 * Show the most recent submitted payment for this order.
 */
$stmt = $conn->prepare(
    "SELECT phone_number, mpesa_receipt_number,
            payment_status, created_at
     FROM mpesa_payments
     WHERE order_id = ?
     ORDER BY payment_id DESC
     LIMIT 1"
);

$stmt->bind_param("i", $order_id);
$stmt->execute();
$existing_payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

$recipient_phone = defined("MPESA_RECIPIENT_PHONE")
    ? trim(MPESA_RECIPIENT_PHONE)
    : "";

$recipient_name = defined("MPESA_RECIPIENT_NAME")
    ? trim(MPESA_RECIPIENT_NAME)
    : "AgriConnect";

$already_paid = $order["payment_status"] === "paid";

$pending_verification = $existing_payment &&
    $existing_payment["payment_status"] === "pending";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Submit M-Pesa Payment | AgriConnect</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>
:root {
    --dark-green: #173D2A;
    --green: #245638;
    --yellow: #F5D547;
    --cream: #F5F7F2;
}

body {
    margin: 0;
    background: var(--cream);
    color: #26352B;
    font-family: Arial, sans-serif;
}

.navbar {
    background: var(--dark-green);
}

.navbar-brand {
    color: var(--yellow);
    font-weight: 800;
}

.navbar-brand:hover {
    color: white;
}

.payment-card {
    max-width: 650px;
    margin: 35px auto;
    padding: 28px;
    background: white;
    border: 1px solid #E4EAE3;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(23, 61, 42, .06);
}

.page-title {
    color: var(--dark-green);
    font-weight: 800;
}

.order-total {
    background: #F1F6EF;
    border-radius: 12px;
    padding: 18px;
}

.amount {
    color: var(--dark-green);
    font-size: 28px;
    font-weight: 800;
}

.btn-success {
    background: var(--green);
    border-color: var(--green);
}

.btn-success:hover {
    background: var(--dark-green);
}

.form-control {
    padding: 12px;
}

footer {
    padding: 20px;
    color: #68776C;
    text-align: center;
    font-size: 13px;
}
</style>
</head>

<body>

<nav class="navbar navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="../index.php">🌱 AgriConnect</a>
        <a href="orders.php" class="btn btn-outline-light btn-sm">
            My Orders
        </a>
    </div>
</nav>

<main class="container px-3">

<section class="payment-card">

    <div class="mb-3">
        <div class="text-uppercase small fw-bold text-success mb-2">
            Order Payment
        </div>

        <h1 class="page-title h3">Submit M-Pesa Receipt</h1>

        <p class="text-secondary">
            Submit the transaction details for your order.
            Your payment will remain pending until an administrator
            independently verifies the transaction.
        </p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= e($message_type) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="order-total mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary">Order number</span>
            <strong>#<?= (int)$order["order_id"] ?></strong>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <span class="text-secondary">Amount due</span>
            <span class="amount">
                KSh <?= number_format((float)$order["total_amount"], 2) ?>
            </span>
        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-center">
            <span>Order status</span>
            <strong><?= e(ucfirst($order["status"] ?? "Unknown")) ?></strong>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
            <span>Payment status</span>

            <?php if ($already_paid): ?>
                <span class="badge text-bg-success">Paid</span>
            <?php elseif ($pending_verification): ?>
                <span class="badge text-bg-warning">Pending verification</span>
            <?php else: ?>
                <span class="badge text-bg-secondary">
                    <?= e(ucfirst($order["payment_status"] ?? "Unknown")) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($already_paid): ?>

        <div class="alert alert-success">
            <strong>Payment verified.</strong>
            This order is already marked as paid. You do not need to
            submit another receipt.
        </div>

    <?php elseif ($pending_verification): ?>

        <div class="alert alert-warning">
            <strong>Receipt submitted.</strong>
            Your receipt
            <strong><?= e($existing_payment["mpesa_receipt_number"]) ?></strong>
            is awaiting administrator verification.
            Please do not submit it again.
        </div>

    <?php else: ?>

        <?php if ($recipient_phone !== ""): ?>

            <div class="alert alert-light border">
                <h2 class="h6 fw-bold">M-Pesa payment instructions</h2>

                <p class="mb-1">
                    Recipient: <strong><?= e($recipient_name) ?></strong>
                </p>

                <p class="mb-0">
                    Send to:
                    <strong><?= e($recipient_phone) ?></strong>
                </p>

                <hr>

                <p class="small mb-0">
                    Confirm the recipient and amount before sending.
                    Only submit a receipt after completing the transaction.
                </p>
            </div>

        <?php else: ?>

            <div class="alert alert-danger">
                <strong>Recipient number not configured.</strong>
                AgriConnect has not been given a confirmed M-Pesa
                recipient number yet. Do not send money to an unconfirmed
                number. Contact the administrator for the correct payment
                instructions before paying.
            </div>

        <?php endif; ?>

        <form method="POST" action="submit-payment.php">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION["payment_csrf"]) ?>">

            <input type="hidden" name="order_id"
                   value="<?= (int)$order["order_id"] ?>">

            <div class="mb-3">
                <label for="phone_number" class="form-label fw-semibold">
                    M-Pesa Phone Number
                </label>

                <input type="tel"
                       class="form-control"
                       id="phone_number"
                       name="phone_number"
                       placeholder="0712345678"
                       pattern="(?:0[17][0-9]{8}|254[17][0-9]{8}|\+254[17][0-9]{8})"
                       maxlength="13"
                       required>

                <div class="form-text">
                    Enter the phone number used to make the payment.
                </div>
            </div>

            <div class="mb-4">
                <label for="receipt_number" class="form-label fw-semibold">
                    M-Pesa Transaction Code
                </label>

                <input type="text"
                       class="form-control text-uppercase"
                       id="receipt_number"
                       name="receipt_number"
                       placeholder="e.g. QWE123ABC"
                       pattern="[A-Za-z0-9]{8,20}"
                       minlength="8"
                       maxlength="20"
                       required>

                <div class="form-text">
                    Enter the transaction code exactly as shown in
                    the M-Pesa confirmation message.
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100 py-3">
                Submit Receipt for Verification
            </button>

        </form>

    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <a href="orders.php" class="btn btn-outline-secondary">
            Back to My Orders
        </a>

        <a href="order-success.php" class="btn btn-outline-success">
            Order Details
        </a>
    </div>

    <p class="small text-secondary mt-4 mb-0">
        Security notice: submitting a transaction code does not prove
        payment. The administrator must check the actual M-Pesa transaction.
    </p>

</section>
</main>

<footer>
    AgriConnect &copy; <?= date("Y") ?>
</footer>

</body>
</html>