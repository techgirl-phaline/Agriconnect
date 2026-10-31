
<?php
session_start();
require_once "../db.php";

$full_name = "";
$email = "";
$phone = "";
$farm_name = "";
$county = "";
$sub_county = "";
$farm_location = "";
$bio = "";
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    $farm_name = trim($_POST["farm_name"] ?? "");
    $county = trim($_POST["county"] ?? "");
    
    if (
        $full_name === "" || $email === "" || $phone === "" ||
        $password === "" || $confirm_password === "" ||
        $farm_name === "" || $county === ""
    ) {
        $message = "Please fill in all required fields.";
        $message_type = "danger";

    } elseif (!preg_match("/^[a-zA-Z\s.'-]{2,100}$/", $full_name)) {
        $message = "Please enter a valid full name.";
        $message_type = "danger";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "danger";

    } elseif (!preg_match("/^(0[0-9]{9}|\+?254[0-9]{9})$/", $phone)) {
        $message = "Enter a valid Kenyan phone number.";
        $message_type = "danger";

    } elseif (strlen($password) < 8) {
        $message = "Your password must contain at least 8 characters.";
        $message_type = "danger";

    } elseif ($password !== $confirm_password) {
        $message = "The passwords do not match.";
        $message_type = "danger";

    } else {
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1"
        );
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "This email is already registered. Please log in.";
            $message_type = "warning";
            $check->close();

        } else {
            $check->close();

            try {
                $conn->begin_transaction();

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );
                $role = "farmer";

                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (full_name, email, phone, password, role)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sssss",
                    $full_name,
                    $email,
                    $phone,
                    $hashed_password,
                    $role
                );

                $stmt->execute();
                $user_id = $conn->insert_id;
                $stmt->close();

                $profile = $conn->prepare(
                    "INSERT INTO farmer_profiles
                    (user_id, farm_name, county, sub_county,
                     farm_location, bio)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $profile->bind_param(
                    "isssss",
                    $user_id,
                    $farm_name,
                    $county,
                    $sub_county,
                    $farm_location,
                    $bio
                );

                $profile->execute();
                $profile->close();
                /* Create admin notification for new farmer registration */
$notification_type = "farmer";
$title = "New Farmer Registration";

$message = "A new farmer, " . $full_name .
           ", has registered and is awaiting review.";

$action_url = "farmers.php";

$notification_stmt = $conn->prepare(
    "INSERT IGNORE INTO admin_notifications
        (notification_type, reference_id, title, message, action_url)
     VALUES (?, ?, ?, ?, ?)"
);

$notification_stmt->bind_param(
    "sisss",
    $notification_type,
    $user_id,
    $title,
    $message,
    $action_url
);

$notification_stmt->execute();
$notification_stmt->close();

                $conn->commit();

                $conn->close();
                header("Location: login.php?registered=success");
                exit;

            } catch (Throwable $e) {
                $conn->rollback();

                error_log("AgriConnect registration error: " . $e->getMessage());

                $message = "Registration could not be completed. Please try again.";
                $message_type = "danger";
            }
        }
    }
}

function escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Registration | AgriConnect</title>

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

        .register-card {
            width: 100%;
            max-width: 510px;
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

        .btn-register {
            width: 100%;
            min-height: 48px;
            background: var(--dark-green);
            border: none;
            color: white;
            border-radius: 9px;
            font-weight: 700;
            margin-top: 8px;
        }

        .btn-register:hover {
            background: var(--main-green);
            color: white;
        }

        .login-text {
            text-align: center;
            margin-top: 22px;
            margin-bottom: 0;
            font-size: 14px;
            color: var(--text-muted);
        }

        .login-text a {
            color: var(--dark-green);
            font-weight: 700;
            text-decoration: none;
        }

        .login-text a:hover,
        .back-link:hover {
            text-decoration: underline;
        }

        .form-note {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 5px;
        }

        @media (max-width: 480px) {
            .register-card {
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
    <section class="register-card">
        <div class="icon-circle">&#127793;</div>

        <h1>Farmer Registration</h1>
        <p class="intro">
            Join AgriConnect and connect your farm produce with buyers.
        </p>

        <?php if ($message !== ""): ?>
            <div class="alert alert-<?php echo escape($message_type); ?>"
                 role="alert">
                <?php echo escape($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input
                    type="text"
                    class="form-control"
                    id="full_name"
                    name="full_name"
                    placeholder="Enter your full name"
                    value="<?php echo escape($full_name); ?>"
                    maxlength="100"
                    autocomplete="name"
                    required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    value="<?php echo escape($email); ?>"
                    maxlength="254"
                    autocomplete="email"
                    required>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input
                    type="tel"
                    class="form-control"
                    id="phone"
                    name="phone"
                    placeholder="0712345678"
                    value="<?php echo escape($phone); ?>"
                    maxlength="13"
                    autocomplete="tel"
                    required>
                <div class="form-note">
                    Example: 0712345678 or 254712345678
                </div>
            </div>
            
<div class="mb-3">
    <label for="farm_name" class="form-label">Farm Name</label>
    <input type="text" class="form-control" id="farm_name"
           name="farm_name" placeholder="e.g. Zipporah's Farm" required>
</div>

<div class="mb-3">
    <label for="county" class="form-label">County</label>
    <input type="text" class="form-control" id="county"
           name="county" placeholder="Enter your county" required>
</div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    placeholder="Create a password"
                    minlength="8"
                    autocomplete="new-password"
                    required>
                <div class="form-note">Use at least 8 characters.</div>
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label">
                    Confirm Password
                </label>
                <input
                    type="password"
                    class="form-control"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Enter your password again"
                    minlength="8"
                    autocomplete="new-password"
                    required>
            </div>

            <button type="submit" class="btn btn-register">
                Create Farmer Account
            </button>
        </form>

        <p class="login-text">
            Already have an account?
            <a href="login.php">Log in</a>
        </p>
    </section>
</main>

</body>
</html>
