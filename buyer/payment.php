
<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../payment-config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Require an authenticated buyer. */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'buyer') {
    http_response_code(403);
    exit('Access denied. Please log in using a buyer account.');
}

$buyer_id = (int) $_SESSION['user_id'];
$errors = [];
$message = '';
$message_type = 'info';
$order = null;
$payment = null;

$phone = '';
$receipt = '';

/* Escape output safely. */
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* Create a CSRF token. */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/*
 * Determine which order is being paid for.
 * The page accepts an order_id in the URL, or the most recent
 * order ID saved by checkout.php.
 */
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
}

if (!$order_id) {
    $order_id = filter_var(
        $_SESSION['last_order_id'] ?? null,
        FILTER_VALIDATE_INT
    );
}

if (!$order_id || $order_id < 1) {
    http_response_code(400);
    exit(
        'No valid order was selected. Please open your order confirmation ' .
        'and select the order you want to pay for.'
    );
}

/* Confirm the buyer account is valid and approved. */
$stmt = $conn->prepare(
    "SELECT user_id, full_name, phone
     FROM users
     WHERE user_id = ?
       AND role = 'buyer'
       AND account_status = 'approved'
     LIMIT 1"
);

$stmt->bind_param('i', $buyer_id);
$stmt->execute();
$buyer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$buyer) {
    http_response_code(403);
    exit('Your buyer account must be approved before making a payment.');
}

$phone = $buyer['phone'] ?? '';

/* Load the order belonging to this buyer. */
$stmt = $conn->prepare(
    "SELECT order_id, buyer_id, order_date, total_amount,
            status, payment_method, payment_status,
            mpesa_receipt_number
     FROM orders
     WHERE order_id = ?
       AND buyer_id = ?
     LIMIT 1"
);

$stmt->bind_param('ii', $order_id, $buyer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit('Order not found, or you do not have permission to access it.');
}

/* Load the latest payment record for this order. */
function loadPayment(mysqli $conn, int $order_id): ?array
{
    $stmt = $conn->prepare(
        "SELECT payment_id, order_id, phone_number, amount,
                mpesa_receipt_number, payment_status,
                result_description, created_at
         FROM mpesa_payments
         WHERE order_id = ?
         ORDER BY payment_id DESC
         LIMIT 1"
    );

    $stmt->bind_param('i', $order_id);
    $stmt->execute();

    $payment = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $payment ?: null;
}

