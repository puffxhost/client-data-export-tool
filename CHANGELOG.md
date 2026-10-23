# Changelog

## 1.0.0

- Added a two-step preview and confirmation flow.
- Added optional contacts, service, domain, order, invoice-summary, and ticket-metadata sections.
- Added explicit, version-tolerant field allowlists and warnings for missing schema elements.
- Added a per-section 10,000-record cap that refuses oversized exports instead of truncating data.
- Added JSON metadata and a long-form multi-section CSV format.
- Added strict request validation, CSRF protection, escaped output, formula-safe CSV cells, no-store response headers, and Activity Log auditing.
- Excluded credentials, payment secrets, invoice item descriptions, ticket conversations, and attachments.

## 0.1.0

- Initial client profile JSON and CSV exporter.

