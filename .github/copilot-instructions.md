# Copilot instructions for api-consumo-dados

## Project purpose

This repository is a small framework-free PHP API for reading MySQL data for a specific client. The server authenticates the caller via an `API-KEY` value, resolves the client ID from the configured key mapping, and reads from the matching table name derived from that client ID.

This is not a general-purpose CRUD app or Laravel/Framework project; the logic is intentionally compact and centered around `config.php`, `index.php`, and `consumir_api.php`.

## Build, test, and validation commands

There is no package manager, CI workflow, or automated unit-test runner in this repository.

Use these validation commands instead:

- PHP syntax checks:
  ```bash
  php -l config.php
  php -l index.php
  php -l consumir_api.php
  ```
- Start the local API server:
  ```bash
  php -S localhost:8000
  ```
- Smoke test the endpoint by consuming from the local server using an authorized client ID and the first configured API key in `.env`:
  ```bash
  php consumir_api.php 7 0
  ```
- Run the endpoint directly in CLI mode, which simulates an authenticated request using the first configured key:
  ```bash
  php index.php
  ```
- HTTP smoke test:
  ```bash
  curl -H "API-KEY: chave_secreta" "http://localhost:8000/index.php?id_cliente=7&last_data_index=0"
  ```

If you need a one-target check for a change, prefer `php -l <file>` for syntax and a focused request to `/index.php` with `API-KEY`, `id_cliente`, and `last_data_index`.

## High-level architecture

- `config.php` is the environment/bootstrap layer. It loads `.env` if present and parses API-key mappings from `API_KEY` (or `API_KEYS` as fallback) using the pattern `chave:id_cliente[,id_cliente]`. Separate key mappings with `|`.
- `index.php` is the request entrypoint. It validates the `API-KEY` header, checks that the requested `id_cliente` belongs to that key, validates `last_data_index`, finds the correct MySQL table for that client, and returns a JSON envelope.
- `resolveDataTable()` matches exactly one table whose name ends with `_<client_id padded to at least two digits>_data` (for example, client `7` maps to `_07_data`). It throws if the table is missing or ambiguous.
- `buscarDados()` reads rows where `data_index > last_data_index`, orders ascending, and respects `QUERY_LIMIT`. It converts `data_format_0` from raw integer storage to a scaled value by dividing by 10 before returning it.
- The response envelope is `valor.last_data_index` and `valor.data`; each row includes `data_index`, `id_cliente`, `data_format_0`, and `time@timestamp`.
- `consumir_api.php` is only a thin HTTP client used for local/manual testing. It reads the first configured key and `API_BASE_URL`, accepts `id_cliente` and an optional cursor as CLI arguments, sends both in the query string, and prints the response and the next cursor command.

## Key conventions and repo-specific patterns

- `id_cliente` must be requested explicitly, but the server must authorize it against the client IDs assigned to the API key before resolving any table.
- A key can map to multiple client IDs using comma-separated values after the colon (for example, `key:7,8`); different key mappings are separated by `|`.
- `QUERY_LIMIT` is configured in `.env`; it is not part of the API request contract.
- `last_data_index` is a cursor supplied per request and should be treated as a non-negative integer.
- Keep database and internal exceptions out of client-facing responses; log them with `error_log()` and return a generic `500`/`401`/`403`/`400` payload instead.
- Keep secrets in `.env`, not in source files; `.env.example` is the safe template to follow.
- The table names are not passed around as request parameters and are resolved server-side from the customer mapping.
- The database schema expected by the API includes fields such as `data_index`, `data_format_0`, and `time@timestamp` on the selected client table.

## Relevant repository files

- `config.php`: `.env` loader and API-key parsing
- `index.php`: authentication, client resolution, table lookup, and JSON response
- `consumir_api.php`: CLI client used for local smoke testing
- `.env.example`: template for local configuration
- `README.md`: project usage and expected runtime behavior
