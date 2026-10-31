
<?php
session_start();
require_once "../db.php";

// Protect this page: administrators only.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit;
}

$message = "";
$message_type = "";

// Process farmer approval or rejection.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $farmer_user_id = filter_input(
        INPUT_POST,
        "user_id",
        FILTER_VALIDATE_INT
    );

    $action = $_POST["action"] ?? "";

    if (!$farmer_user_id || !in_array($action, ["approve", "reject"], true)) {
        $message = "Invalid request.";
        $message_type = "danger";
    } else {
        $new_status = ($action === "approve") ? "approved" : "rejected";

        $stmt = $conn->prepare(
            "UPDATE users
             SET account_status = ?
             WHERE user_id = ? AND role = 'farmer'"
        );

        if ($stmt) {
            $stmt->bind_param("si", $new_status, $farmer_user_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Farmer account has been " . $new_status . ".";
                $message_type = "success";
            } else {
                $message = "No farmer account was updated. It may already have this status.";
                $message_type = "warning";
            }

            $stmt->close();
        } else {
            $message = "Database error while updating the farmer.";
            $message_type = "danger";
        }
    }
}

// Fetch farmer accounts and their profile details.
$sql = "SELECT
            u.user_id,
            u.full_name,
            u.email,
            u.phone,
            u.account_status,
            u.created_at,
            fp.farm_name,
            fp.county,
            fp.sub_county,
            fp.farm_location,
            fp.bio
        FROM users u
        LEFT JOIN farmer_profiles fp ON fp.user_id = u.user_id
        WHERE u.role = 'farmer'
        ORDER BY
            CASE u.account_status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
                ELSE 4
            END,
            u.created_at DESC";

$result = $conn->query($sql);
$database_error = ($result === false);

$farmers = [];
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

