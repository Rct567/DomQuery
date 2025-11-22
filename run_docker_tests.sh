#!/bin/bash

# Enable Docker BuildKit for faster builds and caching
export DOCKER_BUILDKIT=1

# Check prerequisites
if ! command -v docker &> /dev/null || ! command -v docker-compose &> /dev/null; then
    echo -e "\e[31mError: Docker and Docker Compose are required. Please install them before running this script.\e[0m"
    exit 1
fi

# Define PHP versions
PHP_VERSIONS=("7.2" "7.4" "8.0" "8.2" "8.3" "8.4" "8.5")

# Build all images first (parallel for speed)
echo -e "\n================[ Building Docker images ]====================="
docker compose build --parallel

# Run tests serially, stopping on first failure
TESTS_PASSED=true

for php_version in "${PHP_VERSIONS[@]}"; do
    service="phpunit-${php_version}"
    echo -e "\n================[ Testing $service ]====================="
    
    docker compose run --rm "$service"
    exit_code=$?
    
    if [ $exit_code -ne 0 ]; then
        TESTS_PASSED=false
        echo -e "\n\e[31mTests failed on PHP $php_version ($service).\e[0m"
        break
    fi
done

# Final result
if $TESTS_PASSED; then
    echo -e "\n\e[32mAll tests passed for all PHP versions.\e[0m"
else
    echo -e "\n\e[31mSome tests failed.\e[0m"
fi

# Exit with the overall exit code
exit $exit_code
