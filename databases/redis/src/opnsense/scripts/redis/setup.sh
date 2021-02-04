#!/bin/sh

for redis_dir in /var/db/redis /var/log/redis /var/run/redis
do
  mkdir -p $redis_dir
  chown redis:redis $redis_dir
done

touch /var/log/redis/redis.log
chown redis:redis /var/log/redis/redis.log

# Add 'rspamd' user to 'redis' group to allow Unix socket access
if [ -f "/usr/local/bin/rspamd" ]; then
  /usr/sbin/pw group mod redis -m rspamd
fi
