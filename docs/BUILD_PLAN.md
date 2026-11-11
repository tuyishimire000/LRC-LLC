# LRC LLC — Build Plan

## Product baseline

LRC LLC is a responsive web-based real-estate management platform for Rwanda. It manages both LRC-owned properties and properties managed for external clients, supports multiple owners per property, uses RWF only, and is designed for LRC-managed rent collection and owner settlements.

## Architecture

- Backend: Laravel / PHP 8.3+
- UI: Blade + Bootstrap 5; Livewire for selected interactive screens
- Database: MySQL 8, InnoDB, utf8mb4
- Architecture: modular monolith
- Timezone: Africa/Kigali for business display; persist timestamps consistently in UTC
- Currency: RWF only; monetary columns use DECIMAL, never FLOAT/DOUBLE
- Financial core: double-entry ledger, immutable posted transactions, controlled reversals
- Integrations: provider adapters; no provider credentials or live collection until legal, banking and provider approvals are complete

## Build sequence

1. Foundation and secure application bootstrap.
2. Owners, properties, ownership interests, management agreements and units.
3. Tenants and leases.
4. Rent charges and payment verification/allocation.
5. Ledger, expenses, owner balances and payouts.
6. Portals, reports, notifications, security hardening and launch.

## Current implementation increment

The first implementation increment establishes the domain database foundation for property and ownership management. It is deliberately separate from the legacy PHP listing application. The legacy application remains untouched while the new schema and application are built and tested.

### Initial domain entities

- owners
- properties
- property_owners
- management_agreements
- property_units

The SQL foundation is in `database/schema/001_property_foundation.sql`.

## Non-negotiable business rules

1. A property has a scope: `lrc_owned` or `client_managed`.
2. A property can have multiple owners; ownership interests have effective dates.
3. Active ownership shares may not exceed 100%. Before a property is activated for owner accounting or payout, effective shares must total exactly 100%.
4. Ownership history is preserved; do not overwrite historical shares.
5. A unit belongs to one property and has a unique unit code within that property.
6. Currency is RWF only in the first release.
7. Deposits are tracked separately from rent income.
8. Rent charges and payments are separate records. Outstanding balances are calculated from allocations.
9. Posted financial transactions are never deleted or silently edited; corrections use reversals.
10. Owner payouts require approval and cannot exceed the owner's available payable balance.
11. Provider callbacks must be authenticated and idempotent.
12. LRC's collection and settlement model must receive legal, bank and provider clearance before production collection.

## Definition of done for the property foundation

- Schema applies cleanly on MySQL 8.
- Foreign keys and indexes exist for the principal relationships.
- Monetary and ownership fields use fixed precision.
- Ownership-share validation is implemented in the application service layer inside a database transaction.
- Tests cover single-owner, multi-owner, over-allocation, effective dates and unit-code uniqueness.
- No production credentials or personal data are committed.

## Branching and review

Work is developed on feature branches and proposed through pull requests. Do not replace or delete the legacy application as part of this foundation increment.
