<?php

namespace PuffxHost\ClientDataExport;

use RuntimeException;
use Throwable;
use WHMCS\Database\Capsule;

/** A safe, explicit client data export built on the WHMCS Capsule query API. */
final class Exporter
{
    const MAX_ROWS_PER_SECTION = 10000;
    const PAGE_SIZE = 500;

    /**
     * Export scope is deliberately explicit. Authentication material, payment
     * tokens, invoice line-item descriptions, ticket replies, and credentials
     * are never part of this list.
     */
    private static function definitions()
    {
        return [
            'profile' => [
                'label' => 'Client profile',
                'table' => 'tblclients',
                'foreign_key' => 'id',
                'required' => true,
                'fields' => [
                    'id', 'firstname', 'lastname', 'companyname', 'email',
                    'address1', 'address2', 'city', 'state', 'postcode',
                    'country', 'phonenumber', 'currency', 'datecreated', 'status',
                ],
            ],
            'contacts' => [
                'label' => 'Contacts',
                'table' => 'tblcontacts',
                'foreign_key' => 'userid',
                'required' => false,
                'fields' => [
                    'id', 'firstname', 'lastname', 'companyname', 'email',
                    'address1', 'address2', 'city', 'state', 'postcode',
                    'country', 'phonenumber',
                ],
            ],
            'services' => [
                'label' => 'Services',
                'table' => 'tblhosting',
                'foreign_key' => 'userid',
                'required' => false,
                'fields' => [
                    'id', 'packageid', 'domain', 'firstpaymentamount', 'amount', 'currency',
                    'billingcycle', 'nextduedate', 'nextinvoicedate',
                    'termination_date', 'completed_date', 'domainstatus', 'regdate',
                ],
            ],
            'domains' => [
                'label' => 'Domains',
                'table' => 'tbldomains',
                'foreign_key' => 'userid',
                'required' => false,
                'fields' => [
                    'id', 'domain', 'registrationdate', 'expirydate',
                    'nextduedate', 'status', 'registrar',
                ],
            ],
            'orders' => [
                'label' => 'Orders',
                'table' => 'tblorders',
                'foreign_key' => 'userid',
                'required' => false,
                'fields' => [
                    'id', 'status', 'date', 'amount', 'currency',
                ],
            ],
            'invoices' => [
                'label' => 'Invoices (summary)',
                'table' => 'tblinvoices',
                'foreign_key' => 'userid',
                'required' => false,
                'fields' => [
                    'id', 'invoicenum', 'date', 'duedate', 'datepaid', 'subtotal', 'credit', 'currency',
                    'tax', 'tax2', 'total', 'status',
                ],
            ],
            'tickets' => [
                'label' => 'Support tickets (metadata only)',
                'table' => 'tbltickets',
                'foreign_key' => 'userid',
                'required' => false,
                'fields' => [
                    'id', 'tid', 'deptid', 'title', 'status', 'priority',
                    'date', 'lastreply',
                ],
            ],
        ];
    }

    public static function sectionOptions()
    {
        $options = [];
        foreach (self::definitions() as $key => $definition) {
            if ($key !== 'profile') {
                $options[$key] = $definition['label'];
            }
        }
        return $options;
    }

    /** Normalize untrusted input and always include the primary client profile. */
    public static function normalizeSections($input)
    {
        if (!is_array($input)) {
            throw new RuntimeException('Choose at least one valid data section.');
        }

        $allowed = self::definitions();
        $selected = ['profile' => true];
        foreach ($input as $section) {
            if (!is_string($section) || !isset($allowed[$section]) || $section === 'profile') {
                throw new RuntimeException('The selected data scope is invalid. Reload the page and try again.');
            }
            $selected[$section] = true;
        }

        $normalized = [];
        foreach ($allowed as $key => $definition) {
            if (isset($selected[$key])) {
                $normalized[] = $key;
            }
        }
        return $normalized;
    }

    /** Return a data-free preview so the admin can confirm the export scope. */
    public function preview($clientId, $sections)
    {
        $sections = self::normalizeSections($sections);
        $profile = $this->fetchProfile($clientId);
        $counts = ['profile' => 1];
        $warnings = [];

        foreach ($sections as $section) {
            if ($section === 'profile') {
                continue;
            }
            $definition = self::definitions()[$section];
            if (!$this->tableExists($definition['table'])) {
                $warnings[] = $definition['label'] . ' are not available in this WHMCS database and will be skipped.';
                $counts[$section] = 0;
                continue;
            }
            if (!$this->columnExists($definition['table'], $definition['foreign_key'])) {
                $warnings[] = $definition['label'] . ' are not available because the expected client link is missing.';
                $counts[$section] = 0;
                continue;
            }
            if (!$this->columnExists($definition['table'], 'id')) {
                $warnings[] = $definition['label'] . ' are not available because the record identifier is missing.';
                $counts[$section] = 0;
                continue;
            }
            $counts[$section] = (int) Capsule::table($definition['table'])
                ->where($definition['foreign_key'], (int) $clientId)
                ->count();
            if ($counts[$section] > self::MAX_ROWS_PER_SECTION) {
                $warnings[] = $definition['label'] . ' exceed the safe per-section export limit; the export will be refused unless the scope is reduced.';
            }
        }

        return [
            'client' => [
                'id' => (int) $profile['id'],
                'name' => trim((string) $profile['firstname'] . ' ' . (string) $profile['lastname']),
                'email' => (string) $profile['email'],
            ],
            'sections' => $sections,
            'section_labels' => array_intersect_key(self::sectionOptions(), array_flip($sections)),
            'counts' => $counts,
            'warnings' => $warnings,
        ];
    }

