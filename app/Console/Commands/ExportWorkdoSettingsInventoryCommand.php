<?php

namespace App\Console\Commands;

use App\Domain\Settings\SettingsSectionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportWorkdoSettingsInventoryCommand extends Command
{
    protected $signature = 'hiddenleaf:export-workdo-settings';

    protected $description = 'Export the field-level WorkDo settings parity inventory from the runtime registry';

    private const EXPECTED = [
        'ai_agent_api_key', 'ai_agent_model', 'ai_agent_provider', 'allowedFileTypes', 'app_cluster', 'app_id', 'app_key', 'app_secret',
        'awsAccessKeyId', 'awsBucket', 'awsDefaultRegion', 'awsEndpoint', 'awsSecretAccessKey', 'awsUrl', 'bankTransferEnabled',
        'calendarStartDay', 'company_address', 'company_city', 'company_country', 'company_email', 'company_email_from_name', 'company_name',
        'company_state', 'company_telephone', 'company_zipcode', 'contactUsDescription', 'contactUsUrl', 'cookieDescription', 'cookieTitle',
        'currencySymbolPosition', 'currencySymbolSpace', 'customColor', 'dateFormat', 'decimalFormat', 'decimalSeparator', 'defaultCurrency',
        'defaultLanguage', 'driver', 'enableEmailVerification', 'enableLogging', 'enableRegistration', 'encryption', 'favicon', 'floatNumber',
        'footerText', 'fromAddress', 'host', 'instructions', 'landingPageEnabled', 'logo_dark', 'logo_light', 'maxUploadSize', 'metaDescription',
        'metaImage', 'metaKeywords', 'metaTitle', 'password', 'port', 'provider', 'registration_number', 'sidebarStyle', 'sidebarVariant',
        'storageType', 'strictlyCookieDescription', 'strictlyCookieTitle', 'strictlyNecessaryCookies', 'termsConditionsUrl', 'themeColor',
        'themeMode', 'thousandsSeparator', 'timeFormat', 'titleText', 'username', 'wasabiAccessKey', 'wasabiBucket', 'wasabiRegion',
        'wasabiRoot', 'wasabiSecretKey', 'wasabiUrl', 'stripe_enabled', 'stripe_key', 'stripe_secret', 'paypal_client_id', 'paypal_enabled',
        'paypal_mode', 'paypal_secret_key',
    ];

    public function handle(SettingsSectionRegistry $registry): int
    {
        $sections = collect($registry->coreSections());
        $rows = collect(self::EXPECTED)->map(function (string $key) use ($sections): array {
            $section = $sections->first(fn (array $section) => collect($section['fields'])->contains(fn (array $field) => $field['key'] === $key));
            if (! $section) {
                return ['key' => $key, 'status' => 'MISSING'];
            }
            $field = collect($section['fields'])->firstWhere('key', $key);
            $source = str_starts_with($key, 'stripe_')
                ? 'packages/workdo/Stripe/src/Http/Requests/UpdateStripeSettingsRequest.php'
                : (str_starts_with($key, 'paypal_')
                    ? 'packages/workdo/Paypal/src/Http/Requests/UpdatePaypalSettingsRequest.php'
                    : 'app/Http/Controllers/SettingController.php');

            return [
                'key' => $key,
                'role' => $section['scope'] === 'platform' ? ['super_admin'] : ['company_owner', 'company_admin', 'authorized_team_admin'],
                'section' => $section['id'],
                'label' => $field['label'],
                'input_type' => $field['type'],
                'workdo_source' => $source,
                'permission' => $section['permission'],
                'module_dependency' => $section['module'],
                'hiddenleaf_mapping' => "settings-registry:{$section['id']}.{$key}",
                'status' => 'EXACT',
            ];
        });

        $summary = ['expected' => count(self::EXPECTED), 'exact' => $rows->where('status', 'EXACT')->count(), 'functionally_equivalent' => 0, 'intentionally_replaced' => 0, 'partial' => 0, 'missing' => $rows->where('status', 'MISSING')->count()];
        File::put(base_path('docs/parity/workdo-settings-inventory.json'), json_encode([
            'reference_root' => 'C:/Users/manag/Documents/Mr. Fox/workdo-dash-reference',
            'generated_from' => 'App\\Domain\\Settings\\SettingsSectionRegistry',
            'summary' => $summary,
            'settings' => $rows->values(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info("Exported {$summary['exact']} exact mappings; {$summary['missing']} missing.");

        return $summary['missing'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
