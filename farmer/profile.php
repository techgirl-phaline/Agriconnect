
<?php
session_start();

require_once __DIR__ . '/../db.php';

// Allow logged-in farmers only
if (
    !isset($_SESSION['farmer_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'farmer'
) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['farmer_id'];

$message = '';
$message_type = 'success';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Default values
$full_name = '';
$email = '';
$phone = '';
$farm_name = '';
$county = '';
$sub_county = '';
$farm_location = '';
$bio = '';
$mpesa_number = '';
$mpesa_account_name = '';
$farmer_profile_id = null;

// Load existing account and farm details
try {
    $stmt = $conn->prepare(
        "SELECT user_id, full_name, email, phone
         FROM users
         WHERE user_id = ? AND role = 'farmer'
         LIMIT 1"
    );

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $full_name = $user['full_name'] ?? '';
    $email = $user['email'] ?? '';
    $phone = $user['phone'] ?? '';

    $stmt = $conn->prepare(
        "SELECT farmer_id, farm_name, county, sub_county,
                farm_location, bio, mpesa_number,
                mpesa_account_name
         FROM farmer_profiles
         WHERE user_id = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($profile) {
        $farmer_profile_id = (int) $profile['farmer_id'];
        $farm_name = $profile['farm_name'] ?? '';
        $county = $profile['county'] ?? '';
        $sub_county = $profile['sub_county'] ?? '';
        $farm_location = $profile['farm_location'] ?? '';
        $bio = $profile['bio'] ?? '';
        $mpesa_number = $profile['mpesa_number'] ?? '';
        $mpesa_account_name = $profile['mpesa_account_name'] ?? '';
    }
} catch (Throwable $ex) {
    error_log($ex->getMessage());
    $message = 'Unable to load your profile. Check the database columns and connection.';
    $message_type = 'danger';
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $message = 'Your session token is invalid. Refresh the page and try again.';
        $message_type = 'danger';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $farm_name = trim($_POST['farm_name'] ?? '');
        $county = trim($_POST['county'] ?? '');
        $sub_county = trim($_POST['sub_county'] ?? '');
        $farm_location = trim($_POST['farm_location'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $mpesa_number = trim($_POST['mpesa_number'] ?? '');
        $mpesa_account_name = trim($_POST['mpesa_account_name'] ?? '');

        try {
            if ($full_name === '' || strlen($full_name) > 100) {
                throw new Exception('Enter your full name (maximum 100 characters).');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 150) {
                throw new Exception('Enter a valid email address.');
            }

            if ($phone === '' || strlen($phone) > 20) {
                throw new Exception('Enter your phone number (maximum 20 characters).');
            }

            if (strlen($farm_name) > 150) {
                throw new Exception('Farm name must not exceed 150 characters.');
            }

            if (strlen($county) > 100 || strlen($sub_county) > 100) {
                throw new Exception('County and sub-county must not exceed 100 characters.');
            }

            if (strlen($farm_location) > 255) {
                throw new Exception('Farm location must not exceed 255 characters.');
            }

            if (strlen($bio) > 2000) {
                throw new Exception('Farm bio must not exceed 2000 characters.');
            }

            // Validate M-Pesa details when provided
            if ($mpesa_number !== '') {
                $normalized_mpesa = preg_replace('/[\s-]+/', '', $mpesa_number);

                if (!preg_match('/^(?:0[17]\d{8}|254[17]\d{8}|\+254[17]\d{8})$/', $normalized_mpesa)) {
                    throw new Exception(
                        'Enter a valid Kenyan M-Pesa number, e.g. 0712345678 or 254712345678.'
                    );
                }

                $mpesa_number = $normalized_mpesa;
            }

            if (strlen($mpesa_account_name) > 100) {
                throw new Exception('M-Pesa account name must not exceed 100 characters.');
            }

            if (($mpesa_number === '') !== ($mpesa_account_name === '')) {
                throw new Exception(
                    'Enter both the M-Pesa number and the registered account name, or leave both blank.'
                );
            }

            // Check email uniqueness
            $stmt = $conn->prepare(
                "SELECT user_id FROM users
                 WHERE email = ? AND user_id <> ?
                 LIMIT 1"
            );
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $email_exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($email_exists) {
                throw new Exception('That email address is already used by another account.');
            }

            // Save account details
            $stmt = $conn->prepare(
                "UPDATE users
                 SET full_name = ?, email = ?, phone = ?
                 WHERE user_id = ? AND role = 'farmer'"
            );
            $stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
            $stmt->execute();
            $stmt->close();

            // Save farm profile and M-Pesa details
            if ($farmer_profile_id) {
                $stmt = $conn->prepare(
                    "UPDATE farmer_profiles
                     SET farm_name = ?, county = ?, sub_county = ?,
                         farm_location = ?, bio = ?, mpesa_number = ?,
                         mpesa_account_name = ?
                     WHERE farmer_id = ? AND user_id = ?"
                );

                $stmt->bind_param(
                    "sssssssii",
                    $farm_name,
                    $county,
                    $sub_county,
                    $farm_location,
                    $bio,
                    $mpesa_number,
                    $mpesa_account_name,
                    $farmer_profile_id,
                    $user_id
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO farmer_profiles
                     (user_id, farm_name, county, sub_county,
                      farm_location, bio, mpesa_number, mpesa_account_name)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "isssssss",
                    $user_id,
                    $farm_name,
                    $county,
                    $sub_county,
                    $farm_location,
                    $bio,
                    $mpesa_number,
                    $mpesa_account_name
                );
            }

            $stmt->execute();

            if (!$farmer_profile_id) {
                $farmer_profile_id = $conn->insert_id;
            }

            $stmt->close();

            $_SESSION['full_name'] = $full_name;
            $_SESSION['farmer_name'] = $full_name;
            $_SESSION['email'] = $email;

            $message = 'Your profile and M-Pesa details have been saved successfully.';
            $message_type = 'success';

        } catch (Throwable $ex) {
            error_log($ex->getMessage());
            $message = 'Could not update your profile: ' . $ex->getMessage();
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Farmer Profile | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        :root {
            --green: #39734b;
            --green-dark: #285639;
            --green-light: #edf5ee;
            --cream: #faf9f4;
            --text: #26352b;
            --muted: #718075;
            --border: #e4eae3;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
        }

        .brand, .page-heading, .section-title {
            color: var(--green-dark);
            font-weight: 750;
        }

        .brand {
            font-size: 1.35rem;
        }

        .profile-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(35, 65, 43, .04);
        }

        .form-control {
            padding: .75rem .85rem;
            border-color: #dce5dc;
            border-radius: 9px;
        }

        .form-control:focus {
            border-color: #79a985;
            box-shadow: 0 0 0 .2rem rgba(57, 115, 75, .12);
        }

        .btn-green {
            background: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        .btn-green:hover {
            background: var(--green-dark);
            border-color: var(--green-dark);
            color: #fff;
        }

        .muted, footer {
            color: var(--muted);
        }

        .profile-icon {
            width: 58px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--green-light);
            border-radius: 50%;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        footer { font-size: .9rem; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container py-2 d-flex justify-content-between align-items-center">
        <a class="navbar-brand brand" href="Dashboard.php">🌿 AgriConnect</a>
        <a href="Dashboard.php" class="btn btn-outline-success btn-sm">
            ← Dashboard
        </a>
    </div>
</nav>

<main class="container py-4 py-md-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="profile-icon">👨‍🌾</div>
        <div>
            <p class="text-success fw-semibold small mb-1">FARMER ACCOUNT</p>
            <h1 class="page-heading h2 mb-1">My Farmer Profile</h1>
            <p class="muted mb-0">Keep your personal, farm and payment details up to date.</p>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($message_type) ?> alert-dismissible fade show" role="alert">
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

        <div class="profile-card p-3 p-md-4 mb-4">
            <h2 class="section-title h5 mb-1">Personal Information</h2>
            <p class="muted small mb-4">These details are linked to your AgriConnect account.</p>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label fw-semibold">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control"
                           maxlength="100" required value="<?= e($full_name) ?>">
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control"
                           maxlength="150" required value="<?= e($email) ?>">
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           maxlength="20" required value="<?= e($phone) ?>">
                </div>
            </div>
        </div>

        <div class="profile-card p-3 p-md-4 mb-4">
            <h2 class="section-title h5 mb-1">Farm Information</h2>
            <p class="muted small mb-4">Help buyers learn about your farm and where to collect produce.</p>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="farm_name" class="form-label fw-semibold">Farm Name</label>
                    <input type="text" id="farm_name" name="farm_name" class="form-control"
                           maxlength="150" placeholder="e.g. Green Valley Farm"
                           value="<?= e($farm_name) ?>">
                </div>

                <div class="col-md-6">
                    <label for="county" class="form-label fw-semibold">County</label>
                    <input type="text" id="county" name="county" class="form-control"
                           maxlength="100" placeholder="e.g. Nakuru"
                           value="<?= e($county) ?>">
                </div>

                <div class="col-md-6">
                    <label for="sub_county" class="form-label fw-semibold">Sub-County</label>
                    <input type="text" id="sub_county" name="sub_county" class="form-control"
                           maxlength="100" placeholder="Enter your sub-county"
                           value="<?= e($sub_county) ?>">
                </div>

                <div class="col-md-6">
                    <label for="farm_location" class="form-label fw-semibold">
                        Farm Pickup Location
                    </label>
                    <input type="text" id="farm_location" name="farm_location" class="form-control"
                           maxlength="255" placeholder="Village, area or nearby landmark"
                           value="<?= e($farm_location) ?>">
                    <div class="form-text">This is your farm location, not a separate registered collection centre.</div>
                </div>

                <div class="col-12">
                    <label for="bio" class="form-label fw-semibold">About My Farm</label>
                    <textarea id="bio" name="bio" class="form-control" rows="4"
                              maxlength="2000"
                              placeholder="Tell buyers what you grow and what makes your produce special..."><?= e($bio) ?></textarea>
                    <div class="form-text">Maximum 2,000 characters.</div>
                </div>
            </div>
        </div>

        <div class="profile-card p-3 p-md-4 mb-4">
            <h2 class="section-title h5 mb-1">M-Pesa Payment Details</h2>
            <p class="muted small mb-4">
                Add the number buyers should pay when purchasing your products.
                Leave both fields blank if you are not ready to provide them.
            </p>

            <div class="alert alert-warning small">
                Enter your own correct payment details. Buyers must verify the recipient
                shown by M-Pesa before confirming a payment.
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="mpesa_number" class="form-label fw-semibold">
                        M-Pesa Phone Number
                    </label>
                    <input type="tel" id="mpesa_number" name="mpesa_number"
                           class="form-control" maxlength="20"
                           placeholder="0712345678 or 254712345678"
                           value="<?= e($mpesa_number) ?>">
                </div>

                <div class="col-md-6">
                    <label for="mpesa_account_name" class="form-label fw-semibold">
                        M-Pesa Account Name
                    </label>
                    <input type="text" id="mpesa_account_name" name="mpesa_account_name"
                           class="form-control" maxlength="100"
                           placeholder="Name registered with M-Pesa"
                           value="<?= e($mpesa_account_name) ?>">
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2">
            <button type="submit" class="btn btn-green px-4 py-2">
                Save Profile Changes
            </button>
            <a href="Dashboard.php" class="btn btn-outline-secondary px-4 py-2">
                Cancel
            </a>
        </div>
    </form>

    <footer class="text-center py-4 mt-3">
        © <?= date('Y') ?> AgriConnect · Growing connections, growing opportunities.
    </footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
