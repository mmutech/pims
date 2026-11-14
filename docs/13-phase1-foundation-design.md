# 13 — Phase 1 Foundation Design

This is the approved design for the module being implemented now. It follows the required pre-implementation sequence: architecture, database dependencies, migrations, relationships, business rules, permissions, screens, integration points.

## 1. Module architecture

```
app/
  Domain/
    Organization/
      Models/{BusinessUnit,Department,CostCenter,Project,ProjectMember}.php
      Services/OrganizationService.php
    Warehouse/
      Models/{Warehouse,WarehouseLocation,WarehouseBin,UserWarehouseAccess}.php
      Services/WarehouseService.php
    Catalog/
      Models/{ItemCategory,UnitOfMeasure,Item,ItemUnit,ItemSpecification,ItemBatch,ItemSerialNumber}.php
      Services/CatalogService.php
    Supplier/
      Models/{Supplier,SupplierContact,SupplierAddress,SupplierDocument,SupplierEvaluation}.php
      Services/SupplierService.php
    Administration/
      Services/{UserAdministrationService,RoleAdministrationService,DocumentSequenceService,SystemSettingService}.php
      Models/{DocumentSequence,SystemSetting}.php
  DTOs/
    Organization/{BusinessUnitData,DepartmentData,CostCenterData,ProjectData}.php
    Warehouse/{WarehouseData,LocationData,BinData,AccessGrantData}.php
    Catalog/{CategoryData,UnitData,ItemData}.php
    Supplier/SupplierData.php
    Administration/UserData.php
  Enums/ (all documented enums)
  Livewire/
    Dashboard.php
    Organization/{BusinessUnits,Departments,CostCenters,Projects}/Index.php
    Warehouses/{Index,Show}.php
    Catalog/{Categories,Units,Items}/{Index,Show}.php
    Suppliers/{Index,Show}.php
    Administration/{Users,Roles,Settings,Sequences,Activity}/{Index,Show}.php
  Policies/ (one per aggregate)
  Support/{Permissions,Decimal}.php
```

**Dependency direction:** Livewire → DTO → Service → Model. Livewire never writes models directly. Services depend on models, enums, activity log, and the current user. Policies depend on models and the user's permissions/scope. No domain imports another domain's Livewire code.

## 2. Database dependencies

| Module | Depends on |
|--------|------------|
| Organization | users (managers), framework auth |
| Warehouse | organization (business unit), users (manager, access) |
| Supplier | users (approver, evaluator) |
| Catalog | organization (none directly), supplier (batches), users (audit) |
| Administration | all of the above for scoping; Spatie tables |
| Audit | all models via morph |

Creation order: users → permissions/activity → organization → users org fields → warehouses → suppliers → catalog → sequences/settings.

## 3. Migrations (Phase 1)

Detailed in [05 — Migration Plan](05-migration-plan.md). Implemented:

1. `create_organization_tables` — business_units, departments, cost_centers, projects, project_members (the original `regions` table was removed by the Phase 8 cleanup migration).
2. `add_organization_fields_to_users_table` — employee/org/status/last login/soft delete.
3. `create_warehouse_tables` — warehouses, locations, bins, user_warehouse_access.
4. `create_supplier_tables` — suppliers, contacts, addresses, documents, evaluations.
5. `create_catalog_tables` — categories, UoM, items, item_units, item_specifications, item_batches, item_serial_numbers.
6. `create_document_sequences_table`.
7. `create_system_settings_table`.

All tables use bigint PKs, explicit decimals, indexed FKs, unique business keys, and soft deletes where appropriate.

## 4. Model relationships

Detailed in [06 — Model Relationships](06-model-relationships.md). Key aggregates:

- BusinessUnit → Department → CostCenter.
- Project references BU/department/cost centre and has members (users).
- Warehouse references BU and has locations → bins and user access grants.
- ItemCategory self-nests; Item references category and base UoM, with units/specs/batches/serials.
- Supplier owns contacts, addresses, documents, evaluations; batches reference suppliers.
- User has home org, projects, warehouse grants, Spatie roles.

## 5. Business rules

### Organization

1. Codes uppercase, unique, immutable after creation.
2. Business unit deactivation blocked when active departments, projects, or warehouses exist.
3. Department deactivation blocked when active cost centres/projects exist.
4. Project status transitions: planned → active → on_hold/active → completed/cancelled; completed/cancelled are terminal.
5. Project end date ≥ start date; budget ≥ 0.
6. Project member unique per project; `left_at` ≥ `joined_at` when present.
7. Soft delete only; no hard delete when referenced.

### Warehouse

