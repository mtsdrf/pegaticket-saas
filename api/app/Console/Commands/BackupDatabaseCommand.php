<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Backup diário do banco via mysqldump (roadmap 1A — endurecimento de
 * produção). Gera um dump comprimido em gzip dentro de storage/app/backups/
 * (fora da raiz pública), aplicando retenção local: dumps mais antigos que
 * N dias (--days, default 14) são removidos.
 *
 * Registrado em routes/console.php via Schedule::command(...)->daily() —
 * só tem efeito se o cron do servidor rodar `php artisan schedule:run`.
 *
 * Cópia para storage externo (S3/Backblaze/etc.) fica de fora nesta onda
 * (dependeria de serviço de terceiro) — é o próximo passo manual de infra.
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database {--days=14 : Dias de retenção dos backups locais}';

    protected $description = 'Gera um dump comprimido do banco em storage/app/backups/ e aplica retenção.';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("Backup suportado apenas para MySQL — driver atual: '{$config['driver']}'.");

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $filename = sprintf('backup_%s_%s.sql.gz', $config['database'], now()->format('Y-m-d_His'));
        $absolutePath = $disk->path('backups/' . $filename);

        // mysqldump escrito num arquivo temporário e comprimido em gzip. O
        // pipe roda via shell ('sh -c') porque Process::run com um único
        // comando string já usa o shell — mantém stdout do dump indo pro
        // gzip sem materializar o .sql cru em disco.
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --no-tablespaces %s | gzip > %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) ($config['port'] ?? 3306)),
            escapeshellarg((string) $config['username']),
            escapeshellarg((string) $config['password']),
            escapeshellarg((string) $config['database']),
            escapeshellarg($absolutePath)
        );

        $result = Process::timeout(600)->run($command);

        if (! $result->successful()) {
            $this->error('Falha no mysqldump: ' . trim($result->errorOutput()));

            // Remove um arquivo parcial/vazio se o dump abortou no meio.
            if ($disk->exists('backups/' . $filename)) {
                $disk->delete('backups/' . $filename);
            }

            return self::FAILURE;
        }

        $this->info("Backup gerado: backups/{$filename}");

        $removed = $this->pruneOldBackups($disk, (int) $this->option('days'));

        if ($removed > 0) {
            $this->info("Retenção: {$removed} backup(s) antigo(s) removido(s).");
        }

        return self::SUCCESS;
    }

    private function pruneOldBackups($disk, int $days): int
    {
        $threshold = now()->subDays($days)->getTimestamp();
        $removed = 0;

        foreach ($disk->files('backups') as $file) {
            if (! str_ends_with($file, '.sql.gz')) {
                continue;
            }

            if ($disk->lastModified($file) < $threshold) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
