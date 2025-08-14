# Contributing to LeadStream

Thanks for helping improve LeadStream! Please read these guidelines before submitting changes.

## Licensing area is frozen
Do not edit files under `includes/License/` or `leadstream-licserver/`.

- Changes require owner approval and PR label `licensing:approved`.
- CODEOWNERS enforces review by @shaunpalmer.
- CI includes a "Licensing integrity" status check for merges to main.
- Local dev: Husky pre-commit blocks accidental commits touching these paths.

## Development
- Create feature branches and open PRs against `main`.
- Follow conventional commits (`feat:`, `fix:`, `chore:`…).
- Keep changes focused and add tests/docs when public behavior changes.
