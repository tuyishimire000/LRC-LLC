# LRC LLC — Real Estate Management Platform

A web-based property management platform for Rwanda. The platform supports LRC-owned properties and properties managed for external clients, multiple owners per property, RWF-only accounting, tenant rent billing, controlled collections and owner payouts.

## Architecture

- PHP 8.2+ / Laravel 12
- MySQL 8 with InnoDB and utf8mb4
- Blade server-rendered UI; Livewire can be introduced for interactive modules
- Modular monolith: Identity, Property, Tenancy, Billing, Collections, Accounting, Maintenance, Reporting and Audit
- Currency: RWF only
- Display timezone: Africa/Kigali

## Local setup

1. Install PHP 8.2+, Composer and MySQL 8.
2. Create a database and a least-privilege database user.
3. Copy `.env.example` to `.env` and set database credentials.
4. Run `composer install`.
5. Run `php artisan key:generate`.
6. Run `php artisan migrate`.
7. Run `php artisan lrc:admin-create` to provision the first administrator interactively. Use a strong password (12+ characters).\n8. Run `php artisan serve`.\n\nFor automated checks, run `php artisan test`. The GitHub Actions workflow runs Composer validation and the test suite on pushes and pull requests.

Do not use the MySQL root account from the application. Never commit `.env` or provider credentials.

## Product decisions

- Property scope: LRC-owned and client-managed.
- Ownership: multiple owners with effective-dated percentage shares.
- Finance: LRC collection workflow with owner liability accounting and controlled payouts.
- Currency: RWF only.
- Platform: responsive web.

## Financial safety

A payment submission is not proof of payment. Only verified transactions may be posted and allocated. Posted financial records must be corrected by reversal/adjustment, not destructive edits. Owner payouts require approval and must not exceed available payable balances. Deposits are tracked separately from rent.

Before collecting or holding client funds in production, obtain professional confirmation of the applicable Rwanda legal, banking and payment-provider requirements for LRC's exact funds flow.

## Legacy application

The existing PHP application and `realestate.sql` are retained as legacy reference material. Do not connect the new financial core to the legacy database or migrate unverified sample data directly. Data migration will be a separate, validated workstream.

## Current status

Foundation scaffold only. Authentication, domain migrations, accounting ledger, provider integrations and business modules are not yet implemented.
