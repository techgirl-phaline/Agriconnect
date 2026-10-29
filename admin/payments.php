
<?php
session_start();
require_once __DIR__ . '/../db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Admin authentication
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: login.php");
    exit;
}

function e($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = 'success';

// Process verification or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $payment_id = filter_input(
        INPUT_POST,
        'payment_id',
        FILTER_VALIDATE_INT
    );
    $action = $_POST['action'] ?? '';
    $receipt = trim($_POST['verified_receipt'] ?? '');
    $confirmed = ($_POST['confirm_transaction'] ?? '') === 'yes';

    try {
        if (
            !is_string($token) ||
            !hash_equals($_SESSION['csrf_token'], $token)
        ) {
            throw new RuntimeException(
                'Security check failed. Refresh the page and try again.'
            );
        }

        if (!$payment_id || !in_array($action, ['verify', 'reject'], true)) {
            throw new RuntimeException('Invalid payment or action.');
        }

        if ($action === 'verify') {
            if (!$confirmed) {
                throw new RuntimeException(
                    'Confirm that you checked the actual M-Pesa transaction.'
                );
            }

            if ($receipt === '' || strlen($receipt) > 100) {
                throw new RuntimeException(
                    'Enter the receipt confirmed in the M-Pesa records.'
                );
            }
        }

        $conn->begin_transaction();

        // Lock the payment claim.
        $stmt = $conn->prepare(
            "SELECT payment_id, order_id, amount,
                    mpesa_receipt_number, payment_status
             FROM mpesa_payments
             WHERE payment_id = ?
             FOR UPDATE"
        );
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$payment) {
            throw new RuntimeException('Payment record not found.');
        }

        if ($payment['payment_status'] !== 'pending') {
            throw new RuntimeException(
                'This payment is no longer pending. Refresh the page.'
            );
        }

        $order_id = (int)$payment['order_id'];

        // Lock the associated order.
        $stmt = $conn->prepare(
            "SELECT total_amount, payment_status
             FROM orders
             WHERE order_id = ?
             FOR UPDATE"
        );
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            throw new RuntimeException('Associated order not found.');
        }

        if ($action === 'verify') {
            if ($order['payment_status'] === 'paid') {
                throw new RuntimeException(
                    'This order is already marked as paid. Check its payment records.'
                );
            }

            // Require the claimed amount to match the order total.
            if (abs((float)$payment['amount'] -
                    (float)$order['total_amount']) > 0.009) {
                throw new RuntimeException(
                    'Payment amount does not match the order total. Do not approve it.'
                );
            }

            // Prevent reuse of a receipt already used by another paid claim.
            $stmt = $conn->prepare(
                "SELECT payment_id
                 FROM mpesa_payments
                 WHERE mpesa_receipt_number = ?
                   AND payment_status = 'paid'
                   AND payment_id <> ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->bind_param('si', $receipt, $payment_id);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($duplicate) {
                throw new RuntimeException(
                    'This receipt is already attached to another paid claim.'
                );
            }

            // Update the payment claim.
            $stmt = $conn->prepare(
                "UPDATE mpesa_payments
                 SET payment_status = 'paid',
                     mpesa_receipt_number = ?
                 WHERE payment_id = ?
                   AND payment_status = 'pending'"
            );
            $stmt->bind_param('si', $receipt, $payment_id);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed !== 1) {
                throw new RuntimeException(
                    'Payment was not updated. Refresh and try again.'
                );
            }

            // Update the order so orders.php permits dispatch.
            $stmt = $conn->prepare(
                "UPDATE orders
                 SET payment_status = 'paid',
                     mpesa_receipt_number = ?
                 WHERE order_id = ?
                   AND payment_status <> 'paid'"
            );
            $stmt->bind_param('si', $receipt, $order_id);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed !== 1) {
                throw new RuntimeException(
                    'Order payment status was not updated.'
                );
            }

            $conn->commit();

            $_SESSION['payments_flash'] = [
                'type' => 'success',
                'message' => "Payment verified successfully for Order #{$order_id}."
            ];

        } else {
            // Reject only the pending claim; the order stays unpaid.
            $stmt = $conn->prepare(
                "UPDATE mpesa_payments
                 SET payment_status = 'failed',
                     result_description = 'Rejected by administrator'
                 WHERE payment_id = ?
                   AND payment_status = 'pending'"
            );
            $stmt->bind_param('i', $payment_id);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed !== 1) {
                throw new RuntimeException(
                    'Payment was not rejected. Refresh and try again.'
                );
            }

            $conn->commit();

            $_SESSION['payments_flash'] = [
                'type' => 'warning',
                'message' => "Payment claim for Order #{$order_id} was rejected. The order remains unpaid."
            ];
        }

    } catch (Throwable $ex) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // No active transaction to roll back.
        }

        error_log($ex->getMessage());

        $_SESSION['payments_flash'] = [
            'type' => 'danger',
            'message' => $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'A database error occurred. No approval was confirmed.'
        ];
    }

    header('Location: payments.php');
    exit;
}

