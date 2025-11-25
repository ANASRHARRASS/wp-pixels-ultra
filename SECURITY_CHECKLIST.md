# Repo Security Checklist

- [ ] Rotate any keys accidentally committed and revoke old ones.
- [ ] Remove secrets from git history (git-filter-repo or BFG) if present.
- [ ] Add `.env` to `.gitignore` and commit `.env.example` with no secrets.
- [ ] Enable Dependabot (version & security updates).
- [ ] Enable CodeQL code scanning and schedule analysis.
- [ ] Add a secret-detection workflow (detect-secrets/truffleHog) and pre-commit hook.
- [ ] Add branch protection requiring PR reviews and the security checks you want.
- [ ] Use GitHub Secrets or a dedicated secrets manager for runtime secrets.
