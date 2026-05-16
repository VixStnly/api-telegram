<?php

namespace App\Services;

use App\Models\TelegramClientAccount;
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

    protected function run(array $arguments): array
    {
        $python = env('PYROGRAM_PYTHON_BIN', 'python');
        $command = array_merge([$python, 'worker.py'], $arguments);

        $result = Process::path(base_path('userbot_worker'))
            ->timeout(120)
            ->run($command);

        return [
            'ok' => $result->successful(),
            'output' => trim($result->output()),
            'error' => trim($result->errorOutput()),
            'exit_code' => $result->exitCode(),
        ];
    }
}
