# Data scope and field map

The export is deliberately allowlist-based. It never serializes a full database row.

| Section key | WHMCS table | Relationship | Included fields |
| --- | --- | --- | --- |
| `profile` (always) | `tblclients` | `id = client ID` | `id`, `firstname`, `lastname`, `companyname`, `email`, `address1`, `address2`, `city`, `state`, `postcode`, `country`, `phonenumber`, `currency`, `datecreated`, `status` |
| `contacts` | `tblcontacts` | `userid = client ID` | `id`, `firstname`, `lastname`, `companyname`, `email`, `address1`, `address2`, `city`, `state`, `postcode`, `country`, `phonenumber` |
| `services` | `tblhosting` | `userid = client ID` | `id`, `packageid`, `domain`, `firstpaymentamount`, `amount`, `currency`, `billingcycle`, `nextduedate`, `nextinvoicedate`, `termination_date`, `completed_date`, `domainstatus`, `regdate` |
| `domains` | `tbldomains` | `userid = client ID` | `id`, `domain`, `registrationdate`, `expirydate`, `nextduedate`, `status`, `registrar` |
| `orders` | `tblorders` | `userid = client ID` | `id`, `status`, `date`, `amount`, `currency` |
| `invoices` | `tblinvoices` | `userid = client ID` | `id`, `invoicenum`, `date`, `duedate`, `datepaid`, `subtotal`, `credit`, `currency`, `tax`, `tax2`, `total`, `status` |
| `tickets` | `tbltickets` | `userid = client ID` | `id`, `tid`, `deptid`, `title`, `status`, `priority`, `date`, `lastreply` |

## Excluded by design

The exporter does not include password hashes, MFA/authentication data, API keys, payment tokens or card data, service credentials, server credentials, domain transfer/EPP codes, invoice item descriptions, transaction details, ticket message bodies/replies/attachments, private notes, arbitrary custom fields, or complete database rows.

## Schema differences

Before querying a section, the exporter checks that its table and relationship column exist. It checks every selected column before building the query. Missing optional tables are skipped with a warning; missing columns are returned as `null` in JSON / blank in CSV and named in the JSON `warnings` array. The preview identifies optional sections that cannot be queried. The required client profile table/identifier must be available.

## Limits and consistency

Each selected section is capped at 10,000 records. The exporter pages by increasing record ID and refuses the download if a section exceeds the cap. This avoids silent partial exports and bounds memory use. Preview counts and download rows are separate queries; records may change between those steps, so the final download is authoritative. If the client was removed after preview, export is refused.

CSV has one row per field, not one row per related database record. This keeps selected sections together in one file and makes the record type explicit.

