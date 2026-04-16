#!/bin/sh
set -e

cp /home/site/wwwroot/nginx-default.conf /etc/nginx/sites-available/default
cp /home/site/wwwroot/nginx-default.conf /etc/nginx/sites-enabled/default
service nginx reload || service nginx start
