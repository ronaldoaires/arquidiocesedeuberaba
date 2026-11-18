<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

## Required project conventions

- Write all identifiers, classes, methods, variables, namespaces, comments, PHPDoc, tests, internal route names and generated technical documentation in English.
- Write all user-facing labels, messages, validation feedback, accessibility text and emails in Brazilian Portuguese (pt_BR).
- Preserve Portuguese public legacy URLs and original imported data for compatibility; map legacy database fields explicitly to English destination fields.
- Separate controllers and requests into Site, API and Account contexts; separate views into site and account. Keep shared delivery infrastructure at the controller root and Filament resources under app/Filament.
- This application runs on shared hosting without symbolic links. Serve public media through StorageController; never run storage:link. Keep private documents outside the public disk.
- Preserve the existing public design while rebuilding its styles in Tailwind CSS.
- Never modify the legacy source database. Do not run destructive resets or imports before a consistent backup and restore have been verified.
- Follow docs/PROJECT_CONVENTIONS.md and the migration plan. Source schema names and imported values are compatibility data, not a naming example for new code.

## Administrative conventions learned from the reference project

- D:/www/laravel/unset is a read-only reference. Do not modify it while working on this application.
- Never expose a slug input in any Filament create/edit schema, including relationship managers. Generate slugs through App/Support/SlugGenerator from the record title or name. Preserve existing slugs when titles change; uniqueness includes soft-deleted records.
- Every resource with a cover/photo column must display that image in its table using ImageColumn. Prefer an existing thumbnail, fall back to the original, and use the public disk through StorageController. Apply the same rule to related-record tables and image previews in the media library.
- Image processing belongs in a dedicated service, following the separation demonstrated by the reference ImageService. Preserve original migrated files and shared references; do not copy destructive replacement/deletion behavior without the appropriate reference checks and successful replacement write.

## Legacy administrative parity

- config/admin.php defines the ordered editable fields for each legacy module. Compare against docs/migration/evidence/admin-form-contract.json and the original active HTML controls before changing a schema. Do not add technical fields, author/editor selectors, profile fields, counters or slug inputs merely because their columns exist. Preserve those stored values when editing.
- All activity columns use an authorized ToggleColumn. Never display the ID column. Edit and delete actions use large icon buttons with Portuguese tooltips. Successful create and edit operations redirect to the resource index; do not offer create-another.
- Parish clergy assignments remain editable in the parish form. Gallery-photo creation accepts multiple images and creates one record per file; editing preserves the original read-only image preview.
- MediaLifecycle removes unreferenced public media only after a successful database commit. Check references across every active (including unpublished) record and original/thumbnail aliases before removal. Keep a verified private recovery copy, support restoration, and never run an indiscriminate orphan purge. Editor uploads discarded before saving are tracked per form, not globally.
- User deletion is recoverable soft deletion; preserve content authorship. Panel mutations use database transactions. Never change the read-only reference project or legacy source data.

- Audit legacy control types, choices and JavaScript initialization as well as field names. The parish main form shows selected clergy and an Add clergy button. Use a lazy server-paginated modal (10 rows per page) with search, checkboxes and a role select enabled only after checking. Preserve draft selections across search/pages, cancel without applying and synchronize confirmed selections through the modelable field. Hydrate saved selections/roles. Never replace fixed role choices with free text. Parish schedules and event text use the original simple formatting toolbar; clergy's three historical function slots use radio choices. Preserve imported nonstandard role values on unchanged saves.

- Review administrative forms one at a time with the user. Parish, Article, NewsItem and ClergyMember create/edit use normal resource pages; field-level pickers/document dialogs and existing relationship actions may still use modals. Do not silently expand a single-form review into unrelated schema changes. Resource creation actions must link to a registered create page when present. Reviewed-resource edit actions must link to the edit page. Keep full create/edit-page redirects to the index. Use XL icon buttons with XL icons for table edit/delete actions.

