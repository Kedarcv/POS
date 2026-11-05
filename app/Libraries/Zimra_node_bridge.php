<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * Runs the zimra-fdms Node adapter without exposing secrets in argv.
 */
class Zimra_node_bridge
{
    public function isAvailable(): bool
    {
        return is_file($this->scriptPath()) && $this->nodePath() !== null;
    }

    public function profilePath(): string
    {
        $dataDir = getenv('OSPOS_DATA_DIR') ?: rtrim(WRITEPATH . '..', DIRECTORY_SEPARATOR);

        return rtrim($dataDir, '/\\') . DIRECTORY_SEPARATOR . 'zimra';
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function call(array $request): array
    {
        $node = $this->nodePath();
        if ($node === null) {
            throw new RuntimeException('Node.js is required for zimra-fdms fiscalisation.');
        }

        $command = [$node, $this->scriptPath()];
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            rtrim(ROOTPATH, DIRECTORY_SEPARATOR),
            array_merge($_ENV, [
                'NODE_PATH' => rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'node_modules',
            ]),
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the ZIMRA Node bridge.');
        }

        fwrite($pipes[0], json_encode($request, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 && trim($output) === '') {
            throw new RuntimeException(trim($errorOutput) ?: 'The ZIMRA Node bridge failed.');
        }

        $response = json_decode($output, true);
        if (!is_array($response)) {
            throw new RuntimeException('The ZIMRA Node bridge returned invalid JSON.');
        }
        if (($response['success'] ?? false) !== true) {
            throw new RuntimeException((string) ($response['error'] ?? 'ZIMRA bridge request failed.'));
        }

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    private function scriptPath(): string
    {
        return rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'zimra-bridge.mjs';
    }

    private function nodePath(): ?string
    {
        $configured = getenv('ZIMRA_NODE_BIN');
        if ($configured !== false && is_executable($configured)) {
            return $configured;
        }

        $path = trim((string) shell_exec('command -v node 2>/dev/null'));

        return $path !== '' ? $path : null;
    }
}
