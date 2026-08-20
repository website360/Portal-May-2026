# Design System — Sistema May

Guia de referência do design system usado no Sistema May, escrito para ser
**portado para outros projetos**. Os valores são concretos (pode copiar direto),
mas os princípios e a nomenclatura são agnósticos de framework — servem tanto
para outro projeto Tailwind quanto para um design no Figma ou um CSS puro.

> Stack de origem: **Tailwind CSS v4** (tokens via `@theme`), **shadcn/ui**,
> primitivas **Radix**, ícones **Lucide**. Nada disso é obrigatório para reusar o
> sistema — o que importa são os tokens e as regras abaixo.

---

## 1. Princípios

1. **Nunca use cor crua.** Toda cor vem de um *token* semântico (`primary`,
   `muted`, `border`…). Um componente nunca sabe se é azul — ele pede `primary`.
   Isso é o que faz o tema (claro/escuro) e as cores de destaque trocarem sem
   tocar em componente.
2. **Cada cor de fundo tem seu par de texto.** `background`/`foreground`,
   `card`/`card-foreground`, `primary`/`primary-foreground`. Nunca escreva texto
   sobre um fundo sem usar o `-foreground` correspondente — é o que garante
   contraste nos dois temas.
3. **Uma família de fonte só.** Nunito Sans em toda a interface. Hierarquia se faz
   com **peso e tamanho**, não com troca de fonte.
4. **Tudo em `rem`.** A interface inteira escala junto ao mudar o `font-size` da
   raiz — é o que permite o eixo de "tamanho da interface".
5. **Sombras suaves, nunca duras.** Elevação é sugerida por sombras difusas e de
   baixa opacidade, não por linhas pretas.
6. **Sempre nos dois temas.** Todo token é definido em claro **e** escuro. Um
   valor que só existe num tema é um bug.
7. **Personalizável em 3 eixos** sem tocar em código: cor de destaque, tamanho e
   arredondamento (ver §5).

---

## 2. Como os tokens funcionam

Os tokens são **CSS custom properties** guardando a cor completa em HSL. No tema
claro os valores ficam em `:root`; no escuro, em `.dark`. A stack de origem os
expõe ao Tailwind com `@theme` (`--color-primary: var(--primary)`), o que libera
`bg-primary`, `text-primary`, `border-primary` **e os modificadores de opacidade**
(`bg-primary/10`).

Para portar sem Tailwind, use os mesmos nomes de variável e consuma com
`var(--primary)`. O par foreground segue a mesma convenção em qualquer stack.

```css
:root      { --primary: hsl(221 83% 53%); --primary-foreground: hsl(0 0% 100%); }
.dark      { --primary: #6c72ff;          --primary-foreground: #ffffff; }
.botao     { background: var(--primary); color: var(--primary-foreground); }
```

---

## 3. Cor

### 3.1 Tokens neutros e de superfície

| Token | Papel | Claro | Escuro |
|---|---|---|---|
| `background` / `foreground` | Fundo da página / texto base | `0 0% 100%` / `222 47% 11%` | `#0b0b0e` / `#fafafa` |
| `card` / `card-foreground` | Cartões, painéis | `0 0% 100%` / `222 47% 11%` | `#161618` / `#fafafa` |
| `popover` / `popover-foreground` | Menus, tooltips, flutuantes | `0 0% 100%` / `222 47% 11%` | `#161618` / `#fafafa` |
| `secondary` / `secondary-foreground` | Botão/realce secundário | `220 14% 96%` / `222 47% 11%` | `#232327` / `#fafafa` |
| `muted` / `muted-foreground` | Fundos apagados / texto de apoio | `220 14% 96%` / `220 9% 46%` | `#161619` / `#a1a1aa` |
| `accent` / `accent-foreground` | Hover sutil, destaque leve | `221 83% 97%` / `221 83% 40%` | `#1f1f23` / `#d4d4d8` |
| `border` | Bordas e divisórias | `220 13% 91%` | `#29292e` |
| `input` | Borda de campos de formulário | `220 13% 91%` | `#34343a` |
| `ring` | Anel de foco | `221 83% 53%` | `#6c72ff` |