- Parish website/social URL inputs must validate HTTP/HTTPS on the server; email must validate as email. Leave optional fields optional. Show the parish activity toggle last, after clergy, and wrap the complete create/edit form including submit/cancel actions in a native contained Section card.
- Public parish clergy cards must include only active, nondeleted assigned clergy. Keep inactive members and their assignments in the admin, marking Inativo with a red danger badge in both the picker and selected summary. Never change stored activity merely to affect visibility. Omit empty public clergy blocks. Honor legacy role priority and historical function fallback for visible members. This explicit user correction supersedes the original legacy JOIN behavior and the earlier inactive-card decision.
- The user may be mistaken when reporting expected behavior. Inspect actual data and explain discrepancies. When intended business behavior remains ambiguous, ask the user before changing established publication rules; do not infer that a count mismatch authorizes publishing inactive records.

- Shared admin table status standard: an active toggle has the Desativar tooltip; an inactive toggle has Ativar. After an authorized, successful persisted change, show a top-positioned success notification titled Status ativado or Status desativado. Never report success after a rejected/failed update. Apply this convention when reviewing subsequent forms/tables.
- Do not offer a Registros excluídos/TrashedFilter in normal admin listings. Preserve the default soft-delete query scope so removing the filter never exposes deleted rows. Keep deleted records/media recovery data intact; removing a UI filter is not a purge.

- Article create/edit use normal resource pages with the complete form and submit/cancel actions in a native Section card, activity last, and index redirects after saving. Expose only cover, title, registration date, rich text and activity. Keep automatic authorship/slugs, public-disk media and MediaLifecycle form cleanup. Labels follow the original article form (Data de Cadastro and Texto).
- Fullscreen was explicitly authorized and installed using mdobes/rich-editor-fullscreen 1.3.3. Preserve the installed plugin and the existing PreservingRichEditor class: append one fullscreen tool group while retaining formatting, documents, image resizing and original HTML casts. Keep the Portuguese translations under lang/vendor/rich-editor-fullscreen/pt_BR. Publish plugin assets with artisan filament:assets during deployment. The toolbar button and editor-focused Ctrl+Shift+F toggle fullscreen; Escape exits. Do not replace the editor or modify vendor code.

- Article and NewsItem index tables display the existing visits value as a read-only, sortable Visualizações column with Brazilian integer formatting. NewsItem also displays its related category title in a searchable/sortable Categoria column, with Sem categoria for missing parents. Preserve stored counters when viewing/editing; display null as zero without rewriting the database. Never add visits to editable form fields.
- NewsItem create/edit use normal registered resource pages, complete Section cards, activity last, original Data de Cadastro/Texto labels and index redirects after save. Keep precisely the original news fields, internal authorship/slugs, stable public URLs and media cleanup hooks.
- Every administrative rich-text editor must expose exactly one localized fullscreen control. Use PreservingRichEditor for future rich-text fields so toolbars, plugins, media behavior and HTML preservation stay consistent. Current coverage checks every one of the 14 modules with rich-text fields, including the simple parish/event editors.

- ClergyMember listing portraits are circular and prefer existing public thumbnails. Display the related category and existing read-only views counter. Below the name, use the native muted description for the three stored function slots; hide blanks and show duplicate labels only once without rewriting their stored values. Do not infer those functions from separate parish-assignment roles.
- ClergyMember forms have exactly three root Section cards: Conteúdo (photo, name, category, personal date text and rich description), Paróquias (the existing three text/link pairs), and Funções (the three original function radios and final activity toggle). Preserve all 17 legacy editable fields, with data_apresentacao now mapped to presentation_date DATE; keep partial birthday/ordination text and HTML/slugs/counters unchanged.
- The user explicitly asked to leave clergy parish information as it is. Preserve the three legacy text/link pairs and the separate existing parish assignment manager; do not replace them, merge them or synchronize one into the other without a new instruction.

