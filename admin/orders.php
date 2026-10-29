
<?php
session_start();
require_once "../db.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$allowed_statuses = [
    "placed", "confirmed", "dispatched", "delivered"
];

$status_labels = [
    "placed" => "Placed",
    "confirmed" => "Confirmed",
    "dispatched" => "Dispatched",
    "delivered" => "Delivered"
];

$message = "";
$message_type = "";

// CSRF protection
if (empty($_SESSION["orders_csrf"])) {
    $_SESSION["orders_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $order_id = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
    $new_status = $_POST["status"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["orders_csrf"], $token)
    ) {
        $message = "Security check failed. Please refresh and try again.";
        $message_type = "danger";
    } elseif (
        !$order_id ||
        !in_array($new_status, $allowed_statuses, true)
    ) {
        $message = "Invalid order or status.";
        $message_type = "danger";
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "SELECT status, payment_status
                 FROM orders
                 WHERE order_id = ?
                 FOR UPDATE"
            );
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $current_order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$current_order) {
                throw new RuntimeException("Order not found.");
            }

            $current_status = $current_order["status"];
            $payment_status = $current_order["payment_status"];

            $current_index = array_search(
                $current_status,
                $allowed_statuses,
                true
            );

            $next_status = (
                $current_index !== false &&
                isset($allowed_statuses[$current_index + 1])
            ) ? $allowed_statuses[$current_index + 1] : null;

            if ($new_status !== $next_status) {
                throw new RuntimeException(
                    $current_status === "delivered"
                        ? "This order has already been delivered."
                        : "Orders must move forward one step at a time."
                );
            }

            // Confirming an order is separate from verifying payment.
            // Dispatch and delivery require verified payment.
            if (
                in_array($new_status, ["dispatched", "delivered"], true) &&
                $payment_status !== "paid"
            ) {
                throw new RuntimeException(
                    "Payment must be verified as PAID before dispatch or delivery."
                );
            }

            $update = $conn->prepare(
                "UPDATE orders
                 SET status = ?
                 WHERE order_id = ? AND status = ?"
            );
            $update->bind_param(
                "sis",
                $new_status,
                $order_id,
                $current_status
            );
            $update->execute();
            $changed = $update->affected_rows;
            $update->close();

            if ($changed !== 1) {
                throw new RuntimeException(
                    "Order was not updated. Please refresh and try again."
                );
            }

            $conn->commit();

            $message = "Order #" . $order_id .
                " updated to " . $status_labels[$new_status] . ".";
            $message_type = "success";

        } catch (Throwable $ex) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
                // Transaction may not have started.
            }

            $message = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : "A database error occurred while updating the order.";

            $message_type = "danger";
        }
    }

    // Prevent accidental form resubmission on refresh.
    $_SESSION["orders_flash"] = [
        "message" => $message,
        "type" => $message_type
    ];

    header("Location: orders.php");
    exit;
}

if (!empty($_SESSION["orders_flash"])) {
    $message = $_SESSION["orders_flash"]["message"];
    $message_type = $_SESSION["orders_flash"]["type"];
    unset($_SESSION["orders_flash"]);
}

// Order statistics
$stats_sql = "
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(status = 'placed'), 0) AS placed_orders,
        COALESCE(SUM(status = 'confirmed'), 0) AS confirmed_orders,
        COALESCE(SUM(status = 'dispatched'), 0) AS dispatched_orders,
        COALESCE(SUM(status = 'delivered'), 0) AS delivered_orders,
        COALESCE(SUM(payment_status = 'unpaid'), 0) AS unpaid_orders,
        COALESCE(SUM(payment_status = 'paid'), 0) AS paid_orders
    FROM orders