> Valores em HSL estão no formato `H S% L%` (uso: `hsl(220 13% 91%)`). Os do tema
> escuro estão em HEX porque foram portados de um tema pronto — misturar HSL e HEX
> é ok, o token guarda a cor final.

### 3.2 Tokens de ação e estado

| Token | Papel | Claro | Escuro |
|---|---|---|---|
| `primary` / `primary-foreground` | Ação principal, links, seleção | `221 83% 53%` / `0 0% 100%` | `#6c72ff` / `#ffffff` |
| `success` / `success-foreground` | Sucesso, positivo | `142 71% 45%` / `0 0% 100%` | `#14ca74` / `#ffffff` |
| `warning` / `warning-foreground` | Atenção, a vencer | `38 92% 50%` / `0 0% 100%` | `#fdb52a` / `#0b0b0e` |
| `destructive` / `destructive-foreground` | Erro, exclusão, perigo | `0 72% 51%` / `0 0% 100%` | `#e5484d` / `#ffffff` |

**Regra:** `success`/`warning`/`destructive` comunicam *estado* — use com
parcimônia e sempre com significado (nunca "porque ficou bonito verde").

### 3.3 Cores de gráfico

Paleta de 5 cores para dataviz, distinta o suficiente para séries lado a lado:

| Token | Claro | Escuro |
|---|---|---|
| `chart-1` | `221 83% 53%` (azul) | `#6c72ff` (índigo) |
| `chart-2` | `199 89% 48%` (ciano) | `#cb3cff` (magenta) |
| `chart-3` | `142 71% 45%` (verde) | `#00c2ff` (ciano) |
| `chart-4` | `38 92% 50%` (âmbar) | `#14ca74` (verde) |
| `chart-5` | `262 83% 58%` (violeta) | `#fdb52a` (âmbar) |

### 3.4 Tokens de sidebar

A barra lateral tem seu próprio conjunto para poder divergir do resto da UI
(ex.: fundo levemente diferente, item ativo com destaque próprio):

`sidebar-background`, `sidebar-foreground`, `sidebar-primary` (+`-foreground`),
`sidebar-accent` (+`-foreground`), `sidebar-border`, `sidebar-ring`.

No claro acompanham os neutros; no escuro o fundo é `#0b0b0e`, o texto `#a1a1aa`,
o item ativo `#6c72ff`.

### 3.5 Derivados translúcidos do primary

Pré-calculados para brilhos e gradientes sem recalcular alpha em runtime:

| Token | Valor (claro) | Uso |
|---|---|---|
| `primary-10` | `primary / 0.1` | anel do `shadow-glow`, `bg-radial-primary` |
| `primary-12` | `primary / 0.12` | gradiente radial |
| `primary-35` | `primary / 0.35` | halo do `shadow-glow` |
| `border-40` | `border / 0.4` | linhas do `.bg-grid` |

---

## 4. Sistema de cor de destaque (accent)

O `primary` (e seus derivados) pode ser trocado por usuário sem afetar fundos,
textos ou bordas. A troca é feita por um atributo `data-accent` na raiz `<html>`;
cada accent redefine `--primary`, `--ring`, `--accent`, os tokens de sidebar e
`chart-1`, **em claro e escuro separadamente**. Azul é o padrão (sem atributo).

| Accent | Rótulo | Primary claro | Primary escuro |
|---|---|---|---|
| `blue` *(padrão)* | Azul | `221 83% 53%` | `#6c72ff` |
| `violet` | Violeta | `262 83% 58%` | `263 90% 66%` |
| `emerald` | Esmeralda | `160 84% 39%` | `158 74% 44%` |
| `rose` | Rosé | `347 77% 50%` | `346 84% 60%` |
| `amber` | Âmbar | `32 95% 44%` | `35 92% 55%` |
| `sky` | Ciano | `199 89% 48%` | `199 89% 55%` |

Padrão de implementação (seletores separados por tema):

