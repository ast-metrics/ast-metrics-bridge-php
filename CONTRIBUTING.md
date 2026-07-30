# Contributing

Thanks for taking the time. This package is small on purpose, so contributing to
it is mostly a matter of running `make check` before you push.

## Getting started

```bash
git clone https://github.com/ast-metrics/ast-metrics-bridge-php
cd ast-metrics-bridge-php
make install
make check
```

`make install` only generates the autoloader: there is nothing to download. The
test suite is plain PHP, with its own assertions in `tests/bootstrap.php`, so it
runs anywhere the package does.

## Making a change

- Branch off `main`, one topic per pull request.
- Add a test in `tests/*_test.php`. Files matching that pattern are picked up
  automatically by the runner.
- Run `make check` (syntax of every file, then the suite). CI runs the same
  target on PHP 7.0, 7.4 and 8.4.
- Write commit messages in the imperative: "handle a missing binary", not
  "handled" or "fixes".

## Two constraints worth knowing

**PHP 7.0 has to keep working.** The package installs into other people's
projects, including old ones, so no typed properties, no arrow functions, no
`??=`, and no dependency of any kind, not even for the tests. If a change needs
a newer syntax, it probably belongs in the analyzer instead.

**A silent behaviour change is a bug.** When the PhpMetrics compatibility layer
rewrites an argument, it says so on stderr. When an option has no equivalent, it
stops the run instead of dropping it. Please keep that: a quality gate that
quietly disappears turns a build green for the wrong reason.

## Where things live

| Path | What |
| --- | --- |
| `bin/ast-metrics` | The entry point installed as `vendor/bin/ast-metrics` |
| `src/AstMetricsProxy.php` | Ties the three together: resolve, translate, run |
| `src/Binary/` | Resolving, downloading and caching the analyzer |
| `src/Compatibility/` | The PhpMetrics argument translation |
| `src/Process/` | Running the binary and forwarding its exit code |
| `tests/` | The suite and its runner |

Analyzer bugs (the metrics themselves, the reports, the rulesets) belong in
[ast-metrics/ast-metrics](https://github.com/ast-metrics/ast-metrics). This
repository is only the PHP bridge: installing the binary, and translating a
command line.

## Reporting a bug

Include the output of `php vendor/bin/ast-metrics --version`, your PHP version,
and the exact command line you ran. If it concerns the PhpMetrics compatibility
layer, the stderr notices are the most useful part.

By contributing, you agree that your work is released under the
[MIT license](LICENSE).
