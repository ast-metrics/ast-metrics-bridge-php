# AST Metrics for PHP

[AST Metrics](https://github.com/ast-metrics/ast-metrics) is a static analyzer
that tells you which parts of a codebase are risky to touch. This package
installs it with Composer, like any other dev tool: no extension, no service.
The analyzer is a single binary, downloaded once and cached.

> **You may not need this package.** It exists for one thing: getting the
> analyzer through Composer, so it lands in `composer.lock` with the rest of your
> tooling and your PhpMetrics command line keeps working. If that is not what you
> are after, install the analyzer directly:
>
> ```bash
> brew install ast-metrics/tap/ast-metrics    # macOS, Linux
> curl -fsSL https://install.ast-metrics.dev | sh
> ```
>
> And on pull requests, there is a ready-made action:
> [`ast-metrics/action-ast-metrics@v2`](https://github.com/ast-metrics/action-ast-metrics).

## Installation

```bash
composer require --dev ast-metrics/ast-metrics
```

## Usage

```bash
php vendor/bin/ast-metrics analyze src
php vendor/bin/ast-metrics analyze --report-html=./report src
```

Everything else: `php vendor/bin/ast-metrics --help`, or
[ast-metrics.dev](https://ast-metrics.dev).

## Migrating from PhpMetrics?

Replace `phpmetrics` with `ast-metrics`. Most of the time, that is the whole
migration:

```bash
# before
php vendor/bin/phpmetrics --report-html=./report --exclude=tests,vendor src,lib

# after
php vendor/bin/ast-metrics --report-html=./report --exclude=tests,vendor src,lib
```

Same options, same directory list, same report.

### Three things to know

**Some options have no equivalent**, and the run stops rather than ignoring them:

| PhpMetrics | Use instead |
| --- | --- |
| `--report-violations` | `--report-sarif=<file>`, read by GitHub code scanning and GitLab |
| `--report-csv`, `--report-summary-json` | `--report-json=<file>` or `--report-markdown=<file>` |
| `--metrics` | `ast-metrics ruleset list` |
| `--config=<file>` | `ast-metrics init`, then `--config=.ast-metrics.yaml` |
| `--junit` | nothing: test results are not crossed with the metrics |

**Some are just dropped**, because the analyzer already does the work: `--git`
(history analysis is always on) and `--composer` (dependencies come from the
code, as coupling metrics).

**Test directories are not excluded**, on purpose. AST Metrics reports god tests,
orphan classes, isolation and traceability, so excluding tests would silence the
part of the report that says whether your suite is any good. Expect different
numbers from PhpMetrics.

PhpMetrics is still maintained and remains the right answer for a PHP-only
project relying on its groups, searches or PMD output. AST Metrics goes wider:
Go, Python, Rust, Java, C# and TypeScript are parsed too, and it ships an MCP
server so a coding agent can query the risk areas of a codebase.

## Configuration

All optional.

| Variable | Effect |
| --- | --- |
| `AST_METRICS_BINARY` | Path to a binary to use as-is, nothing downloaded: air-gapped CI, distribution packages. |
| `AST_METRICS_VERSION` | Release tag to download, or `latest`. Defaults to the pinned version. |
| `AST_METRICS_CACHE_DIR` | Where binaries are cached. Defaults to `$XDG_CACHE_HOME/ast-metrics`. |

The binary version is pinned by this package rather than resolved to `latest`, so
a locked `composer.lock` gives a reproducible analysis. To upgrade,
`composer update ast-metrics/ast-metrics`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Analyzer bugs go to
[ast-metrics/ast-metrics](https://github.com/ast-metrics/ast-metrics); this
repository is the PHP bridge.

## License

MIT. See [LICENSE](LICENSE).
