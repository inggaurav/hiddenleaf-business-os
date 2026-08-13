<?php

namespace App\Domain\Updates;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class SafeUpdateArchive
{
    public function extract(string $archive, string $destination): string
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Unable to open the update package.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($index));
            $attributes = $zip->getExternalAttributesIndex($index, $operations, $externalAttributes);
            $mode = $attributes ? (($externalAttributes >> 16) & 0170000) : 0;
            if ($name === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name) || in_array('..', explode('/', $name), true) || $mode === 0120000) {
                $zip->close();
                throw new RuntimeException('The update package contains an unsafe path or symbolic link.');
            }
        }

        File::ensureDirectoryExists($destination);
        if (! $zip->extractTo($destination)) {
            $zip->close();
            throw new RuntimeException('Unable to extract the update package.');
        }
        $zip->close();

        $payload = $destination.DIRECTORY_SEPARATOR.'payload';
        if (! is_dir($payload)) {
            throw new RuntimeException('The update package does not contain a payload directory.');
        }

        return $payload;
    }

    public function files(string $payload): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($payload, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                yield $file->getPathname() => str_replace('\\', '/', substr($file->getPathname(), strlen($payload) + 1));
            }
        }
    }
}
