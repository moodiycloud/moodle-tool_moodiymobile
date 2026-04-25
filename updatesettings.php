<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Update endpoint for Moodiy integration.
 *
 * @package    tool_moodiymobile
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output.
define('NO_DEBUG_DISPLAY', true);

// We need to use the right AJAX has_capability() check.
define('AJAX_SCRIPT', true);

// No need for Moodle cookies here (avoid session locking).
define('NO_MOODLE_COOKIES', true);

// Allow direct access to this endpoint without login requirement.
define('NO_REDIRECT_ON_UPGRADE', true);

require_once('../../../config.php');

// Set the appropriate content type for JSON responses.
header('Content-Type: application/json; charset=utf-8');

// Allow CORS requests from the Laravel application.
$apiorigin = \tool_moodiyregistration\api::get_api_origin();
header('Access-Control-Allow-Origin: ' . $apiorigin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Key');

$requestmethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

// Handle OPTIONS request (preflight).
if ($requestmethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests.
if ($requestmethod !== 'POST') {
    http_response_code(405); // Method Not Allowed.
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST method is allowed',
    ]);
    exit;
}

/**
 * Get header key - reliable cross-server method
 */
function get_all_headers() {
    $headers = [];

    // If getallheaders() is available (Apache), use it.
    if (function_exists('getallheaders')) {
        return getallheaders();
    }

    // Otherwise manually extract headers from $_SERVER.
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) === 'HTTP_') {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$name] = $value;
        } else if ($name === 'CONTENT_TYPE') {
            $headers['Content-Type'] = $value;
        } else if ($name === 'CONTENT_LENGTH') {
            $headers['Content-Length'] = $value;
        } else if ($name === 'AUTHORIZATION') {
            $headers['Authorization'] = $value;
        }
    }

    return $headers;
}

// Get header key.
$headerkey = '';
$headers = get_all_headers();
foreach ($headers as $key => $value) {
    if (strtolower($key) === 'key') {
        $headerkey = $value;
        break;
    }
}

// Get the airnotifier settings from the POST data.
$input = file_get_contents('php://input');
$postdata = json_decode($input, true);
if (!is_array($postdata)) {
    http_response_code(400); // Bad Request.
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON provided: ' . json_last_error_msg(),
    ]);
    exit;
} else if (isset($postdata['data']) && is_array($postdata['data']) && array_key_exists('airnotifieraccesskey', $postdata['data'])) {
    ksort($postdata['data']);
    $data = json_encode($postdata['data']);
    if ($data === false) {
        http_response_code(400); // Bad Request.
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid data',
        ]);
        exit;
    }

    // Check for valid payload.
    $siteuuid = \tool_moodiyregistration\registration::get_siteuuid();
    if (empty($siteuuid)) {
        http_response_code(403); // Forbidden.
        echo json_encode([
            'status' => 'error',
            'message' => 'Site is not registered',
        ]);
        exit;
    }
    $hmackey = hash_hmac('sha256', $data, $siteuuid);

    if (!hash_equals($hmackey, $headerkey)) {
        // Invalid HMAC key.
        http_response_code(403); // Forbidden.
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid HMAC key',
        ]);
        exit;
    }
    // HMAC validation stays first; freshness is enforced only for timestamp-aware callers.
    if (
        array_key_exists('timestamp', $postdata['data'])
        && !\tool_moodiymobile\utility::is_fresh_callback_timestamp($postdata['data']['timestamp'])
    ) {
        http_response_code(400); // Bad Request.
        echo json_encode(\tool_moodiymobile\utility::stale_timestamp_error_response());
        exit;
    }
    try {
        \tool_moodiymobile\utility::store_airnotifier_settings($postdata['data']);
        \tool_moodiymobile\utility::apply_airnotifier_settings($postdata['data']);
        \tool_moodiymobile\utility::enable_internal_site_with_airnotifier($postdata['data']);
        $response = [
            'status' => 'success',
        ];
    } catch (Exception $e) {
        // Handle exception.
        $response = [
            'status' => 'error',
            'message' => 'Failed to update settings: ' . $e->getMessage(),
        ];
    }
    echo json_encode($response);
    exit;
} else {
    // Invalid verification.
    http_response_code(403); // Forbidden.
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid data',
    ]);
    exit;
}