    /** Collect selected records. Exceeding a safety cap fails rather than truncating. */
    public function export($clientId, $sections)
    {
        $sections = self::normalizeSections($sections);
        $datasets = [];
        $warnings = [];
        $counts = [];

        foreach ($sections as $section) {
            $definition = self::definitions()[$section];
            $missingFields = [];
            $rows = $this->fetchRows($definition, $clientId, $missingFields);
            if ($rows === null) {
                if ($definition['required']) {
                    throw new RuntimeException('The WHMCS client table is unavailable.');
                }
                $rows = [];
                $warnings[] = $definition['label'] . ' are not available in this WHMCS database and were skipped.';
            }
            if (!empty($missingFields)) {
                $warnings[] = $definition['label'] . ' omitted fields not present in this database: ' . implode(', ', $missingFields) . '.';
            }
            if ($section === 'profile' && count($rows) !== 1) {
                throw new RuntimeException('The requested client record changed after preview. Start a new export.');
            }
            $datasets[$section] = $rows;
            $counts[$section] = count($rows);
        }

        return [
            'meta' => [
                'format_version' => '1.0',
                'exported_at_utc' => gmdate('c'),
                'client_id' => (int) $clientId,
                'sections' => $sections,
                'record_counts' => $counts,
            ],
            'data' => $datasets,
            'warnings' => $warnings,
        ];
    }

    private function fetchProfile($clientId)
    {
        $definition = self::definitions()['profile'];
        if (!$this->tableExists($definition['table'])) {
            throw new RuntimeException('The WHMCS client table is unavailable.');
        }
        if (!$this->columnExists($definition['table'], 'id')) {
            throw new RuntimeException('The WHMCS client table is missing its primary key.');
        }

        $columns = $this->availableColumns($definition, $missingFields);
        $client = Capsule::table($definition['table'])
            ->select($columns)
            ->where('id', (int) $clientId)
            ->first();
        if (!$client) {
            throw new RuntimeException('No client was found for that ID.');
        }

        $record = $this->normalizeRow($client, $definition['fields']);
        if (empty($record['id'])) {
            throw new RuntimeException('The requested client record is invalid.');
        }
        return $record;
    }

    /**
     * Return null only when an optional table/link is unavailable. All other
     * query errors propagate and are handled by the module boundary.
     */
    private function fetchRows($definition, $clientId, &$missingFields)
    {
        $missingFields = [];
        $table = $definition['table'];
        $foreignKey = $definition['foreign_key'];

        if (!$this->tableExists($table) || !$this->columnExists($table, $foreignKey)) {
            return null;
        }
        if (!$this->columnExists($table, 'id')) {
            return null;
        }

        $columns = $this->availableColumns($definition, $missingFields);
        $rows = [];
        $lastId = 0;
        do {
            $remaining = self::MAX_ROWS_PER_SECTION + 1 - count($rows);
            $pageSize = min(self::PAGE_SIZE, $remaining);
            $page = Capsule::table($table)
                ->select($columns)
                ->where($foreignKey, (int) $clientId)
                ->where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->limit($pageSize)
                ->get();

            $pageCount = 0;
            foreach ($page as $row) {
                $rows[] = $this->normalizeRow($row, $definition['fields']);
                $lastId = (int) $row->id;
                $pageCount++;
                if (count($rows) > self::MAX_ROWS_PER_SECTION) {
                    throw new RuntimeException($definition['label'] . ' exceed the safe per-section export limit of ' . self::MAX_ROWS_PER_SECTION . ' records. Narrow the scope or export in a controlled process.');
                }
            }
        } while ($pageCount === $pageSize);

        return $rows;
    }

    private function availableColumns($definition, &$missingFields)
    {
        $columns = [];
        foreach ($definition['fields'] as $field) {
            if ($this->columnExists($definition['table'], $field)) {
                $columns[] = $field;
            } else {
                $missingFields[] = $field;
            }
        }
        if (!in_array('id', $columns, true)) {
            throw new RuntimeException('A required record identifier is missing from ' . $definition['table'] . '.');
        }
        return $columns;
    }

    private function normalizeRow($row, $fields)
    {
        $record = [];
        foreach ($fields as $field) {
            $record[$field] = isset($row->{$field}) ? $row->{$field} : null;
        }
        return $record;
    }

    private function tableExists($table)
    {
        try {
            return Capsule::schema()->hasTable($table);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to inspect the WHMCS database schema.', 0, $exception);
        }
    }

    private function columnExists($table, $column)
    {
        try {
            return Capsule::schema()->hasColumn($table, $column);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to inspect the WHMCS database schema.', 0, $exception);
        }
    }
}

