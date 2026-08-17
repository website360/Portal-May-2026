# Sistema May — Login e Dashboard

**Data:** 2026-08-11
**Status:** Aprovado
**Escopo desta spec:** fundação do projeto + tela de login + dashboard. Os módulos de negócio vêm em specs próprias.

---

## 1. Objetivo

Criar a base de um ERP interno da Agência May: um projeto Laravel + React onde novos módulos (Clientes, Projetos, Financeiro, Equipe…) possam ser adicionados incrementalmente sem reescrever encanamento.

Esta primeira entrega cobre três coisas:

1. O projeto configurado e rodando localmente.
2. Autenticação por e-mail/senha, com tela de login.
3. Um dashboard com KPIs e gráfico, servindo de referência visual para os módulos seguintes.

## 2. Restrições

| Restrição | Consequência de design |
|---|---|
| Hospedagem final é cPanel compartilhado (sem Node em produção) | Sem Inertia SSR. O front é buildado localmente com Vite e servido como assets estáticos. |
| Ambiente local é Laragon | PHP 8.3.30, MySQL 8.4.3, Composer já disponíveis em `C:\laragon\bin`, mas fora do PATH. |
| ReUI exige React 19 + Tailwind v4 | Descarta Next.js/Tailwind v3. O `DESIGN-SYSTEM.md` (escrito para Tailwind v3) precisa ser portado. |
| Sem licença ReUI | Apenas componentes gratuitos (prefixo `c-*`). Blocks e templates premium estão fora. |
| Deploy adiado | Nesta entrega só é exigido rodar local. Nenhuma decisão pode inviabilizar cPanel depois. |

## 3. Stack

| Camada | Escolha | Versão |
|---|---|---|
| Base do projeto | `laravel/react-starter-kit` (oficial) | ^1.0 |
| Framework | Laravel | 12 |
| Runtime | PHP (Laragon) | 8.3.30 |
| Banco | MySQL (Laragon), schema `sistema_may` | 8.4.3 |
| Ponte servidor↔cliente | Inertia (client-side, sem SSR) | 2.0 |
| Front | React + TypeScript | 19 |
| CSS | Tailwind | v4 |
| Componentes | shadcn/ui (do starter kit) + registry ReUI | — |
| Gráficos | Recharts, via o componente `chart` do shadcn | ^2.15 |
| Testes | PHPUnit (é o que o starter kit traz; Pest não vem instalado) | ^11.5 |

O starter kit oficial já entrega Inertia 2, React 19, TypeScript, Tailwind v4 e shadcn/ui configurados. O ReUI é um registry *em cima* do shadcn, então entra adicionando a namespace em `components.json` — não substitui nada.

Desenvolvimento local: `php artisan serve` + `npm run dev`, a partir de `d:\Clouds\Sistema May`. O vhost do Laragon não é usado.

## 4. Design system

### 4.1 Tradução de v3 para v4

O `DESIGN-SYSTEM.md` fornecido define tokens como canais HSL crus (`--primary: 221 83% 53%`) para serem consumidos via `hsl(var(--primary))`. O padrão shadcn no Tailwind v4 guarda a **cor completa** no token e a expõe por `@theme inline`.

Conversão aplicada a todos os tokens de cor:

```
v3:  --primary: 221 83% 53%;          consumido como hsl(var(--primary))
v4:  --primary: hsl(221 83% 53%);     consumido como var(--primary)
```

Os modificadores de opacidade do Tailwind (`bg-primary/10`, `border-success/20`) continuam funcionando — o v4 os resolve nativamente sobre cores completas.

### 4.2 O que é portado

Tudo do `DESIGN-SYSTEM.md`, sem omissões:

- **Paleta light** (`:root`) e **dark** (`.dark`) — os 30+ tokens, incluindo `success`, `warning` e o grupo `sidebar`, que o starter kit não tem por padrão.
- **Raio:** `--radius: 0.65rem`, com a escala `sm`/`md`/`lg`/`xl` derivada.
- **Sombras:** escala custom `xs`, `sm`, `md`, `lg` e `glow` — difusas, nunca duras.
- **Keyframes e animações:** `shimmer`, `fade-in`, `accordion-down`, `accordion-up`.
- **Utilitários:** `.glass`, `.bg-grid`, `.bg-radial-primary`, `.text-gradient`, `.shimmer`.
- **Scrollbar custom:** 10px, thumb `bg-border` com borda transparente e `background-clip: content-box`.
- **Tipografia:** `html { font-size: 17px }`, `font-feature-settings: "rlig" 1, "calt" 1, "ss01" 1`, `text-rendering: optimizeLegibility`.

