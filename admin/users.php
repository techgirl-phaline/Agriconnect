
<?php
session_start();
require_once "../db.php";

// Allow administrators only
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";
$message_type = "";

// Handle buyer approval and rejection
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!$user_id || !in_array($action, ["approve", "reject"], true)) {
        $message = "Invalid request.";
        $message_type = "danger";
    } else {
        $new_status = $action === "approve" ? "approved" : "rejected";

        $stmt = $conn->prepare(
            "UPDATE users
             SET account_status = ?
             WHERE user_id = ?
               AND role = 'buyer'
               AND account_status IN ('pending', 'approved', 'rejected')"
        );

        $stmt->bind_param("si", $new_status, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "Buyer account has been " . $new_status . ".";
            $message_type = "success";
        } else {
            $message = "No account was changed. Check the buyer's current status.";
            $message_type = "warning";
        }

        $stmt->close();
    }
}

// Fetch all buyers
$result = $conn->query(
    "SELECT user_id, full_name, email, phone, account_status, created_at
     FROM users
     WHERE role = 'buyer'
     ORDER BY
       CASE account_status
         WHEN 'pending' THEN 1
         WHEN 'approved' THEN 2
         WHEN 'rejected' THEN 3
         ELSE 4
       END,
       created_at DESC"
);

$buyers = $result->fetch_all(MYSQLI_ASSOC);

// Calculate buyer statistics
$pending = 0;
$approved = 0;
$rejected = 0;

foreach ($buyers as $buyer) {
    if ($buyer["account_status"] === "pending") {
        $pending++;
    } elseif ($buyer["account_status"] === "approved") {
        $approved++;
    } elseif ($buyer["account_status"] === "rejected") {
        $rejected++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>User Management | AgriConnect Admin</title>

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

        /* LEFT SIDEBAR */
        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
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

        .topbar,
        .content-card,
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

        /* STATISTICS */
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

        /* BUYER TABLE */
        .content-card {
            padding: 22px;
            margin-top: 25px;
        }

        .table th {
            background: #f0f4ed;
            color: var(--dark-green);
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        /* STATUS LABELS */
        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
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

        /* BUTTONS */
        .btn-approve {
            background: var(--green);
            color: white;
        }

        .btn-approve:hover {
            background: var(--dark-green);
            color: white;
        }

        /* FOOTER */
        .footer {
            color: #7a887d;
            text-align: center;
            margin-top: 30px;
            font-size: 13px;
        }

        /* RESPONSIVE DESIGN */
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

            .content-card {
                padding: 12px;
            }

            .topbar {
                padding: 15px;
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

    <a class="side-link" href="farmers.php">
        <i class="bi bi-people me-2"></i>Farmer Approvals
    </a>

    <a class="side-link active" href="users.php">
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
<main class="main">

    <!-- TOP BAR -->
    <div class="topbar">

        <div>
            <h2 class="page-title mb-1">Buyer Management</h2>

            <p class="text-secondary mb-0">
                Review buyer registrations and manage account approval.
            </p>
        </div>

        <span class="fw-semibold">
            <i class="bi bi-person-circle me-1"></i>
            <?= htmlspecialchars($_SESSION["full_name"] ?? "Administrator") ?>
        </span>

    </div>

    <!-- ALERT MESSAGES -->
    <?php if ($message !== ""): ?>

        <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>

            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="text-secondary">Registered Buyers</div>
                <div class="stat-number"><?= count($buyers) ?></div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card pending">
                <div class="text-secondary">Pending</div>
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

    <!-- BUYER MANAGEMENT TABLE -->
    <section class="content-card">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

            <div>
                <h2 class="h5 fw-bold mb-1">Registered Buyers</h2>

                <p class="text-secondary small mb-0">
                    Approve accounts to allow buyers to place orders.
                </p>
            </div>

            <input type="search"
                   id="buyerSearch"
                   class="form-control"
                   style="max-width:300px"
                   placeholder="Search buyers..."
                   aria-label="Search buyers">

        </div>

        <div class="table-responsive">

            <table class="table table-hover" id="buyersTable">

                <thead>
                    <tr>
                        <th>Buyer</th>
                        <th>Contact</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($buyers)): ?>

                    <tr>
                        <td colspan="5" class="text-center py-5">

                            <i class="bi bi-people display-5 text-secondary"></i>

                            <h5 class="mt-3">No buyers registered yet</h5>

                            <p class="text-secondary mb-0">
                                Buyer accounts will appear here after registration.
                            </p>

                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($buyers as $buyer): ?>

                        <?php
                        $status = $buyer["account_status"] ?? "pending";

                        $status_class = in_array(
                            $status,
                            ["pending", "approved", "rejected"],
                            true
                        ) ? $status : "pending";
                        ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($buyer["full_name"]) ?>
                                </strong>

                                <br>

                                <small class="text-secondary">
                                    ID: <?= (int)$buyer["user_id"] ?>
                                </small>
                            </td>

                            <td>
                                <?= htmlspecialchars($buyer["email"]) ?>

                                <br>

                                <small class="text-secondary">
                                    <?= htmlspecialchars($buyer["phone"] ?? "No phone") ?>
                                </small>
                            </td>

                            <td>
                                <?= htmlspecialchars(date(
                                    "d M Y",
                                    strtotime($buyer["created_at"])
                                )) ?>
                            </td>

                            <td>
                                <span class="status status-<?= htmlspecialchars($status_class) ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>

                            <td style="min-width:120px">

                                <?php if ($status !== "approved"): ?>

                                    <form method="POST"
                                          class="mb-2"
                                          onsubmit="return confirm('Approve this buyer account?')">

                                        <input type="hidden"
                                               name="user_id"
                                               value="<?= (int)$buyer["user_id"] ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="approve">

                                        <button type="submit"
                                                class="btn btn-sm btn-approve w-100">

                                            <i class="bi bi-check-circle me-1"></i>
                                            Approve

                                        </button>

                                    </form>

                                <?php endif; ?>

                                <?php if ($status !== "rejected"): ?>

                                    <form method="POST"
                                          onsubmit="return confirm('Reject this buyer account?')">

                                        <input type="hidden"
                                               name="user_id"
                                               value="<?= (int)$buyer["user_id"] ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="reject">

                                        <button type="submit"
                                                class="btn btn-sm btn-outline-danger w-100">

                                            <i class="bi bi-x-circle me-1"></i>
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

    <!-- FOOTER -->
    <footer class="footer">
        AgriConnect Administration &copy; <?= date("Y") ?>
    </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Search registered buyers
document.getElementById("buyerSearch").addEventListener("input", function () {

    const searchText = this.value.toLowerCase();

    document.querySelectorAll("#buyersTable tbody tr").forEach(function (row) {

        row.style.display = row.innerText.toLowerCase().includes(searchText)
            ? ""
            : "none";

    });

});
</script>

</body>
</html>
