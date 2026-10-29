
<?php
session_start();
require_once "../db.php";

// Allow administrators only
if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit;
}

$message = "";
$message_type = "success";

// Handle approval and rejection
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $product_id = (int) ($_POST["product_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($product_id > 0 && in_array($action, ["approve", "reject"], true)) {
        $approval_status = ($action === "approve") ? "approved" : "rejected";

        $stmt = $conn->prepare(
            "UPDATE products
             SET approval_status = ?, updated_at = NOW()
             WHERE product_id = ?"
        );

        $stmt->bind_param("si", $approval_status, $product_id);

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            $message = ($action === "approve")
                ? "Produce listing approved successfully."
                : "Produce listing rejected.";
        } else {
            $message = "Could not update the listing. Please try again.";
            $message_type = "danger";
        }

        $stmt->close();
    } else {
        $message = "Invalid request.";
        $message_type = "danger";
    }
}

// Fetch all produce listings
$sql = "SELECT
            p.product_id,
            p.product_name,
            p.description,
            p.price,
            p.unit,
            p.quantity_available,
            p.image,
            p.location,
            p.status,
            p.approval_status,
            p.created_at,
            c.category_name,
            u.full_name AS farmer_name,
            u.email AS farmer_email
        FROM products p
        INNER JOIN categories c
            ON p.category_id = c.category_id
        INNER JOIN farmer_profiles fp
            ON p.farmer_id = fp.farmer_id
        INNER JOIN users u
            ON fp.user_id = u.user_id
        ORDER BY
            CASE p.approval_status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
            END,
            p.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Could not load listings: " . htmlspecialchars($conn->error));
}

$total = $result->num_rows;
$pending = 0;
$approved = 0;
$rejected = 0;

$listings = [];

