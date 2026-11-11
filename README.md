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
- Security deposits are stored separately from monthly rent and do not create rent income.\n- Monthly rent charges support daily proration, effective-dated rent terms and duplicate-safe generation.\n- Payments remain pending until a second authorized finance user verifies receipt and allocates the full amount to outstanding charges.\n- Partial payments automatically reduce charge balances through allocations; balances are derived, not manually edited.\n- Billing routes are restricted to super administrators and finance managers.

Not implemented yet: tenant self-service portal, document upload UI, tenant credits/overpayments/refunds, double-entry general ledger, owner statements and payouts, payment-provider webhooks, bank/MoMo reconciliation and production deployment. These remain future milestones.

The repository has not yet been installed and executed in a PHP environment in this development session. Treat the branch as pending CI and migration validation, not production-ready.


## Rent billing module (in progress)

- Monthly rent charges are generated from approved and active leases using the effective-dated lease rent terms.
- Partial-month charges are prorated by calendar days; monthly amounts are stored in RWF with two decimal places.
- A unique lease/month key prevents duplicate rent charges. The command is `php artisan lrc:rent-generate --month=YYYY-MM`.
- A Kigali-timezone scheduler entry runs the generator on the first day of each month at 00:15.
- Payments are recorded as pending; they do not reduce tenant balances until a staff member verifies the receipt and allocates the full amount to the tenant's rent charges.
- Payment allocation is transactional, locks affected rows, checks tenant ownership/currency/outstanding amounts, and requires allocations to equal the verified payment.
- Charge balances are derived from allocation totals; no manually editable outstanding balance field is stored.

This is an initial internal staff workflow, not a payment-provider integration or a full accounting ledger. Refunds, reversals, credits, late-fee policy and bank reconciliation are not implemented. Validate the business rules and automated tests before using live financial records.
