<?php

namespace App\Console\Commands;

use App\Support\DisposableEmailDomains;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Refresh the disposable email blocklist from its maintained source.
 *
 * Downloads the community list (CC0) and replaces
 * resources/data/disposable-email-domains.txt, keeping the old file when the
 * download fails or comes back implausibly short. Commit the result like any
 * other change.
 */
class UpdateDisposableEmailDomains extends Command
{
    /**
     * The upstream list: one domain per line.
     */
    public const SOURCE = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf';

    /**
     * Fewer than this and the download is treated as broken, not as the list.
     */
    protected const MINIMUM_DOMAINS = 1000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:update-disposable-domains';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download the latest list of disposable email domains';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $response = Http::timeout(30)->get(self::SOURCE);
        } catch (Throwable $exception) {
            $this->error('Could not download the list: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Could not download the list: HTTP '.$response->status());

            return self::FAILURE;
        }

        $domains = collect(preg_split('/\R/', $response->body()) ?: [])
            ->map(fn (string $line): string => mb_strtolower(trim($line)))
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, '#'))
            ->unique()
            ->sort()
            ->values();

        if ($domains->count() < self::MINIMUM_DOMAINS) {
            $this->error('The download held only '.$domains->count().' domains; keeping the current list.');

            return self::FAILURE;
        }

        $path = resource_path(DisposableEmailDomains::LIST_PATH);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $domains->implode("\n")."\n");

        $this->info('Saved '.$domains->count().' disposable domains to resources/'.DisposableEmailDomains::LIST_PATH.'.');

        return self::SUCCESS;
    }
}
