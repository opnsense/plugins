#!/bin/sh

for redis_dir in /var/db/redis /var/log/redis /var/run/redis
do
  mkdir -p $redis_dir
  chown redis:redis $redis_dir
done

touch /var/log/redis/redis.log
chown redis:redis /var/log/redis/redis.log
