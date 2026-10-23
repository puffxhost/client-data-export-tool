<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use PuffxHost\ClientDataExport\Exporter;
use PuffxHost\ClientDataExport\FormatWriter;

require_once __DIR__ . '/lib/Exporter.php';
require_once __DIR__ . '/lib/FormatWriter.php';

function clientdataexport_config()
{
    return [
        'name' => 'Client Data Export',
        'description' => 'Preview and export an allowlisted client data package as JSON or CSV.',
        'version' => '1.0.0',
        'author' => 'Puffx Host',
        'language' => 'english',
        'fields' => [],
    ];
}

function clientdataexport_activate()
{
    return [
        'status' => 'success',
        'description' => 'Client Data Export activated. Assign addon access only to trusted administrator roles.',
    ];
}

function clientdataexport_upgrade($vars)
{
    // Version 1.0 adds no custom tables or persistent settings.
    return ['status' => 'success', 'description' => 'Client Data Export upgraded.'];
}

function clientdataexport_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Client Data Export deactivated. No export files or custom database tables are retained.',
    ];
}

function clientdataexport_output($vars)
{
    $error = '';
    $preview = null;
    $submitted = [
        'client_id' => '',
        'format' => 'json',
        'sections' => [],
    ];

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!function_exists('check_token') || !check_token('WHMCS.admin.default')) {
            $error = 'The security token is invalid or expired. Reload the page and try again.';
        } else {
            try {
                $submitted = clientdataexport_validate_request($_POST);
                $exporter = new Exporter();
                $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

                if ($action === 'preview') {
                    $preview = $exporter->preview($submitted['client_id'], $submitted['sections']);
                } elseif ($action === 'download') {
                    if (!isset($_POST['confirm_export']) || $_POST['confirm_export'] !== '1') {
                        throw new RuntimeException('Confirm that you are authorized to export this client’s data.');
                    }
                    $payload = $exporter->export($submitted['client_id'], $submitted['sections']);
                    $body = $submitted['format'] === 'csv'
                        ? FormatWriter::encodeCsv($payload)
                        : FormatWriter::encodeJson($payload);
                    if (headers_sent()) {
                        throw new RuntimeException('The download could not start because output was already sent. Reload the page and try again.');
                    }
                    clientdataexport_audit($submitted, $payload);
                    clientdataexport_send_download($body, $submitted['format'], $submitted['client_id']);
                } else {
                    throw new RuntimeException('Choose Preview before downloading an export.');
                }
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = 'The export could not be completed. Check the WHMCS database configuration and server logs.';
                if (function_exists('logActivity')) {
                    $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
                    logActivity('Client Data Export: request failed for administrator ID ' . $adminId . '.');
                }
            }
        }
    }

    $token = function_exists('generate_token') ? generate_token('plain') : '';
    echo '<div class="container-fluid">';
    echo '<h2>Client Data Export</h2>';
    echo '<p>Preview the client and record counts before confirming an export. The addon does not retain or transmit exported data.</p>';

    if ($error !== '') {
        echo '<div class="alert alert-danger" role="alert">' . clientdataexport_h($error) . '</div>';
    }

    if ($token === '') {
        echo '<div class="alert alert-danger" role="alert">WHMCS could not create a security token. Reload the admin area before exporting.</div>';
    } elseif ($preview !== null) {
        clientdataexport_render_confirmation($vars, $token, $submitted, $preview);
    } else {
        clientdataexport_render_form($vars, $token, $submitted);
    }

    echo '<hr><p><strong>Privacy:</strong> Export files contain personal data. Restrict addon access, store files securely, and delete them when no longer needed.</p>';
    echo '</div>';
}

/** Validate all form values as untrusted input. */
function clientdataexport_validate_request($input)
{
    if (!is_array($input)) {
        throw new RuntimeException('The submitted form is invalid.');
    }

    $rawId = isset($input['client_id']) ? $input['client_id'] : null;
    if (!is_string($rawId) || strlen($rawId) > 18 || !ctype_digit($rawId)) {
        throw new RuntimeException('Enter a valid positive client ID.');
    }
    $clientId = (int) $rawId;
    if ($clientId < 1) {
        throw new RuntimeException('Enter a valid positive client ID.');
    }

    $format = isset($input['format']) && is_string($input['format']) ? $input['format'] : '';
    if (!in_array($format, ['json', 'csv'], true)) {
        throw new RuntimeException('Choose JSON or CSV format.');
    }

    $rawSections = isset($input['sections']) ? $input['sections'] : [];
    $sections = Exporter::normalizeSections($rawSections);

    return [
        'client_id' => $clientId,
        'format' => $format,
        'sections' => $sections,
    ];
}

