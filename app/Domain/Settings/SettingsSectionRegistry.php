<?php

namespace App\Domain\Settings;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AddonManager;

class SettingsSectionRegistry
{
    public function __construct(private readonly AddonManager $addons) {}

    /** @return array<int, array<string, mixed>> */
    public function visibleFor(User $user, ?Organization $organization, ?Workspace $workspace): array
    {
        return collect($this->sections())
            ->filter(function (array $section) use ($user, $organization, $workspace): bool {
                if ($section['scope'] === 'platform') {
                    return $user->isSuperAdmin();
                }

                if (! $organization || ! $workspace) {
                    return false;
                }

                if (! $user->isSuperAdmin() && ! $user->canInWorkspace($section['permission'], $workspace)) {
                    return false;
                }

                return ! $section['module'] || $this->addons->canUse($workspace, $section['module'], $user->isSuperAdmin());
            })
            ->sortBy('order')
            ->values()
            ->all();
    }

    public function findVisible(string $id, User $user, ?Organization $organization, ?Workspace $workspace): ?array
    {
        return collect($this->visibleFor($user, $organization, $workspace))->firstWhere('id', $id);
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(): array
    {
        return array_merge($this->coreSections(), $this->addonSections());
    }

    /** @return array<int, array<string, mixed>> */
    public function coreSections(): array
    {
        return [
            $this->section('platform.brand', 'Branding', 'platform', 'settings.brand.manage', 10, [
                $this->field('logo_light', 'Light logo', 'file'), $this->field('logo_dark', 'Dark logo', 'file'),
                $this->field('favicon', 'Favicon', 'file'), $this->field('titleText', 'Application title'),
                $this->field('footerText', 'Footer text'), $this->field('themeColor', 'Theme color', 'color'),
                $this->field('customColor', 'Custom theme color', 'color'),
                $this->field('sidebarVariant', 'Sidebar layout', 'select', ['default' => 'Default', 'compact' => 'Compact', 'floating' => 'Floating']),
                $this->field('sidebarStyle', 'Sidebar style', 'select', ['light' => 'Light', 'dark' => 'Dark', 'transparent' => 'Transparent']),
                $this->field('themeMode', 'Theme mode', 'select', ['light' => 'Light', 'dark' => 'Dark', 'system' => 'System']),
                $this->field('layoutDirection', 'Layout direction', 'select', ['ltr' => 'LTR', 'rtl' => 'RTL']),
            ]),
            $this->section('platform.system', 'System', 'platform', 'settings.localization.manage', 20, [
                $this->field('defaultLanguage', 'Default language'), $this->field('dateFormat', 'Date format'),
                $this->field('timeFormat', 'Time format'), $this->field('timezone', 'Timezone'),
                $this->field('calendarStartDay', 'Calendar start day', 'select', ['monday' => 'Monday', 'sunday' => 'Sunday']),
                $this->field('termsConditionsUrl', 'Terms URL', 'url'),
                $this->field('landingPageEnabled', 'Landing page enabled', 'toggle'),
                $this->field('landingPageDisplay', 'Landing page display', 'toggle'),
                $this->field('enableRegistration', 'Registration enabled', 'toggle'),
                $this->field('enableEmailVerification', 'Email verification enabled', 'toggle'),
            ]),
            $this->section('platform.currency', 'Currency', 'platform', 'settings.localization.manage', 30, [
                $this->field('defaultCurrency', 'Currency'), $this->field('currencySymbol', 'Symbol'),
                $this->field('currencySymbolPosition', 'Symbol position', 'select', ['before' => 'Before', 'after' => 'After']),
                $this->field('thousandsSeparator', 'Thousands separator'), $this->field('decimalSeparator', 'Decimal separator'),
                $this->field('decimalFormat', 'Decimal precision', 'number'), $this->field('floatNumber', 'Show decimals', 'toggle'),
                $this->field('currencySymbolSpace', 'Space before symbol', 'toggle'),
            ]),
            $this->section('platform.cookies', 'Cookie consent', 'platform', 'settings.brand.manage', 40, [
                $this->field('enableCookiePopup', 'Enable cookie banner', 'toggle'), $this->field('enableLogging', 'Log consent', 'toggle'),
                $this->field('strictlyNecessaryCookies', 'Necessary cookies enabled', 'toggle'),
                $this->field('cookieTitle', 'Cookie title'), $this->field('cookieDescription', 'Cookie description', 'textarea'),
                $this->field('strictlyCookieTitle', 'Necessary-cookie title'),
                $this->field('strictlyCookieDescription', 'Necessary-cookie description', 'textarea'),
                $this->field('contactUsUrl', 'Contact URL', 'url'), $this->field('contactUsDescription', 'Cookie data behavior', 'textarea'),
            ]),
            $this->section('platform.realtime', 'Realtime (Reverb/WebSocket)', 'platform', 'settings.integrations.manage', 50, [
                $this->field('realtime_driver', 'Realtime driver', 'select', ['reverb' => 'Laravel Reverb', 'pusher' => 'Pusher']),
                $this->field('app_id', 'Application ID'), $this->field('app_key', 'Application key'),
                $this->field('app_secret', 'Application secret', 'password'), $this->field('app_cluster', 'Cluster / region'),
                $this->field('reverb_host', 'WebSocket host'), $this->field('reverb_port', 'WebSocket port', 'number'),
                $this->field('reverb_scheme', 'WebSocket scheme', 'select', ['https' => 'HTTPS/WSS', 'http' => 'HTTP/WS']),
            ], replacement: 'WorkDo Pusher is intentionally replaceable by Laravel Reverb with equivalent UI.'),
            $this->section('platform.seo', 'SEO', 'platform', 'settings.brand.manage', 60, [
                $this->field('metaTitle', 'Meta title'), $this->field('metaDescription', 'Meta description', 'textarea'),
                $this->field('metaKeywords', 'Keywords'), $this->field('metaImage', 'Meta image', 'file'),
            ]),
            $this->section('platform.storage', 'Storage', 'platform', 'settings.integrations.manage', 80, [
                $this->field('storageType', 'Storage provider', 'select', ['local' => 'Local', 's3' => 'Amazon S3 / MinIO', 'wasabi' => 'Wasabi']),
                $this->field('maxUploadSize', 'Maximum upload size (MB)', 'number'), $this->field('allowedFileTypes', 'Allowed file types'),
                $this->field('awsAccessKeyId', 'AWS access key'), $this->field('awsSecretAccessKey', 'AWS secret key', 'password'),
                $this->field('awsDefaultRegion', 'AWS region'), $this->field('awsBucket', 'AWS bucket'),
                $this->field('awsUrl', 'AWS URL', 'url'), $this->field('awsEndpoint', 'AWS endpoint', 'url'),
                $this->field('wasabiAccessKey', 'Wasabi access key'), $this->field('wasabiSecretKey', 'Wasabi secret key', 'password'),
                $this->field('wasabiRegion', 'Wasabi region'), $this->field('wasabiBucket', 'Wasabi bucket'),
                $this->field('wasabiUrl', 'Wasabi URL', 'url'), $this->field('wasabiRoot', 'Wasabi root'),
            ]),
            $this->section('platform.cache', 'Cache', 'platform', 'settings.integrations.manage', 90, [
                $this->field('cache_driver', 'Cache driver', 'select', ['database' => 'Database', 'redis' => 'Redis', 'file' => 'File']),
                $this->field('cache_prefix', 'Cache key prefix'),
            ]),
            $this->section('platform.mail', 'Mail', 'platform', 'settings.notifications.manage', 500, [
                $this->field('provider', 'Mail provider'),
                $this->field('driver', 'Transport', 'select', ['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'log' => 'Log']),
                $this->field('host', 'Host'), $this->field('port', 'Port', 'number'), $this->field('username', 'Username'),
                $this->field('password', 'Password', 'password'), $this->field('encryption', 'Encryption', 'select', ['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None']),
                $this->field('fromAddress', 'From address', 'email'), $this->field('fromName', 'From name'),
            ]),
            $this->section('platform.notifications', 'Email notifications', 'platform', 'settings.notifications.manage', 510, [
                $this->field('email_notifications_enabled', 'Enable email notifications', 'toggle'),
                $this->field('notification_digest', 'Digest frequency', 'select', ['immediate' => 'Immediate', 'daily' => 'Daily', 'weekly' => 'Weekly']),
            ]),
            $this->section('platform.bank-transfer', 'Bank transfer', 'platform', 'settings.billing.manage', 1000, [
                $this->field('bankTransferEnabled', 'Enabled', 'toggle'), $this->field('instructions', 'Bank instructions', 'textarea'),
            ]),
            $this->section('platform.payments', 'Payment gateways', 'platform', 'settings.billing.manage', 1010, [
                $this->field('stripe_enabled', 'Stripe enabled', 'toggle'), $this->field('stripe_key', 'Stripe publishable key'),
                $this->field('stripe_secret', 'Stripe secret', 'password'), $this->field('stripe_webhook_secret', 'Stripe webhook secret', 'password'),
                $this->field('paypal_enabled', 'PayPal enabled', 'toggle'), $this->field('paypal_client_id', 'PayPal client ID'),
                $this->field('paypal_secret_key', 'PayPal secret', 'password'),
                $this->field('paypal_mode', 'PayPal mode', 'select', ['sandbox' => 'Sandbox', 'live' => 'Live']),
            ]),
            $this->section('platform.ai', 'AI providers', 'platform', 'settings.integrations.manage', 1020, [
                $this->field('ai_agent_provider', 'Provider', 'select', ['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'google' => 'Google Gemini', 'ollama' => 'Ollama']),
                $this->field('ai_agent_model', 'Model'), $this->field('ai_agent_api_key', 'API key', 'password'),
            ]),
            $this->section('platform.api', 'API', 'platform', 'settings.integrations.manage', 1030, [
                $this->field('api_enabled', 'API enabled', 'toggle'), $this->field('api_rate_limit', 'Rate limit per minute', 'number'),
                $this->field('api_default_abilities', 'Default token abilities'),
            ]),
            $this->section('company.identity', 'Company identity', 'organization', 'settings.company.manage', 2000, [
                $this->field('company_name', 'Company name'), $this->field('company_logo', 'Company logo', 'file'),
                $this->field('company_address', 'Address'), $this->field('company_city', 'City'), $this->field('company_state', 'State'),
                $this->field('company_country', 'Country'), $this->field('company_zipcode', 'Postal code'),
                $this->field('company_telephone', 'Phone'), $this->field('company_email', 'Email', 'email'),
                $this->field('company_email_from_name', 'Email-from name'), $this->field('tax_number', 'Tax number'),
                $this->field('tax_type', 'Tax type'), $this->field('registration_number', 'Registration number'),
                $this->field('vat_gst_number_switch', 'Show VAT/GST number', 'toggle'),
            ]),
            $this->section('company.brand', 'Company branding', 'organization', 'settings.brand.manage', 2010, [
                $this->field('company_logo_light', 'Light logo', 'file'), $this->field('company_logo_dark', 'Dark logo', 'file'),
                $this->field('company_favicon', 'Favicon', 'file'), $this->field('company_theme_color', 'Theme color', 'color'),
            ]),
            $this->section('company.localization', 'Company localization', 'workspace', 'settings.localization.manage', 2020, [
                $this->field('site_currency', 'Currency'), $this->field('site_currency_symbol', 'Currency symbol'),
                $this->field('currencySymbolPosition', 'Symbol position', 'select', ['before' => 'Before', 'after' => 'After']),
                $this->field('date_format', 'Date format'), $this->field('time_format', 'Time format'),
                $this->field('timezone', 'Timezone'), $this->field('default_language', 'Language'),
            ]),
            $this->section('company.documents', 'Documents & printing', 'workspace', 'settings.company.manage', 2030, [
                $this->field('invoice_prefix', 'Invoice prefix'), $this->field('proposal_prefix', 'Proposal prefix'),
                $this->field('bill_prefix', 'Bill prefix'), $this->field('pos_prefix', 'POS prefix'),
                $this->field('journal_prefix', 'Journal prefix'), $this->field('customer_prefix', 'Customer prefix'),
                $this->field('vendor_prefix', 'Vendor prefix'), $this->field('invoice_terms', 'Invoice terms', 'textarea'),
                $this->field('invoice_footer_notes', 'Invoice footer', 'textarea'),
                $this->field('print_accent_color', 'Print accent color', 'color'), $this->field('show_qr_code', 'Show QR code', 'toggle'),
            ]),
            $this->section('company.billing', 'Billing & subscription', 'organization', 'settings.billing.manage', 2040, [
                $this->field('billing_email', 'Billing email', 'email'), $this->field('billing_purchase_order', 'Purchase-order reference'),
                $this->field('bank_instructions', 'Tenant bank instructions', 'textarea'),
            ]),
            $this->section('company.notifications', 'Company notifications', 'workspace', 'settings.notifications.manage', 2050, [
                $this->field('email_notifications_enabled', 'Email notifications', 'toggle'),
                $this->field('slack_webhook_url', 'Slack webhook URL', 'url'),
                $this->field('notification_digest', 'Digest frequency', 'select', ['immediate' => 'Immediate', 'daily' => 'Daily', 'weekly' => 'Weekly']),
            ]),
            $this->section('module.crm', 'CRM settings', 'workspace', 'settings.modules.manage', 3000, [
                $this->field('crm.default_pipeline', 'Default pipeline'), $this->field('crm.auto_assign', 'Auto-assign leads', 'toggle'),
            ], 'lead'),
            $this->section('module.hrm', 'HRM settings', 'workspace', 'settings.modules.manage', 3010, [
                $this->field('hrm.work_week', 'Work week'), $this->field('hrm.leave_approval', 'Leave approval required', 'toggle'),
            ], 'hrm'),
            $this->section('module.taskly', 'Taskly settings', 'workspace', 'settings.modules.manage', 3020, [
                $this->field('taskly.default_stage', 'Default task stage'), $this->field('taskly.time_tracking', 'Time tracking enabled', 'toggle'),
            ], 'taskly'),
            $this->section('module.pos', 'POS settings', 'workspace', 'settings.modules.manage', 3030, [
                $this->field('pos.default_payment_method', 'Default payment method'), $this->field('pos.receipt_footer', 'Receipt footer', 'textarea'),
            ], 'pos'),
            $this->section('module.landingpage', 'Landing page & custom pages', 'workspace', 'settings.modules.manage', 3040, [
                $this->field('landing.site_title', 'Public site title'), $this->field('landing.site_description', 'Public site description', 'textarea'),
                $this->field('landing.custom_pages_enabled', 'Custom pages enabled', 'toggle'),
                $this->field('landing.media_library_enabled', 'Media library enabled', 'toggle'),
            ], 'landingpage'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function addonSections(): array
    {
        $sections = [];
        foreach ($this->addons->installed() as $addon) {
            $declared = data_get($addon->manifest, 'hiddenleaf.settings.sections', []);
            if (! is_array($declared)) {
                continue;
            }
            foreach ($declared as $section) {
                if (! is_array($section) || empty($section['id']) || empty($section['label']) || empty($section['permission']) || ! is_array($section['fields'] ?? null)) {
                    continue;
                }
                $section['scope'] = $section['scope'] ?? 'workspace';
                $section['module'] = strtolower($addon->alias);
                $section['order'] = (int) ($section['order'] ?? 5000);
                $section['source'] = "addon:{$addon->alias}";
                $sections[] = $section;
            }
        }

        return $sections;
    }

    private function section(string $id, string $label, string $scope, string $permission, int $order, array $fields, ?string $module = null, ?string $replacement = null): array
    {
        return compact('id', 'label', 'scope', 'permission', 'order', 'fields', 'module', 'replacement') + ['source' => 'core'];
    }

    private function field(string $key, string $label, string $type = 'text', array $options = []): array
    {
        return compact('key', 'label', 'type', 'options');
    }
}
