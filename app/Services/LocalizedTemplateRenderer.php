<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateLang;
use InvalidArgumentException;

class LocalizedTemplateRenderer
{
    public function email(EmailTemplate $template, string $locale, array $variables): array
    {
        $localized = EmailTemplateLang::where('parent_id', $template->id)->where('lang', $locale)->first();

        return [
            'subject' => $this->render($localized?->subject ?? $template->subject, $variables),
            'content' => $this->render($localized?->content ?? $template->body, $variables),
            'locale' => $localized ? $locale : 'default',
        ];
    }

    public function notification(NotificationTemplate $template, string $locale, array $variables): array
    {
        $localized = NotificationTemplateLang::where('parent_id', $template->id)->where('lang', $locale)->first();

        return [
            'content' => $this->render($localized?->content ?? 'New notification: {message}', $variables),
            'locale' => $localized ? $locale : 'default',
        ];
    }

    private function render(?string $template, array $variables): string
    {
        $template ??= '';
        preg_match_all('/\{([a-zA-Z][a-zA-Z0-9_.-]*)\}/', $template, $matches);
        $missing = array_values(array_diff(array_unique($matches[1]), array_keys($variables)));
        if ($missing !== []) {
            throw new InvalidArgumentException('Missing template variables: '.implode(', ', $missing));
        }

        return strtr($template, collect($variables)->mapWithKeys(
            fn ($value, $key) => ['{'.$key.'}' => e((string) $value)],
        )->all());
    }
}
