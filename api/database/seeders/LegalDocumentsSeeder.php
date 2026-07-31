<?php

namespace Database\Seeders;

use App\Models\Legal\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'type' => 'terms',
                'version' => '2026-07-26',
                'content' => implode("\n\n", [
                    'Termos de Uso da Plataforma PegaTicket',
                    'Vigência da versão: 26/07/2026.',
                    '1. Objeto',
                    'A PegaTicket disponibiliza uma plataforma de gestão empresarial com recursos como cadastro de clientes, produtos, pedidos, usuários, permissões, relatórios, integrações e demais módulos contratados pela empresa usuária.',
                    '2. Partes',
                    'Neste documento, "PegaTicket" é a fornecedora da plataforma. "Empresa contratante" é a pessoa jurídica ou o empresário responsável pela assinatura e pelo uso do ambiente cadastrado. "Usuários da empresa" são as pessoas autorizadas pela empresa contratante a operar o sistema.',
                    '3. Criação da empresa e conta proprietária',
                    'Ao concluir o cadastro público, a empresa confirma que possui poderes para contratar a plataforma e indicar o usuário proprietário inicial. Esse usuário poderá administrar acessos, configurações, permissões internas e dados operacionais da própria empresa.',
                    '4. Acesso, credenciais e responsabilidade',
                    'Cada usuário deve manter suas credenciais sob sigilo. A empresa contratante é responsável pela gestão dos acessos internos, pela revisão periódica das permissões concedidas e pelo bloqueio de usuários que não devam mais operar o ambiente.',
                    '5. Planos, trial e limitações comerciais',
                    'Os recursos disponíveis variam conforme o plano contratado, período de teste, upgrades, downgrades, suspensão ou cancelamento. Durante o período de teste, a empresa utiliza a configuração comercial definida pela PegaTicket para trial. Ao fim desse período, a continuidade do acesso depende da regularização da assinatura conforme as regras vigentes no sistema.',
                    '6. Uso adequado da plataforma',
                    'A empresa contratante se compromete a utilizar a plataforma apenas para fins lícitos, sem tentativa de violar segurança, burlar limites comerciais, acessar dados de terceiros sem autorização, introduzir códigos maliciosos ou utilizar o ambiente de forma que degrade a operação de outros clientes.',
                    '7. Dados inseridos pela empresa',
                    'A empresa contratante é responsável pela veracidade, qualidade, base legal e legitimidade dos dados cadastrados no sistema, incluindo dados de clientes, usuários, produtos, pedidos, endereços, documentos e integrações externas.',
                    '8. Integrações externas',
                    'Quando a empresa configurar integrações de pagamento, marketplace, logística, contabilidade, fiscal ou qualquer serviço de terceiro, ela será responsável por fornecer credenciais corretas, manter autorizações vigentes e observar os termos do fornecedor externo correspondente.',
                    '9. Disponibilidade e manutenção',
                    'A PegaTicket buscará manter a plataforma disponível de forma contínua, observado que poderão ocorrer indisponibilidades programadas, manutenção corretiva, falhas de terceiros, eventos de infraestrutura, limitações de conectividade e situações fora do controle razoável da fornecedora.',
                    '10. Suporte e evolução do produto',
                    'O escopo de suporte, canais de atendimento, SLA, onboarding assistido, customizações e novas funcionalidades dependem do plano contratado, da proposta comercial aplicável e da política operacional vigente da PegaTicket.',
                    '11. Propriedade intelectual',
                    'O software, sua interface, marca, documentação, código, fluxos, materiais e demais ativos intelectuais permanecem de titularidade da PegaTicket ou de seus licenciadores. A contratação não transfere propriedade do software, apenas direito de uso nos limites contratados.',
                    '12. Segurança da informação',
                    'A PegaTicket adota controles técnicos e organizacionais compatíveis com a natureza da plataforma, mas nenhum ambiente conectado à internet é absolutamente invulnerável. A empresa contratante também deve cumprir sua parte com senhas fortes, revisão de acessos, equipamentos protegidos e uso responsável.',
                    '13. Privacidade e proteção de dados',
                    'O tratamento de dados pessoais realizado no contexto da plataforma segue a Política de Privacidade vigente. A empresa contratante deve ler e aceitar esse documento no momento do cadastro e mantê-lo como referência para sua operação.',
                    '14. Suspensão e cancelamento',
                    'A PegaTicket poderá suspender total ou parcialmente o acesso da empresa em situações como inadimplência, risco de fraude, uso indevido, violação destes termos, determinação legal ou necessidade de contenção de incidente. Cancelamentos, arrependimento, renovação e recontratação seguem o fluxo disponível no sistema e as condições comerciais aplicáveis.',
                    '15. Limitação de responsabilidade',
                    'A PegaTicket não se responsabiliza por decisões de negócio tomadas pela empresa usuária, por cadastros incorretos realizados pelos próprios usuários, por falhas de serviços de terceiros integrados ou por perdas decorrentes de uso em desacordo com a documentação, políticas e boas práticas informadas pela plataforma.',
                    '16. Atualizações destes termos',
                    'Estes termos podem ser atualizados para refletir evolução do produto, requisitos operacionais, exigências legais ou ajustes comerciais. A versão ativa publicada na plataforma será a referência para novos aceites e para consulta pública.',
                    '17. Contato',
                    'Demandas contratuais, dúvidas operacionais e solicitações relacionadas a estes termos devem ser encaminhadas pelos canais oficiais de atendimento informados pela PegaTicket.',
                ]),
            ],
            [
                'type' => 'privacy',
                'version' => '2026-07-26',
                'content' => implode("\n\n", [
                    'Política de Privacidade da Plataforma PegaTicket',
                    'Vigência da versão: 26/07/2026.',
                    '1. Objetivo desta política',
                    'Esta política descreve, em linguagem operacional, como a PegaTicket trata dados pessoais no contexto da plataforma, inclusive no cadastro da empresa, autenticação, administração de usuários, operação dos módulos contratados, suporte e integrações habilitadas.',
                    '2. Papéis no tratamento de dados',
                    'A PegaTicket atua, em regra, como controladora dos dados do relacionamento comercial, suporte, cobrança, segurança da conta e administração da própria plataforma. Em relação aos dados pessoais inseridos pela empresa contratante no uso do sistema, a PegaTicket atua predominantemente como operadora, tratando os dados conforme as instruções legítimas da empresa usuária e as necessidades técnicas do serviço.',
                    '3. Quais dados podem ser tratados',
                    'Podem ser tratados dados cadastrais e de contato de usuários, administradores, clientes, destinatários, responsáveis financeiros, além de dados operacionais lançados no sistema, registros de acesso, identificadores técnicos, logs de auditoria, permissões, histórico de ações, informações de cobrança e dados necessários para integrações externas habilitadas pela empresa.',
                    '4. Finalidades do tratamento',
                    'Os dados pessoais podem ser utilizados para criar contas, autenticar usuários, manter a segurança do ambiente, prover funcionalidades contratadas, registrar trilhas de auditoria, processar pagamentos, emitir comunicações operacionais, viabilizar integrações configuradas pela empresa, atender solicitações de suporte, cumprir obrigações legais e prevenir fraude ou uso indevido.',
                    '5. Bases legais aplicáveis',
                    'A PegaTicket poderá tratar dados pessoais com fundamento, conforme o caso concreto, na execução de contrato, procedimentos preliminares relacionados à contratação, cumprimento de obrigação legal ou regulatória, exercício regular de direitos, legítimo interesse e, quando necessário, consentimento.',
                    '6. Compartilhamento de dados',
                    'Os dados podem ser compartilhados com provedores de infraestrutura, autenticação, comunicação, pagamento, hospedagem, armazenamento, monitoramento, marketplace, contabilidade, fiscal e outros terceiros indispensáveis ao funcionamento das integrações habilitadas pela empresa. Também poderá haver compartilhamento quando exigido por lei, ordem judicial, autoridade competente ou para proteção da própria plataforma.',
                    '7. Armazenamento e retenção',
                    'Os dados são mantidos pelo tempo necessário para cumprir as finalidades desta política, garantir a continuidade do serviço, preservar evidências operacionais, atender obrigações legais, regulatórias, fiscais, contábeis e exercer direitos em processos administrativos, arbitrais ou judiciais.',
                    '8. Segurança da informação',
                    'A PegaTicket adota medidas razoáveis de segurança técnicas e organizacionais para proteger os dados contra acessos não autorizados, perda, destruição, alteração ou divulgação indevida. A empresa contratante também deve adotar boas práticas internas, incluindo gestão de acessos, revisão de permissões, proteção de dispositivos e orientação dos próprios usuários.',
                    '9. Direitos dos titulares',
                    'Nos termos da LGPD e observadas as hipóteses legais aplicáveis, titulares podem solicitar confirmação de tratamento, acesso, correção, anonimização, bloqueio, eliminação, portabilidade, informação sobre compartilhamento e revisão de decisões quando cabível. Solicitações ligadas aos dados operacionais inseridos pela empresa usuária devem, em regra, ser iniciadas junto à própria empresa contratante, que define a base legal e a finalidade principal desses registros.',
                    '10. Atendimento de solicitações LGPD',
                    'A PegaTicket registra internamente solicitações relacionadas à privacidade e coopera com a empresa contratante quando houver pedido que envolva dados tratados dentro do ambiente contratado. Dependendo do tipo de solicitação, pode ser necessário validar identidade, legitimidade do pedido, escopo dos dados e obrigações de retenção aplicáveis.',
                    '11. Logs, auditoria e prevenção a abuso',
                    'Por motivos de segurança, rastreabilidade e operação, a plataforma mantém logs e trilhas de auditoria sobre autenticação, alterações relevantes, permissões, eventos de cobrança e outras ações críticas. Esses registros podem conter dados pessoais e são parte essencial dos controles do sistema.',
                    '12. Transferências e serviços de terceiros',
                    'Alguns fornecedores utilizados pela plataforma podem processar dados em ambientes externos ou distribuídos. Sempre que aplicável, a PegaTicket buscará adotar salvaguardas contratuais e operacionais compatíveis com o nível de risco e com a natureza do serviço contratado.',
                    '13. Atualizações desta política',
                    'Esta política pode ser revisada para refletir mudanças legais, regulatórias, contratuais, tecnológicas ou operacionais. A versão ativa publicada na plataforma será a referência para consulta e novos aceites.',
                    '14. Contato para privacidade',
                    'Dúvidas, comunicações e solicitações relacionadas à privacidade e proteção de dados devem ser encaminhadas pelos canais oficiais da PegaTicket informados no sistema ou na relação comercial vigente.',
                ]),
            ],
        ];

        foreach ($documents as $data) {
            LegalDocument::withTrashed()->updateOrCreate(
                ['type' => $data['type'], 'version' => $data['version']],
                [
                    'content' => $data['content'],
                    'published_at' => now(),
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
