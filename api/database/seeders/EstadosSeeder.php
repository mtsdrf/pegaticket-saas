<?php

namespace Database\Seeders;

use App\Models\Location\Estado;
use Illuminate\Database\Seeder;

/**
 * `estados` é tabela global (sem tenant_id) — os 27 estados/UF do Brasil
 * nunca mudam, então tem sentido vir pronta pra todo tenant, ao contrário de
 * `cidades`/`bairros` (datasets grandes, sem fonte confiável pra hardcodar
 * aqui — nascem via CRUD incremental de cada tenant, mas ficam disponíveis
 * globalmente depois de criadas uma vez).
 */
class EstadosSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['name' => 'Acre', 'uf' => 'AC'],
            ['name' => 'Alagoas', 'uf' => 'AL'],
            ['name' => 'Amapá', 'uf' => 'AP'],
            ['name' => 'Amazonas', 'uf' => 'AM'],
            ['name' => 'Bahia', 'uf' => 'BA'],
            ['name' => 'Ceará', 'uf' => 'CE'],
            ['name' => 'Distrito Federal', 'uf' => 'DF'],
            ['name' => 'Espírito Santo', 'uf' => 'ES'],
            ['name' => 'Goiás', 'uf' => 'GO'],
            ['name' => 'Maranhão', 'uf' => 'MA'],
            ['name' => 'Mato Grosso', 'uf' => 'MT'],
            ['name' => 'Mato Grosso do Sul', 'uf' => 'MS'],
            ['name' => 'Minas Gerais', 'uf' => 'MG'],
            ['name' => 'Pará', 'uf' => 'PA'],
            ['name' => 'Paraíba', 'uf' => 'PB'],
            ['name' => 'Paraná', 'uf' => 'PR'],
            ['name' => 'Pernambuco', 'uf' => 'PE'],
            ['name' => 'Piauí', 'uf' => 'PI'],
            ['name' => 'Rio de Janeiro', 'uf' => 'RJ'],
            ['name' => 'Rio Grande do Norte', 'uf' => 'RN'],
            ['name' => 'Rio Grande do Sul', 'uf' => 'RS'],
            ['name' => 'Rondônia', 'uf' => 'RO'],
            ['name' => 'Roraima', 'uf' => 'RR'],
            ['name' => 'Santa Catarina', 'uf' => 'SC'],
            ['name' => 'São Paulo', 'uf' => 'SP'],
            ['name' => 'Sergipe', 'uf' => 'SE'],
            ['name' => 'Tocantins', 'uf' => 'TO'],
        ];

        foreach ($estados as $data) {
            $data['is_active'] = true;

            $record = Estado::withTrashed()->where('uf', $data['uf'])->first();

            if (!$record) {
                Estado::create($data);
                continue;
            }

            if ($record->trashed()) {
                $record->restore();
            }

            $record->fill($data);
            $record->save();
        }
    }
}