";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Retrieve orders and payment status
$sql = "
    SELECT
        o.order_id,
        o.buyer_id,
        o.order_date,
        o.total_amount,
        o.delivery_location,
        o.status,
        o.payment_status,
        o.mpesa_receipt_number,
        u.full_name,
        u.email,
        u.phone,
        COUNT(oi.order_item_id) AS item_count
    FROM orders o
    LEFT JOIN users u ON o.buyer_id = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    GROUP BY
        o.order_id, o.buyer_id, o.order_date,
        o.total_amount, o.delivery_location,
        o.status, o.payment_status, o.mpesa_receipt_number,
        u.full_name, u.email, u.phone
    ORDER BY o.order_date DESC, o.order_id DESC
";

$result = $conn->query($sql);
$orders = $result->fetch_all(MYSQLI_ASSOC);

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Orders | AgriConnect</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>
:root {
    --dark-green: #173D2A;
    --green: #245638;
    --yellow: #F5D547;
    --background: #F5F7F2;
    --text: #26352B;
    --muted: #68776C;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    background: var(--background);
    color: var(--text);
    font-family: Arial, sans-serif;
}

.sidebar {
    width: 255px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: var(--dark-green);
    color: white;
    display: flex;
    flex-direction: column;
    padding: 25px 16px;
    z-index: 1000;
    overflow-y: auto;
}

.sidebar-brand {
    color: var(--yellow);
    font-size: 23px;
    font-weight: 800;
    text-decoration: none;
    padding: 0 10px;
}

.sidebar-subtitle {
    color: #C9D8CC;
    font-size: 10px;
    letter-spacing: 1.5px;
    padding: 0 10px;
    margin: 5px 0 32px;
}

.sidebar-label {
    color: #AFC5B5;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0 12px;
    margin-bottom: 12px;
}

.sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #E5EEE7;
    padding: 13px 14px;
    margin-bottom: 6px;
    border-radius: 9px;
    text-decoration: none;
    font-size: 14px;
}

.sidebar .nav-link:hover {
    background: #28543B;
    color: var(--yellow);
}

.sidebar .nav-link.active {
    background: var(--yellow);
    color: var(--dark-green);
    font-weight: 700;
}

.sidebar .logout {
    margin-top: auto;
    color: #FFD5D5;
}

.main-content {
    margin-left: 255px;
    min-height: 100vh;
}

.topbar {
    background: white;
    padding: 17px 30px;
    border-bottom: 1px solid #E4EAE3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.topbar-title {
    font-weight: 700;
    color: var(--dark-green);
}