while ($row = $result->fetch_assoc()) {
    $listings[] = $row;

    if ($row["approval_status"] === "pending") {
        $pending++;
    } elseif ($row["approval_status"] === "approved") {
        $approved++;
    } elseif ($row["approval_status"] === "rejected") {
        $rejected++;
    }
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Listing Approvals | AgriConnect Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        :root {
            --dark-green: #173D2A;
            --green: #245638;
            --yellow: #F5D547;
            --background: #F5F7F2;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: #26382e;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 255px;
            padding: 25px 16px;
            background: var(--dark-green);
            color: white;
            overflow-y: auto;
        }

        .brand {
            color: var(--yellow);
            font-size: 23px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .sidebar-subtitle {
            color: #d3dfd5;
            font-size: 13px;
            margin-bottom: 30px;
        }

        .side-link {
            display: block;
            padding: 12px 13px;
            margin-bottom: 7px;
            color: #eef4ee;
            text-decoration: none;
            border-radius: 8px;
        }

        .side-link:hover,
        .side-link.active {
            background: var(--yellow);
            color: var(--dark-green);
            font-weight: bold;
        }

        .main {
            margin-left: 255px;
            padding: 30px;
        }

        .topbar,
        .panel,
        .stat-card {
            background: white;
            border-radius: 13px;
            box-shadow: 0 3px 14px rgba(0,0,0,.05);
        }

        .topbar {
            padding: 19px 22px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .page-title {
            color: var(--dark-green);
            font-weight: 700;
        }

        .stat-card {
            padding: 20px;
            height: 100%;
            border-left: 4px solid var(--green);
        }

        .stat-card.pending {
            border-left-color: #e5b400;
        }

        .stat-card.approved {
            border-left-color: #198754;
        }

        .stat-card.rejected {
            border-left-color: #dc3545;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: var(--dark-green);
        }

        .panel {
            padding: 22px;
            margin-top: 25px;
        }

        .product-image {
            width: 90px;
            height: 75px;
            object-fit: cover;
            border-radius: 8px;
            background: #edf1e9;
        }

        .status {
            display: inline-block;
            border-radius: 30px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fff3cd;
            color: #795b00;
        }

        .status-approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-rejected {
            background: #f8d7da;
            color: #842029;
        }

        .btn-approve {
            background: var(--green);
            color: white;
        }

        .btn-approve:hover {
            background: var(--dark-green);
            color: white;
        }

        .btn-reject {
            background: #fff;
            color: #b02a37;
            border: 1px solid #b02a37;
        }

        .btn-reject:hover {
            background: #b02a37;
            color: white;
        }

        .table th {
            background: #f0f4ed;
            color: var(--dark-green);
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 72px;
                padding: 20px 8px;
            }

            .brand {
                font-size: 13px;
                overflow-wrap: anywhere;
            }

            .sidebar-subtitle {
                display: none;
            }

            .side-link {
                font-size: 0;
                text-align: center;
                padding: 13px 5px;
            }

            .side-link i {
                font-size: 20px;
            }

            .main {
                margin-left: 72px;
                padding: 15px;
            }

            .panel {
                padding: 12px;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">🌱 AgriConnect</div>
    <div class="sidebar-subtitle">Administration Panel</div>

    <a class="side-link" href="dashboard.php">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </a>

    <a class="side-link" href="farmers.php">
        <i class="bi bi-people me-2"></i>Farmer Approvals
    </a>

    <a class="side-link active" href="products.php">
        <i class="bi bi-basket me-2"></i>Listing Approvals
    </a>

    <a class="side-link" href="orders.php">
        <i class="bi bi-box-seam me-2"></i>Manage Orders
    </a>

    <a class="side-link" href="../index.php">
        <i class="bi bi-house me-2"></i>Visit Website
    </a>

    <a class="side-link" href="logout.php">
        <i class="bi bi-box-arrow-right me-2"></i>Log Out
    </a>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <h2 class="page-title mb-1">Listing Approvals</h2>
            <p class="text-secondary mb-0">
                Review and manage farmer produce listings.
            </p>
        </div>
        <span class="fw-semibold">
            <i class="bi bi-person-circle me-1"></i>
            <?= e($_SESSION["full_name"] ?? "Administrator") ?>
        </span>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= e($message_type) ?> alert-dismissible fade show">
            <?= e($message) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="text-secondary">Total Listings</div>
                <div class="stat-number"><?= $total ?></div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card pending">
                <div class="text-secondary">Awaiting Approval</div>
                <div class="stat-number"><?= $pending ?></div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card approved">
                <div class="text-secondary">Approved</div>
                <div class="stat-number"><?= $approved ?></div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card rejected">
                <div class="text-secondary">Rejected</div>
                <div class="stat-number"><?= $rejected ?></div>
            </div>
        </div>
    </div>

    <section class="panel">
        <h5 class="page-title mb-3">All Produce Listings</h5>

        <?php if (count($listings) === 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-basket display-4 text-secondary"></i>
                <h5 class="mt-3">No produce listings yet</h5>
                <p class="text-secondary">
                    Listings submitted by farmers will appear here.
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Produce</th>
                        <th>Farmer</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Approval</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($listings as $item): ?>
                        <tr>
                            <td>
                                <?php
                                $image_path = "../uploads/products/" . basename($item["image"] ?? "");
                                ?>
                                <?php if (!empty($item["image"]) && is_file(__DIR__ . "/../uploads/products/" . basename($item["image"]))): ?>
                                    <img class="product-image mb-2"
                                         src="<?= e($image_path) ?>"
                                         alt="<?= e($item["product_name"]) ?>">
                                <?php else: ?>
                                    <div class="product-image d-flex align-items-center justify-content-center mb-2">
                                        <i class="bi bi-image fs-3 text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="fw-bold"><?= e($item["product_name"]) ?></div>
                                <small class="text-secondary">
                                    <?= e($item["description"]) ?>
                                </small>
                            </td>

                            <td>
                                <div class="fw-semibold"><?= e($item["farmer_name"]) ?></div>
                                <small class="text-secondary"><?= e($item["farmer_email"]) ?></small>
                            </td>

                            <td><?= e($item["category_name"]) ?></td>
                            <td class="fw-bold">KSh <?= number_format((float)$item["price"], 2) ?>/<?= e($item["unit"]) ?></td>
                            <td><?= number_format((float)$item["quantity_available"], 2) ?> <?= e($item["unit"]) ?></td>
                            <td><?= e($item["location"]) ?></td>

                            <td>
                                <span class="status status-<?= e($item["approval_status"]) ?>">
                                    <?= e($item["approval_status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($item["approval_status"] === "pending"): ?>
                                    <form method="POST" class="d-grid gap-2">
                                        <input type="hidden" name="product_id"
                                               value="<?= (int)$item["product_id"] ?>">
                                        <button class="btn btn-approve btn-sm"
                                                type="submit" name="action"
                                                value="approve">
                                            <i class="bi bi-check-circle me-1"></i>Approve
                                        </button>
                                        <button class="btn btn-reject btn-sm"
                                                type="submit" name="action"
                                                value="reject"
                                                onclick="return confirm('Are you sure you want to reject this listing?');">
                                            <i class="bi bi-x-circle me-1"></i>Reject
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-secondary small">No action needed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <p class="text-secondary text-center mt-4 small">
        AgriConnect Administration &copy; <?= date("Y") ?>
    </p>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
