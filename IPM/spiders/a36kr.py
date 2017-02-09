# -*- coding: utf-8 -*-
import scrapy
import json
import redis
from IPM.items import IpmItem
from scrapy.http import Request

class A36krSpider(scrapy.Spider):
    name = "36kr"
    allowed_domains = ["36kr.com"]
    redis = redis.Redis(host="localhost", port=6379, db=0)
    start_urls = (
        'http://36kr.com/asynces/posts/info_flow_post_more.json?b_url_code=0',
    )

    def parse(self, response):
        data = json.loads(response.body)['data']['feed_posts']
        off = response.meta['off'] if response.meta.has_key('off') else 0
        stop_flag = False

        if response.meta.has_key('late'):
            late = response.meta['late']
        else:
            late = self.redis.get('Late:1') if self.redis.exists('Late:1') else ""
            self.redis.set('Late:1', data[0]['url_code'])

        for article in data:
            item = IpmItem()
            item['title'] = article['title']
            item['time'] = article['updated_at']
            item['summary'] = article['summary']
            item['cover'] = article['cover']
            item['category'] = 1
            last = str(article['url_code'])
            item['url'] = "http://36kr.com/p/" + last + '.html'

            if last == late:
                stop_flag = True
                break

            yield item

        off += 20

        if off < 500 and stop_flag == False:
            req = Request('http://36kr.com/asynces/posts/info_flow_post_more.json?b_url_code=' + last)
            req.meta['off'] = off
            req.meta['late'] = late

            yield req
