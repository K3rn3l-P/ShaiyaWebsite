<?php
require_once 'security_bootstrap.php';
appApplySecurityHeaders();
appInitSession();
require_once 'db.php';
require_once 'db_pdo.php';

if (!isset($_SESSION['user'])) {
    header("Location: /");
    exit;
}

$username = $_SESSION['user'];
$message = '';

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = '<div style="color: #00ff00; text-align: center; margin-bottom: 15px; font-weight: bold;">Password changed successfully!</div>';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!appVerifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Session expired. Please refresh and try again.</div>';
    } elseif (!appRateLimitAllow('change_password:' . appGetClientIp() . ':' . strtolower($username), 6, 900)) {
        $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Too many attempts. Please wait a few minutes.</div>';
    } else {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">New passwords do not match.</div>';
    } elseif ($new_password === $current_password) {
        $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Your new password cannot be the same as your current password.</div>';
    } elseif (strlen($new_password) > 12) {
        $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">New password cannot exceed 12 characters.</div>';
    } elseif (strlen($new_password) < 4) {
        $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">New password must be at least 4 characters long.</div>';
    } else {
        // ✅ SECURE: Verify current password against Enpassword (hashed) or Pw (plaintext for migration)
        $verify_query = "SELECT UserID, Enpassword, Pw FROM PS_UserData.dbo.Users_Master WHERE UserID = ?";
        try {
            $verify_stmt = $pdoAdmin->prepare($verify_query);
            $verify_stmt->execute([$username]);
        } catch (Throwable $e) {
            error_log('Change password verify error for user ' . $username . ': ' . $e->getMessage());
            $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Database error. Please try again later.</div>';
        }

        if (isset($verify_stmt) && $user_row = $verify_stmt->fetch(PDO::FETCH_ASSOC)) {
            $enpasswordHash = (string) ($user_row['Enpassword'] ?? '');
            $plainPassword = (string) ($user_row['Pw'] ?? '');
            $currentPasswordValid = false;

            // ✅ PRIMARY: Verify against hashed password
            if (!empty($enpasswordHash)) {
                $currentPasswordValid = password_verify($current_password, $enpasswordHash);
            }
            // ✅ FALLBACK: Verify against plaintext (migration pattern)
            elseif (!empty($plainPassword) && $current_password === $plainPassword) {
                $currentPasswordValid = true;
            }

            if ($currentPasswordValid) {
                // ✅ Hash the new password
                $newPasswordHash = password_hash($new_password, PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536,
                    'time_cost' => 4,
                    'parallelism' => 1
                ]);

                // Update password (both Pw for legacy compatibility and Enpassword for new verification)
                $update_query = "UPDATE PS_UserData.dbo.Users_Master SET Pw = ?, Enpassword = ?, PasswordHashedAt = GETDATE() WHERE UserID = ?";
                try {
                    $update_stmt = $pdoAdmin->prepare($update_query);
                    $update_stmt->execute([$new_password, $newPasswordHash, $username]);
                    appRateLimitReset('change_password:' . appGetClientIp() . ':' . strtolower($username));
                    appRotateCsrfToken();
                    appSecurityLog('Password changed for user: ' . $username);
                    header("Location: /change_password?success=1");
                    exit;
                } catch (Throwable $e) {
                    error_log('Change password failed for user ' . $username . ': ' . $e->getMessage());
                    $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Database error. Please try again later.</div>';
                }
            } else {
                $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">Incorrect current password.</div>';
            }
        } else {
            $message = '<div style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;">User not found.</div>';
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
    <title>Change Password | Shaiya Duff</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php $active_page = 'change_password'; ?>
    <div class="main-wrapper">
        <?php include 'modules/header.php'; ?>
        <?php include 'modules/nav.php'; ?>

        <div class="content-area-box">
            <div class="content-header"
                style="display: flex; justify-content: space-between; align-items: center; padding-right: 20px;">
                <h2 style="margin: 0;"><i class="fas fa-key"></i> Change Password</h2>
                <a href="/account" class="admin-back-btn"><i class="fas fa-arrow-left"></i> Back to Account</a>
            </div>
            <div class="content-body" style="padding: 40px;">
                <form class="generic-form" style="max-width: 500px; margin: 0 auto;" method="POST"
                    action="/change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <p style="text-align:center; margin-bottom: 25px; color: #a3a3a3;">Secure your account by changing
                        your login password.</p>

                    <?php if (!empty($message))
                        echo $message; ?>

                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter your current password"
                            required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password (Max 12 chars)"
                            required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Repeat new password" required>
                    </div>

                    <button type="submit" class="btn-submit" style="margin-top: 20px;">Update Password</button>


                </form>
            </div>
        </div>
    </div>
    <?php include 'modules/footer.php'; ?>