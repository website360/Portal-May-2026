# Sistema May

ERP interno da Agência May. Laravel 12 + Inertia 2 + React 19 + Tailwind v4, com MySQL.

## Módulos

| Módulo | Rota | Situação |
|---|---|---|
| Dashboard | `/dashboard` | KPIs, faturamento de 12 meses, projetos recentes e atividades |
| Tarefas | `/tarefas` | Dia a dia da agência: cadastro rápido, prioridade, prazo e responsável |
| Clientes | `/clientes` | Cadastro completo, busca, filtro, página do cliente e exclusão |
| Domínios | `/dominios` | Vínculo com cliente, quem renova e aviso de vencimento |
| Financeiro | `/financeiro` | Contas a pagar e a receber, por centro de custo |
| Configurações | `/configuracoes/*` | Conta (perfil, senha, aparência) e Financeiro (centros de custo, categorias) |
| Projetos · Equipe | — | A fazer |

---

## Stack

| Camada | Escolha |
|---|---|
| Backend | Laravel 12 · PHP 8.3 |
| Banco | MySQL 8.4, schema `sistema_may` |
| Ponte servidor↔cliente | Inertia 2 (client-side, sem SSR) |
| Front | React 19 + TypeScript |
| CSS | Tailwind v4 |
| Componentes | shadcn/ui + registry [ReUI](https://reui.io) |
| Gráficos | Recharts |
| Testes | PHPUnit |

Não há SSR: a hospedagem final é cPanel compartilhado, sem Node em produção. O
front é buildado localmente e servido como assets estáticos.

## Rodando localmente

O ambiente é o **Laragon** (`C:\laragon`), que já traz PHP, MySQL e Composer. Eles
não estão no `PATH` do Windows, então adicione-os na sessão do terminal:

```powershell
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin;" + $env:Path
```

Com o MySQL do Laragon ligado:

```powershell
php artisan migrate:fresh --seed   # cria o schema e popula com dados de demonstração
php artisan storage:link           # uma vez só: expõe as fotos dos clientes
php artisan serve --port=8010      # backend
npm run dev                        # frontend (outro terminal)
```

Acesse `http://127.0.0.1:8010`.

> A porta 8000 costuma estar ocupada por outro projeto na máquina. Use 8010 ou outra livre.

### Acesso

| E-mail | Senha |
|---|---|
| `admin@agenciamay.com.br` | `password` |

Não existe cadastro público: é um sistema interno. Usuários são criados por seeder
agora e por um módulo de Usuários no futuro.

## Comandos

```powershell
npm run dev            # Vite em modo desenvolvimento
npm run build          # build de produção (public/build)
npx tsc --noEmit       # checagem de tipos
npm run lint           # ESLint
npm run format         # Prettier

php artisan test       # suíte completa
php artisan migrate:fresh --seed
```

## Design system

O visual segue o `DESIGN-SYSTEM.md` original, portado de Tailwind v3 para v4. A
diferença é apenas sintática: no v4 os tokens guardam a cor completa
(`--primary: hsl(221 83% 53%)`) em vez de canais crus, e os modificadores de
opacidade (`bg-primary/10`) continuam funcionando.

Tudo vive em [resources/css/app.css](resources/css/app.css): paleta clara e escura,
`--radius: 0.65rem`, escala de sombras (`xs`→`lg` + `glow`), keyframes
(`shimmer`, `fade-in`, accordion), utilitários (`.glass`, `.bg-grid`,
`.bg-radial-primary`, `.text-gradient`, `.shimmer`, `.tabular`), scrollbar custom
e a base de 17px.

Tipografia: o sistema inteiro usa **uma única família, Nunito Sans** — a do
`DESIGN-SYSTEM.md` da corretora — carregada em
[resources/views/app.blade.php](resources/views/app.blade.php). Números não trocam
de fonte: a classe `.tabular` só liga `font-variant-numeric: tabular-nums`, para
que valores empilhados em coluna fiquem alinhados sem introduzir um segundo tipo.
(O design system previa JetBrains Mono para números; foi descartada a pedido, em
favor da uniformidade.)

Poppins foi testada e descartada: além de não agradar, é bem mais larga no mesmo
tamanho e obrigava a alargar campos de busca e selects por todo o sistema.

### ReUI

O registry do ReUI está declarado em [components.json](components.json). Para
puxar um componente:

```powershell
npx shadcn@latest add @reui/<nome-do-componente>
```

Apenas os componentes gratuitos (prefixo `c-*`) estão disponíveis — não há licença
configurada. Ao escolher entre as variantes Base UI e Radix, prefira **Radix**: é o
que o restante do projeto já usa, e misturar os dois duplicaria os primitivos.

## Como adicionar um módulo

Todo módulo novo toca sempre os mesmos cinco lugares, e nada além:

```
routes/modules/<modulo>.php          rotas (carregadas automaticamente, dentro do grupo `auth`)
app/Http/Controllers/<Modulo>/       controllers
app/Models/                          models (padrão Laravel)
resources/js/pages/<modulo>/         páginas Inertia
resources/js/config/navigation.ts    uma entrada → o item aparece na sidebar
```

Dois pontos que fazem isso funcionar:

- **Rotas.** [routes/web.php](routes/web.php) percorre `routes/modules/*.php` e carrega
  cada arquivo dentro do grupo `auth`. Criar um módulo não exige editar `web.php`.
- **Navegação.** [resources/js/config/navigation.ts](resources/js/config/navigation.ts)
  é a fonte única da sidebar. Adicionar um módulo é adicionar uma entrada lá — o
  componente de layout nunca precisa ser tocado.

Tons de status por domínio (o que é "ok", "atenção", "erro") ficam em
[resources/js/config/domain.ts](resources/js/config/domain.ts), para que o sistema
inteiro fale a mesma língua visual.

## Ordenação das listagens

Toda listagem ordena pela URL (`?sort=…&direction=…`), então a ordem sobrevive ao
recarregar e pode ser compartilhada por link. Tabelas ordenam clicando no
cabeçalho ([sortable-header.tsx](resources/js/components/ui/sortable-header.tsx));
Tarefas é uma lista sem cabeçalho, então usa um seletor "Ordenar por".

**A chave vem do cliente, então nada fora do mapa declarado no controller pode
virar SQL.** Cada listagem declara uma constante `SORTS` — chave → coluna ou
callable — e [ListSorting](app/Support/ListSorting.php) resolve e aplica. Chave
desconhecida cai no padrão em vez de quebrar; direção inválida vira `asc`.

Duas regras que valem em qualquer coluna:

- A **chave padrão** guarda a ordem natural da tela (mais urgente primeiro), que
  quase sempre é multi-coluna — por isso é declarada como callable.
- **Nulo vai sempre para o fim**, nas duas direções: domínio sem vencimento e
  tarefa sem prazo não devem ocupar o topo só porque estão vazios.

Clicar numa coluna nova ordena crescente; clicar de novo inverte. Não há terceiro
estado — um clique que "desliga" a ordenação deixaria a lista sem ordem definida.

## Troca de situação direto na listagem

Qualquer listagem que mostre uma situação permite trocá-la ali mesmo: clicar no
badge abre um menu curto e a escolha vai direto para o servidor, sem abrir o
cadastro. O componente é um só —
[status-picker.tsx](resources/js/components/ui/status-picker.tsx) — e hoje serve a
três campos:

| Onde | Campo | Rota |
|---|---|---|
| Tarefas | situação | `PATCH /tarefas/{id}/situacao` |
| Clientes | situação | `PATCH /clientes/{id}/situacao` |
| Domínios | quem renova | `PATCH /dominios/{id}/gestao` |

As opções ficam em [config/domain.ts](resources/js/config/domain.ts), então a mesma
lista serve a qualquer tela que mostre aquele campo.

**Cada recurso tem sua própria rota PATCH, validando só o campo que aceita mudar.**
Um endpoint genérico de "atualize esta coluna" seria menos código e abriria a porta
para escrever em qualquer campo — há testes garantindo que essas rotas ignorem
qualquer outro dado enviado junto.

Nas listagens em que a linha inteira é clicável, a célula da situação não propaga
o clique: abrir o menu não pode levar embora.

## Módulo Financeiro

Contas a pagar e a receber na mesma lista, separadas pela seta e pela cor: `−`
vermelho sai, `+` verde entra.

**Centro de custo é o eixo** — Empresa, Escritório, Casa. É obrigatório em todo
lançamento: sem ele, não dá para olhar o financeiro por frente, que é o motivo do
campo existir. Categoria é opcional.

**Indicadores em dois trios espelhados**, um por direção do dinheiro:

| A pagar | A receber | O que é |
|---|---|---|
| A pagar | A receber | Total do período, baixado ou não |
| Paga | Recebida | Já baixado, pelo valor efetivamente pago |
| Atrasada | Em aberto | Venceu sem baixa / ainda não entrou |

Todos seguem **o mesmo recorte de período da listagem**, para card e lista sempre
fecharem entre si, e cada um filtra a lista para exatamente a sua fatia.

Total e "já baixado" podem não reconciliar na conta: o total usa o valor previsto
e o baixado usa o efetivamente pago. Quando há juros ou desconto, a diferença é
real e deve aparecer.

**Filtro por mês** com setas para andar e a opção "Todos os períodos". A lista de
meses vem dos lançamentos que existem — oferecer um intervalo fixo mostraria meses
vazios. (O `input type="month"` nativo aparece como "--------- de ----" e não
comunica nada; por isso o controle é próprio.)

**A situação não é um campo.** Sai do vencimento e da baixa:

| Situação | Quando |
|---|---|
| Paga | Tem data de pagamento — manda sobre o vencimento |
| Vencida | Sem baixa e vencimento no passado |
| Em aberto | Sem baixa e vencimento hoje ou à frente |

Por isso "Vencida" aparece no seletor inline mas **não pode ser escolhida**: é
consequência, não decisão. Dá para dar baixa e estornar.

A baixa grava data **e valor pago**, que pode diferir do previsto — uma conta
quitada com juros ou desconto não pode mentir no relatório.

**Parcelamento:** informar N parcelas gera N lançamentos mensais de uma vez, todos
com o mesmo `series_id`. Excluir uma parcela pergunta se é a série inteira.

Apagar um centro de custo ou uma categoria **não apaga lançamento**: o vínculo vira
nulo e o movimento financeiro fica. Configuração não pode destruir histórico.

### Cuidado com datas em SQL

Colunas `date` são gravadas pelo Laravel como `AAAA-MM-DD 00:00:00`. Comparadas
como texto, `'2026-03-31 00:00:00'` é **maior** que `'2026-03-31'` — então um
`whereBetween` até o último dia do mês perde esse dia.

Todos os filtros por intervalo usam **intervalo semiaberto** (`>= início` e
`< próximo`), que não depende da hora e funciona igual no MySQL e no SQLite dos
testes. Vale para o filtro de mês do financeiro e para a janela de 30 dias dos
domínios; os dois têm teste de borda.

## Campos de escolha: quando usar cada um

| Componente | Quando |
|---|---|
| [`Combobox`](resources/js/components/ui/combobox.tsx) | Listas que podem passar de uma dúzia (clientes, pessoas, UF). **Sempre com busca no topo** — rolar atrás de um cliente entre cinquenta não é aceitável. |
| [`SegmentedControl`](resources/js/components/ui/segmented-control.tsx) | Poucas opções que cabem na tela (situação, prioridade). Tudo visível, um clique. |
| [`StatusPicker`](resources/js/components/ui/status-picker.tsx) | Trocar uma situação direto na listagem, sem abrir o cadastro. |
| `Select` | Só onde as opções são poucas e fixas e nenhum dos acima cabe. |

## Módulo de Tarefas

O dia a dia da agência. A ideia é ser prático, então:

- **Cadastro rápido no topo:** digitar e apertar Enter cria a tarefa. Responsável,
  prioridade e prazo ficam ao lado do título, para não obrigar a reabrir a tarefa
  em seguida. Situação não aparece: toda tarefa nova nasce em "A fazer". Ao criar,
  só o título é limpo — quem cadastra em série costuma manter o resto.
- **Clicar na tarefa abre a edição.** O menu `⋯` continua existindo, mas não é o
  caminho. Checkbox, seletor de situação e o menu não propagam o clique.
- **O formulário trata o título como conteúdo,** não como mais um campo: entra
  grande e sem moldura, com os metadados agrupados abaixo em linhas de ícone +
  rótulo + controle.
- **Três situações:** A fazer → Em andamento → Concluída, trocáveis inline ou pela
  caixa de seleção da linha.
- **Ordem de trabalho:** em andamento antes de a fazer, urgente antes de tranquilo,
  prazo mais próximo primeiro, sem prazo por último e concluídas no fim
  (`Task::scopeInWorkOrder`). A lista já chega na ordem em que se trabalha.
- **Contadores clicáveis:** A fazer, Em andamento, Atrasadas, Minhas em aberto e
  Concluídas hoje. **Todos filtram.** "Atrasadas" e "Concluídas hoje" são recortes
  por prazo com situação embutida, então escolher um deles limpa os outros — a
  combinação devolveria lista vazia sem explicar por quê. "Minhas em aberto" é
  filtro de responsável e combina com qualquer um.

Concluir carimba `completed_at`; reabrir apaga. É daí que sai o "concluídas hoje",
sem precisar de tabela de histórico.

Tarefa não exige projeto nem cliente — os dois vínculos são opcionais e
independentes. A tabela `tasks` nasceu no dashboard exigindo projeto; como o
projeto ainda não foi publicado e sempre rodamos `migrate:fresh`, a migration
original foi ajustada em vez de empilhar um `ALTER TABLE`.

## Módulo de Clientes

O cadastro é longo (identificação, contato, endereço, comercial), então em vez de
um formulário único ele abre num **sheet à direita, dividido em quatro etapas** —
[client-form-sheet.tsx](resources/js/components/clients/client-form-sheet.tsx) para
a orquestração, [client-form-steps.tsx](resources/js/components/clients/client-form-steps.tsx)
para os campos de cada etapa.

Três detalhes do comportamento:

- Só `nome` e `situação` são obrigatórios. O botão *Continuar* trava enquanto
  faltar um obrigatório **da etapa atual** — os outros campos não bloqueiam nada.
- Se a validação do servidor recusar algum campo, o sheet **pula para a etapa que
  contém aquele campo**, em vez de deixar o erro escondido numa aba que o usuário
  não está vendo. Isso sai de graça do array `fields` de cada etapa.
- Etapas já visitadas viram atalho: dá pra clicar no número e voltar direto.

O mesmo sheet serve para editar — a listagem já devolve todos os campos, então
abrir a edição não dispara uma segunda requisição.

CNPJ, CPF, telefone e CEP têm máscara em [lib/masks.ts](resources/js/lib/masks.ts).
O tipo do documento acompanha o tipo do cliente (pessoa jurídica → CNPJ).

### Ver um cliente: duas profundidades

Há dois jeitos de olhar um cliente, e eles servem a coisas diferentes:

- **Página do cliente** (`/clientes/{id}`) — clicar na linha leva para lá. Traz o
  cadastro inteiro, os indicadores (projetos, mensalidade, faturado, a receber),
  a lista de projetos e as faturas recentes. É a visão que precisa de histórico.
- **Visualização rápida** — o botão de olho na linha (ou o menu `⋯`) abre um
  painel lateral com o cadastro, sem sair da lista. **Não faz requisição
  nenhuma**: a listagem já devolve todos os campos do cliente, então abrir é
  instantâneo. Do painel dá para editar ou pular para a página completa.

Os dois compartilham os mesmos blocos de leitura
([client-details.tsx](resources/js/components/clients/client-details.tsx)), então
um campo novo aparece nos dois lugares de uma vez.

A linha inteira é clicável, mas o nome também é um link de verdade — teclado,
leitor de tela e "abrir em nova aba" funcionam. A célula de ações não propaga o
clique, senão abrir o menu levaria embora.

Excluir a partir da página do cliente redireciona para a listagem (voltar daria
404); excluir a partir da listagem usa `back()` e preserva busca e página.

### Foto do cliente

Cada cliente pode ter uma foto (JPG, PNG ou WEBP, até 2 MB), enviada na primeira
etapa do formulário e exibida na listagem. Sem foto, aparecem as iniciais num tom
derivado do nome — o mesmo cliente recebe sempre o mesmo tom.

Os arquivos ficam no disco `public` (`storage/app/public/clients`), servidos pelo
symlink que `php artisan storage:link` cria. Trocar ou remover a foto apaga o
arquivo anterior, e excluir o cliente apaga o dele — o disco não acumula órfãos.

Dois detalhes que valem saber:

- A URL do disco `public` é **relativa** (`/storage`), não derivada de `APP_URL`.
  Com `APP_URL` a imagem quebra sempre que a aplicação sobe num host ou porta
  diferente do que está no `.env` — e continuaria quebrando no cPanel.
- Arquivo não trafega em `PUT`. A edição é enviada como `POST` com
  `_method: 'put'`, e o formulário usa `forceFormData` para o corpo ter um
  formato só, com ou sem foto escolhida.

Os 50 clientes semeados **não têm arquivo de foto** — aparecem com as iniciais.
Suba uma foto por qualquer cliente para ver a imagem na listagem.

## Módulo de Domínios

Cada domínio pertence a um cliente e tem um dono da renovação — e é essa distinção
que dá sentido ao módulo:

- **A agência renova** → entra nos avisos de vencimento.
- **O cliente cuida** → fica registrado para referência, sem gerar aviso.

A situação (`vencido`, `vence em breve`, `em dia`, `sem data`) **não é um campo**:
é calculada a partir do vencimento, com uma janela de 30 dias definida em
`Domain::EXPIRING_WINDOW_DAYS`. Como um campo, ela ficaria desatualizada sozinha.

A mesma regra existe em dois lugares, de propósito: `Domain::status()` calcula em
PHP (para exibir), e o scope `withStatus()` filtra em SQL (para a listagem não ter
que carregar tudo em memória). Um teste compara os dois para eles não divergirem.

**Onde o aviso aparece:** o dashboard mostra um card com os domínios da agência
vencidos ou a vencer, os cinco mais urgentes primeiro. Ele só renderiza quando há
algo — um card fixo dizendo "está tudo bem" viraria ruído que ninguém mais lê.

Os domínios de um cliente também aparecem na página dele, e dá para cadastrar por
lá com o cliente já preenchido e travado.

O campo aceita colar `https://www.exemplo.com.br/` e guarda `www.exemplo.com.br`.

## Dados do dashboard

Os dados são fictícios, mas vivem em **tabelas MySQL reais** (`clients`, `projects`,
`invoices`, `tasks`, `activities`), populadas por factories e seeders. O
[DashboardController](app/Http/Controllers/Dashboard/DashboardController.php) faz
query de verdade contra elas.

A razão: quando o módulo real de Projetos chegar, ele estende essas tabelas por
migration e o dashboard continua funcionando sem alteração. Números fixos no
controller exigiriam reescrever o dashboard inteiro no primeiro módulo real.

Essas tabelas nascem mínimas de propósito e vão crescer por migration.

## Deploy

Ainda não configurado. A intenção é cPanel compartilhado, o que exige:

- apontar o document root para `public/`;
- rodar `composer install --no-dev` (ou subir `vendor/` pronto, se não houver SSH);
- subir `public/build` gerado por `npm run build` na máquina local;
- configurar `.env` de produção e rodar `php artisan migrate --force`.
