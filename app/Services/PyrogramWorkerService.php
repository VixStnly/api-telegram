<?php

namespace App\Services;

use App\Models\TelegramClientAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PyrogramWorkerService
{
    public function sendCode(TelegramClientAccount $account): array
    {
        $result = $this->run([
            'send-code-direct',
            (string) $account->phone_number,
            (string) $account->session_name,
        ]);

        return $this->withJsonData($result);
    }

    public function signIn(TelegramClientAccount $account, string $code, ?string $password = null): array
    {
        $command = [
            'sign-in-direct',
            (string) $account->phone_number,
            (string) $account->session_name,
            (string) $account->phone_code_hash,
            $code,
        ];

        if ($password !== null) {
            $command[] = '--password';
            $command[] = $password;
        }

        $result = $this->run($command);

        return $this->withJsonData($result);
    }

    public function debug(): array
    {
        return $this->runRaw([
            '-c',
            'import sys, pymysql, pyrogram; print(sys.executable); print("pymysql=" + pymysql.__version__); print("pyrogram=" + pyrogram.__version__)',
        ]);
    }

    public function listGroups(TelegramClientAccount $account): array
    {
        $result = $this->run([
            'list-groups',
            (string) $account->id,
        ]);

        return $this->withJsonData($result);
    }

    public function startLoginFlow(TelegramClientAccount $account, string $token): array
    {
        $python = base_path('userbot_worker/.venv/bin/python');
        $worker = base_path('userbot_worker/worker.py');
        $log = storage_path('logs/userbot-login-'.$account->id.'.log');

        $command = sprintf(
            'nohup %s %s login-flow %s --token %s --timeout 300 > %s 2>&1 & echo $!',
            escapeshellarg($python),
            escapeshellarg($worker),
            escapeshellarg((string) $account->id),
            escapeshellarg($token),
            escapeshellarg($log)
        );

        $result = Process::path(base_path())
            ->env($this->workerEnvironment())
            ->run(['sh', '-c', $command]);

        return [
            'ok' => $result->successful(),
            'output' => trim($result->output()),
            'error' => trim($result->errorOutput()),
            'exit_code' => $result->exitCode(),
        ];
    }

    public function ensureShareWatcherRunning(): array
    {
        $python = base_path('userbot_worker/.venv/bin/python');
        $worker = base_path('userbot_worker/worker.py');
        $log = storage_path('logs/userbot-share-watcher.log');

        $command = sprintf(
            'nohup %s %s watch-shares --delay %s --refresh %s >> %s 2>&1 & echo $!',
            escapeshellarg($python),
            escapeshellarg($worker),
            escapeshellarg((string) env('SHARE_DELAY', 5)),
            escapeshellarg((string) env('SHARE_REFRESH', 5)),
            escapeshellarg($log)
        );

        $result = Process::path(base_path())
            ->env($this->workerEnvironment())
            ->run(['sh', '-c', $command]);

        return [
            'ok' => $result->successful(),
            'output' => trim($result->output()),
            'error' => trim($result->errorOutput()),
            'exit_code' => $result->exitCode(),
        ];
    }

    protected function run(array $arguments): array
    {
        return $this->runRaw(array_merge(['worker.py'], $arguments));
    }

    protected function runRaw(array $arguments): array
    {
        $candidates = array_values(array_unique(array_filter([
            base_path('userbot_worker/.venv/bin/python'),
            env('PYROGRAM_PYTHON_BIN'),
        ])));

        $attempts = [];

        foreach ($candidates as $python) {
            $command = array_merge([$python], $arguments);

            try {
                $result = Process::path(base_path('userbot_worker'))
                    ->env($this->workerEnvironment())
                    ->timeout(120)
                    ->run($command);
            } catch (\Throwable $e) {
                $attempts[] = [
                    'python' => $python,
                    'ok' => false,
                    'exit_code' => null,
                    'output' => '',
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            $attempt = [
                'python' => $python,
                'ok' => $result->successful(),
                'exit_code' => $result->exitCode(),
                'output' => trim($result->output()),
                'error' => trim($result->errorOutput()),
            ];

            $attempts[] = $attempt;

            if ($result->successful()) {
                return [
                    'ok' => true,
                    'output' => $attempt['output'],
                    'error' => $attempt['error'],
                    'exit_code' => $attempt['exit_code'],
                    'python' => $python,
                    'attempts' => $attempts,
                ];
            }
        }

        Log::warning('Pyrogram worker failed', [
            'arguments' => $arguments,
            'attempts' => $attempts,
        ]);

        $lastAttempt = end($attempts) ?: [];

        return [
            'ok' => false,
            'output' => $lastAttempt['output'] ?? '',
            'error' => $this->formatAttempts($attempts),
            'exit_code' => $lastAttempt['exit_code'] ?? null,
            'python' => $lastAttempt['python'] ?? null,
            'attempts' => $attempts,
        ];
    }

    protected function workerEnvironment(): array
    {
        return array_filter([
            'PYROGRAM_API_ID' => env('PYROGRAM_API_ID'),
            'PYROGRAM_API_HASH' => env('PYROGRAM_API_HASH'),
            'DB_HOST' => env('DB_HOST'),
            'DB_PORT' => env('DB_PORT'),
            'DB_DATABASE' => env('DB_DATABASE'),
            'DB_USERNAME' => env('DB_USERNAME'),
            'DB_PASSWORD' => env('DB_PASSWORD'),
            'MYSQLHOST' => env('MYSQLHOST'),
            'MYSQLPORT' => env('MYSQLPORT'),
            'MYSQLDATABASE' => env('MYSQLDATABASE'),
            'MYSQLUSER' => env('MYSQLUSER'),
            'MYSQLPASSWORD' => env('MYSQLPASSWORD'),
            'MYSQL_URL' => env('MYSQL_URL'),
            'DATABASE_URL' => env('DATABASE_URL'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function withJsonData(array $result): array
    {
        $output = trim((string) ($result['output'] ?? ''));
        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            $lines = array_reverse(array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: []))));

            foreach ($lines as $line) {
                $decoded = json_decode($line, true);

                if (is_array($decoded)) {
                    break;
                }
            }
        }

        if (is_array($decoded)) {
            $result['data'] = $decoded;

            if (($decoded['status'] ?? null) === 'error' && ! empty($decoded['error'])) {
                $result['error'] = $decoded['error'];
            }
        }

        return $result;
    }

    protected function formatAttempts(array $attempts): string
    {
        if ($attempts === []) {
            return 'Worker tidak berjalan dan tidak ada attempt yang tercatat.';
        }

        return collect($attempts)
            ->map(function (array $attempt) {
                $detail = $attempt['error'] ?: $attempt['output'] ?: 'tidak ada output';

                return sprintf(
                    '%s exit=%s: %s',
                    $attempt['python'] ?? 'python',
                    $attempt['exit_code'] ?? '-',
                    $detail
                );
            })
            ->implode("\n");
    }
}
