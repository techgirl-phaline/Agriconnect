
<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['farmer_id']) || ($_SESSION['role'] ?? '') !== 'farmer') {
    header("Location: login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$farmer_id = (int) $_SESSION['farmer_id'];
$message = "";
$message_type = "success";

if (empty($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}

// Load farmer account
$stmt = $conn->prepare(
    "SELECT full_name, email, phone, password
     FROM users
     WHERE user_id = ? AND role = 'farmer'
     LIMIT 1"
);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$farmer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$farmer) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$full_name = $farmer['full_name'];
$email = $farmer['email'];
$phone = $farmer['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(
        $_SESSION['settings_csrf'],
        $_POST['csrf_token'] ?? ''
    )) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "danger";
    } else {
        $action = $_POST['action'] ?? '';

        // Update account details
        if ($action === 'update_account') {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if ($full_name === '' || $email === '') {
                $message = "Full name and email are required.";
                $message_type = "danger";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address.";
                $message_type = "danger";
            } elseif (strlen($full_name) > 100 ||
                      strlen($email) > 100 ||
                      strlen($phone) > 20) {
                $message = "One or more fields are too long.";
                $message_type = "danger";
            } else {
                $check = $conn->prepare(
                    "SELECT user_id FROM users
                     WHERE email = ? AND user_id != ? LIMIT 1"
                );
                $check->bind_param("si", $email, $farmer_id);
                $check->execute();
                $email_exists = $check->get_result()->num_rows > 0;
                $check->close();

                if ($email_exists) {
                    $message = "That email address is already registered.";
                    $message_type = "danger";
                } else {
                    $update = $conn->prepare(
                        "UPDATE users
                         SET full_name = ?, email = ?, phone = ?
                         WHERE user_id = ? AND role = 'farmer'"
                    );
                    $update->bind_param(
                        "sssi",
                        $full_name,
                        $email,
                        $phone,
                        $farmer_id
                    );

                    if ($update->execute()) {
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['farmer_name'] = $full_name;
                        $_SESSION['email'] = $email;

                        $message = "Your account details have been updated.";
                    } else {
                        $message = "Could not update your details. Please try again.";
                        $message_type = "danger";
                    }
                    $update->close();
                }
            }
        }

        // Change password
        elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (!password_verify($current_password, $farmer['password'])) {
                $message = "Your current password is incorrect.";
                $message_type = "danger";
            } elseif (strlen($new_password) < 8) {
                $message = "Your new password must contain at least 8 characters.";
                $message_type = "danger";
            } elseif ($new_password !== $confirm_password) {
                $message = "The new passwords do not match.";
                $message_type = "danger";
            } elseif ($new_password === $current_password) {
                $message = "Choose a password different from your current password.";
                $message_type = "danger";
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare(
                    "UPDATE users SET password = ?
                     WHERE user_id = ? AND role = 'farmer'"
                );
                $update->bind_param("si", $new_hash, $farmer_id);

                if ($update->execute()) {
                    $message = "Your password has been changed successfully.";
                    $farmer['password'] = $new_hash;
                } else {
                    $message = "Could not change your password. Please try again.";
                    $message_type = "danger";
                }
                $update->close();
            }
        } else {
            $message = "Unknown request.";
            $message_type = "danger";
        }

        // Refresh account details after submission
        $stmt = $conn->prepare(
            "SELECT full_name, email, phone, password
             FROM users WHERE user_id = ? AND role = 'farmer' LIMIT 1"
        );
        $stmt->bind_param("i", $farmer_id);
        $stmt->execute();
        $farmer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($farmer) {
            $full_name = $farmer['full_name'];
            $email = $farmer['email'];
            $phone = $farmer['phone'] ?? '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | AgriConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        :root {
            --green: #39734d;
            --light-green: #eaf4e9;
            --cream: #faf8f1;
            --text: #263b2d;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: var(--green);
        }

        .navbar-brand {
            color: white;
            font-weight: bold;
        }

        .page-header {
            background: var(--light-green);
            padding: 32px 0;
        }

        .settings-card {
            background: white;
            border: 1px solid #e5ebdf;
            border-radius: 16px;
            padding: 25px;
            height: 100%;
            box-shadow: 0 5px 18px rgba(30, 60, 35, 0.05);
        }

        .section-icon {
            background: var(--light-green);
            color: var(--green);
            width: 45px;
            height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 21px;
            margin-bottom: 15px;
        }

        .form-label {
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            padding: 11px;
            border-radius: 9px;
        }

        .btn-green {
            background: var(--green);
            color: white;
            border: none;
            padding: 11px 18px;
            border-radius: 9px;
        }

        .btn-green:hover {
            background: #28583a;
            color: white;
        }

        .back-link {
            color: var(--green);
            text-decoration: none;
            font-weight: 600;
        }

        footer {
            color: #6c786d;
            padding: 25px 0;
            text-align: center;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="../index.php">🌿 AgriConnect</a>
        <a href="Dashboard.php" class="btn btn-outline-light btn-sm">
            Back to Dashboard
        </a>
    </div>
</nav>

<header class="page-header">
    <div class="container">
        <h1 class="fw-bold">Account Settings</h1>
        <p class="mb-0">
            Manage your account information and keep your password secure.
        </p>
    </div>
</header>

<main class="container py-4">

    <a href="Dashboard.php" class="back-link">← Back to Dashboard</a>

    <div class="mt-4">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($message_type) ?> alert-dismissible fade show">
                <?= e($message) ?>
                <button type="button" class="btn-close"
                        data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4 mt-1">

        <!-- Account information -->
        <div class="col-lg-6">
            <div class="settings-card">
                <div class="section-icon">👤</div>
                <h4 class="fw-bold">Personal Information</h4>
                <p class="text-secondary">
                    Update the details associated with your farmer account.
                </p>

                <form method="POST">
                    <input type="hidden" name="csrf_token"
                           value="<?= e($_SESSION['settings_csrf']) ?>">
                    <input type="hidden" name="action" value="update_account">

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name"
                               class="form-control"
                               value="<?= e($full_name) ?>"
                               maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email"
                               class="form-control"
                               value="<?= e($email) ?>"
                               maxlength="100" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone"
                               class="form-control"
                               value="<?= e($phone) ?>"
                               maxlength="20">
                    </div>

                    <button type="submit" class="btn btn-green w-100">
                        Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Password settings -->
        <div class="col-lg-6">
            <div class="settings-card">
                <div class="section-icon">🔒</div>
                <h4 class="fw-bold">Change Password</h4>
                <p class="text-secondary">
                    Use your current password to choose a new one.
                </p>

                <form method="POST">
                    <input type="hidden" name="csrf_token"
                           value="<?= e($_SESSION['settings_csrf']) ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password"
                               class="form-control" autocomplete="current-password"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password"
                               class="form-control" minlength="8"
                               autocomplete="new-password" required>
                        <small class="text-secondary">
                            Use at least 8 characters.
                        </small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password"
                               class="form-control" minlength="8"
                               autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-green w-100">
                        Update Password
                    </button>
                </form>
            </div>
        </div>

    </div>
</main>

<footer>
    <div class="container">
        © <?= date('Y') ?> AgriConnect. Growing together.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