try {
    $payment = loadPayment($conn, $order_id);

    /*
     * Process a buyer's payment claim.
     * This records a claim for admin review. It does not verify
     * the transaction with Safaricom or mark the order as paid.
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted_token = $_POST['csrf_token'] ?? '';

        if (
            !is_string($posted_token) ||
            !hash_equals($_SESSION['csrf_token'], $posted_token)
        ) {
            throw new RuntimeException(
                'Your security token is invalid. Refresh the page and try again.'
            );
        }

        if ($order['payment_status'] === 'paid') {
            throw new RuntimeException(
                'This order has already been marked as paid.'
            );
        }

        if (
            !in_array(
                $order['payment_method'],
                ['mpesa_manual', 'mpesa'],
                true
            )
        ) {
            throw new RuntimeException(
                'This order is not configured for manual M-Pesa payment.'
            );
        }

        if ($payment && $payment['payment_status'] === 'pending') {
            throw new RuntimeException(
                'A payment claim is already awaiting admin review for this order.'
            );
        }

        $phone = trim($_POST['phone'] ?? '');
        $receipt = strtoupper(trim($_POST['receipt'] ?? ''));

        /* Validate the phone number. */
        if (!preg_match('/^(?:\+?254|0)\d{9}$/', $phone)) {
            throw new RuntimeException(
                'Enter a valid Kenyan phone number, for example 0712345678 or 254712345678.'
            );
        }

        /* Validate the receipt code format. */
        if (
            $receipt === '' ||
            strlen($receipt) > 50 ||
            !preg_match('/^[A-Z0-9]{6,50}$/', $receipt)
        ) {
            throw new RuntimeException(
                'Enter a valid M-Pesa receipt code using letters and numbers.'
            );
        }

        /*
         * A configured recipient is required before accepting a claim.
         * Do not direct customers to an unconfirmed phone number.
         */
        $recipient_phone = defined('MPESA_RECIPIENT_PHONE')
            ? trim((string) MPESA_RECIPIENT_PHONE)
            : '';

        if ($recipient_phone === '') {
            throw new RuntimeException(
                'M-Pesa payments are not yet configured. Please contact AgriConnect before sending money.'
            );
        }

        /*
         * Lock the order and recheck its payment status to reduce
         * the risk of two simultaneous submissions.
         */
        $conn->begin_transaction();

        $stmt = $conn->prepare(
            "SELECT total_amount, payment_status, payment_method
             FROM orders
             WHERE order_id = ?
               AND buyer_id = ?
             FOR UPDATE"
        );

        $stmt->bind_param('ii', $order_id, $buyer_id);
        $stmt->execute();
        $locked_order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$locked_order) {
            throw new RuntimeException('The selected order could not be found.');
        }

        if ($locked_order['payment_status'] === 'paid') {
            throw new RuntimeException('This order has already been paid.');
        }

        if (
            !in_array(
                $locked_order['payment_method'],
                ['mpesa_manual', 'mpesa'],
                true
            )
        ) {
            throw new RuntimeException(
                'This order is not configured for manual M-Pesa payment.'
            );
        }

        /*
         * Check that the receipt has not already been submitted.
         * The database also has a unique index on this field.
         */
        $stmt = $conn->prepare(
            "SELECT payment_id
             FROM mpesa_payments
             WHERE mpesa_receipt_number = ?
             LIMIT 1"
        );

        $stmt->bind_param('s', $receipt);
        $stmt->execute();
        $duplicate = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($duplicate) {
            throw new RuntimeException(
                'This receipt code has already been submitted. Check the code or contact the administrator.'
            );
        }

        /*
         * Prevent a second pending claim for this order.
         * The earlier check is repeated inside the transaction.
         */
        $stmt = $conn->prepare(
            "SELECT payment_id
             FROM mpesa_payments
             WHERE order_id = ?
               AND payment_status = 'pending'
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $pending_payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($pending_payment) {
            throw new RuntimeException(
                'A payment claim for this order is already awaiting review.'
            );
        }

        $amount = (float) $locked_order['total_amount'];
        $payment_status = 'pending';

        if ($amount <= 0) {
            throw new RuntimeException('The order amount is invalid.');
        }

        /*
         * Insert into the existing table used by admin/payments.php.
         * Do not mark the order as paid here.
         */
        $stmt = $conn->prepare(
            "INSERT INTO mpesa_payments
                (order_id, phone_number, amount,
                 mpesa_receipt_number, payment_status)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            'isdss',
            $order_id,
            $phone,
            $amount,
            $receipt,
            $payment_status
        );

        $stmt->execute();
        $stmt->close();

        $conn->commit();

        /*
         * Refresh the page using POST/Redirect/GET so the buyer
         * does not accidentally submit the same form again.
         */
        $_SESSION['payment_flash'] = [
            'type' => 'success',
            'message' => 'Your payment claim has been submitted. Your order will remain unpaid until the administrator verifies the transaction.'
        ];

        header(
            'Location: payment.php?order_id=' . $order_id
        );
        exit;
    }

} catch (Throwable $ex) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // There may not be an active transaction.
    }

    error_log('AgriConnect buyer payment error: ' . $ex->getMessage());

    $errors[] = $ex instanceof RuntimeException
        ? $ex->getMessage()
        : 'We could not process your payment claim. Please try again.';

    /* Refresh payment details after an error. */
    try {
        $payment = loadPayment($conn, $order_id);
    } catch (Throwable $ignored) {
        // Keep the existing page available if this refresh fails.
    }
}

/* Display one-time success messages. */
if (isset($_SESSION['payment_flash'])) {
    $message = $_SESSION['payment_flash']['message'];
    $message_type = $_SESSION['payment_flash']['type'];
    unset($_SESSION['payment_flash']);
}