function clientdataexport_render_form($vars, $token, $submitted)
{
    $action = isset($vars['modulelink']) ? $vars['modulelink'] : '';
    echo '<form method="post" action="' . clientdataexport_h($action) . '">';
    echo '<input type="hidden" name="token" value="' . clientdataexport_h($token) . '">';
    echo '<input type="hidden" name="action" value="preview">';
    echo '<div class="form-group">';
    echo '<label for="client_id">Client ID</label>';
    echo '<input class="form-control" type="number" min="1" step="1" name="client_id" id="client_id" value="' . clientdataexport_h($submitted['client_id']) . '" required>';
    echo '</div>';
    echo '<fieldset class="form-group"><legend>Data sections</legend>';
    echo '<div class="checkbox"><label><input type="checkbox" checked disabled> Client profile (always included)</label></div>';
    foreach (Exporter::sectionOptions() as $key => $label) {
        $checked = in_array($key, $submitted['sections'], true) ? ' checked' : '';
        echo '<div class="checkbox"><label><input type="checkbox" name="sections[]" value="' . clientdataexport_h($key) . '"' . $checked . '> ' . clientdataexport_h($label) . '</label></div>';
    }
    echo '</fieldset>';
    echo '<div class="form-group">';
    echo '<label for="format">Export format</label>';
    echo '<select class="form-control" name="format" id="format" required>';
    foreach (['json' => 'JSON', 'csv' => 'CSV'] as $key => $label) {
        $selected = $submitted['format'] === $key ? ' selected' : '';
        echo '<option value="' . clientdataexport_h($key) . '"' . $selected . '>' . clientdataexport_h($label) . '</option>';
    }
    echo '</select></div>';
    echo '<button class="btn btn-primary" type="submit">Preview export</button>';
    echo '</form>';
}

function clientdataexport_render_confirmation($vars, $token, $submitted, $preview)
{
    $action = isset($vars['modulelink']) ? $vars['modulelink'] : '';
    echo '<section class="panel panel-default" aria-labelledby="export-preview-title">';
    echo '<div class="panel-heading"><h3 class="panel-title" id="export-preview-title">Confirm export</h3></div>';
    echo '<div class="panel-body">';
    echo '<p><strong>Client:</strong> ' . clientdataexport_h($preview['client']['name']) . ' (ID ' . (int) $preview['client']['id'] . ')</p>';
    echo '<p><strong>Email:</strong> ' . clientdataexport_h($preview['client']['email']) . '</p>';
    echo '<p><strong>Format:</strong> ' . clientdataexport_h(strtoupper($submitted['format'])) . '</p>';
    echo '<h4>Included records</h4><ul>';
    foreach ($preview['sections'] as $section) {
        $label = isset($preview['section_labels'][$section]) ? $preview['section_labels'][$section] : 'Client profile';
        $count = isset($preview['counts'][$section]) ? (int) $preview['counts'][$section] : 0;
        echo '<li>' . clientdataexport_h($label) . ': ' . $count . '</li>';
    }
    echo '</ul>';
    foreach ($preview['warnings'] as $warning) {
        echo '<div class="alert alert-warning" role="alert">' . clientdataexport_h($warning) . '</div>';
    }
    echo '<p>This export contains personal data. Confirm you have authorization and a valid business purpose.</p>';
    echo '<form method="post" action="' . clientdataexport_h($action) . '">';
    echo '<input type="hidden" name="token" value="' . clientdataexport_h($token) . '">';
    echo '<input type="hidden" name="action" value="download">';
    echo '<input type="hidden" name="client_id" value="' . (int) $submitted['client_id'] . '">';
    echo '<input type="hidden" name="format" value="' . clientdataexport_h($submitted['format']) . '">';
    foreach ($submitted['sections'] as $section) {
        if ($section !== 'profile') {
            echo '<input type="hidden" name="sections[]" value="' . clientdataexport_h($section) . '">';
        }
    }
    echo '<div class="checkbox"><label><input type="checkbox" name="confirm_export" value="1" required> I confirm I am authorized to export this client’s data.</label></div>';
    echo '<button class="btn btn-primary" type="submit">Confirm and download</button> ';
    echo '<a class="btn btn-default" href="' . clientdataexport_h($action) . '">Cancel</a>';
    echo '</form></div></section>';
}

function clientdataexport_audit($request, $payload)
{
    if (!function_exists('logActivity')) {
        return;
    }
    $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
    $counts = [];
    foreach ($payload['meta']['record_counts'] as $section => $count) {
        $counts[] = $section . '=' . (int) $count;
    }
    try {
        logActivity(
            'Client Data Export: administrator ID ' . $adminId
            . ' exported client ID ' . (int) $request['client_id']
            . ' as ' . strtoupper($request['format'])
            . ' (records: ' . implode(', ', $counts) . ').'
        );
    } catch (Throwable $exception) {
        // An audit-log failure must not prevent the authorized export.
    }
}
function clientdataexport_send_download($body, $format, $clientId)
{
    if (headers_sent()) {
        throw new RuntimeException('The download could not start because output was already sent. Reload the page and try again.');
    }

    $filename = 'client-export-' . (int) $clientId . '-' . gmdate('Y-m-d') . '.' . $format;
    header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'application/json') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $body;
    exit;
}

function clientdataexport_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

