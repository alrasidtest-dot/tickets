<?php
/**
 * Global constants: filesystem paths, base URL and upload limits.
 * Included early from public/index.php.
 */

// Absolute path to the project root (one level above public/).
define('BASE_PATH', dirname(__DIR__));

// Key directories outside the document root.
define('CONFIG_PATH',      BASE_PATH . '/config');
define('CORE_PATH',        BASE_PATH . '/core');
define('MODELS_PATH',      BASE_PATH . '/models');
define('CONTROLLERS_PATH', BASE_PATH . '/controllers');
define('VIEWS_PATH',       BASE_PATH . '/views');
define('LANG_PATH',        BASE_PATH . '/lang');
define('UPLOADS_PATH',     BASE_PATH . '/uploads');

// Base URL for building links (single entry point: public/index.php).
define('BASE_URL', '/index.php');

// Upload limits (used from phase 4 onward).
// Allowed extensions are the single source of truth and must match the list in
// docs/SECURITY_AUTH.md verbatim (one canonical rule — no duplicate lists).
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx']);

// Default UI language.
define('DEFAULT_LANG', 'ar');