// Show one-time message after redirect
if (isset($_SESSION['payments_flash'])) {
    $message = $_SESSION['payments_flash']['message'];
    $message_type = $_SESSION['payments_flash']['type'];
    unset($_SESSION['payments_flash']);
}

// Load payment claims
$payments = [];
$load_error = false;

try {
    $sql = "
        SELECT
            mp.payment_id,
            mp.order_id,
            mp.phone_number,
            mp.amount,
            mp.mpesa_receipt_number,
            mp.result_description,
            mp.payment_status,
            o.total_amount,
            o.payment_status AS order_payment_status,
            o.status AS order_status,
            u.full_name AS buyer_name
        FROM mpesa_payments mp
        INNER JOIN orders o ON o.order_id = mp.order_id
        INNER JOIN users u ON u.user_id = o.buyer_id
        ORDER BY
            CASE WHEN mp.payment_status = 'pending' THEN 0 ELSE 1 END,
            mp.payment_id DESC
    ";

    $result = $conn->query($sql);
    $payments = $result->fetch_all(MYSQLI_ASSOC);

} catch (Throwable $ex) {
    error_log($ex->getMessage());
    $load_error = true;
    $message = 'Unable to load payment records. Check the database configuration.';
    $message_type = 'danger';
}

$pending_count = 0;