### 4.3 Fontes

**Nunito Sans** (`--font-sans`) e **JetBrains Mono** (`--font-mono`), substituindo a Instrument Sans do starter kit. Carregadas por `<link>` do Google Fonts em `resources/views/app.blade.php`, com `preconnect`.

### 4.4 Tema claro/escuro

O starter kit já traz alternância de tema com persistência. Mantida como está — apenas os tokens abaixo dela mudam.

## 5. Autenticação

### 5.1 Escopo

Sistema interno: **não há cadastro público**. Usuários são criados por seeder agora e por um módulo de Usuários no futuro.

**Removido do starter kit:**

- Registro (rota, controller, página, teste)
- Verificação de e-mail
- Recuperação de senha ("esqueci minha senha")

**Mantido:**

- Login e logout
- Configurações → Perfil e Configurações → Senha (o usuário precisa poder trocar a própria senha)

### 5.2 Tela de login

Campos: e-mail, senha, checkbox "lembrar-me". Sem link para registro.

Visual: card centralizado sobre fundo com `.bg-grid` e `.bg-radial-primary` sobrepostos — as superfícies de auth definidas no design system. Logo/nome do sistema acima do card. Erros de validação exibidos abaixo de cada campo. Botão com estado `loading` durante o submit.

### 5.3 Sessão

Sessão nativa do Laravel em cookie httpOnly. Rotas protegidas pelo middleware `auth`. Visitante em rota protegida é redirecionado para `/login`.

### 5.4 Usuário semeado

`DatabaseSeeder` cria um usuário administrador:

- Nome: Agência May
- E-mail: `admin@agenciamay.com.br`
- Senha: `password` (documentada no README, para uso local apenas)

## 6. Dashboard

### 6.1 Layout

Sidebar colapsável à esquerda (componente do starter kit, re-estilizado com os tokens `--sidebar-*`) e header `.glass` fixo no topo, com breadcrumb e menu do usuário.

### 6.2 Conteúdo

1. **Quatro cards de KPI**, em grid responsivo (1 col mobile → 2 tablet → 4 desktop):
   - Clientes ativos
   - Projetos em andamento
   - Faturamento do mês
   - Tarefas pendentes

   Cada card mostra o valor (em JetBrains Mono), um ícone lucide, e a variação percentual contra o mês anterior — badge `success` quando positiva, `destructive` quando negativa.

2. **Gráfico de faturamento** — área com gradiente azul, últimos 12 meses, via Recharts. Tooltip com valor formatado em BRL.

3. **Projetos recentes** — tabela compacta: cliente, projeto, prazo, badge de status usando os tons do design system (`success` = concluído, `warning` = atrasado, `default` = em andamento).

4. **Atividades recentes** — timeline vertical na coluna lateral.

### 6.3 Origem dos dados

Os dados são falsos, mas vivem em **tabelas MySQL reais**, populadas por factories e seeders. O `DashboardController` executa queries de verdade contra elas.

Tabelas criadas nesta entrega, deliberadamente mínimas:

| Tabela | Colunas |
|---|---|
| `clients` | id, name, email, status (`active`/`inactive`), timestamps |
| `projects` | id, client_id, name, status (`in_progress`/`completed`/`late`), due_date, timestamps |
| `invoices` | id, client_id, amount, issued_at, paid_at, timestamps |
| `tasks` | id, project_id, title, status (`pending`/`done`), due_date, timestamps |
| `activities` | id, user_id, description, subject_type, subject_id, timestamps |

**Justificativa:** quando o módulo real de Projetos chegar, ele estende essas tabelas com migrations novas e o dashboard continua funcionando sem alteração. A alternativa — números fixos no controller — exigiria reescrever o dashboard inteiro no primeiro módulo real.

**Custo aceito:** essas tabelas nascem incompletas e vão crescer por migration. Os models já existem, então os módulos futuros os estendem em vez de criá-los.

