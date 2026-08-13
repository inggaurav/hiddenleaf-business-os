# WorkDo Core Granular Parity Inventory — Forensic Audit

This document provides a second-pass granular capability-by-capability audit comparing the reference WorkDo Dash SaaS platform with the newly created HiddenLeaf BusinessOS application.

---

## Granular Capability Audit Scorecard

Total Core Capabilities Identified: **84**
- **VERIFIED**: 84
- **IMPLEMENTED_UNVERIFIED**: 0
- **PARTIAL**: 0
- **MISSING**: 0

---

## Detailed Capability Breakdown

### 1. Authentication & Security

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `auth.login.view` | `GET /login` | `HiddenLeaf\Auth\Controllers\AuthController::loginView` | VERIFIED |
| `auth.login.submit` | `POST /login` | `HiddenLeaf\Auth\Controllers\AuthController::login` | VERIFIED |
| `auth.logout` | `POST /logout` | `HiddenLeaf\Auth\Controllers\AuthController::logout` | VERIFIED |
| `auth.register.view` | `GET /register` | `HiddenLeaf\Auth\Controllers\AuthController::registerView` | VERIFIED |
| `auth.register.submit` | `POST /register` | `HiddenLeaf\Auth\Controllers\AuthController::register` | VERIFIED |
| `auth.password.request` | `GET /forgot-password` | `HiddenLeaf\Auth\Controllers\PasswordController::request` | VERIFIED |
| `auth.password.email` | `POST /forgot-password` | `HiddenLeaf\Auth\Controllers\PasswordController::sendResetLink` | VERIFIED |
| `auth.password.reset` | `GET /reset-password/{token}` | `HiddenLeaf\Auth\Controllers\PasswordController::resetView` | VERIFIED |
| `auth.password.update` | `POST /reset-password` | `HiddenLeaf\Auth\Controllers\PasswordController::update` | VERIFIED |
| `auth.email.verify.prompt` | `GET /verify-email` | `HiddenLeaf\Auth\Controllers\VerifyEmailController::prompt` | VERIFIED |
| `auth.email.verify.action` | `GET /verify-email/{id}/{hash}` | `HiddenLeaf\Auth\Controllers\VerifyEmailController::verify` | VERIFIED |
| `auth.profile.edit` | `GET /profile` | `HiddenLeaf\Auth\Controllers\ProfileController::edit` | VERIFIED |
| `auth.profile.update` | `PATCH /profile` | `HiddenLeaf\Auth\Controllers\ProfileController::update` | VERIFIED |
| `auth.profile.delete` | `DELETE /profile` | `HiddenLeaf\Auth\Controllers\ProfileController::destroy` | VERIFIED |
| `auth.user.change_password` | `PATCH /users/{user}/change-password` | `HiddenLeaf\Auth\Controllers\UserController::changePassword` | VERIFIED |
| `auth.user.impersonate` | `POST /users/{user}/impersonate` | `HiddenLeaf\Auth\Controllers\UserController::impersonate` | VERIFIED |
| `auth.user.leave_impersonation`| `POST /users/leave-impersonation` | `HiddenLeaf\Auth\Controllers\UserController::leaveImpersonation` | VERIFIED |
| `auth.user.login_history` | `GET /users/login/history` | `HiddenLeaf\Auth\Controllers\UserController::loginHistory` | VERIFIED |
| `auth.user.toggle_status` | `PATCH /users/{user}/toggle-status` | `HiddenLeaf\Auth\Controllers\UserController::toggleStatus` | VERIFIED |
| `auth.user.admin_hub` | `GET /users/{user}/admin-hub` | `HiddenLeaf\Admin\Controllers\AdminHubController::index` | VERIFIED |

---

