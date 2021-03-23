## 简介
swrpc是一个基于swoole开发的高性能rpc包，swrpc提供了注册发现，链路追踪，中间件等等功能，可以很容易集成到第三方框架，如laravel，yii等等。



## 功能

- 支持多进程模式或协程模式
- 支持同步，异步调用
- 支持自定义中间件
- 支持服务端按类提供对外服务
- 支持链路追踪
- 支持注册服务发现
- 支持客户端负载均衡，包含随机，权重两种模式
- 支持自定义日志，包协议，序列化方式等等



## 安装

```bash
php composer.phar require wuzhc/swprc ~1.0 -vvv
```



## 快速体验

假设我们User和School两个模块，为了获得用户学校名称，我们需要在User模块发起rpc请求调用School模块的服务。

### School模块

```php
<?php
use Swrpc\LogicService;
class SchoolService extends LogicService 
{
    public function getUserSchool($userID) {
        $name = $userID == 123 ? '火星' : '水星';
        return $name.'学校';
    }    
}
```

School模块将作为rpc服务端，对外提供服务，启动如下：

```php
<?php
namespace SwrpcTests;
use Swrpc\Server;

$basePath = dirname(dirname(__FILE__));
require_once $basePath . "/vendor/autoload.php";

$options = [
    'enable_coroutine' => true,
    'pid_file'         => __DIR__ . '/swrpc.pid',
];
$server = new Server('School_Module', '127.0.0.1', 9501, 1, $options);
$server->addService(\SwrpcTests\services\SchoolService::class); 
$server->start();
```

将SchoolService添加到server，server会自动检索类中所有可用的public方法。

