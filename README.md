# httpider
A simple crawler written in PHP

## Installation
First add this repo to `composer.json`:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ieu/httpider"
        }
    ]
}
```
Then install it through [composer](https://getcomposer.org/download/):
```shell
composer require ieu/httpider:dev-master
```

## Usage
Basically write an new class that extends `\Ieu\Httpider\Crawler`:
```php
class MyCrawler extends \Ieu\Httpider\Crawler
{
    /**
     * Start url to crawl
     */
    public function startPoint()
    {
        return 'http://www.example.com';
    }

    /**
     * Where you start parsing and extracting information
     */
    public function parse(\Ieu\Httpider\Response $response)
    {

    }
}
```

And start crawling by calling `start`:
```
$result = (new MyCrawler())->start();
print_r($result);
```

To have multiple start urls, just return an array of string in `startPoint`:
```php
public function startPoint()
{
    return [
        'http://www.example.com/page/1',
        'http://www.example.com/page/2',
    ];
}
```

By default start url is sent using HTTP Get method. To send request with POST, create an `\Ieu\Httpider\Request` instance:
```php
public function startPoint()
{
    return $this->post('http://www.example.com/page/1');
}
```

To send request with headers like user-agent, overwrite `request`:
```php
public function request(string $method, string $uri, callable $callback = null, $meta = null)
{
    return parent::request($method, $uri, $callback, $meta)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest'
        ]);
}
```

To crawl through paginated list:
```php
class MyCrawler extends \Ieu\Httpider\Crawler
{
    public function parse(\Ieu\Httpider\Response $response)
    {
        // Crawl through a list
        foreach ($response->html()->filterXPath('<XPath to items>') as $item) {
            $item = new \Ieu\Httpider\Wrapper\Html($item);
            $href = $item->filterXPath('<XPath to a element>')->attr('href');
            yield $this->get($href, [ $this, 'next' ]);
        }
        
        // Crawl through pages
        foreach ($response->html()->filterXPath('<XPath to next page element>') as $item) {
            $item = new \Ieu\Httpider\Wrapper\Html($item);
            yield $this->get($item->attr('href'), [ $this, 'parse' ]);
        }
    }
    
    public function next(\Ieu\Httpider\Response $response)
    {
    
    }
}
```

By default, charset is detected firstly from `Content-Type` header, then from meta tag in page. To specify an charset explicitly:
```php
$dom = $response->html('GB2312')
```

To pass data through pages:
```php
yield $this->get($href, [ $this, 'next' ], $data);
```

To parse a JSON response:
```php
$json = $response->json();
```

## Examples

### [THE SCRAPINGHUB BLOG](https://blog.scrapinghub.com/)

```php
class MyCrawler extends \Ieu\Httpider\Crawler
{
    public function startPoint()
    {
        return "https://blog.scrapinghub.com/";
    }

    public function parse(\Ieu\Httpider\Response $response)
    {
        $dom = $response->html();
        foreach ($dom->filterXPath('//div[@class="post-item"]') as $item) {
            $item = new \Ieu\Httpider\Wrapper\Html($item);
            
            yield $this->get(
                $item->filterXPath('//a[@class="more-link"]')->attr('href'),
                [$this, 'parsePost'],
                [
                    'title' => trim($item->filterXPath('//div[@class="post-header"]/h2')->text()),
                    'date' => trim($item->filterXPath('//span[@class="date"]')->text()),
                    'author' => trim($item->filterXPath('//span[@class="author"]')->text()),
                    'comment' => trim($item->filterXPath('//span[@class="custom_listing_comments"]')->text()),
                    'excerpt' => trim($item->filterXPath('//div[@class="post-content"]/p')->text()),
                ]
            );
        }

        foreach ($dom->filterXPath('//a[@class="next-posts-link"]') as $item) {
            $item = new \Ieu\Httpider\Wrapper\Html($item);
            
            yield $this->get(
                $item->attr('href'),
                [$this, 'parse']
            );
        }

    }

    public function parsePost(\Ieu\Httpider\Response $response)
    {
        return array_merge(
            $response->getMeta(),
            [
                'outlines' => array_map(
                    function (\DOMElement $v) {
                        return trim((new \Ieu\Httpider\Wrapper\Html($v))->text());
                    },
                    iterator_to_array($response->html()->filterXPath('//div[@class="blog-section"]//h2'))
                )
            ]
        );
    }
}
```
