<?php

namespace Tests\Feature\Routes;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class InertiaPageIntegrityTest extends TestCase
{
    public function test_every_literal_inertia_render_has_an_exact_typescript_page(): void
    {
        $missing = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $source = file_get_contents($file);
            preg_match_all("/Inertia::render\\(\\s*['\"]([^'\"]+)['\"]/", $source, $matches);

            foreach ($matches[1] as $component) {
                $page = resource_path('js/Pages/'.$component.'.tsx');
                if (! is_file($page)) {
                    $missing[$component] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
                }
            }
        }

        $messages = array_map(
            fn (string $component, string $source) => "{$component}.tsx (rendered by {$source})",
            array_keys($missing),
            array_values($missing),
        );

        $this->assertEmpty($missing, "Missing exact Inertia page components:\n".implode("\n", $messages));
    }

    private function phpFiles(string $directory): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
