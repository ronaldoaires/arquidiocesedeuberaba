
## Required project conventions

- Write all identifiers, classes, methods, variables, namespaces, comments, PHPDoc, tests, internal route names and generated technical documentation in English.
- Write all user-facing labels, messages, validation feedback, accessibility text and emails in Brazilian Portuguese (pt_BR).
- Preserve Portuguese public legacy URLs and original imported data for compatibility; map legacy database fields explicitly to English destination fields.
- Separate controllers and requests into Site, API and Account contexts; separate views into site and account. Keep shared delivery infrastructure at the controller root and Filament resources under app/Filament.
- This application runs on shared hosting without symbolic links. Serve public media through StorageController; never run storage:link. Keep private documents outside the public disk.
- Preserve the existing public design while rebuilding its styles in Tailwind CSS.
- Never modify the legacy source database. Do not run destructive resets or imports before a consistent backup and restore have been verified.
- Follow docs/PROJECT_CONVENTIONS.md and the migration plan. Source schema names and imported values are compatibility data, not a naming example for new code.