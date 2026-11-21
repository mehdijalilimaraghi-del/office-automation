# V4 telephony code map

## Confirmed existing entry points

- app/Providers/AppServiceProvider.php conditionally registers TelephonyServiceProvider using config/telephony.php.
- app/Modules/Telephony/routes.php: isolated management and token-report routes. EnsureTelephonyEnabled also protects previously cached routes.
- app/Core/Settings/Services/SystemSettingsService.php: non-secret organization settings.
- app/Core/Users/Http/Controllers/UserController.php and app/Models/User.php: organization users; extension ownership must be explicit.
- app/Core/Departments/Models/Department.php: department context for scoped access.
- app/Modules/Notifications/Services/NotificationService.php: existing notification boundary.
- app/Http/Middleware/HandleInertiaRequests.php: frontend abilities.
- resources/js/Layouts/AuthenticatedLayout.vue: navigation and notifications.
- database/seeders/CorePermissionSeeder.php: permission catalog.
- tests/Feature: SQL Server-backed authorization and HTTP tests.

## V4 implementation and remaining scope

- Implemented: app/Modules/Telephony/Providers/TelephonyServiceProvider.php, routes.php, ModemController, ModemDiscoveryService and Discovery/Modem models. Driver contracts and audio integration remain pending.
- Implemented: resources/js/Pages/Telephony/Index.vue and Discovery.vue. Windows helper and installer: tools/WindowsTelephony. Tests: tests/Feature/Modules/Telephony.
- Pending UI: extensions/phone book, call history, recordings and PBX configuration.
- Dedicated additive migrations and integration tests for the selected supported PBX.
- Feature disablement must avoid registering telephony routes, jobs, listeners or navigation.
- Telephony must not query or require CRM data.

## Decisions needed before integration code

The owner has no installed PBX. The modem is a Gnet FM56PCI with LSI PCI-SV92PP Soft Modem driver on another Windows computer. Obtain its capability report, then establish a non-production PBX environment and test the voice path. Do not assume that an Asterisk-compatible distribution exposes the same provisioning API. Read-only discovery comes before any changes to telephone routing.

## Acceptance

Real supported inbound/outbound call tests; authorization for logs/recordings; extension and trunk/queue/IVR behavior; disabled-module isolation; existing module regression checks. Never represent a fake driver as proof of a working PBX integration.
