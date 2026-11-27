<?php
require_once __DIR__ . '/includes/auth.php';

require_guest();

$loginError = '';

if (is_post_request()) {
    $username = post_value('username');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $csrfToken = post_value('_token');

    if (!verify_csrf_token($csrfToken)) {
        $loginError = 'Your form session expired. Refresh the page and try again.';
    } elseif ($username === '' || $password === '') {
        $loginError = 'Enter both your username and password before continuing.';
    } else {
        try {
            $statement = db()->prepare(
                'SELECT user_id, full_name, username, password_hash, status FROM Users WHERE username = :username LIMIT 1'
            );
            $statement->execute(['username' => $username]);
            $user = $statement->fetch();

            if (!$user || $user['status'] !== 'Active' || !password_verify($password, $user['password_hash'])) {
                $loginError = 'The username or password is incorrect, or the account is inactive.';
            } else {
                login_user([
                    'id' => $user['user_id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => 'Secretary',
                ]);
                redirect('dashboard.php');
            }
        } catch (PDOException $exception) {
            error_log('B-TRACK login database error: ' . $exception->getMessage());
            $loginError = 'Unable to connect to the account database. Make sure MySQL is running and the btrack database is imported.';
        }
    }
}

$noticeKey = isset($_GET['notice']) && is_string($_GET['notice']) ? $_GET['notice'] : '';
$notices = [
    'invalid' => [
        'type' => 'error',
        'title' => 'Access denied',
        'message' => 'The username or password is incorrect. Please check your details and try again.',
    ],
    'unauthorized' => [
        'type' => 'warning',
        'title' => 'Unauthorized action',
        'message' => 'Your account does not have permission to open that page. Sign in with an authorized account.',
    ],
    'signed-out' => [
        'type' => 'success',
        'title' => 'Session closed',
        'message' => 'You have been signed out successfully.',
    ],
];

$notice = pull_flash() ?? ($notices[$noticeKey] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="B-TRACK Barangay Document Management System staff login">
    <title>B-TRACK / Staff Access</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400..700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="login-shell">
        <section class="login-card" aria-labelledby="login-title">
            <header class="system-header">
                <div class="brand-lockup">
                    <span class="brand-mark" aria-hidden="true">BT</span>
                    <div>
                        <p class="brand-name">B-TRACK</p>
                        <p class="brand-description">Barangay records</p>
                    </div>
                </div>

            </header>

            <div class="login-content">
                <div class="intro">
                    <h1 id="login-title">Sign in</h1>
                </div>

                <?php if ($notice): ?>
                    <div class="notice notice--<?= htmlspecialchars($notice['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
                        <div>
                            <strong><?= htmlspecialchars($notice['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= htmlspecialchars($notice['message'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($loginError !== ''): ?>
                    <div class="notice notice--error" role="alert">
                        <div><strong>Sign in failed</strong><p><?= e($loginError) ?></p></div>
                    </div>
                <?php endif; ?>

                <div class="validation-summary" id="validation-summary" role="alert" tabindex="-1" hidden>
                    <strong>Check the required fields</strong>
                    <p>Enter both your username and password before continuing.</p>
                </div>

                <form class="login-form" id="login-form" action="login.php" method="post" data-auth-form novalidate>
                    <?= csrf_field() ?>
                    <div class="field-group">
                        <div class="field-heading">
                            <label for="username">Username <span aria-hidden="true">*</span></label>
                        </div>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                            data-error-message="Username is required."
                            aria-describedby="username-error"
                        >
                        <p class="field-error" id="username-error" aria-live="polite"></p>
                    </div>

                    <div class="field-group">
                        <div class="field-heading">
                            <label for="password">Password <span aria-hidden="true">*</span></label>
                        </div>
                        <div class="password-field">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                required
                                data-error-message="Password is required."
                                aria-describedby="password-error"
                            >
                            <button class="password-toggle" type="button" id="password-toggle" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password">
                                <svg class="eye-icon eye-icon--show" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                    <circle cx="12" cy="12" r="2.75"></circle>
                                </svg>
                                <svg class="eye-icon eye-icon--hide" viewBox="0 0 24 24" aria-hidden="true" hidden>
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 6.2A9.8 9.8 0 0 1 12 6c6 0 9.5 6 9.5 6a15.5 15.5 0 0 1-3.1 3.7M6.1 6.1C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 3.1-.5M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                                </svg>
                            </button>
                        </div>
                        <p class="field-error" id="password-error" aria-live="polite"></p>
                    </div>

                    <div class="account-option">
                        <span>New to B-TRACK?</span>
                        <a href="register.php">Create account</a>
                    </div>

                    <button class="submit-button" type="submit">
                        <span>Enter system</span>
                        <span aria-hidden="true">-&gt;</span>
                    </button>
                </form>
            </div>

        </section>

        <p class="privacy-note">For authorized barangay personnel.</p>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>

