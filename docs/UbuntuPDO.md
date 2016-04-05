
# Ubuntu Firebird PDO installing

```
sudo apt-get install php5-dev firebird2.5-dev php-pear devscripts debget
apt-get source php5
cd php5-*
cd ext/pdo_firebird
phpize
sudo ln -s /usr/include/php5 /usr/include/php
./configure
make
sudo make install
```

Load module inside /etc/php5/cli/conf.d/10-pdo.ini and /etc/php5/apache/conf.d/10-pdo.ini:

```
extension=pdo_firebird.so
```

Check:

```
php -i | grep PDO
```

Should return:

```
PDO_Firebird
PDO Driver for Firebird/InterBase => enabled
```