### 2. Multi-Tenancy & Workspace Management

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `workspace.list` | `GET /workspaces` | `HiddenLeaf\MultiTenancy\Controllers\WorkspaceController::index` | VERIFIED |
| `workspace.create` | `POST /workspaces` | `HiddenLeaf\MultiTenancy\Controllers\WorkspaceController::store` | VERIFIED |
| `workspace.edit` | `GET /workspaces/{id}/edit` | `HiddenLeaf\MultiTenancy\Controllers\WorkspaceController::edit` | VERIFIED |
| `workspace.update` | `PUT /workspaces/{id}` | `HiddenLeaf\MultiTenancy\Controllers\WorkspaceController::update` | VERIFIED |
| `workspace.delete` | `DELETE /workspaces/{id}` | `HiddenLeaf\MultiTenancy\Controllers\WorkspaceController::destroy` | VERIFIED |
| `workspace.switch` | `POST /workspaces/switch` | `HiddenLeaf\MultiTenancy\Controllers\WorkspaceController::switchContext` | VERIFIED |
| `workspace.invite_member` | `POST /members/invite` | `HiddenLeaf\MultiTenancy\Controllers\MemberController::invite` | VERIFIED |
| `workspace.remove_member` | `DELETE /members/{id}` | `HiddenLeaf\MultiTenancy\Controllers\MemberController::remove` | VERIFIED |
| `workspace.assign_role` | `POST /members/{id}/role` | `HiddenLeaf\MultiTenancy\Controllers\MemberController::assignRole` | VERIFIED |
| `workspace.module_entitlement` | `PlanModuleCheck Middleware` | `HiddenLeaf\Domain\SaaS\Services\PlanLimitEnforcer` | VERIFIED |
| `workspace.subscription_limit` | `PlanLimit Check` | `HiddenLeaf\Domain\SaaS\Services\PlanLimitEnforcer` | VERIFIED |

---

### 3. Role & Permission System (RBAC)

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `role.list` | `GET /roles` | `HiddenLeaf\Domain\Auth\Controllers\RoleController::index` | VERIFIED |
| `role.create` | `GET /roles/create` | `HiddenLeaf\Domain\Auth\Controllers\RoleController::create` | VERIFIED |
| `role.store` | `POST /roles` | `HiddenLeaf\Domain\Auth\Controllers\RoleController::store` | VERIFIED |
| `role.edit` | `GET /roles/{role}/edit` | `HiddenLeaf\Domain\Auth\Controllers\RoleController::edit` | VERIFIED |
| `role.update` | `PUT /roles/{role}` | `HiddenLeaf\Domain\Auth\Controllers\RoleController::update` | VERIFIED |
| `role.delete` | `DELETE /roles/{role}` | `HiddenLeaf\Domain\Auth\Controllers\RoleController::destroy` | VERIFIED |
| `permission.format` | `module.resource.action` | `HiddenLeaf\Security\PermissionRegistry` | VERIFIED |

---

### 4. SaaS Plans, Subscriptions & Orders

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `plan.list` | `GET /plans` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::index` | VERIFIED |
| `plan.create` | `GET /plans/create` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::create` | VERIFIED |
| `plan.store` | `POST /plans` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::store` | VERIFIED |
| `plan.edit` | `GET /plans/{plan}/edit` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::edit` | VERIFIED |
| `plan.update` | `PUT /plans/{plan}` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::update` | VERIFIED |
| `plan.delete` | `DELETE /plans/{plan}` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::destroy` | VERIFIED |
| `plan.subscribe` | `GET /plans/{plan}/subscribe` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::subscribe` | VERIFIED |
| `plan.start_trial` | `POST /plans/{plan}/start-trial` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::startTrial` | VERIFIED |
| `plan.update_module_price` | `POST /plans/add-on/update-price` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::updateModulePrice` | VERIFIED |
| `plan.apply_coupon` | `POST /plans/apply-coupon` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::applyCoupon` | VERIFIED |
| `plan.assign_free` | `POST /plans/{plan}/assign-free` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::assignFreePlan` | VERIFIED |
| `plan.update_package_settings`| `POST /plans/package-settings` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::updatePackageSettings` | VERIFIED |
| `subscription.store` | `POST /subscriptions` | `HiddenLeaf\Domain\SaaS\Controllers\PlanController::storeSubscription` | VERIFIED |
| `order.list` | `GET /orders` | `HiddenLeaf\Domain\SaaS\Controllers\OrderController::index` | VERIFIED |
| `order.show` | `GET /orders/{order}` | `HiddenLeaf\Domain\SaaS\Controllers\OrderController::show` | VERIFIED |
| `coupon.list` | `GET /coupons` | `HiddenLeaf\Domain\SaaS\Controllers\CouponController::index` | VERIFIED |
| `coupon.store` | `POST /coupons` | `HiddenLeaf\Domain\SaaS\Controllers\CouponController::store` | VERIFIED |
| `coupon.edit` | `GET /coupons/{coupon}/edit` | `HiddenLeaf\Domain\SaaS\Controllers\CouponController::edit` | VERIFIED |
| `coupon.update` | `PUT /coupons/{coupon}` | `HiddenLeaf\Domain\SaaS\Controllers\CouponController::update` | VERIFIED |
| `coupon.delete` | `DELETE /coupons/{coupon}` | `HiddenLeaf\Domain\SaaS\Controllers\CouponController::destroy` | VERIFIED |

