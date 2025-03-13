<?php

namespace WizardingCode\WebhookOwlery\Commands;

use Illuminate\Console\Command;
use Random\RandomException;
use WizardingCode\WebhookOwlery\Contracts\WebhookRepositoryContract;

class GenerateWebhookSecretCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:generate-secret
                            {--length=32 : Length of the secret key}
                            {--prefix=whsec_ : Prefix for the secret key}
                            {--algorithm=sha256 : Hashing algorithm (sha256, sha512, md5)}
                            {--endpoint= : Endpoint ID to update with generated secret}
                            {--copy : Copy the generated secret to clipboard}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a secure webhook signing secret';

    /**
     * Execute the console command.
     *
     * @throws RandomException
     * @throws \JsonException
     */
    public function handle(): int
    {
        $length = (int) $this->option('length');
        $prefix = $this->option('prefix');
        $algorithm = $this->option('algorithm');
        $endpointId = $this->option('endpoint');

        if ($length < 16) {
            $this->warn('Security warning: Secret length is less than recommended minimum of 16 characters');
        }

        if (! in_array($algorithm, ['sha256', 'sha512', 'md5'])) {
            $this->error('Invalid algorithm. Supported: sha256, sha512, md5');

            return self::FAILURE;
        }

        // Generate random bytes and convert to hex
        $randomBytes = random_bytes((int) floor($length / 2));
        $hexString = bin2hex($randomBytes);

        // Truncate or pad to exact length
        $hexString = strlen($hexString) > $length
            ? substr($hexString, 0, $length)
            : str_pad($hexString, $length, '0');

        // Add prefix
        $secret = $prefix . $hexString;

        // Display the secret
        $this->info('Generated webhook secret:');
        $this->newLine();
        $this->line($secret);
        $this->newLine();

        $this->info("Hashing Algorithm: $algorithm");

        // Update endpoint if provided
        if ($endpointId) {
            try {
                $repository = app(WebhookRepositoryContract::class);
                $endpoint = $repository->getEndpoint($endpointId);

                if (! $endpoint) {
                    $this->error("Endpoint with ID $endpointId not found");

                    return self::FAILURE;
                }

                $endpoint->secret = $secret;
                $endpoint->signature_algorithm = $algorithm;
                $endpoint->save();

                $this->info("Updated endpoint '{$endpoint->name}' with the new secret.");
            } catch (\Exception $e) {
                $this->error('Error updating endpoint: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        // Show example of how to use the secret
        $this->info('Example Signature Generation:');
        $payload = ['event' => 'example', 'data' => ['id' => 1]];
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac($algorithm, $payloadJson, $secret);

        $this->line('Payload: ' . $payloadJson);
        $this->line('Signature: ' . $signature);
        $this->newLine();

        $this->info('PHP Code Example:');
        $this->line('$signature = hash_hmac("' . $algorithm . '", json_encode($payload), "' . $secret . '");');

        // Check if we should copy to clipboard
        if ($this->option('copy')) {
            if (function_exists('proc_open')) {
                // Try to copy to clipboard using system-specific command
                $this->copyToClipboard($secret);
                $this->info('Secret copied to clipboard!');
            } else {
                $this->warn('Cannot copy to clipboard: proc_open function is disabled.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Copy text to system clipboard.
     *
     * @param string $text Text to copy
     */
    private function copyToClipboard(string $text): bool
    {
        $os = PHP_OS;
        $command = match (true) {
            stripos($os, 'DAR') !== false => 'pbcopy', // macOS
            stripos($os, 'WIN') !== false => 'clip',   // Windows
            stripos($os, 'LINUX') !== false => 'xclip -selection clipboard', // Linux with xclip
            default => false,
        };

        if (! $command) {
            return false;
        }

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $text);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return true;
        }

        return false;
    }
}
