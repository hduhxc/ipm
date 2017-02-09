# -*- coding: utf-8 -*-
import scrapy
import json
import redis
from IPM.items import IpmItem
from scrapy.http import Request

class TmtpostSpider(scrapy.Spider):
    name = "tmtpost"
    allowed_domains = ["tmtpost.com"]
    redis = redis.Redis(host="localhost", port=6379, db=0)
    start_urls = (
        'http://www.tmtpost.com/api/lists/get_index_list?offset=0&limit=20',
    )

    def parse(self, response):
        data = json.loads(response.body)['data']
        off = response.meta['off'] if response.meta.has_key('off') else 0
        stop_flag = False

        if response.meta.has_key('late'):
            late = response.meta['late']
        else:
            late = self.redis.get('Late:2') if self.redis.exists('Late:2') else ""
            self.redis.set('Late:2', data[0]['short_url'])

        for article in data:
            item = IpmItem()
            item['title'] = article['title']
            item['time'] = article['time_published']
            item['summary'] = article['summary']
            item['cover'] = article['thumb_image']['200_150'][0]['url']
            item['url'] = article['short_url']
            item['category'] = 2

            if late == article['short_url']:
                stop_flag = True
                break

            yield item

        off += 20

        if off < 500 and stop_flag == False:
            req = Request('http://www.tmtpost.com/api/lists/get_index_list?offset=' + str(off) + '&limit=20')
            req.meta['off'] = off
            req.meta['late'] = late

            yield req