![1615562843265](https://segmentfault.com/img/bVcPwbR)



### User模块

User模块作为客户端，调用School模块服务如下

```php
<?php
namespace SwrpcTests;
use Swrpc\Request;
use Swrpc\LogicService;
use Swrpc\Client;

class UserService extends LogicService
{
    public function getUserSchoolName()
    {
        $userID = 123;
        $module = 'School_Module'; //请求目标模块名称，需要和服务端定义的一致
        $client = Client::create($module, '127.0.0.1', 9501);
        return $client->send(Request::create('\SwrpcTests\services\SchoolService_getUserSchool', [$userID]));
    }
}

//调用
echo UserService::factory()->getUserSchoolName();
```
注意：
- Request.method 为服务类命名 + 下划线 + 方法名，例如上面的`\SwrpcTests\services\SchoolService_getUserSchool`，如果服务类有命名空间，记得一定要带上命名空间



## 多进程和协程模式

多进程或协程模式需要和swoole配置一致，具体参考swoole配置

### 多进程模式

创建10进程来处理请求

```php
$options = [
    'worker_num'       => 10
    'pid_file'         => __DIR__ . '/swrpc.pid',
];
$server = new Server('School_Module', '127.0.0.1', 9501, 1, $options);
```

### 协程模式

目前swrpc协程模式是运行在单进程的

```php
$options = [
    'enable_coroutine' => true,
    'pid_file'         => __DIR__ . '/swrpc.pid',
];
$server = new Server('School_Module', '127.0.0.1', 9501, 1, $options);
```



## 同步调用和异步调用

在客户端发起同步调用，客户端会一直等待服务端返回结果

```php
$client = \Swrpc\Client::create($module, '127.0.0.1', 9501);
return $client->send(SyncRequest::create('SchoolService_getUserSchool', [$userID]));
```

在客户端发起异步调用，客户端会立马得到响应结果，请求将被swoole的task进程处理

```php
$client = \Swrpc\Client::create($module, '127.0.0.1', 9501);
return $client->send(AsyncRequest::create('SchoolService_getUserSchool', [$userID]));
```



## 自定义中间件

中间件允许程序可以对请求进行前置操作和后置操作，底层使用了责任链设计模式，所以为了执行下一个中间件，必须返回`$next($request)`，如果想提前返回，则返回结果必须是`Swrpc\Response`类型

```php
//中间件除了用匿名函数定义，还可以用实现Swrpc\Middlewares\MiddlewareInterface接口的类
$middleware = function (\Swrpc\Request $request, Closure $next) {
    $start = microtime(true); //前置操作，记录请求开始时间
    $result = $next($request);
    echo '耗时：'.(microtime(true) - $start).PHP_EOL; //后置操作，记录请求结束时间，从而计算请求耗时
    return $result; //继续下个中间件的处理
};
$server = new Server('School_Module', '127.0.0.1', 9501, 1, $options);
$server->addService(SchoolService::class); 
$server->addMiddleware($middleware); //添加中间件
$server->start();
```
如果要提前中止中间件，可以提前在匿名函数或类方法中返回\Swrpc\Response对象，如下
```php
$middleware = function (\Swrpc\Request $request, Closure $next) {
    if (empty($request->getParams())) {
        return \Swrpc\Response::error('参数不能为空'); //提前返回，必须是Response类型
    }   
    return $next($request); 
};
```



## 服务端按类提供对外服务

从上面的例子中，我们把SchoolService整个类添加的server中，这样server就能对外提供SchoolService类所有public方法的功能。

```php
$server = new Server('School_Module', '127.0.0.1', 9501, 1, $options);
$server->addService(SchoolService::class); //提供SchoolService所有public方法功能
$server->addService(AreaService::class); //提供AreaService所有public方法功能
$server->start();
```
客户端使用参考上面的快速体验



## 注册服务发现

如果服务端启动的时候有设置注册中心，则启动成功会自动向注册中心注册服务端地址。目前swrpc提供了`Consul`作为注册中心，使用如下

```php
$server = new Server('School_Module', '127.0.0.1', 9501, 1, $options);
$server->addRegister(new Consul());
$server->addService(SchoolService::class); 
$server->start();
```

如上，使用Consul作为服务的注册中心，通过`http://127.0.0.1:8500`可以查看注册信息，如果想用etcd等其他注册中心，只要实现`Swrpc\Middlewares\RegisterInterface`接口即可，然后在通过`$server->addRegister()`添加到server

![1615562878292](https://segmentfault.com/img/bVcPwbR)

![1615562927956](https://segmentfault.com/img/bVcPwb4)

![1615562975815](https://segmentfault.com/img/bVcPwb5)



## 客户端负载均衡

如果服务端启动多个节点，例如School模块启动3个节点，并且注册到了注册中心，那么我们可以从注册中心获取所有服务端节点信息，然后做一些策略处理。

```php
$register = new Consul();
$client = \Swrpc\Client::createBalancer('School_Module', $register, \Swrpc\Client::STRATEGY_WEIGHT);
$result = $client->send(Request::create('SchoolService_getUserSchool', [$userID]);
```

目前swrpc提供两种简单策略模式，`\Swrpc\Client::STRATEGY_WEIGHT权重模式`，`\Swrpc\Client::STRATEGY_RANDOM`随机模式



## 链路追踪

当我们的服务非常多并且需要互相调用时候，如果其中某个调用失败，会导致我们得不到我们想要的结果，而要调试出是哪个环节出了问题也比较麻烦，可能你需要登录每台机器看下有没有错误日志，或者看返回的错误信息是哪个服务提供的。链路追踪记录了整个调用链过程，如果某个环节出错，我们可以快速从调用链得到调用中断地方。

```php
class UserService extends LogicService
{
    public function getUserSchoolName()
    {
        $userID = 123;
        $module = 'School_Module'; //请求目标模块名称，需要和服务端定义的一致
        $client = Client::create($module, '127.0.0.1', 9501);
        return $client->send(Request::create('SchoolService_getUserSchool', [$userID], $this->getTracerContext(__FUNCTION__))); //getTracerContext()用于提供追踪上下文
    }
}

$users = UserService::factory()
    ->setModule('User_Module') //当前模块，用于调用链的起点
    ->setTracerUrl('http://127.0.0.1:9411/api/v2/spans') //zipkin链路追踪地址
    ->getUserSchoolName();
```

![1615563070157](https://segmentfault.com/img/bVcPwb6)

如图，User_Module调用Class_Module，Class_Module又去调用School_Module

![1615563170709](https://segmentfault.com/img/bVcPwb8)

每个调用还记录响应结果




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



## 序列化方式

默认使用固定头+包体来解决**tcp粘包**问题，默认配置为`'package_length_type' => 'N'`,`'package_body_offset' => 4`
默认序列化数据会使用`serialize()`，如果swoole版本在4.5以上的自动使用swoole_substr_unserialize()，可以实现的类来覆盖默认配置，只要实现`src/Packer/PackerInterface`即可，注意服务端和客户端需要使用一样的协议，否则解析不了。

```php
use Swrpc\Server;

$packer = new \Swrpc\Packer\SerializeLengthPacker();
$server = new Server('127.0.0.1', 9501, ['enable_coroutine'=>true]);
$server->addService(UserService::class); 
$server->addPacker($packer); //覆盖默认值
```



## 安全证书配置

参考：<https://wiki.swoole.com/#/server/setting?id=ssl_cert_file>

### 服务端

```php
$options = [
    'ssl_cert_file' => __DIR__.'/config/ssl.crt',
    'ssl_key_file'  => __DIR__.'/config/ssl.key',
    'pid_file'      => __DIR__ . '/swrpc.pid',
];
$server = new Server('School_Module', '127.0.0.1', 9501, $options, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
$server->addService(SchoolService::class); 
$server->start();
```

注意：

- 文件必须为 `PEM` 格式，不支持 `DER` 格式，可使用 `openssl` 工具进行转换



## 测试

使用phpuint实现的简单测试案例，配置文件`phpunit.xml`，根据你的服务器配置ip地址

```bash
php phpunit.phar tests --debug
```

![1615602809212](https://segmentfault.com/img/bVcPwcb)

### phpunit 测试报告

```
Client (SwrpcTests\Client)
 [x] Client connect
 [x] Client sync request
 [x] Client async request

Packer (SwrpcTests\Packer)
 [x] Serialize length pack
 [x] Serialize lenght unpack
 [x] Serialize eof pack
 [x] Serialize eof unpack

Server (SwrpcTests\Server)
 [x] Server register to consul
 [x] Server unregister from consul
 [x] Server add service
 [x] Server add middleware
```