/* Read configured recipient information. */
$recipient_name = defined('MPESA_RECIPIENT_NAME')
    ? trim((string) MPESA_RECIPIENT_NAME)
    : '';

$recipient_phone = defined('MPESA_RECIPIENT_PHONE')
    ? trim((string) MPESA_RECIPIENT_PHONE)
    : '';

$recipient_configured = $recipient_phone !== '';

/* Determine whether a claim can be submitted. */
$can_submit =
    $order['payment_status'] !== 'paid' &&
    $order['payment_status'] !== 'failed' &&
    in_array($order['payment_method'], ['mpesa_manual', 'mpesa'], true) &&
    $recipient_configured &&
    !($payment && $payment['payment_status'] === 'pending') &&
    !($payment && $payment['payment_status'] === 'paid');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Pay for Order #<?= (int) $order['order_id'] ?> | AgriConnect</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --green: #28734b;
            --dark-green: #185437;
            --cream: #f7faf5;
            --border: #e0e9df;
        }

        body {
            background: var(--cream);
            color: #29382d;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: var(--dark-green);
        }

        .brand {
            color: white;
            font-size: 1.35rem;
            font-weight: 800;
            text-decoration: none;
        }

        .brand span {
            color: #f3d46b;
        }

        .payment-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 6px 22px rgba(24, 84, 55, .05);
        }

        .section-title {
            color: var(--dark-green);
            font-weight: 800;
        }

        .amount {
            color: var(--dark-green);
            font-size: 2rem;
            font-weight: 800;
        }

        .recipient-box {
            background: #eef7ef;
            border: 1px solid #d6e8d7;
            border-radius: 12px;
            padding: 17px;
        }

        .form-control {
            padding: 11px;
            border-radius: 9px;
        }

        .btn-green {
            background: var(--green);
            border: 0;
            border-radius: 9px;
            color: white;
            font-weight: 700;
            padding: 12px;
        }

        .btn-green:hover {
            background: var(--dark-green);
            color: white;
        }

        .muted {
            color: #728075;
        }

        .status-pill {
            display: inline-block;
            border-radius: 20px;
            padding: 6px 12px;
            font-size: .85rem;
            font-weight: 700;
        }
    </style>
</head>

<body>
<nav class="navbar py-3">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="brand" href="../index.php">
            Agri<span>Connect</span>
        </a>

        <a href="orders.php" class="btn btn-outline-light btn-sm">
            My Orders
        </a>
    </div>
</nav>

