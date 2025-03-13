# Release Checklist

Use this checklist to ensure everything is ready for the Laravel Webhook Owlery v1.0.0 release.

## Code Quality

- [x] All tests passing
- [x] Code formatting with Laravel Pint
- [x] Static analysis with PHPStan
- [x] No risky tests (those marked as risky should be fixed or documented)

## Documentation

- [x] README.md with complete installation instructions
- [x] Comprehensive usage examples
- [x] Configuration documentation
- [x] Command reference
- [x] Contributor guidelines

## Package Infrastructure

- [x] composer.json with correct dependencies
- [x] License file
- [x] .gitattributes to exclude development files from downloads
- [x] .gitignore to exclude sensitive files
- [x] .editorconfig for consistent coding styles
- [x] GitHub workflow files for CI/CD

## Release Documentation

- [x] CHANGELOG.md with release notes
- [x] RELEASE.md with release process
- [x] CONTRIBUTING.md with contribution guidelines
- [x] SECURITY.md with security policy
- [x] Issue and PR templates

## Final Checks

- [x] Version references consistent across all files
- [x] Package name consistent across all files (andreagroferreira/laravel-webhook-owlery)
- [x] No TODO or FIXME comments remaining in production code
- [x] Badges in README pointing to correct repositories
- [x] Composer scripts for testing, linting, and formatting
- [x] Skipped tests documented with clear reasoning

## Release Process

- [ ] Initialize git repository
- [ ] Create GitHub repository
- [ ] Push code to GitHub
- [ ] Create v1.0.0 tag
- [ ] Push tag to GitHub
- [ ] Register package on Packagist
- [ ] Verify package installation works from Packagist

## Post-Release

- [ ] Monitor package download statistics
- [ ] Collect feedback from early adopters
- [ ] Plan for next release based on feedback
- [ ] Update documentation based on common questions

Once all items are checked, the package is ready for release!