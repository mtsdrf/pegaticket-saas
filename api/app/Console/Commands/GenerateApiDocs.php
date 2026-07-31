<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use L5Swagger\Generator;

class GenerateApiDocs extends Command
{
    protected $signature = 'docs:generate 
                            {lang? : Idioma (pt_BR, en, ou all)} 
                            {--clear : Limpar documentação existente}';

    protected $description = 'Gerar documentação Swagger da API em múltiplos idiomas';

    public function handle()
    {
        $lang = $this->argument('lang') ?? 'all';
        $languages = $lang === 'all' ? ['pt_BR', 'en'] : [$lang];

        if (!in_array($lang, ['pt_BR', 'en', 'all'])) {
            $this->error("❌ Idioma inválido. Use: pt_BR, en, ou all");
            return 1;
        }

        if ($this->option('clear')) {
            $this->info('🗑️  Limpando documentação antiga...');
            foreach ($languages as $language) {
                @unlink(storage_path("api-docs/api-docs-{$language}.json"));
                @unlink(storage_path("api-docs/api-docs-{$language}.yaml"));
            }
        }

        foreach ($languages as $language) {
            $langName = $language === 'pt_BR' ? 'Português (Brasil)' : 'English';
            $this->info("📝 Gerando documentação em {$langName}...");

            try {
                // Usar o Generator diretamente com o nome da documentação
                app(Generator::class)->generateDocs($language);
                $this->line("   ✓ {$langName} gerado com sucesso");
            } catch (\Exception $e) {
                $this->error("   ✗ Erro: {$e->getMessage()}");

                // Fallback: tentar com artisan command
                try {
                    \Artisan::call('l5-swagger:generate', [
                        'documentation' => $language
                    ]);
                    $this->line("   ✓ {$langName} gerado (fallback)");
                } catch (\Exception $e2) {
                    $this->error("   ✗ Falha total para {$langName}");
                    continue;
                }
            }
        }

        $this->newLine();
        $this->info('✅ Documentação gerada com sucesso!');
        $this->newLine();

        $baseUrl = config('app.url');

        $this->line('🌐 <fg=cyan>Acesse a documentação:</>');
        $this->line("   <fg=green>Português:</> {$baseUrl}/api/documentation");
        $this->line("   <fg=green>English:</> {$baseUrl}/api/documentation/en");

        $this->newLine();
        $this->line('<fg=yellow>💡 Dica:</> Use --clear para limpar documentação antiga antes de gerar');

        return 0;
    }
}