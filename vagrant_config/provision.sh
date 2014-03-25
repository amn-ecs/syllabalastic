#!/bin/bash

# NB: this is quite a noddy root password, but that's fine - it's accessible on the local machine only, and contains test data!
MYROOTPASS="shoes";
MYDBNAME="syl";
MYDBUSER="syl";
MYDBPASS="shoes";


debconf-set-selections <<< "mysql-server mysql-server/root_password password $MYROOTPASS"
debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $MYROOTPASS"

apt-get update
apt-get install -y apache2 php5 mysql-client mysql-server git build-essential python-mysqldb vim php-apc php5-mysql php5-ldap

# Symlink to the directory with the code in it...
rm -rf /var/www
ln -fs /vagrant /var/www

# Magical apache config, serve out of the code directory and put apache logs in the same place
service apache2 stop
sed -i 's/www-data/vagrant/g' /etc/apache2/envvars
sed -i 's|APACHE_LOG_DIR=.*$|APACHE_LOG_DIR=/vagrant/apache-logs|' /etc/apache2/envvars
chown -R vagrant:vagrant /var/lock/apache2

cat >/etc/apache2/sites-available/default <<EOF
<VirtualHost *:80>
        ServerAdmin webmaster@localhost

        DocumentRoot /var/www/syllabalastic
        <Directory />
                Options FollowSymLinks
                AllowOverride None
        </Directory>
        <Directory /var/www/syllabalastic/>
                Options Indexes FollowSymLinks MultiViews
                AllowOverride All
                Order allow,deny
                allow from all
        </Directory>

        ErrorLog \${APACHE_LOG_DIR}/error.log

        # Possible values include: debug, info, notice, warn, error, crit,
        # alert, emerg.
        LogLevel warn

        CustomLog \${APACHE_LOG_DIR}/access.log combined


</VirtualHost>
EOF
a2enmod rewrite
service apache2 restart

echo "create database $MYDBNAME; grant all on $MYDBNAME.* to $MYDBUSER@localhost identified by '$MYDBPASS';" | mysql -u root --password="$MYROOTPASS";
cat > /var/www/syllabalastic/passwords.ini<<EOF
[globals]
db_user=$MYDBUSER
db_name=$MYDBNAME
db_password=$MYDBPASS
db_host=localhost
api_key=trstu9phasrt89phsrt890hysr09pq3894tbyuq3890gryulol
EOF

# If you have some test data, wedge it in here...
#mysql syl -u $MYDBUSER --password="$MYDBPASS" < /var/www/syllabuslive2014-02-02.sql;
