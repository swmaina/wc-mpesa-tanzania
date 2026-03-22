# WooCommerce M-Pesa Tanzania - Testing Guide

## Setup Test Environment

### Prerequisites

- PHP 7.4+
- PHPUnit 9.0+
- WordPress development environment
- WooCommerce installed and activated

### Installation

```bash
# Install composer dependencies (for development)
composer install

# Install PHPUnit (if not already installed)
composer require --dev phpunit/phpunit

# Ensure WordPress test environment is set up
export WP_CORE_DIR=/path/to/wordpress/
export WP_DB_NAME=wordpress_test
export WP_DB_USER=root
export WP_DB_PASSWORD=password
export WP_DB_HOST=localhost