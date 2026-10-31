
<?php
session_start();

require_once __DIR__ . '/../db.php';

// Only logged-in administrators may access this page.
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login.php");
    exit;
}
/* Get unread notification count */
$unread_query = $conn->query(
    "SELECT COUNT(*) AS unread_count
     FROM admin_notifications
     WHERE is_read = 0"
);

$unread_count = (int) $unread_query->fetch_assoc()['unread_count'];

/* Get the latest 10 admin notifications */
$notifications_query = $conn->query(
    "SELECT *
     FROM admin_notifications
     ORDER BY created_at DESC
     LIMIT 10"
);

// Dashboard statistics
$stats = [
    'farmers' => 0,
    'pending_farmers' => 0,
    'buyers' => 0,
    'pending_buyers' => 0,
    'products' => 0,
    'pending_products' => 0,
    'orders' => 0
];

// Total registered farmers
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'farmer'"
);

if ($result) {
    $stats['farmers'] = (int) $result->fetch_assoc()['total'];
}

// Farmers awaiting approval
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'farmer'
       AND account_status = 'pending'"
);

if ($result) {
    $stats['pending_farmers'] = (int) $result->fetch_assoc()['total'];
}

// Total registered buyers
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'buyer'"
);

if ($result) {
    $stats['buyers'] = (int) $result->fetch_assoc()['total'];
}

// Buyers awaiting approval
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'buyer'
       AND account_status = 'pending'"
);

if ($result) {
    $stats['pending_buyers'] = (int) $result->fetch_assoc()['total'];
}

// Total produce listings
$result = $conn->query(
    "SELECT COUNT(*) AS total FROM products"
);

if ($result) {
    $stats['products'] = (int) $result->fetch_assoc()['total'];
}

// Listings awaiting approval
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE approval_status = 'pending'"
);

if ($result) {
    $stats['pending_products'] = (int) $result->fetch_assoc()['total'];
}

// Total orders
$result = $conn->query(
    "SELECT COUNT(*) AS total FROM orders"
);

if ($result) {
    $stats['orders'] = (int) $result->fetch_assoc()['total'];
}