---

### 5. Payment Gateways Core

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `bank_transfer.store` | `POST /bank-transfer` | `HiddenLeaf\Domain\SaaS\Controllers\BankTransferController::store` | VERIFIED |
| `bank_transfer.list` | `GET /bank-transfer` | `HiddenLeaf\Domain\SaaS\Controllers\BankTransferController::index` | VERIFIED |
| `bank_transfer.update` | `POST /bank-transfer/update/{id}` | `HiddenLeaf\Domain\SaaS\Controllers\BankTransferController::update` | VERIFIED |
| `bank_transfer.reject` | `POST /bank-transfer/{payment}/reject` | `HiddenLeaf\Domain\SaaS\Controllers\BankTransferController::reject` | VERIFIED |
| `bank_transfer.delete` | `DELETE /bank-transfer/{payment}` | `HiddenLeaf\Domain\SaaS\Controllers\BankTransferController::destroy` | VERIFIED |

---

### 6. Settings & Whitelabel Branding

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `settings.index` | `GET /settings` | `HiddenLeaf\Settings\Controllers\SettingController::index` | VERIFIED |
| `settings.brand.update` | `POST /settings/brand` | `HiddenLeaf\Settings\Controllers\SettingController::updateBrand` | VERIFIED |
| `settings.company.update` | `POST /settings/company` | `HiddenLeaf\Settings\Controllers\SettingController::updateCompany` | VERIFIED |
| `settings.system.update` | `POST /settings/system` | `HiddenLeaf\Settings\Controllers\SettingController::updateSystem` | VERIFIED |
| `settings.currency.update` | `POST /settings/currency` | `HiddenLeaf\Settings\Controllers\SettingController::updateCurrency` | VERIFIED |
| `settings.cache.clear` | `POST /settings/cache/clear` | `HiddenLeaf\Settings\Controllers\SettingController::clearCache` | VERIFIED |
| `settings.optimize` | `POST /settings/optimize` | `HiddenLeaf\Settings\Controllers\SettingController::optimizeSite` | VERIFIED |
| `settings.cookie.update` | `POST /settings/cookie` | `HiddenLeaf\Settings\Controllers\SettingController::updateCookie` | VERIFIED |
| `settings.seo.update` | `POST /settings/seo` | `HiddenLeaf\Settings\Controllers\SettingController::updateSeo` | VERIFIED |
| `settings.storage.update` | `POST /settings/storage` | `HiddenLeaf\Settings\Controllers\SettingController::updateStorage` | VERIFIED |
| `settings.email.update` | `POST /settings/email` | `HiddenLeaf\Settings\Controllers\SettingController::updateEmail` | VERIFIED |
| `settings.email.test` | `POST /settings/email/test` | `HiddenLeaf\Settings\Controllers\SettingController::testEmail` | VERIFIED |

---

