<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix real: `accounting_offices.totp_secret` nasceu como `string()` (varchar
 * 255) na migration original; quando o cast `encrypted` foi adicionado no
 * Model (auditoria de segurança 2026-07-22, ver architecture-decisions.md),
 * o payload cifrado (JSON base64 com iv/value/mac) passou a exceder 255
 * caracteres, estourando a coluna (`Data too long for column 'totp_secret'`)
 * em todo INSERT/UPDATE — bug real encontrado ao rodar
 * DemoPlansPresentationSeeder (AccountingAuthService::register()), não uma
 * hipótese. `payment_pix_key` (mesmo padrão de cast `encrypted`, adicionado
 * na mesma auditoria) já nasceu correto como `text()` — só este ponto ficou
 * pra trás. Sem doctrine/dbal instalado no projeto, `Schema::table()->change()`
 * não está disponível — ALTER direto via SQL estático (sem input de
 * usuário), mesmo padrão liberado em database-rules.md para DDL.
 *
 * `ALTER ... MODIFY` é sintaxe MySQL — guardado por driver porque a suíte de
 * testes roda em sqlite (phpunit.xml, DB_CONNECTION=sqlite). SQLite não
 * impõe limite de tamanho em colunas TEXT/VARCHAR (tipagem dinâmica, o
 * "255" declarado é só decorativo) — o bug de truncamento é exclusivo do
 * MySQL de produção, então no sqlite este migration é um no-op seguro.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE accounting_offices MODIFY totp_secret TEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE accounting_offices MODIFY totp_secret VARCHAR(255) NULL');
        }
    }
};
