
<?php
require_once "../db.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($full_name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid name and email address.";
        $message_type = "danger";
    } elseif (strlen($password) < 10) {
        $message = "Password must contain at least 10 characters.";
        $message_type = "danger";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "danger";
    } else {
        // Check whether an admin already exists.
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE role = 'admin' LIMIT 1"
        );

        if (!$check) {
            $message = "Database error. Please check your users table.";
            $message_type = "danger";
        } else {
            $check->execute();
            $admin_exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($admin_exists) {
                $message = "An admin account already exists. Setup is disabled.";
                $message_type = "danger";
            } else {
                // Check whether the email is already registered.
                $check_email = $conn->prepare(
                    "SELECT user_id FROM users WHERE email = ? LIMIT 1"
                );
                $check_email->bind_param("s", $email);
                $check_email->execute();
                $email_exists = $check_email->get_result()->num_rows > 0;
                $check_email->close();

                if ($email_exists) {
                    $message = "This email is already registered. Use another email.";
                    $message_type = "danger";
                } else {
                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $account_status = "approved";

                    $stmt = $conn->prepare(
                        "INSERT INTO users
                        (full_name, email, password, role, account_status)
                        VALUES (?, ?, ?, 'admin', ?)"
                    );

                    if (!$stmt) {
                        $message = "Could not prepare account creation.";
                        $message_type = "danger";
                    } else {
                        $stmt->bind_param(
                            "ssss",
                            $full_name,
                            $email,
                            $hashed_password,
                            $account_status
                        );

                        if ($stmt->execute()) {
                            $message = "Admin account created successfully! Delete create-admin.php now.";
                            $message_type = "success";
                        } else {
                            $message = "Account creation failed. Please try again.";
                            $message_type = "danger";
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Check whether setup is still available.
$setup_available = false;
$result = $conn->query(
    "SELECT user_id FROM users WHERE role = 'admin' LIMIT 1"
);

if ($result && $result->num_rows === 0) {
    $setup_available = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin | AgriConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <style>
        body {
            background: #f3f7f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: Arial, sans-serif;
        }
        .admin-card {
            width: 100%;
            max-width: 480px;
            background: #fff;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }
        .brand {
            color: #347344;
            font-weight: 800;
        }
        .form-control {
            padding: 12px;
        }
        .btn-success {
            background: #347344;
            border-color: #347344;
            padding: 12px;
        }
    </style>
</head>
<body>
<div class="admin-card">
    <h2 class="brand text-center mb-2">AgriConnect</h2>
    <p class="text-center text-secondary mb-4">Create your administrator account</p>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($setup_available): ?>
        <form method="POST">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="full_name"
                       name="full_name" maxlength="100" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Admin Email</label>
                <input type="email" class="form-control" id="email"
                       name="email" maxlength="150" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password"
                       name="password" minlength="10" autocomplete="new-password"
                       required>
                <small class="text-secondary">Use at least 10 characters.</small>
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label">
                    Confirm Password
                </label>
                <input type="password" class="form-control"
                       id="confirm_password" name="confirm_password"
                       minlength="10" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-success w-100">
                Create Admin Account
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">
            Admin setup is closed because an admin account already exists.
        </div>
    <?php endif; ?>

    <p class="text-center text-secondary small mt-4 mb-0">
        AgriConnect Administration
    </p>
</div>
</body>
</html>
