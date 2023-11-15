#!/bin/bash

# Run tests for PHP 7.2
echo -e "\n=============================================================\n"
docker-compose run phpunit-7.2
EXIT_CODE_7_2=$?

# Run tests for PHP 8.2
echo -e "\n=============================================================\n"
docker-compose run phpunit-8.2
EXIT_CODE_8_2=$?

# Check exit codes and display messages
if [ $EXIT_CODE_7_2 -eq 0 ] && [ $EXIT_CODE_8_2 -eq 0 ]; then
    echo -e "\n\n\e[32mTests passed successfully for both PHP 7.2 and PHP 8.2.\e[0m"
else
    echo -e "\n\n\e[31mTests failed for at least one PHP version.\e[0m"
fi

# Exit with the overall exit code
exit $((EXIT_CODE_7_2 + EXIT_CODE_8_2))