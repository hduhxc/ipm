#!/usr/bin/python
# -*- coding: UTF-8 -*-

import MySQLdb
import redis
import time

queue = "MySQL:queue"
redis = redis.Redis(host="localhost", port=6379, db=0)
mysql = MySQLdb.connect("localhost", "guest", "uTmUj8vVL9XYFH6Y", "IPM")
log = open('/root/msgq_log', 'a')

while True:
    cur = mysql.cursor()
    try:
        while True:
            sql = redis.lpop(queue);
            if not sql:
                break;
            cur.execute(sql)
            
        mysql.commit()
        
    except MySQLdb.Error, e:
        mysql.rollback()
        err = 'Error: %s\nSQL: %s' % str(e), sql
        
    time.sleep(1)
