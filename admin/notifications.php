
<?php
session_start();

require_once __DIR__ . '/../db.php';

// Check admin login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get all notifications
$sql = "SELECT * FROM admin_notifications
        ORDER BY created_at DESC";

$result = $conn->query($sql);

// Count unread notifications
$count_sql = "SELECT COUNT(*) AS unread_count
              FROM admin_notifications
              WHERE is_read = 0";

$count_result = $conn->query($count_sql);
$unread_count = (int) $count_result->fetch_assoc()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | AgriConnect Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7f1;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: #123d25;
        }

        .navbar-brand {
            color: #ffffff;
            font-weight: bold;
        }

        .notification-card {
            background: #ffffff;
            border: 1px solid #e5ebdf;
            border-left: 5px solid #d9a900;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .notification-card.read {
            border-left-color: #198754;
        }

        .notification-title {
            color: #174b2d;
            font-weight: bold;
        }

        .btn-green {
            background: #174b2d;
            color: white;
        }

        .btn-green:hover {
            background: #0e321d;
            color: white;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg px-3">
    <a class="navbar-brand" href="dashboard.php">
        AgriConnect | Admin
    </a>

    <div class="ms-auto">
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
            Back to Dashboard
        </a>
    </div>
</nav>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Notifications</h2>
            <p class="text-muted mb-0">
                Stay updated on activities happening on AgriConnect.
            </p>
        </div>

        <span class="badge bg-warning text-dark fs-6">
            <?= $unread_count ?> Unread
        </span>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>

        <?php while ($notification = $result->fetch_assoc()): ?>

            <div class="notification-card <?= $notification['is_read'] ? 'read' : '' ?>">

                <div class="d-flex justify-content-between align-items-start gap-3">

                    <div>
                        <h5 class="notification-title mb-2">
                            <?= htmlspecialchars($notification['title']) ?>
                        </h5>

                        <p class="text-muted mb-2">
                            <?= htmlspecialchars($notification['message']) ?>
                        </p>

                        <small class="text-muted">
                            <?= date('d M Y, h:i A', strtotime($notification['created_at'])) ?>
                        </small>
                    </div>

                    <?php if (!$notification['is_read']): ?>
                        <span class="badge bg-success">New</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Read</span>
                    <?php endif; ?>

                </div>

                <div class="mt-3">
                    <a href="<?= htmlspecialchars($notification['action_url']) ?>"
                       class="btn btn-green btn-sm">
                        Review Activity
                    </a>
                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="text-center bg-white rounded-4 shadow-sm p-5">
            <h5 class="fw-bold">No Notifications Yet</h5>
            <p class="text-muted mb-0">
                New farmer registrations, buyer registrations,
                produce listings and orders will appear here.
            </p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>
