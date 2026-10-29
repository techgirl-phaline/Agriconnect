
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

        // Only change accounts belonging to farmers.
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Approvals | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        :root {
            --green: #347344;
            --light-green: #f3f7f0;
            --dark: #233329;
        }

        body {
            margin: 0;
            background: #f5f7f5;
            color: var(--dark);
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }

        .brand {
            color: var(--green);
            font-weight: 800;
        }

        .nav-link {
            color: #526057;
            font-weight: 600;
        }

        .nav-link.active,
        .nav-link:hover {
            color: var(--green);
        }

        .page-header {
            background: #fff;
            padding: 28px 0;
            border-bottom: 1px solid #e8ece8;
        }

        .page-title {
            font-weight: 800;
        }

        .stat-card,
        .content-card {
            background: #fff;
            border: 1px solid #e7ebe7;
            border-radius: 14px;
            padding: 22px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--green);
        }

        .farmer-name {
            font-weight: 700;
        }

        .btn-approve {
            background: var(--green);
            color: white;
        }

        .btn-approve:hover {
            background: #285b35;
            color: white;
        }

        .table th {
            color: #58655c;
            font-size: 13px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
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

        footer {
            color: #788078;
            font-size: 13px;
            padding: 24px 0;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand brand" href="dashboard.php">🌱 AgriConnect</a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminNav"
                aria-controls="adminNav" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="farmers.php">Farmer Approvals</a>
                <a class="nav-link" href="products.php">Listing Approvals</a>
                <a class="nav-link" href="orders.php">Manage Orders</a>
                <a class="nav-link" href="../index.php">Visit Website</a>
                <a class="nav-link text-danger" href="logout.php">Log Out</a>
            </div>
        </div>
    </div>
</nav>

<header class="page-header">
    <div class="container">
        <p class="text-success fw-bold mb-2">ADMINISTRATION PANEL</p>
        <h1 class="page-title h2">Farmer Approvals</h1>
        <p class="text-secondary mb-0">
            Review farmer registrations and manage account approval.
        </p>
    </div>
</header>

<main class="container py-4">

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

        <?php
        $farmers = $result->fetch_all(MYSQLI_ASSOC);

        $pending_count = 0;
        $approved_count = 0;
        $rejected_count = 0;

        foreach ($farmers as $farmer) {
            if ($farmer["account_status"] === "pending") {
                $pending_count++;
            } elseif ($farmer["account_status"] === "approved") {
                $approved_count++;
            } elseif ($farmer["account_status"] === "rejected") {
                $rejected_count++;
            }
        }
        ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card h-100">
                    <p class="text-secondary mb-1">Registered Farmers</p>
                    <div class="stat-number"><?= count($farmers) ?></div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card h-100">
                    <p class="text-secondary mb-1">Pending Approval</p>
                    <div class="stat-number"><?= $pending_count ?></div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card h-100">
                    <p class="text-secondary mb-1">Approved</p>
                    <div class="stat-number"><?= $approved_count ?></div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card h-100">
                    <p class="text-secondary mb-1">Rejected</p>
                    <div class="stat-number"><?= $rejected_count ?></div>
                </div>
            </div>
        </div>

        <section class="content-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="h5 fw-bold mb-1">Registered Farmers</h2>
                    <p class="text-secondary small mb-0">
                        Review each account before making a decision.
                    </p>
                </div>

                <input type="search" id="farmerSearch"
                       class="form-control" style="max-width:300px"
                       placeholder="Search farmers..." aria-label="Search farmers">
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
                                        <small><?= htmlspecialchars($farmer["farm_location"]) ?></small>
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
                                    <?= htmlspecialchars($farmer["email"]) ?>
                                    <br>
                                    <small class="text-secondary">
                                        <?= htmlspecialchars($farmer["phone"] ?? "No phone") ?>
                                    </small>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        date("d M Y", strtotime($farmer["created_at"]))
                                    ) ?>
                                </td>

                                <td>
                                    <span class="status status-<?= htmlspecialchars($status_class) ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($status === "pending" || $status === "rejected"): ?>
                                        <form method="POST" class="mb-2"
                                              onsubmit="return confirm('Approve this farmer account?');">
                                            <input type="hidden" name="user_id"
                                                   value="<?= (int)$farmer["user_id"] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-sm btn-approve w-100" type="submit">
                                                Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($status === "pending" || $status === "approved"): ?>
                                        <form method="POST"
                                              onsubmit="return confirm('Reject this farmer account?');">
                                            <input type="hidden" name="user_id"
                                                   value="<?= (int)$farmer["user_id"] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="btn btn-sm btn-outline-danger w-100" type="submit">
                                                Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php if (!empty($farmer["bio"])): ?>
                                <div class="modal fade"
                                     id="bio<?= (int)$farmer["user_id"] ?>"
                                     tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <?= htmlspecialchars($farmer["farm_name"] ?: "Farm Description") ?>
                                                </h5>
                                                <button type="button" class="btn-close"
                                                        data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
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
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer class="text-center">
    AgriConnect Administration &copy; <?= date("Y") ?>
</footer>

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
