# Workflow

- After each iteration or chunk of work, run `composer run format`.
- After each schema change, custom attribute or Data class change, run `composer run helpers` (ide-helper and `typescript:transform`).
- Verify each change with `composer run test`.