.admin-label {
    background: #E8F0E8;
    color: var(--green);
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.page-content { padding: 28px; }
.page-title { color: var(--dark-green); font-weight: 800; }

.section-label {
    color: var(--green);
    font-size: 12px;
    letter-spacing: 1px;
    font-weight: 800;
}

.stat-card, .content-card {
    background: white;
    border: 1px solid #E7EBE7;
    border-radius: 14px;
    padding: 20px;
    height: 100%;
}

.stat-card { border-top: 3px solid var(--yellow); }

.stat-number {
    color: var(--dark-green);
    font-size: 28px;
    font-weight: 800;
    margin-top: 8px;
}

.stat-label { color: var(--muted); font-size: 13px; }

.table th {
    color: #58655C;
    font-size: 11px;
    text-transform: uppercase;
    white-space: nowrap;
}

.table td { vertical-align: middle; font-size: 13px; }

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.status-placed { background: #FFF3CD; color: #856404; }
.status-confirmed { background: #CFE2FF; color: #084298; }
.status-dispatched { background: #E2D9F3; color: #59359A; }
.status-delivered { background: #D1E7DD; color: #0F5132; }

.btn-success {
    background: var(--green);
    border-color: var(--green);
}

.btn-success:hover {
    background: var(--dark-green);
    border-color: var(--dark-green);
}

.search-input { max-width: 300px; border-radius: 9px; }

footer {
    color: #788078;
    font-size: 12px;
    padding: 24px;
    text-align: center;
}

@media (max-width: 768px) {
    .sidebar { width: 72px; padding: 22px 8px; }

    .sidebar-brand {
        font-size: 0;
        text-align: center;
        padding: 0;
    }

    .sidebar-brand::after {
        content: "🌱";
        font-size: 25px;
    }

    .sidebar-subtitle, .sidebar-label,
    .sidebar .nav-link span { display: none; }

    .sidebar .nav-link {
        justify-content: center;
        padding: 14px 5px;
        font-size: 18px;
    }

    .main-content { margin-left: 72px; }
    .topbar { padding: 15px; }
    .page-content { padding: 16px 10px; }
    .admin-label { display: none; }
    .stat-card, .content-card { padding: 14px; }
    .stat-number { font-size: 24px; }
}
</style>
</head>

<body>

<aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">🌱 AgriConnect</a>
    <div class="sidebar-subtitle">ADMINISTRATION PANEL</div>
    <div class="sidebar-label">MAIN MENU</div>

    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php">
            <span>▦</span><span>Dashboard</span>
        </a>
        <a class="nav-link" href="farmers.php">
            <span>♙</span><span>Farmer Approvals</span>
        </a>
        <a class="nav-link" href="products.php">
            <span>▣</span><span>Listing Approvals</span>
        </a>
        <a class="nav-link active" href="orders.php">
            <span>🛒</span><span>Manage Orders</span>
        </a>
        <a class="nav-link" href="../index.php">
            <span>⌂</span><span>Visit Website</span>
        </a>
    </nav>

    <a class="nav-link logout" href="logout.php">
        <span>⇥</span><span>Log Out</span>
    </a>
</aside>

<div class="main-content">
    <header class="topbar">
        <div class="topbar-title">Order Management</div>
        <div class="admin-label">Administrator</div>
    </header>

    <main class="page-content">
        <div class="mb-4">
            <div class="section-label mb-2">ADMINISTRATION / ORDERS</div>
            <h1 class="page-title h3 mb-2">Manage Customer Orders</h1>
            <p class="text-secondary mb-0">
                Approve orders, review payments and manage delivery progress.
            </p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="alert alert-<?= e($message_type) ?> alert-dismissible fade show">
                <?= e($message) ?>
                <button type="button" class="btn-close"
                        data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <?php
            $cards = [
                ["Total Orders", "total_orders"],
                ["Awaiting Approval", "placed_orders"],
                ["Confirmed", "confirmed_orders"],
                ["Dispatched", "dispatched_orders"],
                ["Delivered", "delivered_orders"],
                ["Unpaid", "unpaid_orders"],
                ["Paid", "paid_orders"]
            ];
            foreach ($cards as [$label, $key]):
            ?>
                <div class="col-6 col-xl">
                    <div class="stat-card">
                        <div class="stat-label"><?= e($label) ?></div>
                        <div class="stat-number">
                            <?= (int)$stats[$key] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="content-card">
            <div class="d-flex flex-wrap justify-content-between
                        align-items-center gap-3 mb-4">
                <div>
                    <h2 class="h5 fw-bold mb-1">All Customer Orders</h2>
                    <p class="text-secondary small mb-0">
                        Confirm orders first. Payment must be verified before dispatch.
                    </p>
                </div>

                <input type="search" id="orderSearch"
                       class="form-control search-input"
                       placeholder="Search orders or customers..."
                       aria-label="Search orders">
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="ordersTable">
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Delivery Location</th>
                        <th>Order Status</th>
                        <th>Payment</th>
                        <th>M-Pesa Receipt</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div style="font-size:35px">🛒</div>
                                <h5 class="mt-2">No customer orders yet</h5>
                                <p class="text-secondary mb-0">
                                    Orders appear here when buyers place them.
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $status = $order["status"];
                            $payment = $order["payment_status"];

                            $current_index = array_search(
                                $status,
                                $allowed_statuses,
                                true
                            );

                            $next_status = (
                                $current_index !== false &&
                                isset($allowed_statuses[$current_index + 1])
                            ) ? $allowed_statuses[$current_index + 1] : null;

                            $payment_class = match ($payment) {
                                "paid" => "success",
                                "failed" => "danger",
                                default => "warning"
                            };

                            $timestamp = strtotime($order["order_date"]);
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?= (int)$order["order_id"] ?></strong>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($order["full_name"] ?: "Unknown customer") ?>
                                    </strong>
                                    <br>
                                    <small class="text-secondary">
                                        <?= e($order["email"]) ?>
                                    </small>
                                    <br>
                                    <small class="text-secondary">
                                        <?= e($order["phone"] ?: "No phone") ?>
                                    </small>
                                </td>

                                <td>
                                    <?= $timestamp
                                        ? e(date("d M Y, H:i", $timestamp))
                                        : "—" ?>
                                </td>

                                <td><?= (int)$order["item_count"] ?></td>

                                <td>
                                    <strong>
                                        KSh <?= number_format(
                                            (float)$order["total_amount"], 2
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($order["delivery_location"] ?: "Not provided") ?>
                                </td>

                                <td>
                                    <span class="status status-<?= e($status) ?>">
                                        <?= e($status_labels[$status] ?? $status) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge text-bg-<?= $payment_class ?>">
                                        <?= e(ucfirst($payment)) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($order["mpesa_receipt_number"] ?: "Not submitted") ?>
                                </td>

                                <td>
                                    <?php if ($next_status === "confirmed"): ?>
                                        <form method="POST"
                                              onsubmit="return confirm('Approve order #<?= (int)$order["order_id"] ?>? This does not verify payment.');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION["orders_csrf"]) ?>">
                                            <input type="hidden" name="order_id"
                                                   value="<?= (int)$order["order_id"] ?>">
                                            <input type="hidden" name="status"
                                                   value="confirmed">
                                            <button type="submit"
                                                    class="btn btn-success btn-sm">
                                                Approve Order
                                            </button>
                                        </form>

                                    <?php elseif ($next_status === "dispatched"): ?>

                                        <?php if ($payment === "paid"): ?>
                                            <form method="POST"
                                                  onsubmit="return confirm('Mark order #<?= (int)$order["order_id"] ?> as dispatched?');">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= e($_SESSION["orders_csrf"]) ?>">
                                                <input type="hidden" name="order_id"
                                                       value="<?= (int)$order["order_id"] ?>">
                                                <input type="hidden" name="status"
                                                       value="dispatched">
                                                <button type="submit"
                                                        class="btn btn-success btn-sm">
                                                    Mark Dispatched
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-secondary small">
                                                Verify payment first
                                            </span>
                                        <?php endif; ?>

                                    <?php elseif ($next_status === "delivered"): ?>

                                        <?php if ($payment === "paid"): ?>
                                            <form method="POST"
                                                  onsubmit="return confirm('Mark order #<?= (int)$order["order_id"] ?> as delivered?');">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= e($_SESSION["orders_csrf"]) ?>">
                                                <input type="hidden" name="order_id"
                                                       value="<?= (int)$order["order_id"] ?>">
                                                <input type="hidden" name="status"
                                                       value="delivered">
                                                <button type="submit"
                                                        class="btn btn-success btn-sm">
                                                    Mark Delivered
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-secondary small">
                                                Payment not verified
                                            </span>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <span class="text-success fw-semibold small">
                                            ✓ Delivered
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                <strong>Payment security:</strong>
                Approving an order does not mark it as paid. Verify the
                actual M-Pesa transaction independently before recording
                payment as paid. This page does not yet approve payments.
            </div>
        </section>
    </main>

    <footer>
        AgriConnect Administration &copy; <?= date("Y") ?>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const orderSearch = document.getElementById("orderSearch");

if (orderSearch) {
    orderSearch.addEventListener("input", function () {
        const searchText = this.value.toLowerCase();

        document.querySelectorAll("#ordersTable tbody tr").forEach(function (row) {
            row.style.display = row.innerText.toLowerCase().includes(searchText)
                ? ""
                : "none";
        });
    });
}
</script>

</body>
</html>
