### Summary

**Problem:**
<!-- What was broken, missing or risky, and why it matters. Name the real trigger: an issue, a user report, a failing CI job. -->

**Solution:**
<!-- What this PR does and why this approach. Name the mechanism: the class, method or option involved. -->

---

### Linked issue

<!-- `Closes #123`, or "None". -->

---

### Type

- [ ] Bug fix
- [ ] Feature
- [ ] Improvement
- [ ] Refactor (no behavior change)
- [ ] Docs or tests only
- [ ] CI or tooling

---

### Compatibility (SemVer)

Which release does this need?

- [ ] Patch: restores documented behavior
- [ ] Minor: new behavior that is off or neutral for existing callers
- [ ] Major: breaks documented behavior, or removes or renames public API
- [ ] None: no change to the package's behavior

**If existing behavior changes, what breaks and how can a user keep the old behavior?**
<!-- "The default of `allow_x` stays `true`, so nothing changes unless a user sets it to `false`." -->

---

### Tests

- [ ] New or changed tests cover this change
- [ ] A bug fix has a regression test that fails on the old code
- [ ] Not required: <!-- reason -->

**Ran locally:** <!-- `composer lint`, `composer analyse`, `composer test` -->

CI runs PHP 8.3 and 8.4, Laravel 11, 12 and 13, `prefer-lowest` and `prefer-stable`. The suite uses SQLite only.
<!-- Name any database driver you also tried, or "SQLite only". -->

---

### Docs

- [ ] `README.md` updated (section: ___)
- [ ] `config/config.php` and the README Configuration section updated
- [ ] `CHANGELOG.md` entry added
- [ ] Not required: <!-- reason -->

---

### Notes for the reviewer

<!-- Where to start, the one risky file, anything you could not verify. -->
