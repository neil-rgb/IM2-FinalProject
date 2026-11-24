<?php
$noticeKey = isset($_GET['notice']) && is_string($_GET['notice']) ? $_GET['notice'] : '';
$notices = [
    'duplicate' => [
        'type' => 'error',
        'title' => 'Username unavailable',
        'message' => 'That username is already registered. Choose a different username and try again.',
    ],
    'invalid' => [
        'type' => 'error',
        'title' => 'Account not created',
        'message' => 'Some account details are invalid. Review the form and submit it again.',
    ],
    'created' => [
        'type' => 'success',
        'title' => 'Secretary account created',
        'message' => 'The account was saved successfully. You can now return to the sign-in page.',
    ],
];

$notice = $notices[$noticeKey] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create a B-TRACK Barangay Document Management System account">
    <title>B-TRACK / Create Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="login-shell">
        <section class="login-card" aria-labelledby="register-title">
            <header class="system-header">
                <div class="brand-lockup">
                    <span class="brand-mark" aria-hidden="true">BT</span>
                    <div>
                        <p class="brand-name">B-TRACK</p>
                        <p class="brand-description">Barangay Document Management System</p>
                    </div>
                </div>

                <div class="system-meta" aria-label="System information">
                    <span>PORTAL / 02</span>
                    <span class="system-status"><i aria-hidden="true"></i> SECRETARY ACCESS</span>
                </div>
            </header>

            <div class="login-content">
                <div class="section-label" aria-hidden="true">
                    <span>[ SECRETARY REGISTRY ]</span>
                    <span>NEW / ACCOUNT</span>
                </div>

                <div class="intro">
                    <h1 id="register-title">Create account</h1>
                </div>

                <?php if ($notice): ?>
                    <div class="notice notice--<?= htmlspecialchars($notice['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
                        <span class="notice-code" aria-hidden="true">[!]</span>
                        <div>
                            <strong><?= htmlspecialchars($notice['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= htmlspecialchars($notice['message'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="validation-summary" id="validation-summary" role="alert" tabindex="-1" hidden>
                    <strong>Check the required fields</strong>
                    <p>Complete every field and make sure both passwords match.</p>
                </div>

                <form class="login-form" id="register-form" action="register.php" method="post" data-auth-form novalidate>
                    <div class="field-group">
                        <div class="field-heading">
                            <label for="full_name">Full name <span aria-hidden="true">*</span></label>
                            <span>STAFF NAME</span>
                        </div>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            autocomplete="name"
                            required
                            data-error-message="Full name is required."
                            aria-describedby="full_name-error"
                        >
                        <p class="field-error" id="full_name-error" aria-live="polite"></p>
                    </div>

                    <div class="field-group">
                        <div class="field-heading">
                            <label for="username">Username <span aria-hidden="true">*</span></label>
                            <span>USER ID</span>
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
                            <span>PASS KEY</span>
                        </div>
                        <div class="password-field">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                required
                                data-error-message="Password is required."
                                aria-describedby="password-error"
                            >
                            <button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password">
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

                    <div class="field-group">
                        <div class="field-heading">
                            <label for="confirm_password">Confirm password <span aria-hidden="true">*</span></label>
                            <span>VERIFY KEY</span>
                        </div>
                        <div class="password-field">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                required
                                data-error-message="Password confirmation is required."
                                data-match="password"
                                data-match-message="Passwords must match."
                                aria-describedby="confirm_password-error"
                            >
                            <button class="password-toggle" type="button" data-password-toggle aria-controls="confirm_password" aria-pressed="false" aria-label="Show password confirmation">
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
                        <p class="field-error" id="confirm_password-error" aria-live="polite"></p>
                    </div>

                    <div class="account-option">
                        <span>Already registered?</span>
                        <a href="login.php">Sign in</a>
                    </div>

                    <button class="submit-button" type="submit">
                        <span>Create account</span>
                        <span aria-hidden="true">-&gt;</span>
                    </button>
                </form>
            </div>

            <footer class="system-footer">
                <span>ACCOUNT CONTROL / LOCAL OPERATIONS</span>
                <span>VER. 01 / <?= date('Y') ?></span>
            </footer>
        </section>

        <p class="privacy-note">New accounts are intended only for authorized barangay secretaries.</p>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
