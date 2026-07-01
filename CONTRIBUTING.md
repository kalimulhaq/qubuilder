# Contributing

Contributions are **welcome** and will be fully **credited**.

Please read and understand the contribution guide before creating an issue or pull request.

## Etiquette

This project is open source, and as such, the maintainers give their free time to build and maintain the source code
held within. They make the code freely available in the hope that it will be of use to other developers. It would be
extremely unfair for them to suffer abuse or anger for their hard work.

Please be considerate towards maintainers when raising issues or presenting pull requests. Let's show the
world that developers are civilized and selfless people.

It's the duty of the maintainer to ensure that all submissions to the project are of sufficient
quality to benefit the project. Many developers have different skillsets, strengths, and weaknesses. Respect the maintainer's decision, and do not be upset or abusive if your submission is not used.

## Viability

When requesting or submitting new features, first consider whether it might be useful to others. Open
source projects are used by many developers, who may have entirely different needs to your own. Think about
whether or not your feature is likely to be used by other users of the project.

## Procedure

Before filing an issue:

- Attempt to replicate the problem, to ensure that it wasn't a coincidental incident.
- Check to make sure your feature suggestion isn't already present within the project.
- Check the pull requests tab to ensure that the bug doesn't have a fix in progress.
- Check the pull requests tab to ensure that the feature isn't already in progress.

Before submitting a pull request:

- Check the codebase to ensure that your feature doesn't already exist.
- Check the pull requests to ensure that another person hasn't already submitted the feature or fix.

## Requirements

If the project maintainer has any additional requirements, you will find them listed here.

- **Code style ([Laravel Pint](https://laravel.com/docs/pint))** - Run `composer format` before committing (or `composer lint` to check). CI enforces this via the `code-style` workflow.

- **Static analysis ([PHPStan](https://phpstan.org) / [Larastan](https://github.com/larastan/larastan))** - Run `composer analyse`; it must pass at the configured level. New code should not add errors to `phpstan-baseline.neon`.

- **Add tests!** - Your patch won't be accepted if it doesn't have tests. Run `composer test`.

- **Document any change in behaviour** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](https://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.

## Local development

```bash
composer install      # install dependencies
composer test         # run the PHPUnit test suite
composer analyse      # run PHPStan (Larastan) static analysis
composer format       # apply Laravel Pint code style (composer lint to only check)
```

The test suite runs on an in-memory SQLite database via Orchestra Testbench, so the `pdo_sqlite` PHP extension must be enabled locally.

## Continuous integration

Every push and pull request runs three workflows: `run-tests` (PHP 8.3/8.4 × Laravel 11/12/13 × prefer-lowest/prefer-stable), `phpstan`, and `code-style`. A release is only cut (via a `v*` tag) after the full test matrix passes.

## Automated dependency updates

Dependency updates are managed by [Dependabot](.github/dependabot.yml):

- **Patch/minor** updates (composer and GitHub Actions) are grouped into a single PR per ecosystem and **auto-merged once CI passes** (see `.github/workflows/dependabot-auto-merge.yml`).
- **Major** updates are opened as individual PRs and require manual review and merge.

**Happy coding**!
