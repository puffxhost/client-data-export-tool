# WHMCS Client Data Export

A WHMCS admin addon for a deliberate, reviewable export of one client's selected business records in JSON or CSV.

## What it does

- Provides a two-step flow: select scope, review the client and record counts, then confirm the download.
- Always includes the primary client profile. Optional sections cover contacts, services, domains, orders, invoice summaries, and support-ticket metadata.
- Supports JSON (structured package with export metadata) and a long-form CSV (`record_type,record_id,client_id,field,value`).
- Uses the WHMCS Capsule query builder and reads only explicit allowlisted columns.
- Adapts to WHMCS database variations: unavailable optional tables and columns are omitted with warnings instead of breaking the entire export.
- Refuses to silently truncate any selected section above 10,000 records.
- Writes no export files to the server and sends no data to an external service.

## Install

1. Copy `modules/addons/clientdataexport/` into your WHMCS installation's `modules/addons/` directory.
2. Activate **Client Data Export** under **Configuration → System Settings → Addon Modules** (labels may vary by WHMCS version).
3. In **Access Control**, grant access only to a trusted administrator role. WHMCS enforces this addon-level permission.
4. Open **Addons → Client Data Export**. Enter the client ID, choose optional sections and format, review the preview, then explicitly confirm the export.

Target: WHMCS 8.13+ and PHP 7.4+. Validate against a staging installation running the exact WHMCS/PHP/database versions used in production before rollout.

## Data scope

The exact selected columns, exclusions, compatibility behavior, and per-section cap are documented in [DATA-SCOPE.md](docs/DATA-SCOPE.md).

Never add credentials, authentication tokens, payment details, invoice line descriptions, ticket replies, or arbitrary database columns to the allowlist without a separate privacy and security review.

## Security and privacy

- WHMCS admin authentication and addon role access control protect the page.
- POST actions use the WHMCS admin CSRF token and strict input validation.
- A confirmation page shows the client identity and record counts before export.
- HTML output is escaped; CSV formula-leading values are prefixed to prevent spreadsheet formula execution.
- Responses use private/no-store cache headers and a neutral filename containing only the client ID and UTC date.
- Successful attempts are recorded in the WHMCS Activity Log with administrator ID, client ID, format, and section counts. This is an audit trail and therefore stores client identifiers in WHMCS logs; set and review log retention accordingly.
- Export access can expose personal data. Use least privilege and protect files after download. The addon is not a legal-compliance guarantee and does not determine whether a particular export is lawful.

See [SECURITY.md](SECURITY.md) for reporting and operational guidance.

## Compatibility behavior and limits

- Each optional section is independently selected; unselected data is not queried or exported.
- Missing optional tables/columns are reported in the preview or JSON warnings. Missing fields are represented as `null` in JSON and blank in CSV so the format stays stable.
- Each selected section has a hard limit of 10,000 records. The export is refused rather than silently returning a partial section.
- Invoices are summary rows only. Ticket exports contain metadata only; message bodies, replies, and attachments are excluded.
- Payment methods/tokens, passwords, service usernames/passwords, server credentials, domain EPP codes, notes, and custom fields are excluded.
- This release does not support bulk exports, asynchronous jobs, configurable field mapping, or client self-service exports.

## Development

The module has no third-party runtime dependencies. WHMCS provides the Capsule database component. The implementation is split into the addon entry point, export/query service, and format writer so each boundary can be reviewed independently.

## Author

Made with ❤️ by [Nitin Mehta](https://github.com/puffxhost).

