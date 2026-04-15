<?php
// ══════════════════════════════════════════════════════════════
// Environment toggle
// ══════════════════════════════════════════════════════════════
// Change ONE word here to switch between local dev and production:
//   'development' → skip DB entirely, all pages show mock/preview data
//   'production'  → connect to the real RADIUS MySQL on the VM
define('APP_ENV', 'development');

// ── Database credentials (used only in production) ─────────────
define('DB_SERVER',   '192.168.1.2');   // VM IP
define('DB_USERNAME', 'radius');
define('DB_PASSWORD', 'lab2lab2');
define('DB_DATABASE', 'radius');

// ── Connect (production only) ───────────────────────────────────
if (APP_ENV === 'production') {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    } catch (Exception $e) {
        // VM unreachable — fall back gracefully to mock data
        $db = false;
    }
} else {
    // Development: no DB connection, pages use built-in mock data
    $db = false;
}
?>
