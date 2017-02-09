from scrapy.commands import ScrapyCommand
import redis

class Command(ScrapyCommand):
    redis = redis.Redis(host="localhost", port=6379, db=0)
    list_limit = 200;
    hot_list_limit = 20
    
    def run(self, args, opts):
        spiders = self.crawler_process.spider_loader.list()
        
        for spider_name in spiders:
            self.crawler_process.crawl(spider_name)
        
        self.crawler_process.start(stop_after_crawl=True)
        
        for i in range(0, len(spiders)):
            article_list = 'Article:category:' + str(i)
            self.redis.ltrim(article_list, 0, self.list_limit - 1)
        
        article_hot_list = 'Article:hot_list'
        article_hot_set = 'Article:hot'
        hot_list_cnt = self.redis.llen(article_hot_list)
        
        for i in range(self.hot_list_limit, hot_list_cnt):
            index = self.redis.lindex(article_hot_list, i);
            item_prefix = 'Article:' + str(index) + ':'
            self.redis.delete(item_prefix + 'favorite')
            self.redis.delete(item_prefix + 'star')
            self.redis.delete(item_prefix + 'comment')
            self.redis.delete(item_prefix + 'comment_cnt')
            
        self.redis.delete(article_hot_set)
        self.redis.ltrim(article_hot_list, 0, self.hot_list_limit - 1)
        
        for i in range(0, self.hot_list_limit):
            index = self.redis.lindex(article_hot_list, i)
            self.redis.sadd(article_hot_set, index)