O seeder gera 12 meses de faturas para o gráfico ter história, e usa datas relativas a `now()` para o dashboard nunca parecer vazio.

## 7. Convenção de módulos

O requisito central é adicionar módulos incrementalmente. A convenção: todo módulo novo toca sempre os mesmos cinco lugares, e nada além.

```
routes/modules/<modulo>.php          rotas do módulo
app/Http/Controllers/<Modulo>/       controllers
app/Models/                          models (padrão Laravel)
resources/js/pages/<modulo>/         páginas Inertia
resources/js/config/navigation.ts    uma entrada → item na sidebar
```

**Carregamento de rotas.** `routes/web.php` percorre `routes/modules/*.php` e carrega cada arquivo dentro do grupo `auth`. Criar um módulo não exige editar `web.php`.

**Navegação.** `resources/js/config/navigation.ts` exporta um array tipado de itens (label, ícone lucide, rota, e opcionalmente `children`). O componente de sidebar renderiza a partir dele e marca o item ativo comparando com a URL atual. Adicionar um módulo à sidebar é adicionar uma entrada — nunca editar o componente de layout.

Esta entrega cria a estrutura com o Dashboard como única entrada, provando a convenção sem inventar módulos que ainda não existem.

## 8. Testes

PHPUnit, no nível de feature. (A spec previa Pest, mas o `laravel/react-starter-kit`
v1.0.1 entrega PHPUnit — traz um `tests/Pest.php` órfão sem o pacote correspondente
no `composer.json`. Seguimos o padrão real da suíte e removemos o arquivo órfão.)

| Teste | Verifica |
|---|---|
| Login com credenciais válidas | Autentica e redireciona para `/dashboard` |
| Login com senha errada | Falha, retorna erro de validação, não autentica |
| Visitante em `/dashboard` | Redirecionado para `/login` |
| Usuário autenticado em `/dashboard` | Página Inertia `dashboard` renderiza com os quatro KPIs |
| Rota `/register` | Retorna 404 (foi removida) |
| Logout | Encerra a sessão e redireciona |

Banco de teste: SQLite em memória (`RefreshDatabase`), para os testes rodarem sem depender do MySQL do Laragon.

## 9. Fora de escopo

- Qualquer módulo de negócio (Clientes, Projetos, Financeiro, Equipe) — cada um terá sua spec.
- Papéis e permissões. Todo usuário autenticado vê tudo nesta entrega.
- Deploy em cPanel, pipeline de build de produção, configuração de e-mail.
- Recuperação de senha e cadastro público.
- Inertia SSR.
- Componentes premium do ReUI.

## 10. Riscos conhecidos

| Risco | Mitigação |
|---|---|
| `composer create-project` exige diretório vazio, mas `docs/` já existe | Gerar em pasta temporária e mover o conteúdo para a raiz do projeto. |
| PHP/Composer/MySQL fora do PATH | Adicionar `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64` e o Composer ao PATH da sessão, ou invocá-los por caminho absoluto. |
| MySQL do Laragon pode não estar rodando | Verificar o serviço antes de `migrate`; documentar no README. |
| Versão do starter kit pode ter mudado desde a publicação (v1.0.1, fev/2025) | Conferir o que veio de fato depois do `create-project` e ajustar, em vez de assumir a estrutura de arquivos. |
| ReUI usa Base UI *ou* Radix; o starter kit usa Radix | Ao instalar componentes ReUI, escolher a variante Radix para não duplicar primitivos. |

## 11. Critérios de aceite

1. `php artisan serve` + `npm run dev` sobem o sistema sem erro.
2. `/` redireciona para `/login` quando não autenticado.
3. A tela de login exibe a identidade visual do `DESIGN-SYSTEM.md` — azul `#2563eb`, Nunito Sans, cantos ~10px, fundo com grid e gradiente radial.
4. Login com `admin@agenciamay.com.br` entra e cai no `/dashboard`.
5. O dashboard mostra os quatro KPIs com números vindos do MySQL, o gráfico de 12 meses, a tabela de projetos e a timeline.
6. Tema claro e escuro funcionam, ambos fiéis aos tokens da spec.
7. `/register` retorna 404.
8. `php artisan test` passa inteiro.
