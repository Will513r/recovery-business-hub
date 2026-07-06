<?php
// config.php
// Guarded so pages that already called session_start() don't emit notices
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- LOAD ENVIRONMENT VARIABLES ---
// A simple loader for raw PHP environments (like basic Hostinger setups).
// If you are using Composer and phpdotenv, you can remove this block.
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    $env_vars = parse_ini_file($env_path);
} else {
    die("Configuration error: .env file is missing.");
}

// Store your database credentials securely using the loaded environment variables.
// On Hostinger, your host is usually 'localhost' or an IP provided in your panel.
$db_host = $env_vars['DB_HOST'] ?? 'localhost';
$db_user = $env_vars['DB_USER'] ?? '';
$db_pass = $env_vars['DB_PASS'] ?? '';
$db_name = $env_vars['DB_NAME'] ?? '';

// --- STRIPE PAYMENT LINKS ---
define('STRIPE_PAID_URL',    $env_vars['STRIPE_PAID_URL'] ?? '');
define('STRIPE_PREMIUM_URL', $env_vars['STRIPE_PREMIUM_URL'] ?? '');

// --- NOTIFICATION EMAIL (authenticated SMTP) ---
// Raw mail() with a spoofed From silently fails on Hostinger (same bug that
// broke the CRM intake until 2026-07-03). This sends through a real mailbox
// over SMTPS instead. Needs SMTP_HOST/SMTP_PORT/SMTP_USER/SMTP_PASS in .env.
// Returns true on accepted delivery; logs and returns false on any failure.
function rbh_send_email(string $to, string $subject, string $body): bool
{
    global $env_vars;
    $host = $env_vars['SMTP_HOST'] ?? '';
    $port = (int)($env_vars['SMTP_PORT'] ?? 465);
    $user = $env_vars['SMTP_USER'] ?? '';
    $pass = $env_vars['SMTP_PASS'] ?? '';

    if ($host === '' || $user === '' || $pass === '') {
        error_log('[rbh mail] SMTP not configured in .env, notification not sent');
        return false;
    }

    $fp = @stream_socket_client("ssl://$host:$port", $errno, $errstr, 10);
    if (!$fp) {
        error_log("[rbh mail] connect failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 10);

    // Send one SMTP command and require the expected status code
    $say = function (?string $cmd, string $expect) use ($fp): bool {
        if ($cmd !== null) {
            fwrite($fp, $cmd . "\r\n");
        }
        // Read the full (possibly multi-line) response
        do {
            $line = fgets($fp, 512);
            if ($line === false) {
                return false;
            }
        } while (isset($line[3]) && $line[3] === '-');
        if (strpos($line, $expect) !== 0) {
            error_log('[rbh mail] SMTP said: ' . trim($line) . ' after ' . ($cmd === null ? '(connect)' : strtok($cmd, ' ')));
            return false;
        }
        return true;
    };

    // No user input goes into headers here; subject/body are sanitized by callers
    $ok = $say(null, '220')
        && $say('EHLO recoverybusinesshub.com', '250')
        && $say('AUTH LOGIN', '334')
        && $say(base64_encode($user), '334')
        && $say(base64_encode($pass), '235')
        && $say("MAIL FROM:<$user>", '250')
        && $say("RCPT TO:<$to>", '250')
        && $say('DATA', '354')
        && $say(
            "From: Recovery Business Hub <$user>\r\n" .
            "To: <$to>\r\n" .
            'Subject: ' . str_replace(["\r", "\n"], ' ', $subject) . "\r\n" .
            "Date: " . date('r') . "\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "\r\n" .
            str_replace("\n.", "\n..", $body) . "\r\n.",
            '250'
        );

    if ($ok) {
        fwrite($fp, "QUIT\r\n");
    }
    fclose($fp);
    return $ok;
}

// Set the master timezone for the application (Eastern Time)
date_default_timezone_set('America/New_York');

// Disable mysqli exceptions so connect_error can be checked manually (PHP 8.1+ default changed)
mysqli_report(MYSQLI_REPORT_OFF);

// Create a new MySQLi connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check if the connection was successful
if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error); // log privately
    die("A database error occurred. Please try again later.");
}

// Optional: Set character set to utf8mb4 for proper text encoding
$conn->set_charset("utf8mb4");

// --- HELPER FUNCTIONS ---

// Business Hours Processing
function parse_hours_post(): ?string
{
    if (!isset($_POST['hours']) || !is_array($_POST['hours'])) {
        return null;
    }
    $hours_data = [];
    foreach ($_POST['hours'] as $day => $data) {
        $hours_data[$day] = [
            'is_closed' => isset($data['is_closed']),
            'open'      => $data['open']  ?? '',
            'close'     => $data['close'] ?? '',
        ];
    }
    return !empty($hours_data) ? json_encode($hours_data) : null;
}

