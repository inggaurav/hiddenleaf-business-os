<?php

namespace App\Domain\Installation;

use RuntimeException;

class EnvironmentFileWriter
{
    public function write(array $values): void
    {
        $path = base_path('.env');
        $source = is_file($path) ? file_get_contents($path) : file_get_contents(base_path('.env.example'));
        if ($source === false) {
            throw new RuntimeException('Unable to read the environment template.');
        }

        foreach ($values as $key => $value) {
            $escaped = $this->escape((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';
            $replacement = $key.'='.$escaped;
            $source = preg_match($pattern, $source)
                ? preg_replace($pattern, $replacement, $source)
                : rtrim($source).PHP_EOL.$replacement.PHP_EOL;
        }

        $temporary = $path.'.installer-'.bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $source, LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to atomically write the environment configuration.');
        }
    }

    private function escape(string $value): string
    {
        if ($value === '' || preg_match('/[\s#="\']/', $value)) {
            return '"'.addcslashes($value, '\\"').'"';
        }

        return $value;
    }
}
