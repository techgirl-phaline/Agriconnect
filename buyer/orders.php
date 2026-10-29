
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

$buyer_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT order_id, order_date, total_amount,
            delivery_location, status, payment_status
     FROM orders
     WHERE buyer_id = ?
     ORDER BY order_date DESC"
);

$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$orders = $stmt->get_result();

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
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

    <title>My Orders | AgriConnect</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f7faf4;
            color: #25372d;
            font-family: Arial, sans-serif;
        }

        .page-header {
            background: #075b3d;
            color: white;
            padding: 28px 0;
        }

        .orders-card {
            background: white;
            border: 1px solid #e2e9e1;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 5px 20px rgba(20, 60, 35, 0.05);
        }

        .btn-green {
            background: #13804f;
            color: white;
            border: none;
            padding: 9px 15px;
            border-radius: 7px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-green:hover {
            background: #075b3d;
            color: white;
        }

        .table th {
            white-space: nowrap;
        }

        @media (max-width: 576px) {
            .orders-card {
                padding: 14px;
            }
        }
    </style>
</head>

<body>

<header class="page-header">
    <div class="container">
        <h1 class="h3 mb-2">My Orders</h1>
        <p class="mb-0">
            View your AgriConnect orders and payment status.
        </p>
    </div>
</header>

<main class="container py-4">

    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <h2 class="h4 mb-0">Order History</h2>

        <a href="../produce-catalogue.php" class="btn-green">
            Continue Shopping
        </a>
    </div>

    <section class="orders-card">

        <?php if ($orders->num_rows > 0): ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">

                    <thead class="table-light">
                    <tr>
                        <th>Order No.</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Order Status</th>
                        <th>Payment</th>
                        <th>Delivery Location</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php while ($order = $orders->fetch_assoc()): ?>

                        <?php
                        $paymentBadge = match ($order["payment_status"]) {
                            "paid" => "success",
                            "failed" => "danger",
                            default => "warning"
                        };
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    #<?= (int) $order["order_id"] ?>
                                </strong>
                            </td>

                            <td>
                                <?= e($order["order_date"]) ?>
                            </td>

                            <td>
                                KSh <?= number_format(
                                    (float) $order["total_amount"],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <?= e(ucfirst($order["status"])) ?>
                            </td>

                            <td>
                                <span class="badge text-bg-<?= $paymentBadge ?>">
                                    <?= e(ucfirst(
                                        $order["payment_status"]
                                    )) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(
                                    $order["delivery_location"]
                                    ?: "Not provided"
                                ) ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>
                    </tbody>

                </table>
            </div>

            <p class="text-secondary small mb-0">
                Payments remain unpaid until verified by the
                AgriConnect administrator.
            </p>

        <?php else: ?>

            <div class="text-center py-5">
                <div class="display-4">🛒</div>

                <h3 class="h5 mt-3">No Orders Yet</h3>

                <p class="text-secondary">
                    You have not placed any orders yet.
                    Browse available produce to get started.
                </p>

                <a href="../produce-catalogue.php" class="btn-green">
                    Browse Produce
                </a>
            </div>

        <?php endif; ?>

    </section>

    <div class="mt-4">
        <a href="../index.php" class="btn btn-outline-success">
            Back to Home
        </a>
    </div>

</main>

</body>
</html>

<?php
$stmt->close();
?>
