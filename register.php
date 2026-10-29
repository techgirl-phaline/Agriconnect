<?php
session_start();
require_once "db.php";

$full_name = "";
$email = "";
$phone = "";
$role = "buyer";
$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $role = $_POST["role"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Validate full name
    if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100) {
        $errors[] = "Full name must contain between 2 and 100 characters.";
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    // Validate Kenyan phone number
    if (!preg_match('/^(?:0[17][0-9]{8}|254[17][0-9]{8}|\+254[17][0-9]{8})$/', $phone)) {
        $errors[] = "Enter a valid Kenyan phone number, e.g. 0712345678.";
    }

    // Only allow these roles during public registration
    if (!in_array($role, ["farmer", "buyer"], true)) {
        $errors[] = "Select Farmer or Buyer.";
    }

    // Validate password strength
    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        $errors[] = "Password must be at least 8 characters and contain uppercase, lowercase, a number and a special character.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if the email is already registered
    if (empty($errors)) {
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1"
        );

        if (!$check) {
            $errors[] = "Could not check the email. Verify your database connection.";
        } else {
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $errors[] = "This email is already registered.";
            }

            $check->close();
        }
    }

    // Save the new account
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (full_name, email, phone, password, role)
             VALUES (?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $errors[] = "Could not prepare registration. Check your users table.";
        } else {
            $stmt->bind_param(
                "sssss",
                $full_name,
                $email,
                $phone,
                $hashed_password,
                $role
            );

            if ($stmt->execute()) {
                $success = true;
                $full_name = "";
                $email = "";
                $phone = "";
                $role = "buyer";
            } else {
                $errors[] = $stmt->errno === 1062
                    ? "This email is already registered."
                    : "Registration failed. Check your database table structure.";
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
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

        body {
            background: var(--pale-green);
            color: var(--text-dark);
            font-family: Arial, sans-serif;
            min-height: 100vh;
        }

        .navbar {
            background: var(--dark-green);
        }

        .brand {
            color: white;
            font-size: 1.45rem;
            font-weight: 800;
            text-decoration: none;
        }

        .brand span {
            color: var(--yellow);
        }

        .nav-link {
            color: white;
        }

        .nav-link:hover {
            color: var(--yellow);
        }

        .register-wrapper {
            max-width: 650px;
            margin: 35px auto;
            padding: 0 15px;
        }

        .register-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 32px;
            box-shadow: var(--shadow);
        }

        .icon-circle {
            width: 65px;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: var(--soft-green);
            font-size: 30px;
        }

        .page-title {
            color: var(--dark-green);
            font-weight: 800;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control, .form-select {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--main-green);
            box-shadow: 0 0 0 3px rgba(19, 128, 79, 0.12);
        }

        .btn-register {
            background: var(--main-green);
            color: white;
            border: none;
            padding: 13px;
            border-radius: 9px;
            font-weight: 700;
        }

        .btn-register:hover {
            background: var(--deep-green);
            color: white;
        }

        .helper-text {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .login-link {
            color: var(--main-green);
            font-weight: 700;
            text-decoration: none;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
        }

        @media (max-width: 576px) {
            .register-card {
                padding: 22px 18px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="brand" href="index.php">Agri<span>Connect</span></a>

        <button class="navbar-toggler bg-light" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link" href="produce-catalogue.php">Produce Catalogue</a>
                <a class="nav-link active" href="register.php">Register</a>
                <a class="nav-link" href="login.php">Login</a>
            </div>
        </div>
    </div>
</nav>

<main class="register-wrapper">
    <div class="register-card">

        <div class="text-center mb-4">
            <div class="icon-circle">🌱</div>
            <h1 class="page-title h2">Create Your Account</h1>
            <p class="text-secondary">
                Join AgriConnect and connect with Kenya's agricultural community.
            </p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <h5>Registration Successful!</h5>
                Your account has been created. You can now log in.
                <div class="mt-3">
                    <a href="login.php" class="btn btn-register">Go to Login</a>
                </div>
            </div>
        <?php else: ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name"
                           name="full_name" maxlength="100"
                           placeholder="Enter your full name"
                           value="<?= htmlspecialchars($full_name, ENT_QUOTES, "UTF-8") ?>"
                           autocomplete="name" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email"
                           name="email" maxlength="150"
                           placeholder="you@example.com"
                           value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                           autocomplete="email" required>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone"
                           name="phone" maxlength="13"
                           placeholder="0712345678 or 254712345678"
                           value="<?= htmlspecialchars($phone, ENT_QUOTES, "UTF-8") ?>"
                           autocomplete="tel" required>
                    <div class="helper-text mt-1">
                        Enter a valid Kenyan mobile number.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Account Type</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="buyer" <?= $role === "buyer" ? "selected" : "" ?>>
                            Buyer — Browse and purchase produce
                        </option>
                        <option value="farmer" <?= $role === "farmer" ? "selected" : "" ?>>
                            Farmer — Promote your farm and produce
                        </option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password"
                           name="password" minlength="8"
                           autocomplete="new-password"
                           placeholder="Create a strong password" required>
                    <div class="helper-text mt-1">
                        Use at least 8 characters with uppercase and lowercase
                        letters, a number and a special character.
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">
                        Confirm Password
                    </label>
                    <input type="password" class="form-control"
                           id="confirm_password" name="confirm_password"
                           minlength="8" autocomplete="new-password"
                           placeholder="Enter your password again" required>
                </div>

                <button type="submit" class="btn btn-register w-100">
                    Create Account
                </button>

                <p class="text-center mt-4 mb-0">
                    Already have an account?
                    <a href="login.php" class="login-link">Log in</a>
                </p>
            </form>
        <?php endif; ?>

    </div>
</main>

<footer>
    &copy; <?= date("Y") ?> AgriConnect. Connecting farmers and buyers.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>