// Safely display text
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

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
            --white: #FFFFFF;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: Arial, sans-serif;
            margin: 0;
        }

        /* Sidebar */
        .sidebar {
            background: var(--white);
            min-height: 100vh;
            border-right: 1px solid var(--border);
        }

        .brand {
            color: var(--green);
            font-weight: 800;
            font-size: 1.4rem;
        }

        .brand-icon {
            color: var(--fresh-green);
        }

        .nav-link {
            color: #536258;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 6px;
            transition: background .2s, color .2s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--soft-green);
            color: var(--dark-green);
        }

        .nav-link.active {
            font-weight: 700;
        }

        .nav-link.logout {
            color: #B42318;
        }

        .nav-link.logout:hover {
            background: #FDECEC;
        }

        /* Page heading */
        .page-title {
            color: var(--dark-green);
        }

        .admin-badge {
            background: var(--soft-green);
            color: var(--dark-green);
            border: 1px solid #D7E8D2;
            border-radius: 30px;
            padding: 9px 15px;
            white-space: nowrap;
        }

        /* Welcome banner */
        .welcome {
            background: linear-gradient(
                120deg,
                var(--dark-green),
                var(--fresh-green)
            );
            color: var(--white);
            border-radius: 18px;
            padding: 28px;
            position: relative;
            overflow: hidden;
        }

        .welcome h2 {
            font-weight: 800;
        }

        .welcome p {
            line-height: 1.7;
        }

        .welcome-accent {
            width: 65px;
            height: 5px;
            background: var(--yellow);
            border-radius: 5px;
            margin-bottom: 16px;
        }

        /* Section headings */
        .section-title {
            color: var(--dark-green);
            font-weight: 800;
        }

        /* Statistics */
        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px;
            height: 100%;
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(6, 69, 47, .07);
        }

        .stat-label {
            color: var(--muted);
            font-size: .9rem;
            line-height: 1.5;
            min-height: 42px;
        }

        .stat-number {
            color: var(--dark-green);
            font-size: 2rem;
            font-weight: 800;
            margin-top: 8px;
        }

        .stat-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--soft-green);
            font-size: 1.3rem;
            margin-bottom: 14px;
        }

        .stat-icon.yellow {
            background: #FFF7D6;
        }

        /* Quick action cards */
        .action-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 22px;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: transform .2s, box-shadow .2s;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(6, 69, 47, .07);
        }

        .action-icon {
            font-size: 1.8rem;
            margin-bottom: 12px;
        }

        .action-card h6 {
            color: var(--dark-green);
            font-weight: 800;
        }

        .action-card p {
            color: var(--muted);
            font-size: .9rem;
            line-height: 1.6;
            flex-grow: 1;
        }

        /* Buttons */
        .btn-green {
            background: var(--green);
            color: var(--white);
            border: 1px solid var(--green);
            border-radius: 8px;
            padding: 9px 14px;
            font-weight: 600;
        }

        .btn-green:hover {
            background: var(--dark-green);
            border-color: var(--dark-green);
            color: var(--white);
        }

        .btn-yellow {
            background: var(--yellow);
            color: var(--dark-green);
            border: 1px solid var(--yellow);
            border-radius: 8px;
            padding: 9px 14px;
            font-weight: 700;
        }

        .btn-yellow:hover {
            background: #E9BF1D;
            border-color: #E9BF1D;
            color: var(--dark-green);
        }

        /* Footer */
        .footer {
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: .85rem;
        }

        /* Responsive layout */
        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 4px;
            }

            .nav-link {
                font-size: .88rem;
                margin-bottom: 0;
            }

            .welcome {
                padding: 22px;
            }

            .welcome h2 {
                font-size: 1.5rem;
            }

            .stat-card,
            .action-card {
                padding: 17px;
            }

            .stat-number {
                font-size: 1.7rem;
            }

            .admin-badge {
                font-size: .75rem;
                padding: 7px 10px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 sidebar p-3">

            <div class="brand mb-1">
                <span class="brand-icon">🌱</span> AgriConnect
            </div>

            <div class="small text-secondary mb-4">
                Administration Panel
            </div>

            <nav class="nav flex-column">
                <a class="nav-link active" href="dashboard.php">
                    🏠 Dashboard
                </a>

                <a class="nav-link" href="farmers.php">
                    👨‍🌾 Farmer Approvals
                </a>

                <a class="nav-link" href="users.php">
                    👥 User Approvals
                </a>

                <a class="nav-link" href="products.php">
                    🥬 Listing Approvals
                </a>

                <a class="nav-link" href="orders.php">
                    📦 Manage Orders
                </a>
                 
                
                <a class="nav-link" href="notifications.php">
    🔔 Notifications

    <?php if ($unread_count > 0): ?>
        <span class="badge bg-warning text-dark">
            <?= $unread_count ?>
        </span>
    <?php endif; ?>
</a>
                
                
<a href="payments.php" class="btn btn-outline-success">
    <i class="bi bi-shield-check"></i>
    Verify Payments
</a>


                <a class="nav-link" href="../index.php">
                    🌍 Visit Website
                </a>

                <a class="nav-link logout" href="logout.php">
                    ↪ Log Out
                </a>
            </nav>

        </aside>

        <!-- Main Dashboard -->
        <main class="col-md-9 col-lg-10 p-3 p-md-4 p-lg-5">

            <!-- Page Header -->
            <div class="d-flex justify-content-between
                        align-items-center gap-3 mb-4">

                <div>
                    <h3 class="page-title fw-bold mb-1">
                        Admin Dashboard
                    </h3>

                    <p class="text-secondary mb-0">
                        Manage your AgriConnect marketplace.
                    </p>
                </div>

                <span class="admin-badge fw-bold">
                    Administrator
                </span>

            </div>

            <!-- Welcome Banner -->
            <section class="welcome mb-4">

                <div class="welcome-accent"></div>

                <h2>
                    Welcome,
                    <?= e($_SESSION['full_name'] ?? 'Admin') ?>! 👋
                </h2>

                <p class="mb-0">
                    Manage farmer and buyer registrations, review produce
                    listings, and monitor customer orders from one place.
                </p>

                        </section>

            <!-- Admin Activity Notifications -->
            <section class="card border-0 shadow-sm mb-5"
                     style="border-radius: 16px;">

                <!-- PASTE THE REST OF THE NOTIFICATION CODE HERE -->

            </section>

            <!-- Platform Overview -->
            <h5 class="section-title mb-3">
                Platform Overview
            </h5>

            <div class="row g-3 mb-5">

            <div class="row g-3 mb-5">

                <div class="col-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon">👨‍🌾</div>
                        <div class="stat-label">Registered Farmers</div>
                        <div class="stat-number">
                            <?= $stats['farmers'] ?>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon yellow">⏳</div>
                        <div class="stat-label">
                            Farmers Awaiting Approval
                        </div>
                        <div class="stat-number">
                            <?= $stats['pending_farmers'] ?>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-label">Registered Buyers</div>
                        <div class="stat-number">
                            <?= $stats['buyers'] ?>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon yellow">🕒</div>
                        <div class="stat-label">
                            Buyers Awaiting Approval
                        </div>
                        <div class="stat-number">
                            <?= $stats['pending_buyers'] ?>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon">🥬</div>
                        <div class="stat-label">Total Produce Listings</div>
                        <div class="stat-number">
                            <?= $stats['products'] ?>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon yellow">📋</div>
                        <div class="stat-label">
                            Listings Awaiting Approval
                        </div>
                        <div class="stat-number">
                            <?= $stats['pending_products'] ?>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-number">
                            <?= $stats['orders'] ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Quick Actions -->
            <h5 class="section-title mb-3">
                Quick Actions
            </h5>

            <div class="row g-3">

                <!-- Farmer Accounts -->
                <div class="col-md-6 col-xl-4">
                    <div class="action-card">
                        <div class="action-icon">👨‍🌾</div>

                        <h6>Farmer Accounts</h6>

                        <p>
                            Review farmer registrations and approve
                            or reject farmer accounts.
                        </p>

                        <a href="farmers.php"
                           class="btn btn-green btn-sm align-self-start">
                            Review Farmers
                        </a>
                    </div>
                </div>

                <!-- Buyer Accounts -->
                <div class="col-md-6 col-xl-4">
                    <div class="action-card">
                        <div class="action-icon">👥</div>

                        <h6>Buyer Accounts</h6>

                        <p>
                            Review buyer registrations and approve
                            or reject buyer accounts.
                        </p>

                        <a href="users.php"
                           class="btn btn-green btn-sm align-self-start">
                            Review Buyers
                        </a>
                    </div>
                </div>

                <!-- Produce Listings -->
                <div class="col-md-6 col-xl-4">
                    <div class="action-card">
                        <div class="action-icon">🥬</div>

                        <h6>Produce Listings</h6>

                        <p>
                            Check produce details and approve
                            or reject product listings.
                        </p>

                        <a href="products.php"
                           class="btn btn-green btn-sm align-self-start">
                            Review Listings
                        </a>
                    </div>
                </div>

                <!-- Customer Orders -->
                <div class="col-md-6 col-xl-4">
                    <div class="action-card">
                        <div class="action-icon">📦</div>

                        <h6>Customer Orders</h6>

                        <p>
                            View customer orders and monitor
                            their progress.
                        </p>

                        <a href="orders.php"
                           class="btn btn-green btn-sm align-self-start">
                            View Orders
                        </a>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <footer class="footer mt-5 pt-3 pb-2">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <span>
                        AgriConnect Administration &copy; <?= date('Y') ?>
                    </span>

                    <span>
                        Connecting Farmers to Buyers 🌱
                    </span>
                </div>
            </footer>

        </main>

    </div>
</div>

</body>
</html>
