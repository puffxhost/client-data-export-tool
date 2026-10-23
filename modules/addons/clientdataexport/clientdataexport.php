<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * WHMCS Client Data Export addon.
 */
function clientdataexport_config()
{
    return [
        'name' => 'Client Data Export',
        'description' => 'Export an allowlisted client profile as JSON or CSV.',
        'version' => '0.1.0',
        'author' => 'Puffx Host',
        'language' => 'english',
        'fields' => [],
    ];
}

function clientdataexport_activate()
{
    return [
        'status' => 'success',
        'description' => 'Client Data Export activated. Restrict access to trusted administrator roles.',
    ];
}

function clientdataexport_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Client Data Export deactivated.',
    ];
}

/**
 * Keep this list explicit. Do not add credentials, payment data, or free-form
 * database columns without reviewing the privacy and access implications.
 */
function clientdataexport_profile_fields()
{
    return [
        'id',
        'firstname',
        'lastname',
        'companyname',
        'email',
        'address1',
        'address2',
        'city',
        'state',
        'postcode',
        'country',
        'phonenumber',
        'datecreated',
        'status',
    ];
}

/** Prevent spreadsheet applications from interpreting text cells as formulas. */
function clientdataexport_csv_safe_value($value)
{
    $value = (string) $value;
    if (preg_match('/^[\t\r ]*[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function clientdataexport_download($client, $format)
{
    $fields = clientdataexport_profile_fields();
    $record = [];
    foreach ($fields as $field) {
        $record[$field] = isset($client->{$field}) ? $client->{$field} : '';
    }

    // Do not let WHMCS page markup corrupt a machine-readable download.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $filename = 'client-' . (int) $client->id . '-' . gmdate('Y-m-d') . '.' . $format;
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for spreadsheet compatibility.
    fputcsv($output, $fields);
    $row = [];
    foreach ($fields as $field) {
        $row[] = clientdataexport_csv_safe_value($record[$field]);
    }
    fputcsv($output, $row);
    fclose($output);
    exit;
}

function clientdataexport_output($vars)
{
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Fail closed when WHMCS CSRF helpers are unavailable.
        if (!function_exists('check_token') || !check_token('WHMCS.admin.default')) {
            $error = 'The security token is invalid or expired. Reload the page and try again.';
        } else {
            $clientId = isset($_POST['client_id']) ? filter_var($_POST['client_id'], FILTER_VALIDATE_INT) : false;
            $format = isset($_POST['format']) ? $_POST['format'] : '';

            if ($clientId === false || $clientId < 1) {
                $error = 'Enter a valid positive client ID.';
            } elseif (!in_array($format, ['json', 'csv'], true)) {
                $error = 'Choose JSON or CSV format.';
            } else {
                try {
                    $client = Capsule::table('tblclients')
                        ->select(clientdataexport_profile_fields())
                        ->where('id', (int) $clientId)
                        ->first();

                    if (!$client) {
                        $error = 'No client was found for that ID.';
                    } else {
                        clientdataexport_download($client, $format);
                    }
                } catch (Exception $exception) {
                    // Avoid rendering SQL errors or internal details in the browser.
                    $error = 'The export could not be completed. Check the WHMCS database configuration and server logs.';
                }
            }
        }
    }

    echo '<div class="container-fluid">';
    echo '<h2>Client Data Export</h2>';
    echo '<p>Export one client’s allowlisted profile fields. Passwords, payment details, invoices, tickets, and internal notes are excluded.</p>';

    if ($error !== '') {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    $token = function_exists('generate_token') ? generate_token('plain') : '';
    if ($token === '') {
        echo '<div class="alert alert-danger">WHMCS could not create a security token. Reload the admin area before exporting.</div>';
    } else {
        echo '<form method="post" action="' . htmlspecialchars(isset($vars['modulelink']) ? $vars['modulelink'] : '', ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="form-group">';
        echo '<label for="client_id">Client ID</label>';
        echo '<input class="form-control" type="number" min="1" step="1" name="client_id" id="client_id" required>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label for="format">Export format</label>';
        echo '<select class="form-control" name="format" id="format" required>';
        echo '<option value="json">JSON</option><option value="csv">CSV</option>';
        echo '</select>';
        echo '</div>';
        echo '<button class="btn btn-primary" type="submit">Export client profile</button>';
        echo '</form>';
    }

    echo '<hr><p><strong>Privacy:</strong> Export files contain personal data. Handle them securely and delete them when they are no longer needed.</p>';
    echo '</div>';
}

