
<?php
require_once "db.php";

$success = "";
$error = "";

$name = "";
$email = "";
$subject = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    // Validate the form fields
    if (
        $name === "" ||
        $email === "" ||
        $subject === "" ||
        $message === ""
    ) {
        $error = "Please fill in all fields.";
    } elseif (strlen($name) > 100) {
        $error = "Your name must not exceed 100 characters.";
    } elseif (strlen($email) > 150) {
        $error = "Your email address is too long.";
    } elseif (strlen($subject) > 150) {
        $error = "The subject must not exceed 150 characters.";
    } elseif (strlen($message) > 5000) {
        $error = "Your message must not exceed 5000 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Insert into the existing contact_messages table
        $stmt = $conn->prepare(
            "INSERT INTO contact_messages
            (full_name, email, subject, message, status)
            VALUES (?, ?, ?, ?, 'pending')"
        );

        if ($stmt) {
            $stmt->bind_param(
                "ssss",
                $name,
                $email,
                $subject,
                $message
            );

            if ($stmt->execute()) {
                $success = "Thank you! Your message has been sent successfully.";

                // Clear the form after successful submission
                $name = "";
                $email = "";
                $subject = "";
                $message = "";
            } else {
                $error = "Unable to save your message. Please try again.";
            }

            $stmt->close();
        } else {
            $error = "A database error occurred. Please check your database table.";
        }
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contact Us | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        :root {
            --green: #075B3D;
            --main-green: #13804F;
            --yellow: #F5CF32;
            --soft-green: #EFF6E9;
            --text: #25372D;
            --muted: #6B786F;
            --border: #E2E9E1;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #F7FAF4;
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        /* Navigation */
        .navbar {
            background: var(--green);
            padding: 14px 0;
        }

        .navbar-brand {
            color: white !important;
            font-size: 23px;
            font-weight: 800;
        }

        .navbar-brand span {
            color: var(--yellow);
        }

        .nav-link {
            color: white !important;
            font-size: 14px;
            font-weight: 600;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--yellow) !important;
        }

        /* Hero section */
        .contact-hero {
            background: var(--soft-green);
            padding: 65px 0;
            text-align: center;
        }

        .contact-hero h1 {
            color: var(--green);
            font-size: clamp(32px, 5vw, 46px);
            font-weight: 800;
            margin-bottom: 15px;
        }

        .contact-hero p {
            color: var(--muted);
            max-width: 620px;
            margin: auto;
            line-height: 1.8;
        }

        /* Contact section */
        .contact-section {
            padding: 65px 0;
        }

        .contact-info,
        .contact-form {
            height: 100%;
            padding: 30px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(7, 91, 61, 0.06);
        }

        /* Contact information panel */
        .contact-info {
            background: var(--green);
            color: white;
        }

        .contact-info h2,
        .contact-form h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .contact-info > p {
            color: #DCE9E1;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 28px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 25px;
        }

        .info-icon {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--yellow);
            color: var(--green);
            border-radius: 10px;
            font-size: 19px;
        }

        .info-item h3 {
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .info-item p {
            color: #DCE9E1;
            font-size: 13px;
            margin: 0;
            overflow-wrap: anywhere;
        }

        /* Contact form */
        .contact-form h2 {
            color: var(--green);
        }

        .form-intro {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 25px;
        }

        .form-label {
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .form-control {
            padding: 12px 13px;
            border: 1px solid var(--border);
            border-radius: 7px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--main-green);
            box-shadow: 0 0 0 3px rgba(19, 128, 79, 0.1);
        }

        textarea.form-control {
            min-height: 145px;
            resize: vertical;
        }

        .btn-send {
            width: 100%;
            background: var(--green);
            color: white;
            border: 2px solid var(--green);
            border-radius: 7px;
            padding: 13px;
            font-size: 14px;
            font-weight: 800;
            transition: 0.2s;
        }

        .btn-send:hover {
            background: var(--main-green);
            border-color: var(--main-green);
            color: white;
        }

        /* Footer */
        .footer {
            background: #043B29;
            color: #DCE9E1;
            text-align: center;
            padding: 24px 12px;
            font-size: 12px;
        }

        .footer span {
            color: var(--yellow);
        }

        /* Mobile layout */
        @media (max-width: 767px) {
            .contact-section {
                padding: 35px 0;
            }

            .contact-info,
            .contact-form {
                padding: 23px;
            }

            .contact-hero {
                padding: 45px 15px;
            }
        }
    </style>
</head>

<body>

<!-- NAVIGATION -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">

        <a class="navbar-brand" href="index.php">
            <i class="bi bi-leaf-fill me-2"></i>Agri<span>Connect</span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navigation"
                aria-controls="navigation"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navigation">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="about.php">About Us</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="products.php">Products</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active" href="contact.php">Contact Us</a>
                </li>

            </ul>
        </div>

    </div>
</nav>

<!-- PAGE HEADING -->
<section class="contact-hero">
    <div class="container">

        <h1>Get in Touch With Us</h1>

        <p>
            Have a question about AgriConnect? We would love to hear from you.
            Send us a message about our agricultural marketplace,
            farmers, products or services.
        </p>

    </div>
</section>

<!-- CONTACT CONTENT -->
<section class="contact-section">
    <div class="container">
        <div class="row g-4">

            <!-- CONTACT INFORMATION -->
            <div class="col-lg-5">
                <div class="contact-info">

                    <h2>Contact Information</h2>

                    <p>
                        Reach out to the AgriConnect team.
                        We welcome your questions, feedback and suggestions.
                    </p>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>

                        <div>
                            <h3>Our Location</h3>
                            <p>Kenya</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-envelope-fill"></i>
                        </div>

                        <div>
                            <h3>Email Address</h3>
                            <p>Contact details to be added</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="bi bi-telephone-fill"></i>
                        </div>

                        <div>
                            <h3>Phone Number</h3>
                            <p>Contact details to be added</p>
                        </div>
                    </div>

                    <div class="info-item mb-0">
                        <div class="info-icon">
                            <i class="bi bi-clock-fill"></i>
                        </div>

                        <div>
                            <h3>Our Commitment</h3>
                            <p>Supporting farmers and agricultural trade.</p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- CONTACT FORM -->
            <div class="col-lg-7">
                <div class="contact-form">

                    <h2>Send Us a Message</h2>

                    <p class="form-intro">
                        Fill in the form below and submit your enquiry.
                    </p>

                    <?php if ($success !== ""): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= e($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error !== ""): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form action="contact.php" method="POST">

                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Full Name
                            </label>

                            <input type="text"
                                   class="form-control"
                                   id="name"
                                   name="name"
                                   maxlength="100"
                                   value="<?= e($name) ?>"
                                   placeholder="Enter your full name"
                                   autocomplete="name"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">
                                Email Address
                            </label>

                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   maxlength="150"
                                   value="<?= e($email) ?>"
                                   placeholder="example@email.com"
                                   autocomplete="email"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">
                                Subject
                            </label>

                            <input type="text"
                                   class="form-control"
                                   id="subject"
                                   name="subject"
                                   maxlength="150"
                                   value="<?= e($subject) ?>"
                                   placeholder="What is your enquiry about?"
                                   required>
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label">
                                Your Message
                            </label>

                            <textarea class="form-control"
                                      id="message"
                                      name="message"
                                      maxlength="5000"
                                      placeholder="Write your message here..."
                                      required><?= e($message) ?></textarea>
                        </div>

                        <button type="submit" class="btn-send">
                            Send Message
                            <i class="bi bi-send-fill ms-2"></i>
                        </button>

                    </form>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    &copy; <?= date("Y") ?> Agri<span>Connect</span>.
    Connecting Farmers. Growing Opportunities.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
