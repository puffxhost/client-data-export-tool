# WHMCS Client Data Export

A small WHMCS addon for exporting one client's allowlisted profile fields as JSON or CSV.

## Safety scope

- WHMCS admin authentication and addon role access control protect the page.
- Exports require a client ID, a format selection, and a valid WHMCS admin CSRF token.
- The query uses the WHMCS Capsule query builder and an integer client ID.
- Only profile fields explicitly listed in `clientdataexport_profile_fields()` are exported.
- Passwords, authentication tokens, payment details, invoices, tickets, and internal notes are excluded.
- The addon does not retain exports or send data to a remote service.

Treat each export as personal data. Store and share it only in accordance with your privacy policy and applicable law.

## Install

1. Copy `modules/addons/clientdataexport/` into the matching `modules/addons/` directory of your WHMCS installation.
2. Activate **Client Data Export** under **Configuration → System Settings → Addon Modules** (menu labels may vary by WHMCS version).
3. Grant access only to trusted administrator roles in the addon's **Access Control** settings.
4. Open **Addons → Client Data Export**, enter a client ID, select JSON or CSV, and export.

The addon targets WHMCS 8.13+ and PHP 7.4+. Try it on a staging installation first and verify your admin access settings before production use.

## Exported fields

`id`, `firstname`, `lastname`, `companyname`, `email`, `address1`, `address2`, `city`, `state`, `postcode`, `country`, `phonenumber`, `datecreated`, and `status`.

## Review notes

- This first version exports the primary client profile only; it does not export contacts or billing records.
- An administrator who can access the addon can export any client by ID. Restrict the addon to a small, trusted role set.
- Validate against the exact WHMCS and PHP versions in your deployment before release.

## Made by

Made with ❤️ by [Nitin Mehta](https://github.com/puffxhost).