```css
:root:not(.dark)[data-accent='emerald'] {
    --primary: hsl(160 84% 39%); --ring: hsl(160 84% 39%);
    --accent: hsl(160 84% 95%); --accent-foreground: hsl(160 84% 27%);
    --sidebar-primary: hsl(160 84% 39%); --chart-1: hsl(160 84% 39%);
    --primary-10: hsl(160 84% 39% / 0.1); /* +12, +35 */
}
.dark[data-accent='emerald'] { /* … variante escura … */ }
```

---

## 5. Personalização (3 eixos)

Três ajustes por usuário, guardados no `localStorage` e aplicados na raiz `<html>`
**antes do render** (para não piscar). São independentes do claro/escuro.

| Eixo | Como aplica | Opções |
|---|---|---|
| **Accent** (cor) | `data-accent` no `<html>` | Azul*, Violeta, Esmeralda, Rosé, Âmbar, Ciano |
| **Escala** (tamanho) | `font-size` da raiz (UI é toda `rem`) | Compacto `16px`, Padrão* `17px`, Confortável `18px`, Grande `19px` |
| **Raio** (cantos) | `--radius` | Reto `0.35rem`, Padrão* `0.65rem`, Arredondado `0.95rem` |

`*` = padrão. Como tudo é `rem`, mudar a escala redimensiona a interface inteira
proporcionalmente — não só a fonte.

---

## 6. Tipografia

- **Família:** `Nunito Sans`, com fallback `ui-sans-serif, system-ui, sans-serif`
  + emojis. Carregada da Bunny Fonts (alternativa privada ao Google Fonts):
  ```html
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=nunito-sans:400,500,600,700,800" rel="stylesheet">
  ```
- **Pesos:** 400 (corpo), 500 (rótulos), 600 (ênfase/subtítulos), 700 (títulos),
  800 (números/destaques fortes).
- **Base:** `17px` na raiz (um pouco maior que o padrão de 16px, mais confortável).
- **Mono:** não há webfont mono; `--font-mono` cai na mono do sistema, só para
  blocos de código eventuais.
- **Refinamentos** aplicados no `body`:
  ```css
  font-feature-settings: 'rlig' 1, 'calt' 1, 'ss01' 1; /* ligaduras + stylistic set */
  text-rendering: optimizeLegibility;
  ```
- **Números tabulares:** utilitário `.tabular` (`font-variant-numeric: tabular-nums`)
  para valores empilhados em coluna ficarem alinhados. Não troca a fonte.

---

## 7. Forma: raio, sombra e movimento

### 7.1 Raio

`--radius` é a base (padrão `0.65rem`); a escala é derivada dele, então mudar a
base reescala tudo:

| Passo | Cálculo | Uso típico |
|---|---|---|
| `sm` | `radius − 4px` | badges, chips |
| `md` | `radius − 2px` | inputs, botões |
| `lg` | `radius` | cartões |
| `xl` | `radius + 4px` | modais, painéis grandes |

### 7.2 Sombras (suaves e difusas)

| Token | Valor |
|---|---|
| `shadow-xs` | `0 1px 2px 0 rgb(0 0 0 / 0.04)` |
| `shadow-sm` | `0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.06)` |
| `shadow-md` | `0 4px 12px -2px rgb(0 0 0 / 0.08), 0 2px 6px -2px rgb(0 0 0 / 0.05)` |
| `shadow-lg` | `0 12px 32px -8px rgb(0 0 0 / 0.12), 0 4px 12px -4px rgb(0 0 0 / 0.08)` |
| `shadow-glow` | `0 0 0 1px var(--primary-10), 0 8px 24px -8px var(--primary-35)` — realce em torno de elementos primários |

### 7.3 Movimento

| Animação | Valor | Uso |
|---|---|---|
| `fade-in` | `0.3s ease-out` (opacidade + `translateY(4px)`) | entrada de conteúdo |
| `shimmer` | `1.6s infinite` | esqueleto de carregamento |
| `accordion-down` / `-up` | `0.2s ease-out` | abrir/fechar accordion |

Regra: transições curtas (0.2–0.3s) e `ease-out`. Movimento serve à percepção,
não ao espetáculo.

