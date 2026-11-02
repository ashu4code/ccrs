# Controller Dependency Map

Generated on 2026-10-31 by scanning `app/Http/Controllers/**/*.php`.
Dependencies are grouped by how they enter each controller: `use` imports
(models, enums, resources, facades, helpers), action type-hints (`Request` and
route-model bindings), and the base class.

**Key finding:** no controller uses constructor injection — all dependencies
arrive via `use` imports and method signatures.

## App root controllers

### AuthController

- **File:** `app/Http/Controllers/AuthController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Models:** `App\Models\Consumer`
- **Resources:** `App\Http\Resources\ConsumerResource`, `App\Http\Resources\UserResource`
- **Internal controllers:** `App\Http\Controllers\Auth\OtpLoginController`
- **Framework:** `Auth` facade, `Illuminate\Http\Request`, `Illuminate\Contracts\View\View`, `Illuminate\Http\JsonResponse`, `Illuminate\Http\RedirectResponse`
- **Actions:** `showLogin`, `login`, `logout`, `me`

### DashboardController

- **File:** `app/Http/Controllers/DashboardController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Enums:** `App\Enums\ComplaintStatus`, `App\Enums\UserRole`
- **Models:** `App\Models\Complaint`
- **Framework:** `Illuminate\Database\Eloquent\Builder`, `Illuminate\Support\Collection`, `Illuminate\Http\Request`, `Illuminate\Contracts\View\View`
- **Actions:** `index`

### GrievanceController

- **File:** `app/Http/Controllers/GrievanceController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Enums:** `App\Enums\ComplaintCategory`, `App\Enums\ComplaintLevel`, `App\Enums\ComplaintStatus`, `App\Enums\UserRole`
- **Models:** `App\Models\Complaint`, `App\Models\ComplaintAction`, `App\Models\Consumer`, `App\Models\User`
- **Resources:** `App\Http\Resources\ComplaintResource`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`
- **Notifications:** `App\Notifications\ComplaintLodgedNotification`
- **Framework:** `DB` facade, `Illuminate\Validation\Rule`, `Illuminate\Http\Request`, `View`, `JsonResponse`, `RedirectResponse`
- **Route-model bindings:** `Complaint`
- **Actions:** `index`, `show`, `track`, `create`, `register`, `forwardToAuthority`, `resolveGrievance`

### UserNotificationController

- **File:** `app/Http/Controllers/UserNotificationController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Models:** `App\Models\Complaint`, `App\Models\User`
- **Framework:** `Illuminate\Http\Request`, `Illuminate\Http\RedirectResponse`
- **Actions:** `open` (receives `string $notification`, not a bound model)

## Auth controllers

### Auth\OtpLoginController

- **File:** `app/Http/Controllers/Auth/OtpLoginController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Models:** `App\Models\Consumer`, `App\Models\Otp`
- **Resources:** `App\Http\Resources\ConsumerResource`
- **Framework:** `Auth`, `DB`, `Log` facades, `Illuminate\Support\Str`, `Request`, `JsonResponse`, `RedirectResponse`
- **Actions:** `request`, `verify`

### Auth\FirstPasswordController

- **File:** `app/Http/Controllers/Auth/FirstPasswordController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Resources:** `App\Http\Resources\ConsumerResource`
- **Framework:** `Illuminate\Validation\Rules\Password`, `Request`, `View`, `JsonResponse`, `RedirectResponse`
- **Actions:** `create`, `store`

## Admin controllers

### Admin\HierarchyController

- **File:** `app/Http/Controllers/Admin/HierarchyController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Models:** `App\Models\Circle`, `App\Models\Division`, `App\Models\Section`, `App\Models\Subdivision`
- **Framework:** `Illuminate\Database\Eloquent\Builder`, `Request`, `JsonResponse`
- **Actions:** `circles`, `divisions`, `subdivisions`, `sections`

### Admin\RoleController

- **File:** `app/Http/Controllers/Admin/RoleController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Enums:** `App\Enums\UserRole`
- **Models:** `App\Models\Circle`, `App\Models\Discom`, `App\Models\Division`, `App\Models\RoleType`, `App\Models\Section`, `App\Models\Subdivision`, `App\Models\User`
- **Framework:** `Illuminate\Validation\Rule`, `Request`, `View`, `RedirectResponse`
- **Route-model bindings:** `User`
- **Actions:** `index`, `create`, `store`, `edit`, `update`, `destroy`

### Admin\PortalMenuController

- **File:** `app/Http/Controllers/Admin/PortalMenuController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Models:** `App\Models\PortalMenuItem`
- **Framework:** `Closure`, `Route` facade, `Illuminate\Support\Str`, `Illuminate\Validation\Rule`, `Request`, `View`, `RedirectResponse`
- **Route-model bindings:** `PortalMenuItem`
- **Actions:** `index`, `edit`, `store`, `update`, `toggle`, `move`, `destroy`

### Admin\ComplaintCategoryController

- **File:** `app/Http/Controllers/Admin/ComplaintCategoryController.php`
- **Base:** `App\Http\Controllers\Controller`
- **Models:** `App\Models\ComplaintCategory`
- **Framework:** `Illuminate\Validation\Rule`, `Request`, `View`, `RedirectResponse`
- **Route-model bindings:** `ComplaintCategory`
- **Actions:** `index`, `create`, `store`, `edit`, `update`, `destroy`

## Base class

### App\Http\Controllers\Controller

- **File:** `app/Http/Controllers/Controller.php`
- Abstract, empty — no traits or Laravel boilerplate dependencies.

## Shared patterns

- Every controller extends `App\Http\Controllers\Controller`.
- Zero constructor-injected services across the codebase.
- Facades in play: `Auth` (AuthController, OtpLoginController), `DB`
  (GrievanceController, OtpLoginController), `Log` (OtpLoginController),
  `Route` (PortalMenuController) — plus validation `Rule`.
- Route-model binding covers `Complaint` (GrievanceController), `User`
  (RoleController), `PortalMenuItem` (PortalMenuController), and
  `ComplaintCategory` (ComplaintCategoryController).
