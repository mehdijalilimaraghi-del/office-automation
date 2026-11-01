# Project management rules

## Project administration boundary

- Project creation and administration require an active user with the relevant project permission and either membership in, or management of, the active department configured as **واحد مدیریت پروژه‌ها** in Organization Settings.
- Active system administrators retain the existing administrative bypass; inactive accounts are always denied.
- Authorized Project Management staff administer non-default project records centrally; edit/delete controls do not depend on being the selected operational project manager.
- The active manager of the configured project-management department receives organization-wide project visibility. Its parent must be the department configured as **واحد مدیریتی**; these roles are stored by department ID, not by name.
- The project manager selected during creation must be an active member of, or manager of, the project-management department (or an active system administrator).
- Project manager choices in the creation page include eligible active members and the department manager.
- Permission grants do not replace department responsibility. Leaving and no longer managing the project-management department removes effective project-administration access even if a role still grants project permissions.

## Project sections and task assignment

- A project can contain sections assigned to different organizational departments.
- The responsible department and its manager define the operational ownership of a section.
- Task assignees must be selected from the active members of the section's responsible department (including its manager where applicable), rather than from legacy project-member records.
- Historical project membership records must not be destructively removed as part of the transition; they may be retained for audit/history while being excluded from authorization and current workflows.
- New project creation must not create legacy `project_user` membership rows; project managers are defined by `projects.manager_id` and eligible organizational membership.

## Project detail UI

- The project detail page presents section cards with a clear hierarchy, section ownership, and explicit section actions.
- Project-wide progress is displayed once in a dedicated summary; duplicate progress indicators were removed from section headings, leaving the detailed progress indicator inside each expanded section.
- Each section's task list has its own heading and count, with a dedicated empty state when no tasks exist.
- Summary and empty-state glyphs were replaced with consistent inline SVG icons, and the section title is not repeated in its expanded details.
- Keep the page free of temporary TODO copy and preserve the permission-driven visibility of project and section actions.

## Compatibility and rollout

- V1 remains the active product version.
- Regression tests for project creation must explicitly give the selected project manager active membership in the project-management department; permission-denial tests must use a non-system-admin user so the system-admin bypass does not invalidate the scenario.
- Project manager lifecycle and policy tests must configure the management and project-management department IDs in `system_settings`; renaming either department must not change access. A `manager_id` alone does not grant project-administration eligibility.
- Organization Settings can also assign a department to the **دبیرخانه** role. No department names are reserved or seeded as defaults; the role mappings reference existing department IDs.
- Changes to authorization should be covered by feature tests for allowed members, users outside the project-management department, inactive users, and system administrators.
- Project sections can be created, edited, and deleted from the project detail page. Sections with tasks or documents cannot be deleted, and changing a section's responsible department is blocked when tasks already exist.
- Task assignees, delegates, and next-activity assignees must be active members of the section's responsible department or that department's active manager.
- The project detail page no longer presents legacy project members. Existing membership rows are retained for historical compatibility, but are not used to authorize project visibility or task assignment.
- Project deletion is blocked when task or document history is attached, to avoid cascading loss of operational records.


## Correspondence-driven project creation and response task

The authoritative workflow contract is `CORRESPONDENCE_PROJECT_WORKFLOW.md`.

- The four Project Management employee actions are requests with mandatory feedback, not final workflow transitions. The PM manager's decision is a separate step and must be authorized independently.
- The response department is selected per project during creation. An organization-level default may prefill the field but must remain overridable by an authorized creator.
- The new project's response Section and response Task belong only to the selected response department. Do not provision an automatic Section or response Task for the origin/requesting department.
- The response Task is linked to both the new Project and the original incoming Correspondence. A response Correspondence created from that Task must preserve all three links: Task, Project and source Correspondence.
- Use existing Projects, Tasks and Correspondence modules and their authorization/workflow contracts; do not introduce parallel task or workflow structures.
- Implementation status is tracked in `CORRESPONDENCE_PROJECT_WORKFLOW.md`; documentation is complete, while the project-request workflow and project/response-task integration are not yet implemented.
