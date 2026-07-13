<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class GitStatusChecker
{
    public function check(): array
    {
        $branch = config('deploy.branch');
        $base = base_path();

        Process::path($base)->timeout(30)->run(['git', 'fetch', 'origin', $branch]);

        $current = trim(Process::path($base)->run(['git', 'rev-parse', 'HEAD'])->output());
        $remote = trim(Process::path($base)->run(['git', 'rev-parse', "origin/{$branch}"])->output());
        $behindBy = (int) trim(Process::path($base)->run(['git', 'rev-list', '--count', "HEAD..origin/{$branch}"])->output());

        $log = Process::path($base)->run([
            'git', 'log', '--oneline', '--no-decorate', "HEAD..origin/{$branch}",
        ])->output();

        return [
            'branch' => $branch,
            'current_sha' => $current,
            'remote_sha' => $remote,
            'up_to_date' => $current === $remote,
            'behind_by' => $behindBy,
            'commits' => array_values(array_filter(explode(PHP_EOL, trim($log)))),
        ];
    }
}
