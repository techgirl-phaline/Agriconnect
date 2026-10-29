
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

$message = "";
$message_type = "";

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
<title>User Management | AgriConnect</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">
<style>
body {
    background: #f5f7f5;
    color: #233329;
    font-family: Arial, sans-serif;
}
.navbar { background: #fff; box-shadow: 0 2px 10px #0000000d; }
.brand { color: #347344; font-weight: 800; text-decoration: none; }
.nav-link { color: #526057; font-weight: 600; }
.nav-link:hover, .nav-link.active { color: #347344; }
.page-header { background: #fff; padding: 28px 0; border-bottom: 1px solid #e8ece8; }
.page-title { font-weight: 800; }
.stat-card, .content-card {
    background: #fff;
    border: 1px solid #e7ebe7;
    border-radius: 14px;
    padding: 22px;
}
.stat-number { font-size: 28px; font-weight: 800; color: #347344; }
.table th {
    color: #58655c;
    font-size: 12px;
    text-transform: uppercase;
    white-space: nowrap;
}
.table td { vertical-align: middle; }
.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}
.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d1e7dd; color: #0f5132; }
.status-rejected { background: #f8d7da; color: #842029; }
.btn-approve { background: #347344; color: #fff; }
.btn-approve:hover { background: #285b35; color: #fff; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
<div class="container">
    <a class="navbar-brand brand" href="dashboard.php">🌱 AgriConnect</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#adminNav" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="farmers.php">Farmer Approvals</a>
            <a class="nav-link active" href="users.php">User Management</a>
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
    <h1 class="page-title h2">Buyer Management</h1>
    <p class="text-secondary mb-0">
        Review buyer registrations and manage account approval.
    </p>
</div>
</header>

<main class="container py-4">

<?php if ($message !== ""): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"
                aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <p class="text-secondary mb-1">Registered Buyers</p>
            <div class="stat-number"><?= count($buyers) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <p class="text-secondary mb-1">Pending</p>
            <div class="stat-number"><?= $pending ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <p class="text-secondary mb-1">Approved</p>
            <div class="stat-number"><?= $approved ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <p class="text-secondary mb-1">Rejected</p>
            <div class="stat-number"><?= $rejected ?></div>
        </div>
    </div>
</div>

<section class="content-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="h5 fw-bold mb-1">Registered Buyers</h2>
            <p class="text-secondary small mb-0">
                Approve accounts to allow buyers to place orders.
            </p>
        </div>
        <input type="search" id="buyerSearch" class="form-control"
               style="max-width:300px" placeholder="Search buyers..."
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
                        <h5>No buyers registered yet</h5>
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
                        $status, ["pending", "approved", "rejected"], true
                    ) ? $status : "pending";
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($buyer["full_name"]) ?></strong><br>
                            <small class="text-secondary">
                                ID: <?= (int)$buyer["user_id"] ?>
                            </small>
                        </td>
                        <td>
                            <?= htmlspecialchars($buyer["email"]) ?><br>
                            <small class="text-secondary">
                                <?= htmlspecialchars($buyer["phone"] ?? "No phone") ?>
                            </small>
                        </td>
                        <td>
                            <?= htmlspecialchars(date("d M Y", strtotime($buyer["created_at"]))) ?>
                        </td>
                        <td>
                            <span class="status status-<?= htmlspecialchars($status_class) ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                        </td>
                        <td style="min-width:120px">
                            <?php if ($status !== "approved"): ?>
                                <form method="POST" class="mb-2"
                                      onsubmit="return confirm('Approve this buyer account?')">
                                    <input type="hidden" name="user_id"
                                           value="<?= (int)$buyer["user_id"] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-approve w-100">
                                        Approve
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($status !== "rejected"): ?>
                                <form method="POST"
                                      onsubmit="return confirm('Reject this buyer account?')">
                                    <input type="hidden" name="user_id"
                                           value="<?= (int)$buyer["user_id"] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
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
</main>

<footer class="text-center text-secondary py-4">
    AgriConnect Administration &copy; <?= date("Y") ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById("buyerSearch").addEventListener("input", function () {
    const searchText = this.value.toLowerCase();
    document.querySelectorAll("#buyersTable tbody tr").forEach(function (row) {
        row.style.display = row.innerText.toLowerCase().includes(searchText) ? "" : "none";
    });
});
</script>
</body>
</html>
