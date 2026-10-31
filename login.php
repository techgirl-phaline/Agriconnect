<?php
session_start();
require_once "db.php";

$email = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if ($password === "") {
        $errors[] = "Please enter your password.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $errors[] = "Unable to process login. Please check your database connection.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result(
                    $user_id,
                    $full_name,
                    $user_email,
                    $hashed_password,
                    $role
                );
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user_id;
                    $_SESSION["full_name"] = $full_name;
                    $_SESSION["email"] = $user_email;
                    $_SESSION["role"] = $role;

                    $stmt->close();

                    if ($role === "farmer") {
                        header("Location: farmer/Dashboard.php");
                        exit;
                    }

                    if ($role === "buyer") {
                        header("Location: buyer/dashboard.php");
                        exit;
                    }

                    // Unknown or unsupported role
                    $_SESSION = [];
                    session_destroy();
                    $errors[] = "This account has an unsupported role. Please contact the administrator.";
                } else {
                    $errors[] = "Incorrect email or password.";
                }
            } else {
                $errors[] = "Incorrect email or password.";
            }

            if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                try {
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    // Statement was already closed.
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AgriConnect</title>

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
            color: var(--white);
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

        .login-wrapper {
            max-width: 480px;
            margin: 55px auto;
            padding: 0 15px;
        }

        .login-card {
            background: var(--white);
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

        .form-control {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .form-control:focus {
            border-color: var(--main-green);
            box-shadow: 0 0 0 3px rgba(19, 128, 79, 0.12);
        }

        .btn-login {
            background: var(--main-green);
            color: white;
            border: none;
            padding: 13px;
            border-radius: 9px;
            font-weight: 700;
        }

        .btn-login:hover {
            background: var(--deep-green);
            color: white;
        }

        .register-link {
            color: var(--main-green);
            font-weight: 700;
            text-decoration: none;
        }

        .register-link:hover {
            text-decoration: underline;
        }

        footer {
            color: var(--text-muted);
            text-align: center;
            padding: 20px;
            font-size: 0.9rem;
        }

        @media (max-width: 576px) {
            .login-wrapper {
                margin: 25px auto;
            }

            .login-card {
                padding: 24px 19px;
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
                <a class="nav-link" href="register.php">Register</a>
                <a class="nav-link active" href="login.php">Login</a>
            </div>
        </div>
    </div>
</nav>

<main class="login-wrapper">
    <div class="login-card">

        <div class="text-center mb-4">
            <div class="icon-circle">ðŸŒ±</div>
            <h1 class="page-title h2">Welcome Back</h1>
            <p class="text-secondary">
                Log in to your AgriConnect account.
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                    autocomplete="email"
                    required>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required>
            </div>

            <button type="submit" class="btn btn-login w-100">
                Log In
            </button>

            <p class="text-center mt-4 mb-0">
                Don't have an account?
                <a href="register.php" class="register-link">Create an account</a>
            </p>
        </form>

    </div>
</main>

<footer>
    &copy; <?= date("Y") ?> AgriConnect. Connecting farmers and buyers.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

