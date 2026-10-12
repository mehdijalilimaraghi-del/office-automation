# Project management rules

## Project administration boundary

- Project creation and administration require an active user with the relevant project permission and membership in the active **مدیریت پروژه** department whose parent department is **مدیریت**.
- Active system administrators retain the existing administrative bypass; inactive accounts are always denied.
- The project manager selected during creation must be an active member of the same project-management department (or an active system administrator).
- Project manager choices in the creation page are restricted to eligible active users.
- Permission grants do not replace department membership. Leaving the project-management department immediately removes effective project-administration access even if a role still grants project permissions.

## Project sections and task assignment

- A project can contain sections assigned to different organizational departments.
- The responsible department and its manager define the operational ownership of a section.
- Task assignees must be selected from the active members of the section's responsible department (including its manager where applicable), rather than from legacy project-member records.
- Historical project membership records must not be destructively removed as part of the transition; they may be retained for audit/history while being excluded from authorization and current workflows.

## Compatibility and rollout

- V1 remains the active product version.
- Changes to authorization should be covered by feature tests for allowed members, users outside the project-management department, inactive users, and system administrators.
- Section CRUD, the remaining task assignment validation, legacy member UI removal, and the project detail-page redesign are tracked as follow-on implementation work in this rollout.
