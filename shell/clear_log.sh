#!/bin/bash

# log 暂存时间
LOG_EXPIRE=14

# log 目录
LOG_DIR=/path/to/log
# 删除过期日志文件
find ${LOG_DIR}/ -type f -atime +${LOG_EXPIRE} -exec rm {} \;
