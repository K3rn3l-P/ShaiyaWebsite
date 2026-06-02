<?php
require_once 'security_bootstrap.php';
appApplySecurityHeaders();
appInitSession();
if (isset($_SESSION['user'])) {
    header("Location: /account");
    exit;
}
require_once 'db.php';
require_once 'db_pdo.php';

$message = '';
if (isset($_SESSION['register_msg'])) {
    $message = $_SESSION['register_msg'];
    unset($_SESSION['register_msg']);
} elseif (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = '<div style="color: #00ff00; text-align: center; margin-bottom: 15px; font-weight: bold;">Account created successfully!</div>';
}

// Fetch Registration Setting
$regEnabled = fetchWebsiteSettings(['RegistrationEnabled'])['RegistrationEnabled'] ?? '1';

// Generate new captcha numbers on GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['captcha_answer'])) {
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
    $_SESSION['captcha_answer'] = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $regEnabled == '1') {
    if (!appVerifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Session expired. Please refresh and try again.</div>';
        header("Location: /register");
        exit;
    }

    if (!empty($_POST['hidden_field'])) {
        $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Invalid request.</div>';
        header("Location: /register");
        exit;
    }

    $registerRateKey = 'register:' . appGetClientIp();
    if (!appRateLimitAllow($registerRateKey, 5, 900)) {
        $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Too many registration attempts. Please wait a few minutes.</div>';
        header("Location: /register");
        exit;
    }

    // Validate Captcha
    $user_captcha = (int) ($_POST['captcha'] ?? 0);
    if ($user_captcha !== $_SESSION['captcha_answer']) {
        $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Incorrect captcha answer. Please try again.</div>';
        header("Location: /register");
        exit;
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($password !== $confirm_password) {
        $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Passwords do not match.</div>';
        header("Location: /register");
        exit;
    } else {
        // Simple validation
        if (strlen($username) < 4 || strlen($username) > 18) {
            $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Username must be between 4 and 18 characters.</div>';
            header("Location: /register");
            exit;
        } elseif (!preg_match('/^[a-zA-Z0-9_@!#.-]+$/', $username)) {
            $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Username contains invalid characters.</div>';
            header("Location: /register");
            exit;
        } elseif (strlen($password) > 12) {
            $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Password cannot exceed 12 characters.</div>';
            header("Location: /register");
            exit;
        } elseif (strlen($password) < 4) {
            $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Password must be at least 4 characters.</div>';
            header("Location: /register");
            exit;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Invalid email address.</div>';
            header("Location: /register");
            exit;
        } else {
            // Check if user already exists
            try {
                $check_stmt = $pdoAdmin->prepare("SELECT UserID FROM PS_UserData.dbo.Users_Master WHERE UserID = ?");
                $check_stmt->execute([$username]);
            } catch (Throwable $e) {
                error_log('Register username check error: ' . $e->getMessage());
                $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Database error. Please try again.</div>';
                header("Location: /register");
                exit;
            }

            if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Username already taken.</div>';
                header("Location: /register");
                exit;
            } else {
                // Check how many accounts use this email
                $emailCount = 0;
                try {
                    $email_check_stmt = $pdoAdmin->prepare("SELECT COUNT(UserID) AS EmailCount FROM PS_UserData.dbo.Users_Master WHERE CAST(Email AS VARCHAR(255)) = ?");
                    $email_check_stmt->execute([$email]);
                    if ($email_row = $email_check_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $emailCount = (int) $email_row['EmailCount'];
                    }
                } catch (Throwable $e) {
                    error_log('Register email check error: ' . $e->getMessage());
                }

                if ($emailCount >= 3) {
                    $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">You have reached the maximum allowed limit of 3 accounts registered to this email address.</div>';
                    header("Location: /register");
                    exit;
                } else {
                    // Insert new user with hashed password (Enpassword) + plaintext (Pw for legacy game engine)
                    $user_ip = substr($_SERVER['REMOTE_ADDR'], 0, 15); // Max length for UserIp is 15
                    $passwordHash = appHashPbkdf2($password);
                    try {
                        $insert_stmt = $pdoAdmin->prepare("INSERT INTO PS_UserData.dbo.Users_Master (UserID, Pw, Enpassword, Email, JoinDate, Admin, AdminLevel, UseQueue, Status, [Leave], LeaveDate, UserType, UserIp, Point, PasswordHashedAt) VALUES (?, ?, ?, ?, GETDATE(), 0, 0, 0, 0, 0, NULL, 'N', ?, 0, GETDATE())");
                        $insert_stmt->execute([$username, $password, $passwordHash, $email, $user_ip]);
                        session_regenerate_id(true);
                        $_SESSION['user'] = $username;
                        appRotateCsrfToken();
                        appRateLimitReset($registerRateKey);
                        appSecurityLog('New account created (pbkdf2): ' . $username);
                        header("Location: /account");
                        exit;
                    } catch (Throwable $e) {
                        error_log('Register insert failed for username: ' . $username . ' - ' . $e->getMessage());
                        $_SESSION['register_msg'] = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Error creating account. Please try again later.</div>';
                        header("Location: /register");
                        exit;
                    }
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
    <link rel="icon" href="assets/Duff.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration | Shaiya Duff</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php $active_page = 'register'; ?>
    <div class="main-wrapper">
        <?php include 'modules/header.php'; ?>
        <?php include 'modules/nav.php'; ?>

        <div class="content-area-box">
            <div class="content-header">
                <h2><i class="fas fa-user-plus"></i> Account Registration</h2>
            </div>
            <div class="content-body" style="padding: 40px;">
                <?php if ($regEnabled == '1'): ?>
                    <form class="generic-form" style="max-width: 500px; margin: 0 auto;" method="POST"
                        action="/register">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="text" name="hidden_field" autocomplete="off" tabindex="-1" style="display:none;" aria-hidden="true">
                        <p style="text-align:center; margin-bottom: 25px; color: #a3a3a3;">Join the war today! Registration
                            is fast and entirely free.</p>

                        <?php if (!empty($message))
                            echo $message; ?>

                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" placeholder="Enter desired username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Enter password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Repeat password" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="example@domain.com" required>
                        </div>
                        <div class="form-group">
                            <label>Security Question: What is <?php echo $_SESSION['captcha_num1']; ?> +
                                <?php echo $_SESSION['captcha_num2']; ?> ?</label>
                            <input type="number" name="captcha" placeholder="Enter the sum" required
                                style="width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px; font-family: inherit; outline: none;">
                        </div>
                        <div class="form-group" style="text-align: center; margin-top: 30px;">
                            <label
                                style="cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                                <input type="checkbox" required
                                    style="width: auto; margin: 0 8px 0 0; display: inline-block;">
                                <span>I agree to the <a href="/rules"
                                        style="color: var(--text-gold); text-decoration: none;">Server Rules</a></span>
                            </label>
                        </div>
                        <button type="submit" class="btn-submit" style="margin-top: 20px;">Create Account</button>

                        <div style="text-align: center; margin-top: 20px;">
                            <a href="/" style="font-size: 13px; color: #9ca3af;">Already have an account? Back to
                                Home.</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div
                        style="max-width: 600px; margin: 0 auto; text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                        <i class="fas fa-tools"
                            style="font-size: 48px; color: var(--text-gold); opacity: 0.5; margin-bottom: 20px;"></i>
                        <h3 style="font-family: 'Cinzel', serif; color: #fff; margin-bottom: 15px;">Registration is
                            currently unavailable</h3>
                        <p style="color: #a3a3a3; line-height: 1.6;">Account registrations are not available at this moment.
                            We are likely performing maintenance or reached a temporary limit. Please check back again soon!
                        </p>
                        <div style="margin-top: 30px;">
                            <a href="/" class="btn-submit"
                                style="text-decoration: none; display: inline-block; padding: 10px 30px;">Back to Home</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include 'modules/footer.php'; ?>