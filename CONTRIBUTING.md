# Contributing

Thank you for considering contributing to this project! Every contribution is welcome and helps improve the quality of the project.

Please note that this project adheres to the [TYPO3 Code of Conduct](https://typo3.org/community/values/code-of-conduct). By participating, you are expected to uphold this code.

## Requirements

- [DDEV](https://ddev.readthedocs.io/en/stable/)

## Preparation

```bash
# Clone repository
git clone https://github.com/konradmichalik/typo3-request-profiler.git
cd typo3-request-profiler

# Install dependencies
composer install

# Set up the multi-version test environment (TYPO3 13 + 14)
ddev add-on get konradmichalik/ddev-typo3-multi-version-extension
# Limit TYPO3_VERSIONS to "13 14" in .ddev/docker-compose.typo3-setup.yaml
ddev restart
ddev install all
ddev launch 13   # or: ddev launch 14
```

The `Tests/Acceptance/Fixtures/packages/sitepackage` package contains a deliberate
N+1 demo page for verifying the profiler end to end in the browser.

## Run tests & checks

```bash
# Unit tests
composer test

# Functional tests (require a database; run inside DDEV)
ddev exec "composer test:functional"

# Coding standards, static analysis, rector (CGL)
composer cgl install
composer cgl lint
composer cgl sca
composer cgl migration
```

## Pull requests

1. Create a feature branch.
2. Add tests for your change and keep the existing suite green.
3. Run the CGL checks (`composer cgl lint` / `composer cgl sca`).
4. Open a pull request with a clear description.
