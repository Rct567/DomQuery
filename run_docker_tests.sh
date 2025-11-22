#!/bin/bash

# Check prerequisites
if ! command -v docker &> /dev/null || ! command -v docker-compose &> /dev/null; then
    echo -e "\e[31mError: Docker and Docker Compose are required. Please install them before running this script.\e[0m"
    exit 1
fi

PHP_VERSIONS=("7.2" "7.4" "8.0" "8.2" "8.3" "8.4" "8.5")
TESTS_PASSED=true

function run_phpunit_tests() {
    local php_version="$1"
    echo -e "\n================[ Testing PHP $php_version: ]=============================================\n"
    docker-compose run "phpunit-$php_version"
    return $?
}

# Loop through PHP versions
for php_version in "${PHP_VERSIONS[@]}"; do
    run_phpunit_tests "$php_version"
    exit_code=$?

    # Check if tests failed
    if [ $exit_code -ne 0 ]; then
        TESTS_PASSED=false
        break
    fi
done

# Display messages based on test results
if $TESTS_PASSED; then
    echo -e "\n\n\e[32mTests passed successfully for all PHP versions.\e[0m"
else
    echo -e "\n\n\e[31mTests failed for at least one PHP version.\e[0m"
fi

# Exit with the overall exit code
exit $exit_code