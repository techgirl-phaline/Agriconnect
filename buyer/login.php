
<?php
session_start();
require_once "../db.php";

$email = "";
$message = "";
$message_type = "";

if (isset($_GET["registered"]) && $_GET["registered"] === "success") {
    $message = "Your buyer account has been created successfully. Please log in.";
    $message_type = "success";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $message = "Please enter your email and password.";
        $message_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "danger";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role
             FROM users
             WHERE email = ? AND role = 'buyer'
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user["password"])) {
                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];

                $stmt->close();
                $conn->close();

                header("Location: dashboard.php");
                exit;
            }
        }

        $stmt->close();

        $message = "Incorrect email or password, or this account is not registered as a buyer.";
        $message_type = "danger";
    }
}

function escape($value) {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Login | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        :root {
            --dark-green: #075B3D;
            --main-green: #13804F;
            --soft-green: #EFF6E9;
            --pale-green: #F7FAF4;
            --text-dark: #25372D;
            --text-muted: #6B786F;
            --border: #E2E9E1;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--pale-green);
            color: var(--text-dark);
            font-family: Arial, Helvetica, sans-serif;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 15px 0;
        }

        .brand {
            color: var(--dark-green);
            font-size: 25px;
            font-weight: 800;
            text-decoration: none;
        }

        .brand span {
            color: var(--main-green);
        }

        .back-link {
            color: var(--dark-green);
            text-decoration: none;
            font-weight: 600;
        }

        .page-wrapper {
            min-height: calc(100vh - 70px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 15px;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 35px;
            box-shadow: 0 8px 25px rgba(7, 91, 61, 0.08);
        }

        .icon-circle {
            width: 60px;
            height: 60px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: var(--soft-green);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-green);
            font-size: 28px;
        }

        h1 {
            color: var(--dark-green);
            font-size: 28px;
            font-weight: 750;
            text-align: center;
            margin-bottom: 8px;
        }

        .intro {
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 26px;
        }

        .form-label {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 7px;
        }

        .form-control {
            min-height: 47px;
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 11px 13px;
        }

        .form-control:focus {
            border-color: var(--main-green);
            box-shadow: 0 0 0 3px rgba(19, 128, 79, 0.12);
        }

        .btn-login {
            width: 100%;
            min-height: 48px;
            background: var(--dark-green);
            border: none;
            color: white;
            border-radius: 9px;
            font-weight: 700;
            margin-top: 8px;
        }

        .btn-login:hover {
            background: var(--main-green);
            color: white;
        }

        .register-text {
            text-align: center;
            margin-top: 22px;
            margin-bottom: 0;
            font-size: 14px;
            color: var(--text-muted);
        }

        .register-text a {
            color: var(--dark-green);
            font-weight: 700;
            text-decoration: none;
        }

        .register-text a:hover,
        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 25px 20px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="brand" href="../index.php">Agri<span>Connect</span></a>
        <a class="back-link" href="../index.php">&larr; Back to Home</a>
    </div>
</nav>

<main class="page-wrapper">
    <section class="login-card">
        <div class="icon-circle">&#128722;</div>

        <h1>Hello, Welcome Back!</h1>
<p class="intro">
    We're happy to have you here. Log in to continue exploring
    fresh farm produce with AgriConnect.
</p>

        <?php if ($message !== ""): ?>
            <div class="alert alert-<?php echo escape($message_type); ?>"
                 role="alert">
                <?php echo escape($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="Enter your registered email"
                    value="<?php echo escape($email); ?>"
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

            <button type="submit" class="btn btn-login">
                Log In as Buyer
            </button>
        </form>

        <p class="register-text">
            Don't have a buyer account?
            <a href="register.php">Register here</a>
        </p>
    </section>
</main>

</body>
</html>
