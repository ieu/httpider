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
