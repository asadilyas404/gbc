<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class POSUpdateController extends Controller
{
    private function runCmdWindows(string $command, string $cwd): array
    {
        // cmd.exe is required for .bat + Windows quoting
        $p = Process::fromShellCommandline('cmd.exe /V:ON /C ' . $command, $cwd);
        $p->setTimeout(600); // 10 minutes
        $p->run();

        return [
            'ok' => $p->isSuccessful(),
            'exit_code' => $p->getExitCode(),
            'output' => $p->getOutput() . $p->getErrorOutput(),
        ];
    }

    public function check(Request $request)
    {
        $cwd = base_path();

        $git = trim(config('posupdater.git'));
        $branch = trim(config('posupdater.branch'));

        // cache results for 60s (avoid spamming remote)
        $res = Cache::remember('pos_update_check', 60, function () use ($cwd, $git, $branch) {
            $local = $this->runCmdWindows("\"{$git}\" rev-parse HEAD", $cwd);
            if (!$local['ok']) {
                return ['ok' => false, 'error' => 'local_head_failed', 'details' => $local['output']];
            }

            $remote = $this->runCmdWindows("\"{$git}\" ls-remote origin refs/heads/{$branch}", $cwd);
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

    public function run(Request $request)
    {
        // SECURITY: allow only admins (adjust for your app)
        abort_unless($request->user()->role === 'admin', 403);

        // Prevent parallel updates
        if (!Cache::add('pos_update_lock', true, 600)) {
            return response()->json(['ok' => false, 'message' => 'Update already running'], 409);
        }

        try {
            $repo   = base_path();
            $git    = trim(config('posupdater.git'));
            $php    = trim(config('posupdater.php'));
            $branch = trim(config('posupdater.branch'));

            $bat = base_path('scripts\\update.bat');

            // run: scripts\update.bat "repo" "git" "php" "branch"
            $cmd = "\"{$bat}\" \"{$repo}\" \"{$git}\" \"{$php}\" \"{$branch}\"";

            $result = $this->runCmdWindows($cmd, $repo);

            Cache::forget('pos_update_check');

            return response()->json([
                'ok' => $result['ok'],
                'exit_code' => $result['exit_code'],
                'output' => $result['output'],
            ], $result['ok'] ? 200 : 500);

        } finally {
            Cache::forget('pos_update_lock');
        }
    }
}
