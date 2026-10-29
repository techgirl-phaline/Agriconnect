
<?php
session_start();
require_once "../db.php";

$error = "";

if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])
    && $_SESSION["role"] === "admin") {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
        $error = "Please enter a valid email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role, account_status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $error = "A database error occurred. Please try again.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (
                    $user["role"] === "admin"
                    && $user["account_status"] === "approved"
                    && password_verify($password, $user["password"])
                ) {
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user["user_id"];
                    $_SESSION["full_name"] = $user["full_name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    $stmt->close();

                    header("Location: dashboard.php");
                    exit;
                }
            }

            $stmt->close();
            $error = "Incorrect email or password, or this account is not authorised.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f3f7f0;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .brand {
            color: #347344;
            font-weight: 800;
        }

        .form-control {
            padding: 12px;
            border-radius: 9px;
        }

        .btn-login {
            background: #347344;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 9px;
            font-weight: 600;
        }

        .btn-login:hover {
            background: #285b35;
            color: white;
        }

        .back-link {
            color: #347344;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <h2 class="brand">AgriConnect</h2>
        <h5 class="mt-3">Administrator Login</h5>
        <p class="text-secondary">
            Sign in to manage your agricultural marketplace.
        </p>
    </div>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                maxlength="150"
                value="<?= htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                autocomplete="username"
                required
            >
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input
                type="password"
                class="form-control"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-login w-100">
            Sign In
        </button>
    </form>

    <div class="text-center mt-4">
        <a href="../index.php" class="back-link">
            &larr; Back to AgriConnect Homepage
        </a>
    </div>

    <p class="text-center text-secondary small mt-4 mb-0">
        Secure Administrator Access
    </p>
</div>

</body>
</html>
