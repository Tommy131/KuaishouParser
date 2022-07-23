# KuaishouParser
快手短视频批量下载脚本

此项目采用 `OwOFrame` 进行的后端构建, 需要配套使用. 项目地址: [点我](https://github.com/Tommy131/OwOFrame)<br/>
本项目仅用于学习交流等途径, 严禁贩卖此源码! 个人/组织闭源申请请提交 `Issue` 以进行申请!<br/>

## 实现目标

- [x] 支持解析单个作者的全部作品
- [x] 支持解析分享的视频链接(尽管作品被作者设为不允许下载)
- [x] 后台日志显示最近的解析记录
- [ ] 定时解析指定作者的作品
- [x] 记录解析作品的详情（标题，发布时间，赞数等）
- [ ] 敬请期待......给个Star鼓励我开发吧!!! 拜托了!!! 这个项目真的花了我好多时间抓接口和优化!!!

## 怎么使用?
1. 先克隆项目[OwOFrame](https://github.com/Tommy131/OwOFrame)到一个文件目录下.
2. 安装好后端框架 `OwOFrame` 之后, 将此项目克隆到 `application` 项目内.
3. 重命名 `KuaishouParser` 为 `kuai`.
4. 打开快手官网并登录你的账号.
5. 搜索你想保存的作者名称, 点击加载完成页面后打开浏览器控制台 (F12).

### 在OwO-CLI中使用指令获取
- 在控制台中输入 `document.cookie` 之后, 将其长串Cookie复制, 并且粘贴到 `KuaiApp.php` 中的 `getCookie()` 方法的 `return '(cookie: string)'` 内.
- 在 `CMD` 或 `任意终端` 中输入指令 `php owo kuai [作者ID]` 进行资源地址批量下载.
- 下载的视频目前皆为无水印视频.

可携带的参数: php owo kuai [authorId: string] (--autoDownload)<br/>
- authorId:     作者ID (不同平台的ID不同, 域名开头为 `www` 的ID为数字英文字母混合; 开头为`live` 的ID为用户自定义或默认的ID)
- autoDownload: 自动下载获取到的作品 (参数同上)

<br/>解析分享ID使用的命令: `php owo kuai shareId [id:string]`<br/>
此处的 `id` 为 `https://v.kuaishou.com/sharId` 中的 `shareId`.


## 特性
- [x] 支持保存客户端中无法下载的视频/照片
- [x] 下载的照片目前皆无水印 (也许后期官方会修复这个BUG)
- [x] 下载的视频目前为原始上传视频 (也就是说没有后面的 `快手, 记录美好生活`)


### 截图展示 (后端指令实现)
CLI数据抓取<br/>
![CLI数据抓取](static/img/cli-command.png)

<br/>

## Statement
&copy; 2016-2022 [`OwOBlog-DGMT`](https://www.owoblog.com). Please comply with the open source license of this project for modification, derivative or commercial use of this project.

> My Contacts:
- Website: [`HanskiJay`](https://www.owoblog.com)
- Telegram: [`HanskiJay`](https://t.me/HanskiJay)
- E-Mail: [`HanskiJay`](mailto:support@owoblog.com)
