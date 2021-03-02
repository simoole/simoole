# Simple-Swoole-Framework
基于swoole引擎的多进程&协程&常驻内存式PHP框架，结构清晰，部署简单，使用方便。可以灵活应对HTTP/Websocket服务，内置子进程通信，可以灵活处理各类复杂业务。

-----------
## 简单部署
<pre><code>git clone https://github.com/ljj7410/Simple-Swoole-Framework.git
cd Simple-Swoole-Framework
docker build -t ssf .
docker run --name myssf -p 9200:9200 -d ssf</code></pre>
### 即刻访问
<pre><code>curl http://127.0.0.1:9200</code></pre>
## CLI命令
<pre><code>php index.php start //开启实例
php index.php stop //关闭实例
php index.php reload //热更新（重启worker进程，公共内存无影响）
php index.php restart //重启实例</code></pre>

----------
## 目录结构
<pre>
|- app  --应用目录
|- |- common  --公共目录
|- |- controller  --默认模块目录
|- |- |- index.class.php  --默认控制器文件
|- |- model  --模块目录
|- |- websocket  --websocket目录
|- |- |- index.class.php  --默认websocket文件
|- log  --日志目录
|- tmp  --临时文件目录
|- config  --配置文件目录
|- |- system  --系统配置目录
|- |- |- server.ini.php  --实例综合配置
|- |- |- database.ini.php  --数据库配置
|- |- |- map.ini.php  --加载模式配置
|- |- |- mtable.ini.php  --内存表配置
|- |- |- process.ini.php  --子进程配置
|- |- |- redis.ini.php  --REDIS配置
|- |- extend  --扩展配置目录
|- |- |- config.ini.php  --用户自定义配置
|- |- route.ini.php  --路由配置
|- core  --框架核心类库目录
|- Dockerfile  --用于快速生成docker镜像
|- index.php  --CLI启动文件
</pre>

----------
### 作者
李俊杰（Dean.Lee），毕业于解放军信息工程大学
> 邮箱：dean7410@163.com

### 声明
SSF由作者独立研发，版权归属个人，与任何组织无关。未经作者授权，谢绝任何人或组织对本开源程序进行篡改转载。

----------
## 函数说明

SSF为开发者提供了许多简单便捷的全局函数。开发者也可以自行添加自定义函数库，函数库文件需以[.fun.php]结尾命名，系统则会自动检测并加载函数库中函数到常驻内存中，如tool.fun.php。
- 如果配置了 MAP_TYPE 为 2，系统是不会自动加载该函数库文件的，需手动配置待加载项。

### 1. M(string $tableName, [string $dbConfName]) 数据模型函数
- $tableName 去掉前缀配置的数据表名称
- $dbConfName 数据库配置的键名，默认DB_CONF
- 返回Model实例
```php
//...[示例:带分页的用户列表]
    public function getUserList()
    {
        //从GET方式中获取分页页码，如果没有传递则默认赋值第一页
        $page = I('get.page', 1);
        //设置每页显示行数
        $pagecount = 10;
        //获取会话用户的权限ID
        $auth_id = session('user.auth');
        //从ucenter配置库的users表中查询数据
        $rs = M('users U', 'ucenter')
            //选择users表中需要输出的字段  
            ->field(['id', 'username', 'sex'], 'U')
            //选择pictures表中将要输出的字段，并为path设置输出别名headpic
            ->field(['path' => 'headpic'], 'P')
            //左联方式关联ucenter配置库中的pictures表
            ->join('pictures P', ['U.pic_id' => 'P.id'], 'left')
            //查询筛选
            ->where(['U.is_exist' => 1, 'U.type' => ['in', [1,2,3]]])
            //可以多次调用查询筛选，最终会以and方式自动合并
            ->where(['U.auth' => [['like', $auth_id . ',%'], ['like', '%,' . $auth_id], ['like', '%,'. $auth_id .',%'], 'or']])
            //以users表中的id字段的倒序排列
            ->order('U.id', 'desc')
            //分页
            ->limit(($page-1)*$pagecount, $pagecount)
            //最终查询
            ->select();
        //将数据输出到页面的$userlist变量中
        $this->jsonReturn(['userlist' => $rs]);
    }
//...
```

