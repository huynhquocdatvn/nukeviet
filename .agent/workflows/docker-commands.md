---
description: How to run commands inside the NukeViet Docker container
---
All commands for this project MUST be executed inside the `app` service container.

1. Ensure the Docker containers are running.
2. Use the following prefix for any command: `docker exec -it nukeviet5_app [command]`
// turbo
3. Example for running composer: `docker exec -it nukeviet5_app composer install`
// turbo
4. Example for running tests: `docker exec -it nukeviet5_app vendor/bin/codecept run`
