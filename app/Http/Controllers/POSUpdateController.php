<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class POSUpdateController extends Controller
{
    private function cleanPath(string $path): string
    {
        // remove accidental wrapping quotes
        $path = trim($path);
        $path = trim($path, "\"'");
        return $path;
    }

    private function runProcess(array $cmd, string $cwd): array
    {
        $p = new \Symfony\Component\Process\Process($cmd, $cwd);
        $p->setTimeout(600);
        $p->run();

        return [
            'ok' => $p->isSuccessful(),
            'exit_code' => $p->getExitCode(),
            'output' => $p->getOutput() . $p->getErrorOutput(),
        ];
    }

    public function check()
    {
        $cwd = base_path();

        $git = $this->cleanPath((string) config('posupdater.git'));
        $branch = trim((string) config('posupdater.branch', 'main'));

        // quick validation (helps debugging)
        if (!is_file($git)) {
            return response()->json([
                'ok' => false,
                'error' => 'git_not_found',
                'details' => "Git not found at: {$git}",
            ]);
        }

        $res = \Illuminate\Support\Facades\Cache::remember('pos_update_check', 60, function () use ($cwd, $git, $branch) {

            $local = $this->runProcess([$git, 'rev-parse', 'HEAD'], $cwd);
            if (!$local['ok']) {
                return ['ok' => false, 'error' => 'local_head_failed', 'details' => $local['output']];
            }

            $remote = $this->runProcess([$git, 'ls-remote', 'origin', "refs/heads/{$branch}"], $cwd);
            if (!$remote['ok']) {
                return ['ok' => false, 'error' => 'remote_head_failed', 'details' => $remote['output']];
            }

            $remoteHash = trim(explode("\t", trim($remote['output']))[0] ?? '');
            $localHash  = trim($local['output']);

            return [
                'ok' => true,
                'has_update' => ($remoteHash && $localHash && $remoteHash !== $localHash),
                'local' => $localHash,
                'remote' => $remoteHash,
            ];
        });

        return response()->json($res);
    }

    public function run(\Illuminate\Http\Request $request)
    {
        // abort_unless($request->user()->role === 'admin', 403);

        if (!\Illuminate\Support\Facades\Cache::add('pos_update_lock', true, 600)) {
            return response()->json(['ok' => false, 'message' => 'Update already running'], 409);
        }

        try {
            $repo   = base_path();
            $git    = $this->cleanPath((string) config('posupdater.git'));
            $php    = $this->cleanPath((string) config('posupdater.php'));
            $branch = trim((string) config('posupdater.branch', 'main'));

            $bat = base_path('scripts\\update.bat');

            if (!is_file($bat)) {
                return response()->json(['ok' => false, 'message' => "BAT not found: {$bat}"], 500);
            }

            $result = $this->runProcess(
                ['cmd.exe', '/C', $bat, $repo, $git, $php, $branch],
                $repo
            );

            \Illuminate\Support\Facades\Cache::forget('pos_update_check');

            return response()->json([
                'ok' => $result['ok'],
                'exit_code' => $result['exit_code'],
                'output' => $result['output'],
            ], $result['ok'] ? 200 : 500);

        } finally {
            \Illuminate\Support\Facades\Cache::forget('pos_update_lock');
        }
    }

}