1. Code unique; warehouse belongs to a business unit.
2. Source ≠ destination is enforced later for transfers; Phase 1 validates warehouse type.
3. Location code unique within warehouse; bin code unique within location.
4. Deactivation blocked when active locations exist (warehouse) or active bins exist (location); stock checks added in Phase 5.
5. Warehouse access unique per user; level ∈ view/operate/approve/manage; grant records grantor and time; revoke sets `revoked_at`.
6. Users cannot grant themselves access unless they hold `warehouse.access.manage` (and even then the action is audited).
7. Manager must be an active user with access to the warehouse.

### Catalog

1. Item code unique, uppercase, immutable.
2. Category code unique; parent cannot be self or a descendant; max depth 5.
3. UoM code unique; decimal places 0–6.
4. Item base UoM required; conversion factors > 0; exactly one base unit per item.
5. `maximum_stock ≥ minimum_stock`; reorder level/quantity/standard cost ≥ 0.
6. Batch number unique per item; expiry ≥ manufacture; serial number globally unique.
7. Tracking type controls required data: batch requires batch on receipt, serial requires serials, batch_serial requires both.
8. Items with existing batches/serials cannot change tracking type in a way that invalidates history (blocked once stock exists; Phase 5).

### Supplier

1. Supplier code unique; tax ID unique when present.
2. Status transitions: pending → active → suspended/inactive; blacklisted is terminal until lifted by a Super Administrator with reason.
3. Approval requires `supplier.suppliers.approve` and sets approver/time; creator cannot approve own supplier when SoD is enabled.
4. Only active approved suppliers can receive awards (enforced in Phase 3).
5. Documents private; expiry tracked; file type/size validated.
6. Evaluations 0–5; overall score computed and stored.

### Users and security

1. Email and employee number unique.
2. Cannot deactivate or delete own account; cannot remove own Super Administrator role.
3. Role/permission changes activity-logged.
4. Organization scope: global access or matching home org; project access via membership.
5. Warehouse access is explicit; org membership alone does not grant operational access.

## 6. Permissions

Phase 1 permission groups: system, administration, organization, warehouse, catalog, supplier. Roles: Super Administrator, System Administrator, Executive, Procurement Manager, Procurement Officer, Warehouse Manager, Store Officer, Project Manager, Department Requester, Auditor. Full matrix in [11 — Permission Matrix](11-permission-matrix.md).

Enforcement layers:

1. Route middleware `permission:...`.
2. Policy checks in Livewire actions.
3. Service re-checks for writes and scope.
4. Query scopes filter lists by organization/warehouse.

## 7. Livewire screens

| Screen | Component | Key actions |
|--------|-----------|-------------|
| Dashboard | `Dashboard` | KPI cards, recent activity |
| Business Units | `Organization\BusinessUnits\Index` | list/search/filter, create, edit, deactivate |
| Departments | `Organization\Departments\Index` | same + BU filter |
| Cost Centres | `Organization\CostCenters\Index` | same + department |
| Projects | `Organization\Projects\Index` | CRUD + members |
| Warehouses | `Warehouses\Index` | CRUD |
| Warehouse detail | `Warehouses\Show` | locations, bins, access grants |
| Categories | `Catalog\Categories\Index` | CRUD, parent |
| Units | `Catalog\Units\Index` | CRUD |
| Items | `Catalog\Items\Index` | CRUD, specs, units, batches/serials summary |
| Item detail | `Catalog\Items\Show` | specifications, alternate units, batches, serials, activity |
| Suppliers | `Suppliers\Index` | CRUD, approve, status, archive |
| Supplier detail | `Suppliers\Show` | contacts/addresses/docs/evals |
| Users | `Administration\Users\Index` | CRUD, roles, org, warehouse access |
| User detail | `Administration\Users\Show` | profile, status, roles, warehouse access, activity |
| Roles | `Administration\Roles\Index` | permission matrix |
| Document numbering | `Administration\Sequences\Index` | sequence CRUD |
| Settings | `Administration\Settings\Index` | grouped settings |
| Audit log | `Administration\Activity\Index` | read-only filter |

Shared UI: Flux sidebar/header shell, page header, search/filter bar, data table, modal form, status badge, confirmation dialog, empty state, toast.

## 8. Integration points

- **Approval engine (Phase 2):** documents will call `ApprovalEngine::submit()`; Phase 1 creates no approval rows.
- **Inventory posting (Phase 5):** warehouse/location/bin and item tracking data are the inputs to `stock_dimensions`; Phase 1 keeps them consistent so dimensions can be generated.
- **Procurement (Phase 3):** suppliers, items, warehouses, cost centres, projects are the master references.
- **Receiving (Phase 4):** warehouses/locations/bins and item tracking rules drive SRA/GRN.
- **Reports (Phase 8):** organization/warehouse/item/supplier filters are indexed now.
- **Integration outbox (Phase 9):** master data changes will emit events through the same service layer; no direct finance calls.

## 9. Seed data

