# Manual do MCP da gestão do Pequena Via

## O que é o Pequena Via

App para secretarias paroquiais. Cada **cliente** é uma paróquia, uma conta de usuário com `admin = false`. O app tem:

- **Agenda e reservas** de espaços (salão, igreja, salas) e equipamentos.
- **Grupos** (pastorais, movimentos) e **solicitações** que chegam pelo link público da paróquia.
- **Certificados** (batismo, crisma, cursos) gerados a partir de modelos.
- **Assistente de IA**: um chat que consulta a agenda, propõe reservas e cadastros e responde solicitações. As **ações propostas** precisam da confirmação da secretária.

Você vê o que o gestor do projeto vê em `/gestao`. O objetivo é descobrir **o que melhorar no app e no assistente**.

## Como as conversas são classificadas

- Duas rodadas por dia, às 05h e às 17h (São Paulo). Cada rodada pega as conversas paradas há mais de 1 hora.
- O **JEV** responde perguntas fechadas sobre cada conversa: caso de uso, resultado, frustração, se houve pedido fora do app e qual tema.
- O **Haiku** escreve um resumo e dá nome ao pedido não atendido. Ele só roda para conversas não resolvidas, com pedido fora do app ou com confiança baixa.
- Pedidos novos viram **temas** (`temas_de_pedido`). Os temas ativos viram opções para o JEV na rodada seguinte.
- Uma **correção** feita pelo gestor fica num campo à parte e vale mais que o JEV. Todos os números usam o valor efetivo: a correção, se houver; se não, o JEV.

## Glossário dos campos

| Campo                  | Valores e significado                                                                                                                                                                                                                                                                     |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `caso` / `caso_de_uso` | `consultar_agenda`, `reserva` (criar ou mudar), `solicitacoes`, `cadastro` (espaços, equipamentos, grupos), `certificados`, `duvida_app`, `fora_do_escopo`, `outro`                                                                                                                       |
| `resultado`            | `resolvida`; `parcial` (conta como não resolvida nos gráficos); `nao_resolvida`; `aguardando`: a conversa parou esperando a secretaria responder há menos de 3 dias. Ela fica fora da taxa de resolvidas. Se a secretaria não voltar, a conversa é reclassificada e fecha como abandonada |
| `frustracao`           | Nota de 0 a 3: abaixo de 0,75 é calma, até 1,5 é neutra, até 2,25 é frustrada mas educada, acima disso é muito frustrada. **1,5 ou mais conta como "frustrada"**                                                                                                                          |
| `abandonada`           | A conversa parou esperando a secretaria, que não voltou em 3 dias. É uma marca sobre conversas `parcial` ou `nao_resolvida`, não um resultado à parte                                                                                                                                     |
| `a_revisar`            | O JEV ficou com confiança abaixo de 0,6 em algum rótulo. Vale conferir à mão                                                                                                                                                                                                              |
| `pedido_fora`          | A pessoa pediu algo que o app ainda não faz                                                                                                                                                                                                                                               |
| `tema`                 | O pedido não atendido, agrupado. A situação pode ser `novo`, `acompanhando`, `planejado` ou `descartado`                                                                                                                                                                                  |
| `periodo`              | 7, 30 ou 90 dias contando hoje. Os números de "anterior" são da janela de mesmo tamanho logo antes                                                                                                                                                                                        |
| `incluir_gestores`     | Por padrão ficam de fora as contas de gestão, que são as conversas de teste do gestor. Use `true` para incluí-las                                                                                                                                                                         |

## Ferramentas, da mais geral para a mais específica

1. `resumo_do_painel`: comece sempre por aqui. Traz números, tendência, casos de uso, alertas e a última rodada.
2. `listar_temas`: os pedidos não atendidos, com quantas vezes e por quantas paróquias foram citados. **É a fonte principal de ideias de funcionalidade.** Passe `tema_id` para ver as conversas de um tema.
3. `listar_conversas`: conversas filtráveis por `caso`, `resultado`, `frustrada`, `a_revisar` e `cliente_id`, com a matriz de caso por resultado e as listas de ids (`opcoes`).
4. `ver_conversa`: as mensagens de uma conversa. **Cada leitura fica registrada no histórico de acessos**, então abra só as que precisa.
5. `listar_clientes` e `ver_cliente`: a adoção por paróquia (configuração, reservas, uso do assistente, última atividade).
6. `corrigir_classificacao`, `atualizar_tema` e `juntar_temas`: **alteram dados**. Proponha a mudança e só execute depois que o gestor confirmar. `juntar_temas` não tem como desfazer.

## Receitas

**"O que devo melhorar?"**

1. Rode `resumo_do_painel` com `periodo` 30 e anote os casos com mais conversas e a pior taxa de resolução.
2. Rode `listar_temas` e priorize pelo número de **paróquias diferentes** que citaram o tema, depois pelas citações e pela frustração.
3. Rode `listar_conversas` com `resultado` `nao_resolvida` e depois com `frustrada` `true`. Abra de 3 a 5 conversas representativas com `ver_conversa`.
4. Separe o que encontrou em três grupos:
    - **funcionalidade que falta**: veio de um tema;
    - **falha do assistente**: o app já faz, mas o assistente errou ou não soube;
    - **problema de usabilidade**: a pessoa não achou como fazer.
5. Entregue uma lista priorizada com evidência: ids das conversas, número de paróquias e citações.

**"Como está a adoção?"**

1. Rode `listar_clientes` e veja quem tem configuração incompleta ou está sem acesso há muito tempo.
2. Abra `ver_cliente` para os casos que se destacam e confira a linha do tempo e a trava de uso.

**"A classificação está boa?"**

1. Rode `listar_conversas` com `a_revisar` `true` e abra algumas conversas.
2. Se um rótulo estiver errado, proponha `corrigir_classificacao`.
3. Erros que se repetem indicam que as perguntas do JEV precisam de ajuste. Nesse caso, sugira a mudança em `app/Services/Gestao/PerguntasDoJev.php`.

## Cuidados

- **Dados pessoais vêm mascarados** (nomes, telefones, e-mails, CPFs). Não tente reconstruí-los e não os repita fora do contexto da análise.
- Ainda há **poucas paróquias e poucas conversas**. Diga o tamanho da amostra e evite porcentagens sobre poucos casos. "2 de 3" diz mais que "67%".
- Conversas `aguardando` ainda podem ter desfecho. Não as conte como fracasso do assistente; veja se a pergunta que ele deixou era necessária.
- Os números do período usam a **data da classificação**, não a da conversa. Uma conversa de ontem à noite pode entrar na rodada de hoje.
- Se uma ferramenta voltar vazia, confira o `periodo` e o `incluir_gestores` antes de concluir que não há dados.
