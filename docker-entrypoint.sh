#!/bin/bash
set -e

# Configure PHP for multiple file uploads
echo "max_file_uploads = 20" >> /usr/local/etc/php/conf.d/uploads.ini
echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini
echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini
echo "max_input_vars = 3000" >> /usr/local/etc/php/conf.d/uploads.ini

# Disable all MPM modules
a2dismod mpm_event mpm_worker 2>/dev/null || true

# Enable only mpm_prefork
a2enmod mpm_prefork 2>/dev/null || true

# Start Apache
exec apache2-foreground
