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

1. Install PHP 8.2+, Composer and Docker with the Compose plugin (or install MySQL 8 directly).
2. Copy `.env.example` to `.env` and set a strong `DB_PASSWORD` for the `lrc_app` database user. If using Docker, also set a strong `MYSQL_ROOT_PASSWORD`.
3. Start MySQL with `docker compose up -d mysql`. Wait until the MySQL health check reports healthy. The named volume `lrc_mysql_data` keeps database data between restarts.
4. Run `composer install`.
5. Run `php artisan key:generate`.
6. Run `php artisan migrate`.
7. Run `php artisan lrc:admin-create` to provision the first administrator interactively. Use a strong password (12+ characters).
8. Run `php artisan lrc:finance-user` to create a finance manager account. Create at least two authorized staff accounts so the payment recorder and verifier can be different people.
9. Run `php artisan lrc:accounting-seed` to create the provisional starter chart of accounts. Have your accountant approve account mappings before posting live transactions.
10. Run `php artisan serve`.


For automated checks, run `php artisan test`. Tests are configured to use a dedicated MySQL database named `lrc_testing`; they do not use SQLite or your development database. Ensure this test database exists and the test credentials in `phpunit.xml` match your local MySQL setup. GitHub Actions starts an isolated MySQL 8 service and runs the test suite against it.

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
- Monthly rent charges support daily proration, effective-dated rent terms and duplicate-safe generation.
- Payments remain pending until a second authorized finance user verifies receipt and allocates the full amount to outstanding charges.
- Partial payments automatically reduce charge balances through allocations; balances are derived, not manually edited.
- Billing routes are restricted to super administrators and finance managers.

Not implemented yet: tenant self-service portal, document upload UI, tenant credits/overpayments/refunds, payment-provider webhooks, bank/MoMo reconciliation, supplier payment settlement and production deployment. These remain future milestones.

The repository has not yet been installed and executed in a PHP environment in this development session. Treat the branch as pending CI and migration validation, not production-ready.


## Rent billing module (in progress)

- Monthly rent charges are generated from approved and active leases using the effective-dated lease rent terms.
- Partial-month charges are prorated by calendar days; monthly amounts are stored in RWF with two decimal places.
- A unique lease/month key prevents duplicate rent charges. The command is `php artisan lrc:rent-generate --month=YYYY-MM`.
- A Kigali-timezone scheduler entry runs the generator on the first day of each month at 00:15.
- Payments are recorded as pending; they do not reduce tenant balances until a staff member verifies the receipt and allocates the full amount to the tenant's rent charges.
- Payment allocation is transactional, locks affected rows, checks tenant ownership/currency/outstanding amounts, and requires allocations to equal the verified payment.
- Payment recorders cannot verify their own payments; a second finance manager must perform the verification.
- Charge balances are derived from allocation totals; no manually editable outstanding balance field is stored.

This is an initial internal staff workflow, not a payment-provider integration. Refunds, reversals, credits, late-fee policy and bank reconciliation are not implemented. Validate the business rules and automated tests before using live financial records.


## Accounting and owner statement milestone (in progress)

- Added ledger account, journal entry and journal line tables with a service that rejects unbalanced entries and requires a different user to post a draft journal.
- Added a provisional starter chart command: `php artisan lrc:accounting-seed`. Account codes and mappings must be reviewed by LRC's accountant before live posting.
- Added property expense entry and approval. Only approved expenses marked owner-borne are considered for owner statements; the person recording an expense cannot approve it.
- Added owner statement preparation for client-managed properties. It requires a management agreement covering the full period and effective ownership records totalling exactly 100%.
- Draft statements use verified payment allocations, the agreement's percentage/fixed management fee and approved owner-borne RWF expenses. Ownership share rounding is reconciled to the cent and each collection/expense line links to its source record.
- Prevents overlapping statement periods for an owner/property; an existing draft can be recalculated after new verified collections or approved expenses, while approved/paid statements are locked against recalculation.
- Payout workflow is draft → approved → paid, with separate preparer, approver and transfer recorder. Recording a transfer stores the bank/provider reference only; it does not initiate a transfer.
- Payout preparation blocks negative net statements; no expense carry-forward policy is implemented.

Verified rent payments, approved expenses and owner payout fee/transfer events now create balanced double-entry journals inside the same database transaction as their business status changes. Source references prevent duplicate postings. The reports page provides a trial balance, period income statement and balance sheet with accumulated earnings. These are management reports only: supplier payable settlement, opening balances, depreciation, tax, year-end closing, reversals/refunds and bank reconciliation are not implemented. The starter chart remains provisional and must be approved by LRC's accountant. Do not use these workflows for live client funds until the ledger mappings, payout policy, migration/tests and Rwanda legal/banking/payment-provider requirements have been reviewed and validated.


### Ledger posting and financial reports (in progress)

- A verified tenant payment posts a debit to bank/payment clearing and credits either LRC rental income or client-owner funds payable plus contractual percentage management fee income.
- An approved expense posts a debit to owner funds payable when it is an owner-borne client-property expense, or to LRC property expense when LRC bears it; the offset is vendor payable.
- Approval of a fixed-fee owner statement accrues the fixed management fee by debiting owner funds payable and crediting management fee income.
- Recording an actual payout reference as paid debits owner funds payable and credits bank/payment clearing.
- Business status changes and their journal postings run in the same database transaction. A failure to create/post the journal rolls back the business action.
- Each event has a unique source type/id journal reference to prevent duplicate postings.
- Finance users can open /accounting/reports for a period income statement, trial balance through the selected date, and balance sheet with cumulative earnings.
- Payment verification feature tests now seed the provisional chart and assert that a posted journal is created.

The bank/payment clearing account is an internal book balance, not a live bank feed. Vendor payables are accrued but no supplier payment workflow currently settles them. Payout transfer references are entered by staff and must be independently reconciled to a bank/provider statement.

### Supplier payment settlement (in progress)

- Finance staff can record a supplier payment and allocate it across one or more approved property expenses.
- Empty expense rows are ignored; selected allocation amounts must equal the payment total exactly.
- Payments remain pending until a different authorized user verifies them.
- Verification locks the payment and affected expense rows, aggregates allocations per expense, and rejects settlement above each expense's remaining unpaid amount.
- Verified supplier payments automatically debit vendor payables and credit the internal bank/payment clearing account in a balanced journal.
- Payment verification and ledger posting are one database transaction; the source reference prevents duplicate journal entries.
- The workflow records the bank/provider reference but does not send money. Match each reference to an independently obtained bank/provider statement.
