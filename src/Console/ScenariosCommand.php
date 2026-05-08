<?php

namespace Peanutgraphic\BloxyTesterBridge\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class ScenariosCommand extends Command
{
    protected $signature = 'tester:scenarios {--json : Emit JSON to stdout (default human-readable)}';
    protected $description = 'List TESTER scenarios discovered in <app>/tester/scenarios/*.ts';

    private const CACHE_KEY = 'tester:scenarios:manifest';

    public function handle(): int
    {
        $path = (string) config('tester-bridge.scenarios_path', '');
        $cacheSeconds = (int) config('tester-bridge.scenarios_cache_seconds', 60);

        if ($path === '') {
            // Bridge installed but no scenarios path configured. Empty list is OK.
            $this->emit([]);
            return self::SUCCESS;
        }

        if (! is_dir($path)) {
            $this->error("Scenarios path does not exist: {$path}");
            return self::FAILURE;
        }

        $manifest = Cache::remember(self::CACHE_KEY, $cacheSeconds, function () use ($path) {
            return $this->discover($path);
        });

        $this->emit($manifest);
        return self::SUCCESS;
    }

    private function discover(string $path): array
    {
        // Shell to node to read TS module metadata. Each scenario file is expected
        // to export a `meta` constant with { slug, title, roles, mode? }.
        // Phase 2: this just tries a glob + simple regex fallback if node fails.
        // Phase 2c can deepen this to a real TS parser.

        $files = glob(rtrim($path, '/') . '/*.ts') ?: [];
        $manifest = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (! is_string($content)) continue;

            // Cheap regex parse — looks for `export const ... Meta = { slug: '...', title: '...', roles: [...], mode: '...' }`
            if (! preg_match('/export\s+const\s+\w+Meta\s*=\s*(\{[\s\S]*?\});/', $content, $m)) {
                continue;
            }

            $literal = $m[1];
            $slug = $this->extractStringLit($literal, 'slug');
            $title = $this->extractStringLit($literal, 'title');
            $mode = $this->extractStringLit($literal, 'mode') ?? 'cooperative';
            $roles = $this->extractStringArray($literal, 'roles');

            if ($slug === null) continue;

            $manifest[] = [
                'slug' => $slug,
                'title' => $title ?? $slug,
                'roles' => $roles,
                'mode' => $mode,
                'source_sha' => substr(hash('sha256', $content), 0, 12),
            ];
        }

        return $manifest;
    }

    private function extractStringLit(string $haystack, string $key): ?string
    {
        if (preg_match("/{$key}:\\s*['\"]([^'\"]+)['\"]/", $haystack, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractStringArray(string $haystack, string $key): array
    {
        if (! preg_match("/{$key}:\\s*\\[([^\\]]+)\\]/", $haystack, $m)) {
            return [];
        }
        preg_match_all("/['\"]([^'\"]+)['\"]/", $m[1], $items);
        return $items[1] ?? [];
    }

    private function emit(array $manifest): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($manifest, JSON_UNESCAPED_SLASHES));
            return;
        }

        if (empty($manifest)) {
            $this->info('No scenarios found.');
            return;
        }

        $this->table(['Slug', 'Title', 'Roles', 'Mode'], array_map(
            fn ($s) => [$s['slug'], $s['title'], implode(',', $s['roles']), $s['mode']],
            $manifest,
        ));
    }
}
