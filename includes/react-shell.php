<?php
declare(strict_types=1);

// Existing POST handlers continue to own writes and their validation.
if (!is_post_request()) {
    $manifestPath = BASE_PATH . '/assets/build/.vite/manifest.json';
    if (is_file($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $entry = $manifest['frontend/main.jsx'];
        $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $baseUrl = in_array(basename($scriptDirectory), ['documents', 'reports'], true)
            ? dirname($scriptDirectory) : $scriptDirectory;
        $baseUrl = rtrim(str_replace('\\', '/', $baseUrl), '/') . '/';
        header('Cache-Control: no-store');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>B-TRACK / Barangay records</title>
    <link rel="icon" href="data:,">
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400..700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>assets/css/theme.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>assets/css/<?= in_array(basename($_SERVER['SCRIPT_NAME']), ['login.php', 'register.php'], true) ? 'style' : 'dashboard' ?>.css">
    <?php foreach ($entry['css'] ?? [] as $css): ?>
        <link rel="stylesheet" href="<?= e($baseUrl . 'assets/build/' . $css) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= e($baseUrl) ?>assets/css/ui-system.css">
</head>
<body>
    <div id="btrack-app" data-base="<?= e($baseUrl) ?>" aria-label="B-TRACK workspace"></div>
    <noscript>Enable JavaScript to use the interactive dashboard.</noscript>
    <script type="module" src="<?= e($baseUrl . 'assets/build/' . $entry['file']) ?>"></script>
</body>
</html>
        <?php
        exit;
    }
}
