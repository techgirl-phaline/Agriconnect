
<?php
session_start();
require_once "../db.php";

$email = "";
$error = "";
$success = "";

if (isset($_GET["registered"]) && $_GET["registered"] === "success") {
    $success = "Registration successful! Please log in to your account.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Please enter both your email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role
             FROM users
             WHERE email = ? AND role = 'farmer'
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {
                session_regenerate_id(true);

// Store the user's account details
$_SESSION["user_id"] = (int) $user["user_id"];
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["farmer_name"] = $user["full_name"];
$_SESSION["email"] = $user["email"];
$_SESSION["role"] = $user["role"];

// Get the actual farmer profile ID
$user_id = (int) $user["user_id"];

$stmt_profile = $conn->prepare(
    "SELECT farmer_id
     FROM farmer_profiles
     WHERE user_id = ?
     LIMIT 1"
);

$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();

$profile_result = $stmt_profile->get_result();
$farmer_profile = $profile_result->fetch_assoc();

$stmt_profile->close();

if (!$farmer_profile) {
    $error = "Your farmer profile has not been created. Please contact the administrator.";
} else {
    $_SESSION["farmer_id"] = (int) $farmer_profile["farmer_id"];

    header("Location: Dashboard.php");
    exit;
}

               
            } else {
                $error = "Incorrect email or password.";
            }
        } else {
            $error = "Incorrect email or password, or this account is not registered as a farmer.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Login | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #f3f7f3;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: #06452F;
            padding: 16px 0;
        }

        .navbar-brand {
            color: #F5CF32;
            font-size: 25px;
            font-weight: bold;
        }

        .navbar-brand:hover {
            color: #F5CF32;
        }

        .login-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 15px;
        }

        .login-card {
            background: white;
            width: 100%;
            max-width: 450px;
            padding: 38px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .login-card h1 {
            color: #06452F;
            font-size: 29px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }

        .intro {
            color: #6b7280;
            text-align: center;
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .form-label {
            font-weight: 600;
            color: #26382e;
        }

        .form-control {
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.15);
        }

        .btn-login {
            background: #06452F;
            color: white;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: #087047;
            color: white;
        }

        .register-link {
            text-align: center;
            margin-top: 22px;
            color: #555;
        }

        .register-link a {
            color: #06452F;
            font-weight: bold;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        footer {
            background: #06452F;
            color: white;
            text-align: center;
            padding: 15px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 26px 20px;
            }

            .login-card h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <div class="container">
        <a class="navbar-brand text-decoration-none"
           href="../index.php">AgriConnect</a>
    </div>
</nav>

<main class="login-wrapper">
    <div class="login-card">

        <h1>Hello, Welcome Back!</h1>

        <p class="intro">
            Log in to manage your farm profile and connect with buyers
            through AgriConnect.
        </p>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
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
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($email) ?>"
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
                    placeholder="Enter your password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-login">
                Login as Farmer
            </button>
        </form>

        <div class="register-link">
            Don't have a farmer account?
            <a href="register.php">Register here</a>
        </div>

        <div class="text-center mt-3">
            <a href="../index.php"
               class="text-secondary text-decoration-none">
                Back to Home
            </a>
        </div>

    </div>
</main>

<footer>
    &copy; <?= date("Y") ?> AgriConnect. Connecting Farmers to Markets.
</footer>

</body>
</html>
