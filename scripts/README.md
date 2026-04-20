# scripts/

Developer utilities. Nothing here is required to run the module in production — these are convenience wrappers for local development against the Docker setup.

| Script | What it does |
|---|---|
| `dev-reset.sh` | Wipes the Docker DB volume and restarts PrestaShop from scratch. Useful when you've mangled the install during testing. |
| `dump-state.sh` | Prints a concise summary of `ps_carrefour_*` tables (config, listings counts, jobs by status, last 10 log entries). Paste into bug reports. |
| `logs.sh` | Tails the module's file-based logs (`modules/carrefourmarketplace/logs/*.log`) from inside the container. |
| `run-worker.sh` | Runs `cron/worker.php` once inside the container with `--verbose`, without waiting for cron. |

All scripts assume the Docker Compose stack from the repo root is up (`make dev`). They use `docker exec` to reach into the `carrefour_ps` and `carrefour_db` containers by fixed name.

## Running them

From the repo root:

```bash
chmod +x scripts/*.sh         # first time only
scripts/dev-reset.sh
scripts/dump-state.sh
scripts/run-worker.sh --max-jobs=5
scripts/logs.sh
```

## Adding your own

Keep scripts in POSIX-ish bash (no fancy `zsh` features). Add a header comment with one-line purpose + usage example. Update this README table.
