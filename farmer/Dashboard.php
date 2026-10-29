
<?php
session_start();

/*
|--------------------------------------------------------------------------
| Farmer access protection
|--------------------------------------------------------------------------
| The farmer must log in before accessing this dashboard.
*/

if (
    !isset($_SESSION['farmer_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'farmer'
) {
    header("Location: login.php");
    exit;
}

$farmer_name = $_SESSION['farmer_name'] ?? 'Farmer';
$first_name = explode(' ', trim($farmer_name))[0];

$total_products = 0;
$available_products = 0;
$unavailable_products = 0;
$recent_products = [];
$db_error = false;

/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
| Update this path if your database connection file is elsewhere.
*/

require_once __DIR__ . '/../db.php';

try {
    /*
    | Count this farmer's products only.
    */
    $farmer_id = (int) $_SESSION['farmer_id'];

    $stmt = $conn->prepare(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(
                CASE WHEN status = 'available'
                THEN 1 ELSE 0 END
            ), 0) AS available,
            COALESCE(SUM(
                CASE WHEN status != 'available'
                THEN 1 ELSE 0 END
            ), 0) AS unavailable
         FROM products
         WHERE farmer_id = ?"
    );

    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();

    $stats = $stmt->get_result()->fetch_assoc();

    $total_products = (int) $stats['total'];
    $available_products = (int) $stats['available'];
    $unavailable_products = (int) $stats['unavailable'];

    $stmt->close();

    /*
    | Fetch the latest products belonging to this farmer.
    */
    $stmt = $conn->prepare(
        "SELECT product_id, product_name, price, quantity_available,
        status, image
 FROM products
 WHERE farmer_id = ?
 ORDER BY product_id DESC
 LIMIT 5"
    );

    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recent_products[] = $row;
    }

    $stmt->close();
    } catch (Throwable $e) {
    $db_error = true;
    echo "<pre>Database error: "
        . htmlspecialchars($e->getMessage())
        . "</pre>";
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Farmer Dashboard | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        :root {
            --dark-green: #075B3D;
            --main-green: #13804F;
            --deep-green: #06452F;
            --yellow: #F5CF32;
            --soft-green: #EFF6E9;
            --pale-green: #F7FAF4;
            --white: #FFFFFF;
            --text-dark: #25372D;
            --text-muted: #6B786F;
            --border: #E2E9E1;
            --shadow: 0 8px 25px rgba(7, 91, 61, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--pale-green);
            color: var(--text-dark);
            font-family: Arial, sans-serif;
        }

        a {
            text-decoration: none;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 255px;
            height: 100vh;
            background: var(--dark-green);
            color: white;
            padding: 25px 16px;
            display: flex;
            flex-direction: column;
            z-index: 1040;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 28px;
            border-bottom: 1px solid rgba(255,255,255,.15);
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: var(--yellow);
            color: var(--dark-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
        }

        .brand h4 {
            font-size: 21px;
            margin: 0;
            font-weight: 800;
        }

        .brand small {
            color: #D5E8D8;
            font-size: 12px;
        }

        .menu-label {
            color: #BFD6C6;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 28px 12px 12px;
        }

        .side-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            margin-bottom: 6px;
            border-radius: 9px;
            color: #E5F1E8;
            font-size: 14px;
            transition: .2s;
        }

        .side-link i {
            font-size: 18px;
        }

        .side-link:hover,
        .side-link.active {
            color: var(--deep-green);
            background: var(--yellow);
        }

        .sidebar-bottom {
            margin-top: auto;
        }

        .sidebar-bottom .side-link {
            color: #FFDADA;
        }

        .main-content {
            margin-left: 255px;
            padding: 28px 32px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .welcome h2 {
            font-size: 27px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .welcome p {
            color: var(--text-muted);
            margin: 0;
        }

        .profile-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 9px 14px;
        }

        .avatar {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--soft-green);
            color: var(--dark-green);
            border-radius: 50%;
            font-size: 20px;
        }

        .profile-chip strong {
            display: block;
            font-size: 14px;
        }

        .profile-chip small {
            color: var(--text-muted);
        }

        .btn-green {
            background: var(--main-green);
            border: 1px solid var(--main-green);
            color: white;
            border-radius: 9px;
            padding: 11px 17px;
            font-weight: 600;
        }

        .btn-green:hover {
            background: var(--deep-green);
            color: white;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 22px;
            height: 100%;
            box-shadow: var(--shadow);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 19px;
        }

        .stat-icon {
            width: 47px;
            height: 47px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 22px;
        }

        .green-icon {
            background: var(--soft-green);
            color: var(--main-green);
        }

        .yellow-icon {
            background: #FFF7D5;
            color: #967600;
        }

        .stat-card p {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-card h3 {
            font-size: 29px;
            font-weight: 800;
            margin: 0;
        }

        .section-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 23px;
            margin-top: 26px;
            box-shadow: var(--shadow);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-heading h4 {
            font-size: 19px;
            font-weight: 800;
            margin: 0 0 5px;
        }

        .section-heading p {
            color: var(--text-muted);
            font-size: 13px;
            margin: 0;
        }

        .table thead th {
            background: var(--pale-green);
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
            padding: 14px 12px;
        }

        .table tbody td {
            padding: 13px 12px;
            vertical-align: middle;
            font-size: 14px;
        }

        .product-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 9px;
            background: var(--soft-green);
        }

        .status-pill {
            display: inline-block;
            border-radius: 20px;
            padding: 5px 10px;
            background: var(--soft-green);
            color: var(--dark-green);
            font-size: 12px;
            font-weight: 700;
        }

        .empty-state {
            padding: 35px 15px;
            text-align: center;
        }

        .empty-state i {
            font-size: 43px;
            color: var(--main-green);
        }

        .empty-state h5 {
            margin-top: 12px;
            font-weight: 800;
        }

        .empty-state p {
            color: var(--text-muted);
        }

        .mobile-header {
            display: none;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .mobile-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 25px;
            }

            .mobile-brand {
                color: var(--dark-green);
                font-weight: 800;
                font-size: 21px;
            }

            .mobile-menu-btn {
                background: var(--dark-green);
                color: white;
                border: none;
                border-radius: 9px;
                padding: 9px 13px;
                font-size: 21px;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.35);
                z-index: 1030;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 575.98px) {
            .main-content {
                padding: 16px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome h2 {
                font-size: 23px;
            }

            .profile-chip {
                width: 100%;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-card h3 {
                font-size: 25px;
            }

            .section-card {
                padding: 15px;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- LEFT SIDEBAR -->
<aside class="sidebar" id="sidebar">

    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-flower1"></i>
        </div>

        <div>
            <h4>AgriConnect</h4>
            <small>Farmer Portal</small>
        </div>
    </div>

    <div class="menu-label">Main Menu</div>

    <a href="dashboard.php" class="side-link active">
        <i class="bi bi-grid-1x2"></i>
        Dashboard
    </a>

    <a href="manage-produce.php" class="side-link">
        <i class="bi bi-box-seam"></i>
        Manage My Produce
    </a>

    <a href="profile.php" class="side-link">
        <i class="bi bi-person-circle"></i>
        My Farmer Profile
    </a>

    <div class="menu-label">Marketplace</div>

    <a href="../produce-catalogue.php" class="side-link">
        <i class="bi bi-shop"></i>
        Browse Marketplace
    </a>

    <a href="../index.php" class="side-link">
        <i class="bi bi-house"></i>
        Visit Homepage
    </a>

    <div class="sidebar-bottom">
        <div class="menu-label">Account</div>

        <a href="profile.php" class="side-link">
            <i class="bi bi-gear"></i>
            Account Settings
        </a>

        <a href="logout.php" class="side-link"
           onclick="return confirm('Are you sure you want to log out?');">
            <i class="bi bi-box-arrow-left"></i>
            Log Out
        </a>
    </div>

</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- MOBILE MENU -->
    <div class="mobile-header">
        <div class="mobile-brand">
            <i class="bi bi-flower1"></i> AgriConnect
        </div>

        <button class="mobile-menu-btn"
                type="button"
                id="menuToggle"
                aria-label="Open navigation menu"
                aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- WELCOME HEADER -->
    <header class="topbar">

        <div class="welcome">
            <h2>Welcome back, <?= h($first_name) ?>! 👋</h2>
            <p>Manage your farm produce and grow your marketplace.</p>
        </div>

        <div class="profile-chip">
            <div class="avatar">
                <i class="bi bi-person"></i>
            </div>

            <div>
                <strong><?= h($farmer_name) ?></strong>
                <small>Registered Farmer</small>
            </div>
        </div>

    </header>

    <?php if ($db_error): ?>
        <div class="alert alert-warning">
            We could not load your product statistics.
            Check your database connection and products table columns.
        </div>
    <?php endif; ?>

    <!-- QUICK ACTION -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h5 class="fw-bold mb-1">Your Farm Overview</h5>
            <p class="text-secondary small mb-0">
                Here is a summary of your produce listings.
            </p>
        </div>

        <a href="manage-produce.php" class="btn btn-green">
            <i class="bi bi-plus-lg me-2"></i>
            Add New Produce
        </a>
    </div>

    <!-- STATISTICS -->
    <div class="row g-3">

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon green-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>

                <p>Total Products</p>
                <h3><?= $total_products ?></h3>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon yellow-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>

                <p>Available Products</p>
                <h3><?= $available_products ?></h3>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon green-icon">
                        <i class="bi bi-archive"></i>
                    </div>
                </div>

                <p>Other Listings</p>
                <h3><?= $unavailable_products ?></h3>
            </div>
        </div>

    </div>

    <!-- RECENT PRODUCTS -->
    <section class="section-card">

        <div class="section-heading">
            <div>
                <h4>My Recent Produce</h4>
                <p>Your five most recently added listings.</p>
            </div>

            <a href="manage-produce.php"
               class="btn btn-outline-success btn-sm">
                Manage All
                <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (empty($recent_products)): ?>

            <div class="empty-state">
                <i class="bi bi-basket"></i>

                <h5>No produce listed yet</h5>

                <p>
                    Start by adding your first farm product to
                    the AgriConnect marketplace.
                </p>

                <a href="manage-produce.php" class="btn btn-green">
                    <i class="bi bi-plus-lg me-2"></i>
                    Add Your First Product
                </a>
            </div>

        <?php else: ?>

            <div class="table-responsive">
                <table class="table align-middle mb-0">

                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($recent_products as $product): ?>

                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">

                                    <?php if (!empty($product['image'])): ?>
                                        <img
                                            src="../<?= h($product['image']) ?>"
                                            alt="<?= h($product['product_name']) ?>"
                                            class="product-thumb">
                                    <?php else: ?>
                                        <div class="product-thumb d-flex align-items-center justify-content-center">
                                            <i class="bi bi-image text-success"></i>
                                        </div>
                                    <?php endif; ?>

                                    <strong>
                                        <?= h($product['product_name']) ?>
                                    </strong>

                                </div>
                            </td>

                            <td>
                                KSh <?= number_format((float) $product['price'], 2) ?>
                            </td>

                            <td>
                                <?= h($product['quantity_available']) ?>
                            </td>

                            <td>
                                <span class="status-pill">
                                    <?= h(ucfirst($product['status'])) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </section>

    <footer class="text-center text-secondary small mt-4">
        &copy; <?= date('Y') ?> AgriConnect.
        Connecting farmers to the marketplace.
    </footer>

</main>

<script>
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const overlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        const isOpen = sidebar.classList.toggle('show');

        overlay.classList.toggle('show', isOpen);

        menuToggle.setAttribute('aria-expanded', isOpen);

        menuToggle.innerHTML = isOpen
            ? '<i class="bi bi-x-lg"></i>'
            : '<i class="bi bi-list"></i>';
    }

    menuToggle.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);

    document.querySelectorAll('.sidebar .side-link').forEach(link => {
        link.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.innerHTML = '<i class="bi bi-list"></i>';
        });
    });
</script>

</body>
</html>
