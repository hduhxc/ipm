# -*- coding: utf-8 -*-

# Define your item pipelines here
#
# Don't forget to add your pipeline to the ITEM_PIPELINES setting
# See: http://doc.scrapy.org/en/latest/topics/item-pipeline.html

import MySQLdb
import redis

class IpmPipeline(object):
    redis = redis.Redis(host="localhost", port=6379, db=0)
    mysql = MySQLdb.connect("localhost", "guest", "uTmUj8vVL9XYFH6Y", "IPM", unix_socket="/tmp/mysql.sock")
    
    def encode_item(self, item):
        return '%d!@%s!@%s!@%s!@%s!@%d'% (item['id'], item['title'], item['summary'], item['url'], item['cover'], item['category'])
        
    def open_spider(self, spider):
        self.cur = self.mysql.cursor()
        
    def process_item(self, item, spider):
        ins_item = {
            'title': item['title'].encode('utf-8'),
            'summary': item['summary'].encode('utf-8'),
            'time': item['time'].encode('utf-8'),
            'cover': item['cover'].encode('utf-8'),
            'url': item['url'].encode('utf-8'),
            'category': item['category']
        }
        
        article_list = 'Article:category:0'
        article_cat_list = 'Article:category:%d' % (ins_item['category'])
        hot_article_list = 'Article:hot_list'
        hot_article_set = 'Article:hot'
        sql = 'INSERT INTO `ipm_article`(`title`, `summary`, `time`, `cover`, `link`, `category`) VALUES ("%s", "%s", "%s", "%s", "%s", %d)' % (ins_item['title'], ins_item['summary'], ins_item['time'], ins_item['cover'], ins_item['url'], ins_item['category'])
       
        try:
            self.cur.execute(sql)
            ins_item['id'] = self.cur.lastrowid
        
            encoded_item = self.encode_item(ins_item)
            self.redis.lpush(article_list, encoded_item)
            self.redis.lpush(article_cat_list, encoded_item)
            self.redis.lpush(hot_article_list, ins_item['id'])
        except:
            print 'Error SQL: ' + sql
        
        return item
        
    def close_spider(self, spider):
        try:
            self.mysql.commit()
        except:
            self.mysql.rollback()
            
        self.mysql.close()
