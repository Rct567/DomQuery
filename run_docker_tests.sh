#!/bin/bash
set -euo pipefail

# Check prerequisites
if ! command -v docker &> /dev/null || ! command -v docker-compose &> /dev/null; then
    echo -e "\e[31mError: Docker and Docker Compose are required. Please install them before running this script.\e[0m"
    exit 1
fi

# Optional: clean stale containers from previous runs
docker compose down --remove-orphans || true

PHP_VERSIONS=("7.2" "7.4" "8.0" "8.2" "8.3" "8.4" "8.5")
TESTS_PASSED=true
EXIT_CODE=0

run_phpunit_tests() {
    local php_version="$1"
    echo -e "\n================[ Testing PHP ${php_version}: ]=============================================\n"
    # Use --rm to remove the ephemeral container after run
    docker compose run --rm "phpunit-${php_version}"
    return $?
}

for php_version in "${PHP_VERSIONS[@]}"; do
    if ! run_phpunit_tests "$php_version"; then
        TESTS_PASSED=false
        EXIT_CODE=1
        break
    fi
done

if $TESTS_PASSED; then
    echo -e "\n\n\e[32mTests passed successfully for all PHP versions.\e[0m"
else
    echo -e "\n\n\e[31mTests failed for at least one PHP version.\e[0m"
fi

exit $EXIT_CODE
