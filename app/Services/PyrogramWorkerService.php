<?php

namespace App\Services;

use App\Models\TelegramClientAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PyrogramWorkerService
{
    public function sendCode(TelegramClientAccount $account): array
    {
        return $this->run(['send-code', (string) $account->id]);
    }

    public function signIn(TelegramClientAccount $account, string $code, ?string $password = null): array
    {
        $command = ['sign-in', (string) $account->id, $code];

        if ($password !== null) {
            $command[] = '--password';
            $command[] = $password;
        }

        return $this->run($command);
    }

    public function debug(): array
    {
        return $this->runRaw([
            '-c',
            'import sys, pymysql, pyrogram; print(sys.executable); print("pymysql=" + pymysql.__version__); print("pyrogram=" + pyrogram.__version__)',
        ]);
    }

    protected function run(array $arguments): array
    {
        return $this->runRaw(array_merge(['worker.py'], $arguments));
    }

    protected function runRaw(array $arguments): array
    {
        $candidates = array_values(array_unique(array_filter([
            base_path('userbot_worker/.venv/bin/python'),
            base_path('userbot_worker/.venv/Scripts/python.exe'),
            '/usr/bin/python3',
            env('PYROGRAM_PYTHON_BIN'),
            'python3',
            'python',
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
