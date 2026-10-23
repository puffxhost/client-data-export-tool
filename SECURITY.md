# Security policy

## Security boundary

This addon runs only as a WHMCS admin addon. WHMCS authentication and the addon's administrator-role access control are the first authorization boundary. Give access only to trusted roles. The addon does not provide a client-facing or unauthenticated endpoint.

All state-changing-style actions (preview and download) are POST requests guarded by the WHMCS admin CSRF token. Client IDs, formats, sections, and confirmation are validated server-side. The exporter uses Capsule query bindings and explicit field allowlists. HTML output is escaped. CSV cells are neutralized when they begin with spreadsheet formula characters. Downloads are marked private/no-store and are not persisted by the addon.

## Sensitive data handling

Exports contain personal data. A successful download attempt is recorded in the WHMCS Activity Log with administrator ID, client ID, format, and per-section counts; do not treat this log as anonymous. Apply an appropriate log retention policy. Downloaded files are under the administrator's control and must be stored, shared, and deleted according to the site's privacy and security requirements.

The module intentionally excludes credentials, payment tokens, invoice line-item descriptions, ticket conversations, and attachments. Do not add fields or tables casually: first document why the data is necessary, who can access it, and how it will be protected.

## Reporting a vulnerability

Do not publish credentials, personal data, or a working exploit in a public issue. Contact the repository owner through the contact method listed on the [GitHub profile](https://github.com/puffxhost) or use GitHub's private vulnerability reporting if it is enabled for this repository.

## Release checks

Before production deployment, review changes to the field map, test with the exact supported WHMCS/PHP/database versions on staging, confirm only the intended administrator roles have addon access, and verify server and Activity Log retention settings.

