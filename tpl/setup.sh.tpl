#!/bin/bash

{{REDIS}}

/path/to/nutcracker/sbin/nutcracker -c /path/to/nutcracker/conf/nutcracker.ubhvrcache.yml -p /path/to/nutcracker/log/ubhvrcache.pid -o /path/to/nutcracker/log/ubhvrcache.log -m 512 -a {{IP}} -s 13578 -d

nohup /path/to/php/bin/php /data/redis/ubhvrcache/script/bgsave.ubhvrcache.php > /dev/null 2>&1 &
