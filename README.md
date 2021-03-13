## 简介
swrpc是一个基于swoole开发的rpc扩展库， 可以很容易集成到第三方框架，如`laravel`，`yii`等等 by wuzhc.

## 功能
- 支持多进程模式或协程模式
- 支持同步，异步调用
- 支持自定义中间件
- 支持按类导入业务逻辑服务
- 支持链路追踪
- 支持注册服务发现
- 支持客户端负载均衡，包含随机，权重两种模式
- 支持自定义日志，包协议，序列化方式等等

## 安装
```bash
php composer.phar require "wuzhc/swprc:^1.0.1" -vvv
```

## 快速上手
### 业务服务
```php
<?php
class UserService 
{
    public static function getName()
    {
        return 'wuzhc';
    }

    public static function getAge()
    {
        return 123;
    }
}
```
### 服务端
server.php
```php
<?php
use Swrpc\Request;
use Swrpc\Server;

include "./vendor/autoload.php";

$middleware1 = function (Request $request, Closure $next) {
    return $next($request);
};
$middleware2 = function (Request $request, Closure $next) {
    return $next($request);
};
$server = new Server('127.0.0.1', 9501);
$server->addMiddleware($middleware1, $middleware2);
$server->addService(UserService::class);
$server->start();
```
启动服务端
```php
php server.php
```
输出如下：
```bash
[2021-03-09T06:33:19.559598+00:00] swrpc.INFO: import UserService_getName => UserService. [] []
[2021-03-09T06:33:19.560459+00:00] swrpc.INFO: import UserService_getAge => UserService. [] []
[2021-03-09T06:33:19.560618+00:00] swrpc.INFO: Rpc Server start. [] []
```

## 配置说明
自定义worker进程数量
```php
use Swrpc\Server;
$server = new Server('127.0.0.1', 9501, ['worker_num'=>swoole_cpu_num()*2]);
$server->addService(UserService::class);
$server->start();
```
启用协程模式
```php
use Swrpc\Server;
$server = new Server('127.0.0.1', 9501, ['enable_coroutine'=>true]);
$server->addService(UserService::class);
$server->start();
```
更多配置参考https://wiki.swoole.com/#/server/setting


## 自定义日志处理器
默认使用`Monolog/Logger`作为日志处理器，日志信息会输出到控制台。可根据自己需求覆盖默认处理器，只要日志类
实现`Psr\Log\LoggerInterface`即可
```php
use Swrpc\Server;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$logger = new Logger('swrpc');
$logger->pushHandler(new StreamHandler(fopen('xxxx.log','w+'), Logger::DEBUG));

$server = new Server('127.0.0.1', 9501, ['enable_coroutine'=>true]);
$server->addService(UserService::class);
$server->addLogger($logger); //覆盖默认日志处理器
$server->start();
```

## 包协议和序列化方式
默认使用固定头+包体来解决tcp粘包问题，默认配置为`'package_length_type' => 'N'`,`'package_body_offset' => 4`
默认序列化数据会使用`serialize()`，如果swoole版本在4.5以上的自动使用swoole_substr_unserialize()，可以实现的类来覆盖默认配置，只要实现``src/Packer/PackerInterface.php
即可，注意服务端和客户端需要使用一样的协议，否则解析不了。

```php
use Swrpc\Server;

$packer = new \Swrpc\Packer\SerializeEofPacker();
$server = new Server('127.0.0.1', 9501, ['enable_coroutine'=>true]);
$server->addService(UserService::class); 
$server->addPacker($packer); //覆盖默认值
```

## 中间件
```php
//匿名函数
$middleware = function (Request $request, Closure $next) {
    return $next($request);
};
//类
class CoustomMiddleware implements MiddlewareInterface
{
    function handle(\Swrpc\Request $request, Closure $next): \Swrpc\Response
    {
        return $next($request);
    }
}
```
中间件可以使用匿名函数或实现`\Swrpc\Middleware\MiddlewareInterface`接口的类，以统计耗时为例：
```php
$middleware = function (\Swrpc\Request $request, Closure $next) {
    $start = microtime(true); //前置中间件
    $result = $next($request);
    echo '耗时：'.(microtime(true) - $start).PHP_EOL; //后置中间件
    return $result;
};
```
如果要提前中止中间件，可以提前在匿名函数或类方法中返回`\Swrpc\Response`对象，如下
```php
$middleware = function (\Swrpc\Request $request, Closure $next) {
    if (empty($request->getParams())) {
        return \Swrpc\Response::error('参数不能为空'); //必须是Response类型
    }   
    return $next($request); 
};
```

## 客户端
```php
use Swrpc\Client;
use Swrpc\Request;

include "./vendor/autoload.php";

$client = new Client('127.0.0.1',9501);
try {
    $result1 = $client->send(Request::create('UserService_getName', ['value' => '00000']));
    $result2 = $client->send(Request::create('UserService_getXX', ['value' => '00000']));
} catch (\Swrpc\Exceptions\RpcException $e) {
    echo $e->getMessage() . PHP_EOL;
    exit;
}

var_dump($result1,$result2);
$client->close();
```

```php
use Swrpc\Middlewares\MiddlewareInterface;
use Swrpc\Register\Consul;
use Swrpc\Request;
use Swrpc\Server;

include "./vendor/autoload.php";

class ReqMiddlewareInterface implements MiddlewareInterface
{
    function handle(\Swrpc\Request $request, Closure $next): \Swrpc\Response
    {
        return $next($request);
    }
}

$func1 = function (Request $request, Closure $next) {
    return $next($request);
};

$func2 = function (Request $request, Closure $next) {
    return $next($request);
};

$server = new Server('10.8.8.158', 9501, ['enable_coroutine' => true]);
$server->addRegister(new Consul(['weights' => ['Passing' => 10, 'Warning' => 1]]));
$server->addMiddleware($func1, $func2);
$server->addService(\Swrpc\Example\UserService::class);
$server->addService(\Swrpc\Example\SchoolService::class);
$server->addService(\Swrpc\Example\ClassService::class);
$server->start();
```