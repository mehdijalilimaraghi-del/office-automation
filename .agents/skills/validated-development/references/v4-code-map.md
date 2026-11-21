# V4 telephony code map

## Confirmed existing entry points

- bootstrap/providers.php: current provider registration; PBX must be conditionally registered.
- routes/web.php: current authenticated module routes. No PBX routes exist.
- app/Core/Settings/Services/SystemSettingsService.php: non-secret organization settings.
- app/Core/Users/Http/Controllers/UserController.php and app/Models/User.php: organization users; extension ownership must be explicit.
- app/Core/Departments/Models/Department.php: department context for scoped access.
- app/Modules/Notifications/Services/NotificationService.php: existing notification boundary.
- app/Http/Middleware/HandleInertiaRequests.php: frontend abilities.
- resources/js/Layouts/AuthenticatedLayout.vue: navigation and notifications.
- database/seeders/CorePermissionSeeder.php: permission catalog.
- tests/Feature: SQL Server-backed authorization and HTTP tests.

## Planned V4 module (not implemented)

- app/Modules/Telephony: contracts, driver, configuration/application services, policies, controllers and models.
- resources/js/Pages/Telephony: separate dashboard, extensions/phone book, call history, recordings and connection settings.
- Dedicated additive migrations and integration tests for the selected supported PBX.
- Feature disablement must avoid registering telephony routes, jobs, listeners or navigation.
- Telephony must not query or require CRM data.

## Decisions needed before integration code

Determine the installed PBX product/version, whether it is existing production infrastructure, supported read/event interfaces, extension and trunk topology, and availability of a non-production integration environment. Do not assume that an Asterisk-compatible distribution exposes the same provisioning API. Read-only discovery comes before any changes to telephone routing.

## Acceptance

Real supported inbound/outbound call tests; authorization for logs/recordings; extension and trunk/queue/IVR behavior; disabled-module isolation; existing module regression checks. Never represent a fake driver as proof of a working PBX integration.
