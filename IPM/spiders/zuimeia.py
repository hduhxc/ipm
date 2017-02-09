# -*- coding: utf-8 -*-
import scrapy
import redis
from IPM.items import IpmItem
from scrapy.http import Request


class ZuimeiaSpider(scrapy.Spider):
    name = "zuimeia"
    allowed_domains = ["zuimeia.com"]
    redis = redis.Redis(host="localhost", port=6379, db=0)
    start_urls = (
        'http://www.zuimeia.com/',
    )

    def parse(self, response):
        data = response.xpath("//div[@class='content-card']")
        page = response.meta['page'] if response.meta.has_key('page') else 1
        stop_flag = False

        if response.meta.has_key('late'):
            late = response.meta['late']
        else:
            late = self.redis.get('Late:3') if self.redis.exists('Late:3') else ""
            url = data[0].xpath("div[@class='article-title']/a/@href")[0].extract()
            self.redis.set('Late:3', url)

        for sel in data:
            item = IpmItem()
            title_sel = sel.xpath("div[@class='article-title']")
            main_title = title_sel.xpath("a/h1/text()")[0].extract()
            sub_title = title_sel.xpath("a/h1/span/text()")[1].extract()
            url = title_sel.xpath("a/@href")[0].extract()
            time = title_sel.xpath("div[@class='pub-time-and-version']/span/text()")[0].extract()
            img = sel.xpath("a[@class='article-img']/img/@data-original")[0].extract()
            summary = sel.xpath("../div[@class='quote-area']/div/a/text()")[0].extract()

            item['title'] = main_title + ' -- ' + sub_title
            item['url'] = "http://www.zuimeia.com" + url
            item['time'] = time
            item['cover'] = img
            item['summary'] = summary
            item['category'] = 3

            if late == url:
                stop_flag = True
                break

            yield item

        page += 1

        if page < 20 and stop_flag == False:
            req = Request('http://zuimeia.com/apps/?page=' + str(page) + '&platform=1')
            req.meta['page'] = page
            req.meta['late'] = late

            yield req
