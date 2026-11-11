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
7. Run `php artisan lrc:admin-create` to provision the first administrator interactively. Use a strong password (12+ characters).
8. Run `php artisan serve`.

For automated checks, run `php artisan test`. The GitHub Actions workflow runs Composer validation and the test suite on pushes and pull requests.

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

## Current implementation status

- Laravel 12 foundation, environment configuration and CI workflow.
- Staff sign-in with login throttling and role-based access for property managers and super administrators.
- Owner, property, unit and management-agreement schema and models.
- Tenant registration, tenant register/profile pages and encrypted national-ID storage.
- Lease creation, term history, approval and termination workflows.
- Lease validation prevents cross-property unit assignment and overlapping approved/active occupancy; approval rechecks occupancy while locking the unit row.
- Security deposits are stored separately from monthly rent and do not create rent income.

Not implemented yet: tenant self-service portal, document upload UI, recurring rent billing, payment verification, double-entry ledger, owner payouts, provider integrations and production deployment. These remain future milestones.

The repository has not yet been installed and executed in a PHP environment in this development session. Treat the branch as pending CI and migration validation, not production-ready.
