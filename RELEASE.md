# Release Process

This document outlines the steps to release a new version of the Laravel Webhook Owlery package.

## Preparation

1. Ensure all tests are passing:
   ```bash
   composer test
   ```

2. Check code styling:
   ```bash
   composer check-style
   ```

3. Update the CHANGELOG.md with all notable changes for the release

4. Update version references in documentation if needed

## Release Process

1. Create a new git repository if this is the first release:
   ```bash
   git init
   git add .
   git commit -m "Initial commit"
   ```

2. Push to GitHub:
   ```bash
   git remote add origin git@github.com:andreagroferreira/laravel-webhook-owlery.git
   git push -u origin main
   ```

3. Create a version tag:
   ```bash
   git tag v1.0.0
   git push --tags
   ```

4. Register the package on Packagist:
   - Go to https://packagist.org/packages/submit
   - Submit your GitHub repository URL: https://github.com/andreagroferreira/laravel-webhook-owlery
   - Make sure Packagist settings are configured to automatically update when you push new tags

## Post-Release

1. Monitor package installation and usage reports

2. Address any issues that users encounter

3. Plan for the next release

## Version Naming Convention

We follow Semantic Versioning (SemVer):

- MAJOR version for incompatible API changes
- MINOR version for backward-compatible functionality additions
- PATCH version for backward-compatible bug fixes

## Documentation Update

After releasing a new version, ensure that the README.md and other documentation are updated to reflect any changes in installation instructions, configuration options, or usage examples.