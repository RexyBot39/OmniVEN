#!/bin/sh
# Runs on Railway before each deploy goes live.
set -e
php artisan migrate --force
php artisan omniven:seed-if-empty
php artisan optimize
