#!/bin/bash
set -e

# Disable all MPM modules
a2dismod mpm_event mpm_worker 2>/dev/null || true

# Enable only mpm_prefork
a2enmod mpm_prefork 2>/dev/null || true

# Start Apache
exec apache2-foreground
