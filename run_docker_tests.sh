#!/bin/bash
set -euo pipefail

# Check prerequisites
if ! command -v docker &> /dev/null || ! command -v docker-compose &> /dev/null; then
    printf "\e[31mError: Docker and Docker Compose are required. Please install them before running this script.\e[0m\n"
    exit 1
fi

# Clean stale containers from any previous runs
docker compose down --remove-orphans || true

PHP_VERSIONS=("7.2" "7.4" "8.0" "8.2" "8.3" "8.4" "8.5")
PHP_SERVICES=()
for v in "${PHP_VERSIONS[@]}"; do
    PHP_SERVICES+=("phpunit-${v}")
done

# Build all images in parallel first
printf "Building images in parallel: %s\n" "${PHP_SERVICES[*]}"
if ! docker compose build --parallel "${PHP_SERVICES[@]}"; then
    printf "\e[31mOne or more builds failed.\e[0m\n"
    exit 1
fi

TESTS_PASSED=true
EXIT_CODE=0

run_phpunit_tests() {
    local php_version="$1"
    printf "\n================[ Testing PHP ${php_version}: ]=============================================\n\n"
    
    # Run the test container (image already built above)
    if ! docker compose run --rm "phpunit-${php_version}"; then
        printf "\e[31mRun failed for PHP ${php_version}.\e[0m\n"
        return 1
    fi
    return 0
}

printf "Starting tests for PHP versions: %s\n" "${PHP_VERSIONS[*]}"

for php_version in "${PHP_VERSIONS[@]}"; do
    if ! run_phpunit_tests "$php_version"; then
        TESTS_PASSED=false
        EXIT_CODE=1
        break
    fi
done

if $TESTS_PASSED; then
    printf "\n\n\e[32mTests passed successfully for all PHP versions.\e[0m\n"
else
    printf "\n\n\e[31mTests failed for at least one PHP version.\e[0m\n"
fi

exit $EXIT_CODE