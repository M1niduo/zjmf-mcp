# CLAUDE.md — ixc-soft/mcp-sdk-php

## O que este repositório é

Fork de [`logiscape/mcp-sdk-php`](https://github.com/logiscape/mcp-sdk-php)
**backportado para PHP 7.4**. Não é um fork divergente: não existe feature
própria, nem correção de bug própria, nem código de domínio da IXC aqui. O
`src/` é o `src/` do upstream passado pelo Rector, mais um conjunto pequeno e
enumerado de correções manuais para o que o Rector não sabe rebaixar.

Consequência prática: **toda mudança de comportamento deve vir do upstream.**
Se algo está errado, a correção é sincronizar, não patchear localmente.

- Upstream: `git remote add upstream https://github.com/logiscape/mcp-sdk-php.git`
- Versão do fork acompanha a do upstream (fork em `2.0.0` ⇒ upstream `2.0.0`).
- Único consumidor conhecido: `ixcsoft/mcp-server` (repo `mcp-server-service`).
  Ele usa 15 classes do SDK, todas server-side, e registra handlers via
  `Server::registerHandler()` — não usa o facade `McpServer`, nem client, nem
  Tasks.

## Ambiente

PHP 7.4 é o alvo, então **a suíte roda no container, não no PHP do host**:

```bash
docker compose run --rm --user root php composer install
docker compose run --rm --user root php vendor/bin/phpunit
docker compose run --rm --user root php php -d memory_limit=1G vendor/bin/phpstan analyse
```

O `docker-compose.yml` monta `docker/php.ini` — é obrigatório, veja Lab Notes.

Estado esperado: **1310 testes, 0 falhas, 1 skip** (o skip é `TaskManagerTest::
testSweepDoesNotCountFailedUnlinkAsDeleted`, que não roda como root) e
**phpstan limpo com baseline**.

## Procedimento de re-sync com o upstream

Não use `git merge upstream/main` — o fork não tem commits de conteúdo a
preservar, e o merge só gera conflito em cima de um rewrite. O caminho é
substituir e re-rebaixar:

1. `git fetch upstream`
2. `git checkout upstream/main -- src examples webclient tests conformance docs CHANGELOG.md phpunit.xml.dist phpstan.neon`
3. `docker compose run --rm --user root php vendor/bin/rector process`
4. Reaplicar os **deltas manuais** abaixo (o Rector não os cobre e serão
   sobrescritos pelo passo 2).
5. Reaplicar os **deltas de packaging**: nome `ixc-soft/mcp-sdk-php`,
   `php: >=7.4`, `monolog/monolog` em `require` (o upstream deixa em `suggest`,
   mas o `public/index.php` do consumidor usa `Logger`/`StreamHandler` direto),
   `phpunit ^9.6` + `phpstan ^2.1`, e o `phpunit.xml.dist` no schema 9.
6. Lint de sintaxe em 7.4 em **todos** os arquivos, não só no `src/`:
   `for f in $(find src tests conformance examples webclient -name "*.php"); do php -l "$f" >/dev/null || echo "FAIL $f"; done`
7. Suíte + phpstan (regenerar o baseline: `--generate-baseline=phpstan-baseline.neon`).

## Deltas manuais (o que o Rector não rebaixa)

Toda re-sync tem que reaplicar isto. Se aparecer um caso novo, adicione aqui.

1. **Enums.** O Rector converte `enum X: string` numa classe de constantes e
   descarta `from()`/`tryFrom()`/`cases()`. Os três enums do upstream
   (`Types/Role`, `Types/LoggingLevel`, `Server/InitializationState`) recebem
   `use BackedEnumCompat;` — trait em `src/Types/BackedEnumCompat.php`, escrita
   à mão, que devolve a API por reflexão sobre as constantes. Um "case" é o
   próprio escalar, então **`->value` não existe** e todo call site que o usava
   foi ajustado (`Annotations`, `PromptMessage`, `SamplingMessage`,
   `LoggingMessageNotificationParams`, `SetLevelRequestParams`, `ServerSession`,
   `HttpServerSession`), assim como o `instanceof Role` em `Annotations::validate()`
   e os type hints `LoggingLevel $level` (viraram `string $level`).
2. **Tipos de classe do PHP 8 em assinaturas nativas.** `?\OpenSSLAsymmetricKey`
   em `Server/Auth/JwtTokenValidator` (3 métodos) e `\CurlHandle` em
   `Client/Transport/SseConnection::createCurlHandle()` — em 7.4 essas funções
   devolvem `resource`, então o tipo nativo sai e fica só no docblock. Sem isso
   a validação de JWT RS256 e o SSE do client quebram em runtime, não em parse.
3. **Named arguments que o Rector não alcança.** Ele resolve a maioria, mas não
   `parent::__construct(nome: ...)` dentro de classe anônima, nem código PHP
   gerado em runtime dentro de heredoc (fixtures de teste que são escritas em
   arquivo e executadas por subprocesso — `ClientOnSamplingTest`).
4. **Spread com chaves string.** `f(...$arrayComChavesString)` é named-arg
   unpacking do PHP 8.1 e não tem downgrade. Ocorre em `AppsExtensionTest`;
   reescrito posicionalmente.
5. **Promoção de propriedade por referência.** `__construct(private &$x)` perde
   a referência no downgrade. Em 7.4 se recupera com `$this->x = &$x;`.
   Ocorre em `HttpModernStreamingTest`.
6. **Reflexão nos testes.** O PHP 8.1 dispensou `setAccessible(true)`; o 7.4
   não. As ~114 chamadas de `getProperty`/`getMethod`/`new ReflectionProperty`
   da suíte passam por `tests/Reflect.php` (helper escrito à mão).
7. **`phpunit.xml.dist`** vem no schema do PHPUnit 10 (`cacheDirectory`,
   `<source>`), que o 9.6 não lê. Mantido no schema 9.

## Lab Notes — What Not To Do

- **Não faça `git merge upstream/main`.** São ~190 commits em cima de um rewrite
  v1→v2; não há conteúdo próprio no fork para preservar. Substituir os
  diretórios e re-rodar o Rector é mais rápido e mais auditável.
- **Não confie no `php -l` só do `src/`.** Os primeiros erros de sintaxe em 7.4
  apareceram em `tests/`, e um deles só apareceu em *runtime* porque estava
  dentro de um heredoc que gera um script PHP executado por subprocesso.
- **Não rode a suíte no PHP do host** (8.x). O código rebaixado roda em 8.x sem
  reclamar, então o host não prova nada sobre 7.4.
- **Não remova o mount de `docker/php.ini`.** A imagem `serversideup/php:7.4-cli`
  escreve `memory_limit = ${PHP_MEMORY_LIMIT}` no php.ini e resolve pelo
  ambiente; o `StdioTransport` spawna o processo servidor com um allowlist de env
  que dropa `PHP_*`, então o filho sobe com memory_limit ~2M e morre no meio do
  handshake. O sintoma é `RuntimeException: Server process terminated.` em
  `ClientOnSamplingTest`, que parece bug de downgrade e não é.
- **Não tente rebaixar os enums preservando `->value`.** A tentativa anterior
  (v1 do fork, commit `7029cf4`) adicionou só `from()` e deixou `tryFrom()`
  faltando e um `instanceof Role` que nunca mais dava true — bug latente que só
  não apareceu porque o consumidor não usa `annotations`.
- **Não corrija os ~150 erros de phpstan do baseline.** São artefato do
  downgrade (Rector move tipos nativos para docblock, e nomes de classe do PHP 8
  sobrevivem em docblock onde o 7.4 tem `resource`). O upstream é limpo no nível
  6; o baseline existe para que regressão *nova* apareça.
- **A suíte de conformance (`conformance/`) não roda neste container.** Ela
  precisa de Node/npm e do tool oficial do MCP via npm; a imagem PHP 7.4 não
  tem. Os arquivos estão versionados, mas nunca foram executados neste fork.
