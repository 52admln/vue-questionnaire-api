## 微型问卷调查系统 API

适用于 微型问卷调查系统 的后端接口

## 数据库

导入根目录中的 `questionnaire.sql` 文件即可

数据库配置文件 `/config/database.php` ，修改相应参数即可，本代码中因使用 Docker 部署，故使用了环境变量作为 `host`，如果非 Docker 环境，可以自行更改为 `localhost` 或 `127.0.0.1`

## 伪静态

Apache 环境下需要使用到根目录中的 `.htaccess` 文件

Nginx 环境下需要在 `vhost.conf` 文件中配置伪静态规则

```
location / {
    # something ...
    # Codeigniter Nginx Rewrite
	if (!-e $request_filename) {
    	rewrite ^/index.php(.*)$ /index.php?s=$1 last;
     	rewrite ^(.*)$ /index.php?s=$1 last;
     	break;
	}
  }
``` 

## Docker 部署

- Nginx 配置： `/vhost.conf`
- nginx Dockerfile：`/web.dockerfile` 
- php Dockerfile：`/app.dockerfile` 
- docker-compose 配置：`/docker-compose.yml`

一键部署 php 7.1 + mysql 5.6 + nginx 的命令

```bash
docker-compose up -d
```

> 后端代码努力重构中...
