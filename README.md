# ForceApproval (GLPI 10)

Plugin para **obrigar** aprovação e avaliação de chamados no GLPI 10.

## Problema
Usuários deixam chamados sem aprovar/avaliar, prejudicando o controle de qualidade.

## Solução
Criar um plugin que **obrigue (literalmente)** o usuário a aprovar e avaliar chamados.

- Sempre que existir pelo menos um chamado:
  - **Solucionado (status 5, aguardando aprovação)**, ou
  - **Fechado com satisfação pendente (status 6)**  
- O sistema exibe um **pop-up bloqueando totalmente o acesso ao GLPI** até que o usuário resolva todas as pendências.

Esse pop-up:
- Mostra uma mensagem informando que existem chamados pendentes de aprovação/avaliação.
- Oferece um link direto para o local onde o usuário pode aprovar/avaliar.
- **Enquanto houver pendências, o GLPI permanece bloqueado.**  
Somente após aprovar e responder as pesquisas de satisfação o acesso normal é liberado.


## Instalação
1. Copiar a pasta `forceapproval` para `GLPI/plugins/`.
2. No GLPI: **Configurar → Plugins → Ativar ForceApproval**.
3. Limpar cache do navegador/GLPI se necessário.

## Configuração
- O plugin já força o fluxo padrão **aprovar → avaliar**.
- Se a aba de satisfação no seu GLPI não for `Ticket$3`, ajustar no `hook.php`.

## Roadmap
- Testar com dados reais do banco.
- Item 3: Integração com Teams para alertas de pendências em evolutivas.

## Licença
GPL-2.0-or-later.