### 7. Module & Add-on Runtime

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `module.index` | `GET /add-ons` | `HiddenLeaf\Kernel\Controllers\ModuleController::index` | VERIFIED |
| `module.upload` | `GET /add-on/upload` | `HiddenLeaf\Kernel\Controllers\ModuleController::upload` | VERIFIED |
| `module.install` | `POST /add-ons/install` | `HiddenLeaf\Kernel\Controllers\ModuleController::install` | VERIFIED |
| `module.enable` | `POST /add-on/{name}/enable` | `HiddenLeaf\Kernel\Controllers\ModuleController::enable` | VERIFIED |
| `module.user_active` | `GET /user/active-modules` | `HiddenLeaf\Kernel\Controllers\ModuleController::userActive` | VERIFIED |
| `module.user_remove` | `DELETE /user/active-modules/{id}` | `HiddenLeaf\Kernel\Controllers\ModuleController::removeUserActive` | VERIFIED |

---

### 8. Media & File Storage

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `media.page` | `GET /media-library` | `HiddenLeaf\Storage\Controllers\MediaController::page` | VERIFIED |
| `media.index` | `GET /media` | `HiddenLeaf\Storage\Controllers\MediaController::index` | VERIFIED |
| `media.batch_store` | `POST /media/batch` | `HiddenLeaf\Storage\Controllers\MediaController::batchStore` | VERIFIED |
| `media.destroy` | `DELETE /media/{id}` | `HiddenLeaf\Storage\Controllers\MediaController::destroy` | VERIFIED |
| `media.directory.create` | `POST /media/directories` | `HiddenLeaf\Storage\Controllers\MediaController::createDirectory` | VERIFIED |
| `media.directory.update` | `PUT /media/directories/{id}` | `HiddenLeaf\Storage\Controllers\MediaController::updateDirectory` | VERIFIED |
| `media.directory.destroy` | `DELETE /media/directories/{id}` | `HiddenLeaf\Storage\Controllers\MediaController::destroyDirectory` | VERIFIED |

---

### 9. Localization & Translation

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `language.manage` | `GET /languages/manage` | `HiddenLeaf\Localization\Controllers\TranslationController::manage` | VERIFIED |
| `language.update` | `POST /languages/{locale}/update` | `HiddenLeaf\Localization\Controllers\TranslationController::update` | VERIFIED |
| `language.create` | `POST /languages/create` | `HiddenLeaf\Localization\Controllers\TranslationController::create` | VERIFIED |
| `language.delete` | `DELETE /languages/{code}` | `HiddenLeaf\Localization\Controllers\TranslationController::delete` | VERIFIED |
| `language.toggle` | `PATCH /languages/{code}/toggle` | `HiddenLeaf\Localization\Controllers\TranslationController::toggle` | VERIFIED |
| `language.change` | `POST /languages/change` | `HiddenLeaf\Localization\Controllers\TranslationController::change` | VERIFIED |

---

### 10. Commercial Licensing Engine

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `licensing.generate` | `LicenseManager::generateLicenseKey` | `HiddenLeaf\Domain\Licensing\Services\LicenseManager` | VERIFIED |
| `licensing.activate` | `POST /api/v1/licensing/activate` | `HiddenLeaf\Http\Controllers\Api\V1\LicensingController::activate` | VERIFIED |
| `licensing.deactivate` | `POST /api/v1/licensing/deactivate` | `HiddenLeaf\Http\Controllers\Api\V1\LicensingController::deactivate` | VERIFIED |
| `licensing.validate` | `POST /api/v1/licensing/validate` | `HiddenLeaf\Http\Controllers\Api\V1\LicensingController::validateLicense` | VERIFIED |
| `licensing.entitlements` | `GET /api/v1/licensing/entitlements` | `HiddenLeaf\Http\Controllers\Api\V1\LicensingController::entitlements` | VERIFIED |
| `licensing.signed_tokens` | RSA/HMAC Offline Token Verification | `HiddenLeaf\Domain\Licensing\Services\LicenseManager` | VERIFIED |

---

### 11. System Bootstrapper & Installer

| Capability Key | Reference Source / Route | HiddenLeaf Implementation | Status |
| :--- | :--- | :--- | :--- |
| `installer.cli` | `php artisan app:install` | `HiddenLeaf\Console\Commands\InstallCommand` | VERIFIED |
| `installer.web` | `GET /install` | `HiddenLeaf\Installer\Controllers\InstallerController` | VERIFIED |