foreach ($payments as $payment) {
    if ($payment['payment_status'] === 'pending') {
        $pending_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Verification | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        :root {
            --green: #39734b;
            --dark-green: #285639;
            --cream: #faf9f4;
            --border: #e4eae3;
        }

        body {
            background: var(--cream);
            color: #26352b;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid var(--border);
        }

        .brand {
            color: var(--dark-green);
            font-size: 1.3rem;
            font-weight: 800;
            text-decoration: none;
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 22px;
            box-shadow: 0 4px 16px rgba(35, 65, 43, .04);
        }

        .stat {
            background: #edf5ee;
            color: var(--dark-green);
            border-radius: 12px;
            padding: 18px;
        }

        .table th {
            white-space: nowrap;
            font-size: .85rem;
        }

        .table td {
            vertical-align: middle;
            font-size: .9rem;
        }

        .muted {
            color: #718075;
        }

        .btn-verify {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }

        .btn-verify:hover {
            background: var(--dark-green);
            color: white;
        }

        .action-form {
            min-width: 235px;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container py-3 d-flex justify-content-between align-items-center">
        <a href="dashboard.php" class="brand">🌿 AgriConnect</a>
        <div class="d-flex gap-2">
            <a href="orders.php" class="btn btn-outline-success btn-sm">
                View Orders
            </a>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">
                Log Out
            </a>
        </div>
    </div>
</nav>

<main class="container py-4 py-md-5">
    <div class="mb-4">
        <p class="text-success fw-semibold small mb-1">ADMINISTRATION</p>
        <h1 class="h2 fw-bold">Payment Verification</h1>
        <p class="muted">
            Review claims and compare them with actual M-Pesa transaction records.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($message_type) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="stat mb-4">
        <div class="small">Payments awaiting review</div>
        <div class="display-6 fw-bold"><?= $pending_count ?></div>
    </div>

    <div class="panel">
        <h2 class="h5 fw-bold mb-3">Payment Records</h2>

        <?php if ($load_error): ?>
            <div class="alert alert-danger">
                Payment records could not be loaded. Check the PHP error log.
            </div>

        <?php elseif (empty($payments)): ?>
            <div class="alert alert-info mb-0">
                No payment records found yet.
            </div>

        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Payment</th>
                        <th>Order</th>
                        <th>Buyer</th>
                        <th>Phone</th>
                        <th>Claimed Amount</th>
                        <th>Order Total</th>
                        <th>Receipt</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $status = $payment['payment_status'];
                        $badge = match ($status) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            'failed' => 'danger',
                            default => 'secondary'
                        };
                        ?>
                        <tr>
                            <td><?= (int)$payment['payment_id'] ?></td>
                            <td>
                                <strong>#<?= (int)$payment['order_id'] ?></strong>
                                <div class="small muted">
                                    Order: <?= e($payment['order_status']) ?>
                                </div>
                            </td>
                            <td><?= e($payment['buyer_name']) ?></td>
                            <td><?= e($payment['phone_number']) ?></td>
                            <td>KSh <?= number_format((float)$payment['amount'], 2) ?></td>
                            <td>KSh <?= number_format((float)$payment['total_amount'], 2) ?></td>
                            <td><?= e($payment['mpesa_receipt_number'] ?: 'Not provided') ?></td>
                            <td>
                                <span class="badge text-bg-<?= $badge ?>">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                                <div class="small muted mt-1">
                                    Order payment: <?= e($payment['order_payment_status']) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($status === 'pending'): ?>
                                    <form method="POST" class="action-form mb-2"
                                          onsubmit="return confirm('Only continue if you independently confirmed this transaction in actual M-Pesa records.');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="payment_id"
                                               value="<?= (int)$payment['payment_id'] ?>">
                                        <input type="hidden" name="action" value="verify">

                                        <label class="form-label small fw-semibold">
                                            Confirmed M-Pesa receipt
                                        </label>
                                        <input type="text" name="verified_receipt"
                                               class="form-control form-control-sm mb-2"
                                               maxlength="100"
                                               value="<?= e($payment['mpesa_receipt_number']) ?>"
                                               placeholder="Enter verified receipt"
                                               required>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="confirm_transaction"
                                                   value="yes"
                                                   id="confirm<?= (int)$payment['payment_id'] ?>"
                                                   required>
                                            <label class="form-check-label small"
                                                   for="confirm<?= (int)$payment['payment_id'] ?>">
                                                I checked the actual M-Pesa record, recipient and amount.
                                            </label>
                                        </div>

                                        <button type="submit"
                                                class="btn btn-verify btn-sm w-100 mb-2">
                                            Verify Payment
                                        </button>
                                    </form>

                                    <form method="POST" class="action-form"
                                          onsubmit="return confirm('Reject this pending payment claim? The order will remain unpaid.');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="payment_id"
                                               value="<?= (int)$payment['payment_id'] ?>">
                                        <input type="hidden" name="action" value="reject">

                                        <button type="submit"
                                                class="btn btn-outline-danger btn-sm w-100">
                                            Reject Claim
                                        </button>
                                    </form>

                                <?php elseif ($status === 'paid'): ?>
                                    <span class="text-success fw-semibold">
                                        ✓ Verified / marked paid
                                    </span>
                                <?php else: ?>
                                    <span class="muted">No action available</span>
                                    <?php if (!empty($payment['result_description'])): ?>
                                        <div class="small muted mt-1">
                                            <?= e($payment['result_description']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning mt-3 mb-0">
            <strong>Important:</strong> The checkbox records the administrator's
            confirmation; it does not contact Safaricom. Only approve after
            independently confirming the transaction, recipient and amount.
            The amount must match the full order total.
        </div>
    </div>

    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-outline-secondary">
            ← Back to Dashboard
        </a>
    </div>
</main>
</body>
</html>
