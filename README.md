# Simple-Swoole-Framework
基于swoole引擎的PHP框架，结构清晰，部署简单，使用方便。可以灵活应对HTTP/Websocket服务，另有定时器、异步任务等。

-----------
## 简单部署
<pre><code>git clone git@github.com:ljj7410/Simple-Swoole-Framework.git</code></pre>
<pre><code>php index.php start //开启应用，第一次开启将会自动生成[apps]应用目录
php index.php stop //关闭应用
php index.php reload //热更新
php index.php restart //重启
php index.php status //查看进程状态</code></pre>

----------
## 应用目录结构
<pre>
|- apps
|- |- common  --公共目录
|- |- |- config  --配置目录
|- |- |- |- config.ini.php  --配置文件
|- |- |- |- database.ini.php  --数据库配置文件
|- |- |- tpl  --公共模板目录
|- |- |- |- error.tpl.php  --错误提示模板文件
|- |- |- |- success.tpl.php  --成功提示模板文件
|- |- |- util  --第三方类库目录
|- |- home  --默认模块目录
|- |- |- controller  --控制器目录
|- |- |- |- index.class.php  --默认控制器文件
|- |- |- model  --模块目录
|- |- |- view  --视图目录
|- |- |- |- index  --默认视图目录
|- |- |- |- |- index.tpl.php  --默认视图模板文件
|- |- |- websocket  --websocket目录
|- |- |- |- index.class.php  --默认websocket文件
|- |- runtime  --运行时产生的文件目录
|- |- |- log  --日志目录
|- |- |- tmp  --临时文件目录
</pre>