- Every module with a visits column must show the read-only Visualizações counter and an inclusive minimum/maximum Visualizações filter. Bounds are optional nonnegative integers; null stored counts behave as zero in display/filtering without data rewrites. This applies to previous reviews and all future views-bearing modules.
- Always supply Portuguese model/plural labels to shared tables and create actions, including relationship managers. Never expose generated English class names in button labels, modal headings, empty states or notifications. Parish/clergy assignment actions use Adicionar vínculo, a contextual Portuguese modal heading and Salvar vínculo; their tables show actual parish/clergy/function columns instead of an ID heading.
- All dedicated website/social/link fields must accept optional valid HTTP/HTTPS URLs, including clergy parish links. Do not silently invent URLs, prefix invalid values or delete historical invalid links. Existing invalid data must be corrected or cleared before its form can save. The current private audit identifies three preexisting invalid values in test records.
- Presentation date is now a nullable DATE column named presentation_date, displayed as d/m/Y with no time. Use PresentationDate::toIso for legacy normalization and the strict date-only picker. Preserve the exact original strings in legacy_presentation_date_text (hidden from normal serialization/forms). The only authorized invalid-date conversion was clergy id 168 with dsafda to operational NULL. All other invalid/incomplete dates must abort conversion. The legacy source database remains untouched; never revert this field to a free-text editor or discard its archive.

- Related-record create/edit/delete actions must stay on the current parent edit page, close their modal on success and refresh only their own table. Never reuse standalone resource URLs or index redirects inside RelatedContentManager; preserve unsaved parent fields and pending media. Only standalone full-record saves follow the index redirect convention. Test actual relation mutations for absent redirects and retained parent drafts.

- Page create/edit now use normal resource pages with the complete form/actions inside a native Section card and activity last. Keep exactly the seven original fields. Label category_id as Sub Página in the form; group Menu, Sub Página and integer Posição in one responsive 3/3/1 row (stack on small screens). The index shows readable Menu and related Categoria, with search/sort and eagerly loaded category data. Preserve automatic slugs, existing URLs/counters/media and in-place section relationship actions.

- Page list rows with no menu must show an empty cell, without a Sem menu placeholder. Wrap the full page title within a constrained width; also wrap menu/category labels so long values do not push edit/delete actions off screen. Keep large accessible action icons.

- Category resources for Institutes, News, Clergy and Pages remain registered and authorized but are hidden from the main sidebar. Access each through a Portuguese Categorias button on its parent listing. Institute category tables explicitly omit the cover column; do not delete stored media/schema values to remove this presentation.

- Hide ParishClergyAssignments, ContactSubmissions, MediaAssets and PageSections from the main sidebar without unregistering routes or deleting records. Manage parish/clergy assignments through their parent forms. Provide a Portuguese Complementos button on the Page index linking to PageSections, while retaining each page edit form's existing section manager.

- Every auxiliary listing reached through a parent-list button needs a visible Portuguese return action with an explicit parent index URL, independent of browser history. PageSections and PageCategories use Voltar para Páginas; InstituteCategories, ClergyCategories and NewsCategories return to Institutos, Clero and Notícias respectively. Keep this return path when adding future auxiliary screens or hiding sidebar entries.

- Newspapers use one read-only Mês/Ano column (e.g. janeiro/2026), sorted by year/month, plus existing visits and downloads counters. Do not reset imported counts or expose counters in forms. New year defaults use America/Sao_Paulo and never replace saved years. A newspaper reading counts only when its validated published file is opened; the detail HTML alone does not count again. Count explicit download endpoint GET deliveries separately, excluding HEAD, Range, conditional failures, missing/unsafe files and smoke checks. Preserve content timestamps and use atomic COALESCE increments.
- Public file previews use the global x-site.file-dialog component and file-preview.js. Keep native modal keyboard/focus handling, scroll restoration, separate explicit download links, no-JavaScript href fallback, same-origin previews and private-file protections through StorageController. Newspaper preview redirects count once before raw PDF/range delivery. Native PDF rendering depends on browser support; unsupported document formats offer download instead of pretending to render. Do not alter original uploaded bytes or rich HTML to enable previews.

- Hide RadioPrograms and RadioTracks from the main sidebar; maintain their registered routes and parent radio relationship managers. The Programação table inside radio forms explicitly omits the visits column without deleting/resetting stored counts; this user-requested context exception does not remove other resource view counters.

