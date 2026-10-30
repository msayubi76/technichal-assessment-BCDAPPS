#!/bin/sh
# Tests get their own database so running them can never touch the app data.
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`ride_booking_test\`;
GRANT ALL PRIVILEGES ON \`ride_booking_test\`.* TO '$MYSQL_USER'@'%';
FLUSH PRIVILEGES;
SQL