if (!$database_error) {
    $farmers = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($farmers as $farmer) {
        if ($farmer["account_status"] === "pending") {
            $pending_count++;
        } elseif ($farmer["account_status"] === "approved") {
            $approved_count++;
        } elseif ($farmer["account_status"] === "rejected") {
            $rejected_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Approvals | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

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

        /* LEFT SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 255px;
            padding: 25px 16px;
            background: var(--dark-green);
            color: white;
            overflow-y: auto;
            z-index: 1000;
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
            transition: 0.2s;
        }

        .side-link:hover,
        .side-link.active {
            background: var(--yellow);
            color: var(--dark-green);
            font-weight: bold;
        }

        /* MAIN CONTENT */
        .main {
            margin-left: 255px;
            padding: 30px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 28px;
        }

        .page-title {
            font-weight: 800;
            margin-bottom: 5px;
        }

        .welcome-text {
            color: #718078;
            font-size: 14px;
        }

        .admin-label {
            background: #e4eddf;
            color: var(--green);
            padding: 9px 15px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: bold;
        }

        /* STAT CARDS */
        .stat-card,
        .content-card {
            background: white;
            border: 1px solid #e7ebe7;
            border-radius: 14px;
            padding: 22px;
        }

        .stat-card {
            height: 100%;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--green);
        }

        .stat-icon {
            font-size: 23px;
            color: var(--green);
            margin-bottom: 12px;
        }

        .farmer-name {
            font-weight: 700;
        }

        /* TABLE */
        .table th {
            color: #58655c;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
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

        .search-box {
            max-width: 300px;
        }

        footer {
            color: #788078;
            font-size: 13px;
            padding: 25px 0;
            text-align: center;
        }

        /* MOBILE */
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

            .search-box {
                max-width: 100%;
                width: 100%;
            }

            .stat-card,
            .content-card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<!-- LEFT SIDEBAR -->
<aside class="sidebar">

    <div class="brand">🌱 AgriConnect</div>
    <div class="sidebar-subtitle">Administration Panel</div>

    <a class="side-link" href="dashboard.php">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </a>

    <a class="side-link active" href="farmers.php">
        <i class="bi bi-people me-2"></i>Farmer Approvals
    </a>

    <a class="side-link" href="users.php">
        <i class="bi bi-person-check me-2"></i>User Management
    </a>

    <a class="side-link" href="products.php">
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

<!-- MAIN CONTENT -->
<div class="main">

    <div class="topbar">
        <div>
            <h1 class="page-title h3">Farmer Approvals</h1>
            <p class="welcome-text mb-0">
                Review farmer registrations and manage account approval.
            </p>
        </div>

        <div class="admin-label">
            <i class="bi bi-shield-check me-1"></i>
            Administrator
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($database_error): ?>

        <div class="alert alert-danger">
            Could not load farmer records. Check your database tables and column names.
        </div>

    <?php else: ?>

        <!-- STATISTICS -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <p class="text-secondary mb-1">Registered Farmers</p>
                    <div class="stat-number"><?= count($farmers) ?></div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <p class="text-secondary mb-1">Pending Approval</p>
                    <div class="stat-number"><?= $pending_count ?></div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <p class="text-secondary mb-1">Approved</p>
                    <div class="stat-number"><?= $approved_count ?></div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <p class="text-secondary mb-1">Rejected</p>
                    <div class="stat-number"><?= $rejected_count ?></div>
                </div>
            </div>

        </div>

        <!-- FARMER RECORDS -->
        <section class="content-card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

                <div>
                    <h2 class="h5 fw-bold mb-1">Registered Farmers</h2>
                    <p class="text-secondary small mb-0">
                        Review each account before making a decision.
                    </p>
                </div>

                <input type="search"
                       id="farmerSearch"
                       class="form-control search-box"
                       placeholder="Search farmers..."
                       aria-label="Search farmers">

            </div>

            <div class="table-responsive">

                <table class="table table-hover" id="farmersTable">

                    <thead>
                        <tr>
                            <th>Farmer</th>
                            <th>Farm Details</th>
                            <th>Contact</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($farmers)): ?>

                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <h5>No farmers registered yet</h5>
                                <p class="text-secondary mb-0">
                                    Farmer accounts will appear here after registration.
                                </p>
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($farmers as $farmer): ?>

                            <?php
                            $status = $farmer["account_status"] ?? "pending";

                            $status_class = in_array(
                                $status,
                                ["pending", "approved", "rejected"],
                                true
                            ) ? $status : "pending";
                            ?>

                            <tr>

                                <td>
                                    <div class="farmer-name">
                                        <?= htmlspecialchars($farmer["full_name"] ?? "") ?>
                                    </div>

                                    <small class="text-secondary">
                                        ID: <?= (int)$farmer["user_id"] ?>
                                    </small>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($farmer["farm_name"] ?: "Not provided") ?>
                                    </strong>

                                    <br>

                                    <small class="text-secondary">
                                        <?= htmlspecialchars(
                                            implode(", ", array_filter([
                                                $farmer["sub_county"] ?? "",
                                                $farmer["county"] ?? ""
                                            ])) ?: "Location not provided"
                                        ) ?>
                                    </small>

                                    <?php if (!empty($farmer["farm_location"])): ?>
                                        <br>
                                        <small>
                                            <?= htmlspecialchars($farmer["farm_location"]) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (!empty($farmer["bio"])): ?>
                                        <br>
                                        <button type="button"
                                                class="btn btn-sm btn-link p-0"
                                                data-bs-toggle="modal"
                                                data-bs-target="#bio<?= (int)$farmer["user_id"] ?>">
                                            View farm description
                                        </button>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($farmer["email"] ?? "") ?>

                                    <br>

                                    <small class="text-secondary">
                                        <?= htmlspecialchars($farmer["phone"] ?? "No phone") ?>
                                    </small>
                                </td>

                                <td>
                                    <?= !empty($farmer["created_at"])
                                        ? htmlspecialchars(date("d M Y", strtotime($farmer["created_at"])))
                                        : "Not available" ?>
                                </td>

                                <td>
                                    <span class="status status-<?= htmlspecialchars($status_class) ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($status === "pending" || $status === "rejected"): ?>

                                        <form method="POST"
                                              class="mb-2"
                                              onsubmit="return confirm('Approve this farmer account?');">

                                            <input type="hidden"
                                                   name="user_id"
                                                   value="<?= (int)$farmer["user_id"] ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="approve">

                                            <button class="btn btn-sm btn-approve w-100"
                                                    type="submit">
                                                Approve
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                    <?php if ($status === "pending" || $status === "approved"): ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Reject this farmer account?');">

                                            <input type="hidden"
                                                   name="user_id"
                                                   value="<?= (int)$farmer["user_id"] ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="reject">

                                            <button class="btn btn-sm btn-outline-danger w-100"
                                                    type="submit">
                                                Reject
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

        <!-- FARM DESCRIPTION MODALS -->
        <?php foreach ($farmers as $farmer): ?>

            <?php if (!empty($farmer["bio"])): ?>

                <div class="modal fade"
                     id="bio<?= (int)$farmer["user_id"] ?>"
                     tabindex="-1"
                     aria-hidden="true">

                    <div class="modal-dialog modal-dialog-centered">

                        <div class="modal-content">

                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <?= htmlspecialchars($farmer["farm_name"] ?: "Farm Description") ?>
                                </h5>

                                <button type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Close">
                                </button>
                            </div>

                            <div class="modal-body">
                                <?= nl2br(htmlspecialchars($farmer["bio"])) ?>
                            </div>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        <?php endforeach; ?>

    <?php endif; ?>

    <footer>
        AgriConnect Administration &copy; <?= date("Y") ?>
    </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById("farmerSearch").addEventListener("input", function () {

    const searchText = this.value.toLowerCase();
    const rows = document.querySelectorAll("#farmersTable tbody tr");

    rows.forEach(function (row) {
        row.style.display = row.innerText.toLowerCase().includes(searchText)
            ? ""
            : "none";
    });

});
</script>

</body>
</html>