- PastoralGroups belongs to Mídias. Hide PastoralDownloads, PastoralGalleries, PastoralVideos and PastoralGalleryPhotos from the sidebar while retaining registered routes and parent relationship managers. Pastoral downloads have operational unsigned visits/downloads columns, separate from the legacy import field contract; show both read-only with a visits filter, never as editable controls.
- TrackedPublicFile is shared by newspaper and pastoral delivery. Pastoral files require a published, nondeleted parent before preview/download counting; reuse StorageController path/MIME protection and existing no-HEAD/no-Range/no-smoke counting. Never reset counters or alter source/media fields to record activity.
- Pastoral videos remain ID-only forms. Display title/thumbnail from YouTubeVideoMetadata without overwriting legacy fields. Batch metadata requests, cache successful results 24 hours and failures 15 minutes, retain last-good values for seven days, and memoize per request to avoid repeated database cache queries. Validate IDs and use only fixed YouTube endpoints; never render provider-returned HTML. Preserve any stored legacy title/cover. Use the admin Assistir vídeo modal and site click-to-play component, with an explicit YouTube fallback for provider restrictions. Tests disable real metadata requests via YOUTUBE_METADATA_ENABLED and explicitly fake HTTP in metadata tests.

- Event tables display starts_on and ends_on as sortable Data de início/Data de fim columns in d/m/Y format. Event body_html uses the full shared PreservingRichEditor with documents, resizable images and fullscreen, explicitly replacing the former simple event toolbar. Parish schedules keep their simple toolbar. Do not change stored dates, HTML or the event field contract for this presentation update.

- Award listings hide the views column and display the existing category_text and year fields as Categoria and Ano. Preserve stored view counts. New award forms default year to the current America/Sao_Paulo year; editing must preserve the saved year. This explicit display exception overrides the general visible-views-column convention for awards.

- Hide GalleryPhotos, Deaneries and Cities from the admin sidebar without unregistering resources or deleting data. Parish listings provide Foranias and Cidades buttons to their authorized index routes; both auxiliary listings provide Voltar para Paróquias. Gallery photos remain managed through the existing gallery relationship manager.

- Site settings explicitly expose the existing cover_path as an image upload labeled Logo; the index image column is also Logo. This user-authorized field addition overrides the original config form contract. Serve uploaded logos through the public disk/StorageController and use logo_url in the site header/footer. Keep the existing static logo as fallback when no configured file exists. Preserve media-reference checks and recovery behavior on replacement/removal; do not rename or drop the existing database column.

- User forms explicitly expose existing cover_path as an optional public-disk image upload labeled Foto; keep global mass-assignment permissions restricted. User tables label the circular image Foto and display searchable E-mail plus a Nível de acesso badge: legacy level 3 is Administrador in danger/red, level 1 is Usuário, other stored values remain identifiable without inventing permissions. User media updates use the same reference-aware, after-commit cleanup/tracking/recovery as content models. Preserve stored access levels, passwords and existing photos when not edited.

- The admin dashboard has exactly six module cards in this explicit order: Notícias (noticia), Artigos (artigo), Eventos (evento), Páginas (pagina), Paróquias (paroquia), Destaques (slide). Hide Banners and Magazines from the sidebar and dashboard without deleting their routes/data. Omit the former Visão geral dos conteúdos heading and aggregate summary cards; retain each module's statistics, scheduled-news badges, listing/create shortcuts and manual refresh. Parish remains in its existing sidebar group. Registered create pages are linked directly; modal-only resources use their native ?action=create index URL. Do not change reviewed form navigation to implement shortcuts.
- ContentDashboard uses one scoped, read-only aggregate query per permitted selected dashboard module, excludes soft-deleted rows, treats null view sums as zero and identifies nonstandard status values separately. Do not invent visitor uniqueness/trends or mutate counters while reading statistics. Enforce admin authorization in both widget and service. Use a manual refresh action instead of automatic polling; clear per-request report cache when refreshing.

- Dashboard chart follows the explicit user correction in docs/PROJECT_CONVENTIONS.md: one Visualizações de conteúdo card containing its graph and controls; sum existing cumulative visits by the records created_at registration month/day. Use the original six tables directly, preserve counters, exclude soft-deleted records and keep the six tabs aligned with ContentDashboard::MODULE_ORDER. Do not reinstate a visit-history collector, ranking, summary cards or separate report sections.
