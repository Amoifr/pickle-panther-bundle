# Symfony Flex recipe (ready to submit)

This folder is **not** used by the bundle at runtime. It holds the Symfony Flex
recipe in the exact layout expected by
[`symfony/recipes-contrib`](https://github.com/symfony/recipes-contrib), so that
`composer require --dev amoifr/pickle-panther-bundle` can auto-create
`config/packages/test/pickle_panther.yaml` and register the bundle in `test`.

## Layout

```
amoifr/pickle-panther-bundle/0.3/
├── manifest.json
└── config/packages/test/pickle_panther.yaml
```

- `manifest.json` registers the bundle in the **test** environment only and
  copies everything under `config/` into the project's `%CONFIG_DIR%/`.
- The version directory (`0.3`) is the **minimum** package version the recipe
  applies to. Bump it (e.g. `0.4`, `1.0`) only when a new release needs a
  different recipe.

## How to submit

Recipes for third-party bundles live in `symfony/recipes-contrib`, not in the
bundle itself. To publish:

1. Fork <https://github.com/symfony/recipes-contrib>.
2. Copy this `amoifr/` tree into the fork's root (so the path becomes
   `amoifr/pickle-panther-bundle/0.3/...`).
3. Validate locally:
   ```bash
   composer validate-recipes   # from the recipes-contrib repo
   ```
4. Open a PR. Once merged, the recipe is served automatically via Flex.

Until the PR is merged, the config file must be created manually (see the main
`README.md`). The bundle works without it — every key is optional.