<main class="container py-4 py-md-5">
    <div class="mb-4">
        <p class="text-success fw-semibold small mb-1">ORDER PAYMENT</p>
        <h1 class="h2 fw-bold">Pay for Your Order</h1>
        <p class="muted mb-0">
            Submit your M-Pesa receipt for administrator verification.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($message_type) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <section class="payment-card h-100">
                <h2 class="section-title h5 mb-3">Order Summary</h2>

                <div class="d-flex justify-content-between mb-3">
                    <span class="muted">Order number</span>
                    <strong>#<?= (int) $order['order_id'] ?></strong>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="muted">Order date</span>
                    <span><?= e($order['order_date']) ?></span>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="muted">Order status</span>
                    <span><?= e(ucfirst($order['status'])) ?></span>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="muted">Payment status</span>
                    <strong><?= e(ucfirst($order['payment_status'])) ?></strong>
                </div>

                <hr>

                <p class="muted mb-1">Total amount due</p>
                <div class="amount">
                    KSh <?= number_format((float) $order['total_amount'], 2) ?>
                </div>

                <p class="small muted mt-3 mb-0">
                    Make sure the payment amount matches the full order total.
                </p>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="payment-card">
                <h2 class="section-title h5 mb-3">M-Pesa Payment</h2>

                <?php if ($order['payment_status'] === 'paid'): ?>

                    <div class="alert alert-success">
                        <strong>Payment completed.</strong>
                        This order is already marked as paid.
                    </div>

                    <?php if (!empty($order['mpesa_receipt_number'])): ?>
                        <p class="mb-0">
                            Receipt:
                            <strong><?= e($order['mpesa_receipt_number']) ?></strong>
                        </p>
                    <?php endif; ?>

                <?php elseif ($payment && $payment['payment_status'] === 'paid'): ?>

                    <div class="alert alert-info">
                        A payment record is marked paid. If the order above
                        still says unpaid, contact the administrator to
                        reconcile the records before making another payment.
                    </div>

                <?php elseif ($payment && $payment['payment_status'] === 'pending'): ?>

                    <div class="alert alert-warning">
                        <strong>Payment claim awaiting review.</strong>
                        Your submitted receipt is awaiting administrator
                        verification. Do not submit the same claim again.
                    </div>

                    <div class="recipient-box mb-3">
                        <div class="small muted">Submitted receipt</div>
                        <strong><?= e($payment['mpesa_receipt_number'] ?: 'Not provided') ?></strong>

                        <div class="small muted mt-2">Claimed amount</div>
                        <strong>
                            KSh <?= number_format((float) $payment['amount'], 2) ?>
                        </strong>
                    </div>

                <?php elseif (!$recipient_configured): ?>

                    <div class="alert alert-warning">
                        <strong>Payments are not configured yet.</strong>
                        The administrator must add the correct M-Pesa
                        recipient number to AgriConnect before buyers send
                        money or submit payment claims.
                    </div>

                <?php elseif ($order['payment_status'] === 'failed'): ?>

                    <div class="alert alert-danger">
                        This order has a failed payment status. Contact the
                        administrator before trying to pay again.
                    </div>

                <?php else: ?>

                    <div class="recipient-box mb-4">
                        <h3 class="h6 fw-bold mb-3">Payment Instructions</h3>

                        <div class="small muted">Recipient name</div>
                        <div class="fw-bold mb-3">
                            <?= e($recipient_name !== '' ? $recipient_name : 'AgriConnect') ?>
                        </div>

                        <div class="small muted">M-Pesa number</div>
                        <div class="fs-5 fw-bold">
                            <?= e($recipient_phone) ?>
                        </div>

                        <hr>

                        <p class="small mb-0">
                            Send exactly
                            <strong>
                                KSh <?= number_format((float) $order['total_amount'], 2) ?>
                            </strong>
                            to the recipient shown above. Keep the genuine
                            M-Pesa confirmation message for reference.
                        </p>
                    </div>

                    <form method="POST"
                          action="payment.php?order_id=<?= (int) $order_id ?>">

                        <input type="hidden" name="csrf_token"
                               value="<?= e($csrf_token) ?>">

                        <input type="hidden" name="order_id"
                               value="<?= (int) $order_id ?>">

                        <div class="mb-3">
                            <label for="phone" class="form-label fw-semibold">
                                Phone Number Used to Pay
                            </label>

                            <input type="tel" id="phone" name="phone"
                                   class="form-control" maxlength="16"
                                   placeholder="0712345678 or 254712345678"
                                   value="<?= e($phone) ?>"
                                   pattern="(\+?254|0)[0-9]{9}"
                                   required>

                            <div class="form-text">
                                Enter the phone number from which you made
                                the M-Pesa payment.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="receipt" class="form-label fw-semibold">
                                M-Pesa Transaction Receipt
                            </label>

                            <input type="text" id="receipt" name="receipt"
                                   class="form-control text-uppercase"
                                   maxlength="50"
                                   placeholder="Enter the receipt code"
                                   value="<?= e($receipt) ?>"
                                   pattern="[A-Za-z0-9]{6,50}"
                                   required>

                            <div class="form-text">
                                Enter the receipt code from the actual
                                M-Pesa confirmation message.
                            </div>
                        </div>

                        <div class="alert alert-warning small">
                            Submitting this form does not automatically
                            verify payment. Your order will remain unpaid
                            until the administrator independently confirms
                            the transaction, recipient and amount.
                        </div>

                        <button type="submit" class="btn-green w-100">
                            Submit Payment for Verification
                        </button>
                    </form>

                <?php endif; ?>

                <div class="mt-4">
                    <a href="order-success.php" class="btn btn-outline-secondary">
                        Back to Order Confirmation
                    </a>
                </div>
            </section>
        </div>
    </div>
</main>

</body>
</html>