- Permissions from `App\Support\Permissions`.
- Ten roles with mapped permissions.
- Demo organization: 4 business units, departments, cost centres, 3 projects.
- 4 warehouses with locations/bins and access grants.
- 12 item categories (transformers, meters, conductors, cables, poles, insulators, breakers, switchgear, protection, PPE, tools, consumables).
- 8 units of measure.
- 12 representative items with specs/units/batches/serials.
- 6 suppliers with contacts/addresses/evaluations.
- 8 demo users across roles with known password `password`.
- System settings defaults and document sequences.

Seeders are idempotent and safe to re-run.

## 10. Tests (Pest)

Implemented suites under `tests/`:

| File | Coverage |
|------|----------|
| `Feature/Auth/AuthenticationTest.php` | login, invalid password, inactive-user rejection, dashboard permission, logout |
| `Feature/ProfileTest.php` | Breeze profile update, password, soft-deleted account |
| `Feature/Administration/UserManagementTest.php` | user creation with roles/warehouse grants, self-deactivation block, system-role escalation guard, access revocation |
| `Feature/Administration/RoleManagementTest.php` | custom role create/rename, permission replacement, system-role lock, assigned-role delete guard |
| `Feature/Administration/SettingsAndSequencesTest.php` | sequence allocation/padding validation, encrypted setting masking, sequence screen authorization |
| `Feature/Organization/OrganizationScopeTest.php` | business-unit scoping, global access, policy denial |
| `Feature/Warehouse/WarehouseAccessTest.php` | explicit grants, level checks, revoke audit trail, inactive-user guard, delete guard |
| `Feature/Catalog/CatalogTest.php` | item creation with specs/units, immutable code, decimal validation, base-unit existence, policy matrix |
| `Feature/Supplier/SupplierTest.php` | pending registration, approval SoD, approval permission, suspension reason, blacklist restriction, evaluation precision |
| `Feature/ApplicationSmokeTest.php` | every Phase 1 index/detail screen renders for authorized users and is forbidden otherwise |
| `Unit/DecimalTest.php` | bcmath add/subtract/multiply/compare/sign/divide-by-zero |

Factories declare explicit domain models, so `Model::factory()` resolves `App\Domain\*\Models\*` correctly. Tests use MySQL `ims_testing` with `RefreshDatabase`.

**Latest result:** `php artisan test` → 84 passed, 241 assertions.

## 11. Acceptance criteria

- [x] `php artisan migrate:fresh --seed` succeeds (MySQL 9.7 local).
- [x] `php artisan test` green — 84 passed, 241 assertions.
- [x] `npm run build` succeeds (Vite 6.4.5, 61 modules).
- [x] Every domain route is permission-protected; `logout` is auth-only and `profile` requires auth.
- [x] Lists are organization-scoped; warehouse operations require explicit grants (verified by tests).
- [x] Codes/serials unique; decimals validated through `App\Support\Decimal`; no float arithmetic.
- [x] Deactivation guards work; posted/historical data is never deleted (soft deletes and revoke timestamps).
- [x] Activity log records create/update/delete with causer (Spatie Activitylog via `RecordsActivity`).
- [x] UI responsive at 360 px and keyboard navigable — verified with a 360 px same-origin iframe probe across all Phase 1 screens (no horizontal overflow; `scrollWidth == clientWidth`), a visible skip-to-content link on focus, and Lighthouse accessibility 1.0 on the dashboard.
- [x] Documentation matches implementation.

### Verification record

| Check | Command | Result |
|-------|---------|--------|
| Schema + seed | `php artisan migrate:fresh --seed --force` | Pass |
| PHP syntax | `php -l` across `app`, `routes`, `database`, `config` (181 files) | 0 failures |
| Routes | `php artisan route:list --except-vendor` | 24 routes, including `POST logout` |
| Tests | `php artisan test --compact` | 84 passed (241 assertions) |
| Frontend | `npm run build` | Pass |
| Browser smoke | Seeded login + all 13 domain screens via in-app Chromium | Renders, 0 console errors |
| Responsive | 360 px same-origin iframe probe on dashboard, all list screens, and detail screens | `scrollWidth == clientWidth` (no horizontal overflow) |
| Accessibility | Lighthouse (dashboard) | Accessibility 1.0, Best Practices 1.0, SEO 1.0; skip-to-content link visible on focus |

Environment deviations: local verification uses MySQL 9.7 because no PostgreSQL server is installed; Redis/Horizon are not run on Windows. Production parity items remain in the Phase 10 hardening checklist.

## 12. Explicitly deferred

Approval execution, procurement documents, receiving/quality, ledger/posting, requisitions/issues/transfers/returns, stocktaking, reports/exports, finance integration, and production hardening. Their schemas and contracts are documented; no placeholder business logic is shipped in Phase 1.
