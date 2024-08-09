apt -y update
apt-get -y install ca-certificates apt-transport-https software-properties-common
add-apt-repository -y ppa:ondrej/php
apt -y update
apt -y install unzip curl imagemagick ffmpeg

# Install PHP 8.3, Swoole, and other dependencies
apt -y install php8.3 php8.3-mysql php8.3-curl php8.3-mbstring php8.3-zip php8.3-xml php8.3-swoole

# Install Redis
apt -y install redis-server=5:6.* php8.3-redis

# Disable and stops Apache
systemctl disable apache2
systemctl stop apache2