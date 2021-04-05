#!/bin/bash
#
# cloudinit script to spin up and configure a bob
#
# Copyright (C) 2019 kevin olson <ko@kittyhawk.io>
#
SERVICE=bob
ENV=staging
REPO=git@github.com:fumeapp/bob.git
PORT=4000
MAXSIZE=25G
MAXMEMORY=1G

KEY="-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACCb3GUG+VJJ7IEuPOYCEOJCt4vnsj28RL4Z1uA3XyMajAAAAJhy42AHcuNg
BwAAAAtzc2gtZWQyNTUxOQAAACCb3GUG+VJJ7IEuPOYCEOJCt4vnsj28RL4Z1uA3XyMajA
AAAEAV6pQFtosbkNXfVL0hoRFEubwEb8/Ea/TKvyiozGixOJvcZQb5UknsgS485gIQ4kK3
i+eyPbxEvhnW4DdfIxqMAAAAEmFjaWRqYXp6QGdtYWlsLmNvbQECAw==
-----END OPENSSH PRIVATE KEY-----"
echo "$KEY" >/home/ec2-user/.ssh/id_rsa
chmod 0700 /home/ec2-user/.ssh/*
chown -R ec2-user:ec2-user /home/ec2-user/.ssh/

HOSTNAME=$SERVICE-$ENV
hostname $HOSTNAME

yum -y update
amazon-linux-extras install nginx1.12 php8.0
yum -y install git docker php-fpm

usermod -aG docker ec2-user

# nginx config
echo "
user  nginx;
worker_processes  4;
pid        /var/run/nginx.pid;
events {
  worker_connections  1024;
}
http {
  include       /etc/nginx/mime.types;
  default_type  application/octet-stream;
  access_log  /var/log/nginx/access.log;
  error_log /var/log/nginx/error.log;
  sendfile        on;
  keepalive_timeout  65;
  gzip on;
  gzip_disable "msie6";
  client_max_body_size $MAXSIZE;
  server {
    listen $PORT;
    root /home/ec2-user/$SERVICE/public;
    index index.php;
    charset utf-8;
    location / {
      try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
      fastcgi_pass unix:/run/php-fpm/www.sock;
      fastcgi_index index.php;
      fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
      include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

  }
}
" > /etc/nginx/nginx.conf

# php-fpm config

echo '
[www]
user = ec2-user
group = ec2-user
listen = /run/php-fpm/www.sock
listen.acl_users = apache,nginx,ec2-user
listen.allowed_clients = 127.0.0.1
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
slowlog = /var/log/php-fpm/www-slow.log
php_admin_value[error_log] = /var/opt/remi/php80/log/php-fpm/www-error.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = files
php_value[session.save_path]    = /var/opt/remi/php80/lib/php/session
php_value[soap.wsdl_cache_dir]  = /var/opt/remi/php80/lib/php/wsdlcache
' > /etc/opt/remi/php80/php-fpm.d/www.conf

# php.ini updates

memory_limit=$MAXMEMORY
upload_max_filesize=$MAXSIZE
post_max_size=$MAXSIZE
max_execution_time=100
max_input_time=223

for key in memory_limit upload_max_filesize post_max_size max_execution_time max_input_time
do
 sed -i "s/^\($key\).*/\1 $(eval echo = \${$key})/" /etc/php.ini
done

systemctl restart php-fpm.service
systemctl restart nginx.service

# Install Composer
curl -sS https://getcomposer.org/installer | HOME="/home/ec2-user" php -- --install-dir=/usr/local/bin --filename=composer

su ec2-user -c "
scl enable php74 bash
cd ~/
source ~/.bashrc
ssh-keyscan github.com >> ~/.ssh/known_hosts
git clone $REPO
cd $SERVICE
git checkout $ENV
/usr/local/bin/composer storage
/usr/local/bin/composer $ENV
"

gpasswd -a nginx ec2-user
chmod g+x /home/ec2-user
chmod g+x /home/ec2-user/$SERVICE
service nginx restart
service docker start
