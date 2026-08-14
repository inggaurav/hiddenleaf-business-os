# Route audit

Generated deterministically by `php tools/forensic-audit.php`. The audit covers 335 non-vendor application routes.

| Severity | Findings |
|---|---:|
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 35 |

Critical means an apparently sensitive mutation has neither authentication middleware nor an explicit public-endpoint classification. High means a route points to a missing application controller/method. Medium covers duplicate route names. Low covers duplicate signatures and unnamed controller routes.

## Critical

No findings.

## High

No findings.

## Medium

No findings.

## Low

- `DELETE api/v1/account → App\Http\Controllers\Api\V1\AccountController@destroy`
- `POST api/v1/auth/login → App\Http\Controllers\Api\V1\AuthController@login`
- `POST api/v1/auth/logout → App\Http\Controllers\Api\V1\AuthController@logout`
- `GET|HEAD api/v1/client-users → App\Http\Controllers\Api\V1\UserDirectoryApiController@users`
- `GET|HEAD api/v1/helpdesk/tickets → App\Http\Controllers\Api\V1\HelpdeskApiController@tickets`
- `POST api/v1/helpdesk/tickets → App\Http\Controllers\Api\V1\HelpdeskApiController@storeTicket`
- `GET|HEAD api/v1/helpdesk/tickets/{ticket} → App\Http\Controllers\Api\V1\HelpdeskApiController@ticketDetails`
- `POST api/v1/licensing/activate → HiddenLeaf\Http\Controllers\Api\V1\LicensingController@activate`
- `POST api/v1/licensing/deactivate → HiddenLeaf\Http\Controllers\Api\V1\LicensingController@deactivate`
- `GET|HEAD api/v1/licensing/entitlements → HiddenLeaf\Http\Controllers\Api\V1\LicensingController@entitlements`
- `POST api/v1/licensing/validate → HiddenLeaf\Http\Controllers\Api\V1\LicensingController@validateLicense`
- `GET|HEAD api/v1/media → App\Http\Controllers\Api\V1\MediaApiController@index`
- `POST api/v1/media/upload → App\Http\Controllers\Api\V1\MediaApiController@upload`
- `PUT api/v1/password → App\Http\Controllers\Api\V1\AccountController@updatePassword`
- `GET|HEAD api/v1/plans → App\Http\Controllers\Api\V1\PlanApiController@index`
- `GET|HEAD api/v1/plans/{plan} → App\Http\Controllers\Api\V1\PlanApiController@show`
- `GET|HEAD api/v1/products-services → App\Http\Controllers\Api\V1\ProductServiceApiController@index`
- `GET|HEAD api/v1/products-services/{item} → App\Http\Controllers\Api\V1\ProductServiceApiController@show`
- `PATCH api/v1/profile → App\Http\Controllers\Api\V1\AccountController@updateProfile`
- `GET|HEAD api/v1/purchase-invoices → App\Http\Controllers\Api\V1\SalesProcurementApiController@purchaseInvoices`
- `GET|HEAD api/v1/sales-invoices → App\Http\Controllers\Api\V1\SalesProcurementApiController@salesInvoices`
- `GET|HEAD api/v1/sales-proposals → App\Http\Controllers\Api\V1\SalesProcurementApiController@salesProposals`
- `GET|HEAD api/v1/staff-users → App\Http\Controllers\Api\V1\UserDirectoryApiController@users`
- `GET|HEAD api/v1/subscription → App\Http\Controllers\Api\V1\UserDirectoryApiController@subscription`
- `GET|HEAD api/v1/tokens → App\Http\Controllers\Api\V1\AccountController@tokens`
- `POST api/v1/tokens → App\Http\Controllers\Api\V1\AccountController@storeToken`
- `DELETE api/v1/tokens/{token} → App\Http\Controllers\Api\V1\AccountController@destroyToken`
- `GET|HEAD api/v1/user → App\Http\Controllers\Api\V1\AuthController@me`
- `GET|HEAD api/v1/users → App\Http\Controllers\Api\V1\UserDirectoryApiController@users`
- `GET|HEAD api/v1/vendor-users → App\Http\Controllers\Api\V1\UserDirectoryApiController@users`
- `GET|HEAD api/v1/warehouses → App\Http\Controllers\Api\V1\SalesProcurementApiController@warehouses`
- `GET|HEAD api/v1/workspaces → App\Http\Controllers\Api\V1\WorkspaceApiController@index`
- `GET|HEAD api/v1/workspaces/{workspace} → App\Http\Controllers\Api\V1\WorkspaceApiController@show`
- `POST login → App\Http\Controllers\Auth\AuthController@login`
- `POST register → App\Http\Controllers\Auth\AuthController@register`

## Guard interpretation

- Installer endpoints are intentionally public before installation and are closed by `InstallController::abortWhenInstalled()` after the install lock exists.
- Licensing activation/validation endpoints are intentionally public protocol endpoints; license lookup, domain binding, activation limits, signatures, and state checks provide their domain authorization.
- Tenant-scoped module routes use authenticated sessions plus controller-level workspace permission resolution; `CheckModuleStatus` additionally enforces module activation.
- Updater and module-management mutations are authenticated and perform explicit super-admin/workspace authorization in their controllers.
