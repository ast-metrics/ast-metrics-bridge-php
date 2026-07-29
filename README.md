# AST Metrics, bridge for PHP

Run [AST Metrics](https://github.com/ast-metrics/ast-metrics) from a PHP project,
installed and versioned like any other Composer tool. No PHP extension, no
runtime dependency: the analyzer is a single binary, downloaded once and cached.

```bash
composer require --dev halleck45/ast-metrics
php vendor/bin/ast-metrics analyze src
```

## Coming from PhpMetrics

Your existing command line keeps working. The bridge recognises PhpMetrics
options and translates them, then tells you what it did:

```bash
$ php vendor/bin/ast-metrics --report-html=./report --extensions=php,inc --exclude=tests,vendor src,lib

warning: --extensions became --php-extensions=.inc
warning: --exclude became --exclude=tests --exclude=vendor
warning: no subcommand given, assuming "analyze" for PhpMetrics compatibility. Prefer: ast-metrics analyze src lib
warning: the directory list became separate arguments: src lib
```

One line per rewrite, on stderr, and nothing at all when there was nothing to
rewrite. Enough to migrate the command line when you feel like it, without a
banner in front of the report on every run.

It is on by default, and a native command line comes out untouched: nothing is
rewritten unless it means something to PhpMetrics and not to AST Metrics. An
exclusion you wrote as a regular expression stays a regular expression. Set
`AST_METRICS_NO_COMPAT=1` to pass every argument through verbatim.

### What is translated

| PhpMetrics | AST Metrics | Note |
| --- | --- | --- |
| `src,lib` | `src lib` | comma-separated directories are split |
| *(no subcommand)* | `analyze` | features are grouped into subcommands |
| `--report-html=<dir>` | `--report-html=<dir>` | identical |
| `--report-json=<file>` | `--report-json=<file>` | identical |
| `--exclude=a,b` | `--exclude=a --exclude=b` | a plain directory name is escaped so it matches literally; an expression is left alone |
| `--extensions=php,inc` | `--php-extensions=.inc` | `.php` is parsed by default |

Test directories are **not** excluded. That is deliberate: AST Metrics analyzes
tests as tests, and reports god tests, orphan classes, isolation and traceability.
Excluding them would silence the part of the report that says whether your test
suite is any good. Expect different numbers from PhpMetrics, for that reason.

### What is dropped

Nothing is lost here: the analyzer either already does it, or has nothing to
switch off. The bridge says so rather than staying silent.

| PhpMetrics | Why |
| --- | --- |
| `--git` | git history analysis is built in and always on: bus factor, churn, activity |
| `--composer` | dependencies come from the code itself, as afferent and efferent coupling |
| `--quiet` | already non-interactive |

### What stops the run

These would change what the analysis produces or enforces, so they are refused
with an explanation instead of being ignored. A quality gate that quietly
disappears turns a build green for the wrong reason.

| PhpMetrics | Instead |
| --- | --- |
| `--report-violations` | `--report-sarif=<file>`, read by GitHub code scanning and GitLab |
| `--report-csv` | `--report-json=<file>` or `--report-markdown=<file>` |
| `--report-summary-json` | `--report-json=<file>` already holds the aggregates |
| `--junit` | no equivalent: test results are not crossed with the metrics |
| `--metrics` | `ruleset list` |
| `--config=<file>` | run `ast-metrics init`, then `--config=.ast-metrics.yaml` |

PhpMetrics configuration files are refused rather than half-read: groups, searches
and violation thresholds have no counterpart, and dropping them silently would
change what the analysis enforces.

### Beyond PhpMetrics

Same author, and the same focus on maintainability, but the analyzer is not
PHP-only: Go, Python, Rust, Java, C# and TypeScript are parsed too, so a polyglot
repository gets one report instead of one tool per language. It also ships an MCP
server, which lets a coding agent query the structure and the risk areas of a
codebase.

PhpMetrics remains maintained, and stays the right answer for a PHP-only project
that relies on its groups, searches or PMD output.

## Configuration

| Variable | Effect |
| --- | --- |
| `AST_METRICS_BINARY` | Absolute path to a binary to use as-is. Nothing is downloaded: for air-gapped CI, distribution packages, or working on AST Metrics itself. |
| `AST_METRICS_VERSION` | Release tag to download, or `latest`. Defaults to the version this bridge was released against. |
| `AST_METRICS_CACHE_DIR` | Where downloaded binaries are stored. Defaults to `$XDG_CACHE_HOME/ast-metrics`, then `$HOME/.cache/ast-metrics`. |
| `AST_METRICS_NO_COMPAT` | Set to `1` to disable the PhpMetrics translation and pass every argument through verbatim. |

The binary version is pinned by this package rather than always resolved to
`latest`, so a locked `composer.lock` gives a reproducible analysis: two runs of
the same project use the same analyzer, and the metrics do not move on their own.
Downloads are cached per version and per user, and installed atomically, so an
interrupted download never leaves a half-written binary behind.

## In CI

```yaml
- run: composer install --no-interaction
- run: php vendor/bin/ast-metrics analyze --report-sarif=ast-metrics.sarif src
```

The exit code is the analyzer's own, so a failed quality gate fails the build.
Output is streamed as it is produced, and colours follow the terminal: colours on
a terminal, plain text in a CI log, exactly as if the binary had been run
directly.

## Updating the binary

```bash
php vendor/bin/ast-metrics self-update
```

Or, more predictably, upgrade this package: `composer update halleck45/ast-metrics`.

## Tests

```bash
make test     # or: php tests/run.php
make lint     # syntax of every file
make check    # both
```

No test dependency to install: the suite is plain PHP, so it runs anywhere the
package itself does.

## License

MIT. See [LICENSE](LICENSE) for more details.