// Handle Logo Uploads securely
function handle_logo_upload(): string|false
{
    if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
        return false; // No upload attempted
    }
    $file_tmp  = $_FILES['logo_file']['tmp_name'];
    $file_size = $_FILES['logo_file']['size'];

    if ($file_size > 2 * 1024 * 1024) {
        die("<p style='color:red; text-align:center;'>Error: Image must be smaller than 2MB.</p>");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file_tmp);
    $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];

    if (!array_key_exists($mime, $allowed)) {
        die("<p style='color:red; text-align:center;'>Error: Only JPG, PNG, and WEBP allowed.</p>");
    }

    $new_filename = uniqid('logo_') . '_' . bin2hex(random_bytes(4)) . $allowed[$mime];
    $destination  = 'uploads/' . $new_filename;

    if (!move_uploaded_file($file_tmp, $destination)) {
        die("<p style='color:red; text-align:center;'>Failed to save uploaded image.</p>");
    }

    return $destination;
}

// CSRF helpers
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die("Invalid request.");
    }
}

// --- SHARED DATA LISTS ---

// Expanded category list for better coverage
$categories = [
    'Accounting & Tax Prep',
    'Automotive Repair',
    'Beauty & Salons',
    'Childcare & Daycare',
    'Cleaning Services',
    'Concrete & Paving',
    'Consulting',
    'Creative Services',
    'Decks & Patios',
    'Electrical',
    'Event Planning & Catering',
    'Fitness & Gyms',
    'Food & Dining',
    'General Construction',
    'HVAC',
    'Health & Wellness',
    'Insurance Agencies',
    'Landscaping & Lawn Care',
    'Legal Services',
    'Marketing & Social Media',
    'Painting',
    'Pet Services',
    'Plumbing',
    'Real Estate & Property Mgmt',
    'Retail',
    'Roofing & Gutters',
    'Transportation & Moving',
    'Tree Services'
];
sort($categories); // Keep them alphabetical automatically

$cities_list = [
    // --- MAJOR US METRO AREAS ---
    'Atlanta',
    'Austin',
    'Baltimore',
    'Boston',
    'Charlotte',
    'Chicago',
    'Cleveland',
    'Columbus',
    'Dallas',
    'Denver',
    'Detroit',
    'Houston',
    'Indianapolis',
    'Jacksonville',
    'Kansas City',
    'Las Vegas',
    'Los Angeles',
    'Louisville',
    'Memphis',
    'Miami',
    'Milwaukee',
    'Minneapolis',
    'Nashville',
    'New Orleans',
    'New York City',
    'Oklahoma City',
    'Orlando',
    'Philadelphia',
    'Phoenix',
    'Pittsburgh',
    'Portland',
    'Raleigh',
    'Sacramento',
    'San Antonio',
    'San Diego',
    'San Francisco',
    'San Jose',
    'Seattle',
    'St. Louis',
    'Tampa',
    'Washington D.C.',

    // --- CINCINNATI TRI-STATE FOCUS ---
    'Cincinnati',
    'Blue Ash',
    'Fairfield',
    'Hamilton',
    'Harrison',
    'Loveland',
    'Mason',
    'Middletown',
    'Milford',
    'Montgomery',
    'North Bend',
    'Norwood',
    'Reading',
    'Sharonville',
    'Springdale',
    'West Chester',
    'Wyoming',
    'Alexandria',
    'Bellevue',
    'Burlington',
    'Covington',
    'Dayton',
    'Erlanger',
    'Florence',
    'Fort Mitchell',
    'Fort Thomas',
    'Independence',
    'Newport',
    'Union',
    'Wilder',
    'Aurora',
    'Greendale',
    'Lawrenceburg'
];
sort($cities_list);

$states_list = [
    'AL',
    'AK',
    'AZ',
    'AR',
    'CA',
    'CO',
    'CT',
    'DE',
    'FL',
    'GA',
    'HI',
    'ID',
    'IL',
    'IN',
    'IA',
    'KS',
    'KY',
    'LA',
    'ME',
    'MD',
    'MA',
    'MI',
    'MN',
    'MS',
    'MO',
    'MT',
    'NE',
    'NV',
    'NH',
    'NJ',
    'NM',
    'NY',
    'NC',
    'ND',
    'OH',
    'OK',
    'OR',
    'PA',
    'RI',
    'SC',
    'SD',
    'TN',
    'TX',
    'UT',
    'VT',
    'VA',
    'WA',
    'WV',
    'WI',
    'WY'
];
