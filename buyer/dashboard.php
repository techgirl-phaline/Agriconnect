
<?php
session_start();
require_once __DIR__ . '/../db.php';

// Require a logged-in buyer.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'buyer'
) {
    header("Location: ../login.php");
    exit;
}

$buyer_id = (int) $_SESSION['user_id'];

// Verify the buyer account.
$stmt = $conn->prepare(
    "SELECT full_name, account_status
     FROM users
     WHERE user_id = ? AND role = 'buyer'
     LIMIT 1"
);
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$buyer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$buyer) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Count available approved products.
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE status = 'available'
       AND approval_status = 'approved'"
);
$product_count = (int) $result->fetch_assoc()['total'];

// Count items in the shopping cart.
$cart_count = 0;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $quantity) {
        $cart_count += max(0, (int) $quantity);
    }
}

// Count this buyer's unpaid orders.
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE buyer_id = ?
       AND payment_status = 'unpaid'"
);
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$unpaid_orders = (int) $stmt->get_result()
    ->fetch_assoc()['total'];
$stmt->close();

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Buyer Dashboard | AgriConnect</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --green: #075B3D;
            --dark-green: #06452F;
            --fresh-green: #13804F;
            --yellow: #F5CF32;
            --cream: #F7FAF4;
            --soft-green: #EFF6E9;
            --text: #25372D;
            --muted: #718075;
            --border: #E2E9E1;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: var(--green);
        }

        .brand {
            color: white;
            font-size: 1.4rem;
            font-weight: 800;
            text-decoration: none;
        }

        .brand span {
            color: var(--yellow);
        }

        .nav-link-custom {
            color: white;
            text-decoration: none;
            margin-left: 18px;
        }

        .nav-link-custom:hover {
            color: var(--yellow);
        }

        .welcome {
            background: linear-gradient(
                120deg,
                var(--dark-green),
                var(--fresh-green)
            );
            color: white;
            border-radius: 18px;
            padding: 35px;
        }

        .welcome h1 {
            font-weight: 800;
        }

        .welcome p {
            line-height: 1.7;
        }

        .btn-yellow {
            background: var(--yellow);
            color: var(--dark-green);
            border: 1px solid var(--yellow);
            border-radius: 8px;
            padding: 11px 20px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
        }

        .btn-yellow:hover {
            background: #E9BF1D;
            color: var(--dark-green);
        }

        .section-title {
            color: var(--dark-green);
            font-weight: 800;
        }

        .dashboard-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 25px;
            height: 100%;
        }

        .card-icon {
            background: var(--soft-green);
            width: 55px;
            height: 55px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            margin-bottom: 17px;
        }

        .dashboard-card h5 {
            color: var(--dark-green);
            font-weight: 800;
        }

        .dashboard-card p {
            color: var(--muted);
            line-height: 1.6;
        }

        .btn-green {
            background: var(--green);
            color: white;
            border: 1px solid var(--green);
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
        }

        .btn-green:hover {
            background: var(--dark-green);
            color: white;
        }

        .status-badge {
            background: var(--soft-green);
            color: var(--dark-green);
            padding: 8px 12px;
            border-radius: 20px;
            font-size: .85rem;
            font-weight: 700;
        }

        footer {
            border-top: 1px solid var(--border);
            color: var(--muted);
        }

        @media (max-width: 576px) {
            .welcome {
                padding: 24px;
            }

            .welcome h1 {
                font-size: 1.7rem;
            }

            .dashboard-card {
                padding: 20px;
            }

            .nav-link-custom {
                margin-left: 10px;
                font-size: .9rem;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg py-3">
    <div class="container">
        <a href="../index.php" class="brand">
            🌱 Agri<span>Connect</span>
        </a>

        <div class="ms-auto d-flex align-items-center">
            <a href="cart.php" class="nav-link-custom">
                🛒 Cart (<?= $cart_count ?>)
            </a>

            <a href="logout.php" class="nav-link-custom">
                Log Out
            </a>
        </div>
    </div>
</nav>

<main class="container py-4 py-md-5">

    <!-- Welcome -->
    <section class="welcome mb-5">
        <span class="status-badge">Buyer Account</span>

        <h1 class="mt-3">
            Welcome, <?= e($buyer['full_name']) ?>! 👋
        </h1>

        <p class="mb-4">
            Discover fresh produce from farmers, shop for what you need,
            and manage your orders and payments conveniently with AgriConnect.
        </p>

        <a href="../produce-catalogue.php" class="btn-yellow">
            🥬 Browse Produce
        </a>
    </section>

    <?php if ($buyer['account_status'] !== 'approved'): ?>

        <div class="alert alert-warning">
            <strong>Account awaiting approval.</strong>
            You can explore the dashboard, but you must be approved
            before placing an order.
        </div>

    <?php else: ?>

        <div class="alert alert-success">
            Your buyer account is approved.
            You can shop for available produce.
        </div>

    <?php endif; ?>

    <!-- Dashboard cards -->
    <h4 class="section-title mb-3">Your Shopping Dashboard</h4>

    <div class="row g-3">

        <!-- Browse Produce -->
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card">
                <div class="card-icon">🥬</div>

                <h5>Browse Produce</h5>

                <p>
                    Explore available produce and compare prices
                    before choosing what to buy.
                </p>

                <a href="../produce-catalogue.php" class="btn-green">
                    Shop Now →
                </a>
            </div>
        </div>

        <!-- Shopping Cart -->
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card">
                <div class="card-icon">🛒</div>

                <h5>My Shopping Cart</h5>

                <p>
                    Review selected products and quantities
                    before proceeding to checkout.
                </p>

                <a href="cart.php" class="btn-green">
                    View Cart (<?= $cart_count ?>) →
                </a>
            </div>
        </div>

        <!-- My Orders -->
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card">
                <div class="card-icon">📦</div>

                <h5>My Orders</h5>

                <p>
                    View your orders, totals, delivery locations
                    and order progress.
                </p>

                <a href="orders.php" class="btn-green">
                    View My Orders →
                </a>
            </div>
        </div>

        <!-- My Payments -->
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card">
                <div class="card-icon">💳</div>

                <h5>My Payments</h5>

                <p>
                    You have <?= $unpaid_orders ?>
                    unpaid order(s). Review your order payment status
                    and payment instructions.
                </p>

                <a href="orders.php" class="btn-green">
                    View Payments →
                </a>
            </div>
        </div>

    </div>

    <!-- Product availability -->
    <section class="dashboard-card mt-4">
        <div class="d-flex flex-wrap justify-content-between
                    align-items-center gap-3">

            <div>
                <h5 class="section-title mb-2">
                    Fresh Produce Marketplace
                </h5>

                <p class="mb-0">
                    <?= $product_count ?>
                    approved product listing(s) currently available.
                </p>
            </div>

            <a href="../produce-catalogue.php" class="btn-green">
                Explore Produce →
            </a>

        </div>
    </section>

    <footer class="mt-5 pt-3 pb-2">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <span>AgriConnect &copy; <?= date('Y') ?></span>
            <span>Connecting Farmers to Buyers 🌱</span>
        </div>
    </footer>

</main>

</body>
</html>