---

## 8. Utilitários de marca

Classes utilitárias próprias do sistema (além do Tailwind):

| Classe | O que faz |
|---|---|
| `.glass` | Fundo translúcido com `backdrop-filter: blur(24px) saturate(150%)` — headers e barras flutuantes |
| `.bg-grid` | Grade sutil de 40×40px com as linhas em `border/40` — telas de auth e marketing |
| `.bg-radial-primary` | Gradiente radial suave do primary a partir do topo |
| `.text-gradient` | Texto com gradiente `foreground → foreground/60` (títulos) |
| `.shimmer` | Brilho deslizante para placeholders de carregamento |
| `.tabular` | Números de largura fixa (ver §6) |

Extra: **scrollbar custom** (10px, thumb na cor `border`, cantos arredondados),
aplicada globalmente em WebKit e via `scrollbar-color` no Firefox.

---

## 9. Inventário de componentes

34 componentes. A base segue o padrão **shadcn/ui** (headless Radix + variantes
por `class-variance-authority`); os marcados com ★ são **próprios do projeto**.

**Formulário e entrada:** `input`, `textarea`, `label`, `checkbox`, `select`,
`toggle`, `toggle-group`, `combobox`★, `multi-select`★, `currency-input`★,
`photo-picker`★, `segmented-control`★, `status-picker`★.

**Ação e navegação:** `button`, `dropdown-menu`, `navigation-menu`, `sidebar`,
`breadcrumb`, `command`, `sortable-header`★.

**Contêiner e sobreposição:** `card`, `dialog`, `sheet`, `popover`, `tooltip`,
`collapsible`, `separator`, `confirm-dialog`★.

**Exibição e status:** `alert`, `badge`, `avatar`, `skeleton`, `filter-chip`★,
`icon`★ (wrapper de Lucide).

Convenção de componente: primitiva Radix para comportamento/acessibilidade +
variantes com CVA + cores só por token. Um componente não conhece cor literal.

---

## 10. Convenções

- **Aliases de import** (da stack de origem): `@/components`, `@/components/ui`,
  `@/lib`, `@/lib/utils`, `@/hooks`.
- **Ícones:** biblioteca única (Lucide). Tamanho segue o texto (em `em`/`rem`).
- **Nome de token:** sempre semântico (o papel), nunca a cor. `primary`, não
  `azul`. `muted-foreground`, não `cinza-texto`.
- **Novo componente** herda os tokens; se precisar de uma cor que não existe,
  primeiro pergunte se não é um estado já coberto por `success`/`warning`/
  `destructive` antes de criar token novo.

---

## 11. Como aplicar em um projeto novo

**Mesma stack (Tailwind v4 + React + shadcn):**
1. Copie o bloco `@theme` + `:root` + `.dark` + os blocos `[data-accent]` para o
   `app.css` (ou um `tokens.css` importado).
2. Carregue a Nunito Sans (link da Bunny Fonts, §6) e ajuste `--font-sans`.
3. Traga os utilitários de marca (§8) e as animações (§7.3).
4. Replique o hook de personalização (3 eixos, §5) se quiser o mesmo controle por
   usuário.
5. Adicione componentes shadcn conforme a necessidade — eles já consomem os
   tokens automaticamente.

**Outra stack (Vue, Svelte, CSS puro, e-mail…):**
1. Leve as CSS variables de `:root`/`.dark` — funcionam em qualquer lugar.
2. Aplique a regra do par `background`/`foreground` (§1, §2) em cada superfície.
3. Use a paleta de estado (§3.2) e de gráfico (§3.3) pelos mesmos papéis.
4. Mantenha a tipografia (§6), a escala de sombra (§7.2) e o vocabulário de raio
   (§7.1) — são o que dá a "cara" do sistema, independente de framework.

**Só design (Figma, apresentação):**
Use as tabelas das §3–§7 como a fonte de verdade de cores, tipografia, raio e
sombra. Nomeie os estilos pelos papéis (não pelas cores) para o handoff bater com
o código.
