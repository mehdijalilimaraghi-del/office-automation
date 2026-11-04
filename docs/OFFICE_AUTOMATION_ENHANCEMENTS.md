# Office Automation Enhancements — Approved Scope

Last updated: 2026-11-04
Branch: development

## Approved product decisions

### 1. Manager self-assignment / accepting responsibility

A department manager must be able to personally take responsibility for an actionable correspondence item and a task. This is a distinct business action (accept responsibility), not a referral to the manager themselves. It must not create a self-referral. The operation must update the current responsible user/assignee, preserve assignment/workflow history, and create an explicit audit event. Existing authorization, department-manager scope, workflow-state, finalized-task and pending-review protections remain enforced.

### 2. External correspondence recipient

When an external organization or department is selected, its configured default contact is selected automatically. The user may change the recipient for an individual letter. A recipient may also be an organizational addressee without a named person.

Recipient addressing data is modeled as separate parts:
- Organizational title prefix: e.g. «مدیریت»، «مدیرعامل»، «معاونت»، «ریاست»
- Organizational position: e.g. «مدیر واحد مالی»
- Personal salutation: e.g. «جناب آقای»، «سرکار خانم»
- Professional title: e.g. «مهندس»، «دکتر»
- Contact full name

The organizational title prefix is an additional independent field, not a replacement for position. At most one active default contact should exist per external department (and an organization-level default where no department is selected). The selected addressing text must be snapshotted on correspondence finalization so later contact edits do not rewrite historical letters.

### 3. Letterhead, PDF and physical printing

One shared correspondence document template and coordinate system must support both PDF and physical printing. For each output/print operation, the operator can select:
- With letterhead
- Without letterhead

The choice applies both to generated PDF and to physical print output. Letterhead elements are configurable independently, including image/background, logo, organization name, address, telephone, email, website and footer. Contact information such as address and phone is part of the configurable letterhead composition, but each element can be enabled/disabled independently. Page size, margins, content start position and registration number/date/attachment coordinates are configurable. The no-letterhead output suppresses only letterhead layers; body text and coordinates remain unchanged. Preview is required before output.

## Implementation stages and status

| Stage | Scope | Status |
|---|---|---|
| 0 | Approved requirements and architecture recorded | Complete |
| 1 | Manager accepts responsibility in Correspondence and Tasks, with history/audit and regression tests | Not started |
| 2 | External department/contact defaults, addressing fields, selection and historical snapshot | Not started |
| 3 | Shared letterhead/template settings and PDF/print modes | Not started |
| 4 | End-to-end regression, build and manual acceptance | Not started |

## Engineering constraints

- Implement only on `development`; do not change `master` or deploy to the server.
- Inspect current module contracts and authorization before implementation.
- Keep assignment separate from review/approval.
- Do not model manager acceptance as assigning a task/letter to oneself through a referral action.
- Add regression coverage for authorization, history, no-self-referral and output variants.
- Update this document and the authoritative status/context documents after each completed stage.
- Local PHP tests and frontend build are run by the project owner; never report them as passed until the owner supplies results.