### 2. D(string $tableName, [string $dbConfName]) 自定义数据模型函数
- $tableName 自定义模型名称或去掉前缀配置的数据表名称
- $dbConfName 数据库配置的键名，默认DB_CONF
- 返回Model实例
> 与M()最大的区别是可以加载自定义数据模型，目前支持的数据模型底层除原生Model外还有ViewModel和RelationModel(试验版)两种。
```php
//示例：用户数据模型[app/model/city.class.php]
<?php
namespace App\Model;
use Core\Base\Model;

class SiteModel extends Model
{
    private $provinces = [];
    private $citys = [];
    private $areas = [];

    public function __construct(string $dbname = null)
    {
        parent::__construct($dbname);
        $this->provinces = $this->table('SiteProvince')->select();
        $this->citys = $this->table('SiteCity')->select();
        $this->areas = $this->table('SiteArea')->select();
    }

    /**
     * 获取省份列表
     * @return array
     */
    public function getProvinceList() : array
    {
        return $this->provinces;
    }

    /**
     * 获取市列表
     * @param int $province_id
     * @return array
     */
    public function getCityList(int $province_id) : array
    {
        $data = [];
        foreach ($this->citys as $city){
            if($city['province_id'] == $province_id)$data[] = $city;
        }
        return $data;
    }

    /**
     * 获取区列表
     * @param int $city_id
     * @return array
     */
    public function getAreaList(int $city_id) : array
    {
        $data = [];
        foreach ($this->areas as $area){
            if($area['city_id'] == $city_id)$data[] = $area;
        }
        return $data;
    }
}

//在实例中的使用方式
//...
    $res = D('Site')->getAreaList(11);
//...
```

### 3. I(string $name, [$default = false]) 通用输入函数
- $name 要采集的输入索引
- $default 如果不存在则赋予的默认值
- 反馈采集到的输入数据

允许采集8种输入数据：

| 索 引 | 注 解 |
| :---: | :-----------------------------: |
| get.* | 采集以GET方式传递的数据，相当于 $_GET |
| post.* | 采集以POST方式传递的数据，相当于 $_POST |
| cookie.* | 采集以COOKIE形式传递的数据，相当于 $_COOKIE |
| server.* | 采集服务器信息和请求的头部数据，相当于 $_SERVER |
| files.* | 采集以FILES传递的数据，相当于 $_FILES |
| header.* | 采集请求的头部数据 |
| request.* | 采集以GET/POST方式传递的数据，相当于 $_REQUEST |
| input | 采集原始的POST包体，用于非application/x-www-form-urlencoded格式的Http POST请求。 |
```php
//示例
$username = I('post.username', 'admin');
$password = I('post.password');
$type = I('get.type', 1);
$sessid = I('cookie.phpsessid'); //请求的键名是忽略大小写的
$files = I('files.'); //以名值对形式返回所有$_FILES
$ip = I('server.remote_addr', '127.0.0.1');
```

### 4. C(string $key, [$val = null]) 获取配置数据
- $key 配置索引键名
- $val 为某键赋值
- 返回获取到的配置数据
```php
//示例`注意大小写`
$sess_count = C('MEMORY_TABLE.__SESSION.__total');
```

### 5. session(string $key, [$val = [NULL]]) SESSION处理函数
- $key string-session键名|[START]-开启session|[ID]-获取sessionid|[HAS]-判断KEY值是否存在|[CLEAR]-清空session
- $val [NULL]-获取session值|string-赋予字符串值|array-赋予数组值
- 返回对应的session数据|null-删除对应session
> session()只能用于会话期，会话结束session()将失效，谨慎使用$_SESSION全局变量，多协程下此变量取值会异常
```php
//JWT示例 配置为手动开启session，默认是自动开启
$token = I('get.token');
//判断token是否有效
if (!empty($token)) {
    if (!session('[HAS]', $token)) {
        $this->error('TOKEN不存在!!!!');
        return false;
    }
    //使用token开启session
    session('[START]', $token);
} else {
    $this->getToken();
    return false;
}
//判断是否登录
if(session('?user'))$user = session('user');
```

### 6. cookie(string $key, string $val = '[NULL]', int $expire = null) COOKIE处理函数
- $key string-cookie键名
- $val [NULL]-获取cookie值|string-赋予字符串值|array-赋予数组值
- $expire int-过期时间|null-默认会话期
- 返回对应的cookie数据|null-删除对应cookie
> session()只能用于会话期，会话结束session()将失效，谨慎使用$_SESSION全局变量，多协程下此变量取值会异常
```php
//示例自动登录失败删除cookie
if(!$this->login(cookie('username'), cookie('passkey'))){
    cookie('username', null);
    cookie('passkey', null);
}
```

### 7. L($msg, $prefix = 'user', $dirname = null) 记录日志
- $msg 要记录的日志，如非字符串则自动加上var_export($msg, true)
- $prefix 日志文件前缀
- $dirname 日志文件保存的目录（日志文件统一保存在app/runtime/log/下）
```php
//示例
$class = new myClass();
L($class, 'class', 'ext');
```

-----------
更多WIKI请跳转：https://github.com/ljj7410/Simple-Swoole-Framework/wiki
