
/*********************************************************************
	 _____   _          __  _____   _____   _       _____   _____
	/  _  \ | |        / / /  _  \ |  _  \ | |     /  _  \ /  ___|
	| | | | | |  __   / /  | | | | | |_| | | |     | | | | | |
	| | | | | | /  | / /   | | | | |  _  { | |     | | | | | |  _
	| |_| | | |/   |/ /    | |_| | | |_| | | |___  | |_| | | |_| |
	\_____/ |___/|___/     \_____/ |_____/ |_____| \_____/ \_____/

	* Copyright (c) 2015-2021 OwOBlog-DGMT.
	* Developer: HanskiJay(Tommy131)
	* Telegram:  https://t.me/HanskiJay
	* E-Mail:    support@owoblog.com
	* GitHub:    https://github.com/Tommy131

**********************************************************************/

/**
 * ~ OwOTools.js
 * @see https://github.com/Tommy131/OwOTools
 */
const logger = {
  settings: {
    useDate: true,
    useTime: false,
    bindTag: '-',
    style: {
      colorFormat(primaryColor = 'white', secondColor = 'black', weight = '200') {
        return 'background-color: ' + primaryColor + '; color: ' + secondColor + '; font-weight: ' + weight + '; ';
      },
      basePadding: 'padding: 2px 3px; ',
      level: '',
      message: '',
      time: ['#424242', 'white', 200]
    },
  },
  date() {
    const fillZero = (number) => {
      return (number > 0 && number <= 9) ? '0' + number : number;
    };
    let date = new Date();
    let year = date.getFullYear();
    let month = date.getMonth();
    let day = date.getDate();
    let time = '';
    if (this.settings.useTime == true) {
      time = ' ' + fillZero(date.getHours()) + ':' + fillZero(date.getMinutes()) + ':' + fillZero(date.getSeconds());
    }
    return year + this.settings.bindTag + fillZero(month) + this.settings.bindTag + fillZero(day) + time;
  },
  format(level = 'info') {
    let colorFormat = {};
    // 颜色编辑区域;
    colorFormat.info = {
      prefix: ['#607D8B', 'white', 'bold'],
      text: ['white']
    };
    colorFormat.notice = {
      prefix: ['#15AC', 'white', 'bold'],
      text: ['white']
    };
    colorFormat.success = {
      prefix: ['#228329', 'white', 'bold'],
      text: ['#006057', 'white']
    };
    colorFormat.warning = {
      prefix: ['#ff7c55', 'white', 'bold'],
      text: ['#FFDC2F', 'black', '300']
    };
    colorFormat.alert = {
      prefix: ['#970000', 'white', 'bold'],
      text: ['#FFDC2F', 'black', '300']
    };
    colorFormat.error = {
      prefix: ['#830000', 'white', 'bold'],
      text: ['#560000', 'white', '300']
    };
    colorFormat.emergency = {
      prefix: ['#830000', 'white', 'bold'],
      text: ['#560000', 'white', '300']
    };
    // 输出区域;
    if (colorFormat[level] === undefined) {
      level = 'info';
    }
    let result = [
      this.settings.style.basePadding + this.settings.style.colorFormat(...colorFormat[level].prefix) + this.settings.style.level,
      this.settings.style.basePadding + this.settings.style.colorFormat(...colorFormat[level].text) + this.settings.style.message
    ];
    if (this.settings.useDate == true) {
      result.unshift(this.settings.style.basePadding + this.settings.style.colorFormat(...this.settings.style.time));
    }
    result[0] += '; border-radius: 3px 0 0 3px;';
    result[result.length - 1] += '; border-radius: 0 3px 3px 0';
    return result;
  },
  getDateFormat() {
    if (this.settings.useDate == true) {
      return '%c ' + this.date(this.settings.useTime, this.settings.bindTag) + ' ';
    }
    return '';
  },
  send(message, level = 'info', boom = false) {
    level = level.toLowerCase();
    let consoleOutPutType;
    switch (level) {
      default:
      case 'info':
      case 'notice':
      case 'success':
      case 'alert':
      case 'error':
        consoleOutPutType = 'log';
        break;
      case 'warning':
        consoleOutPutType = 'warn';
        break;
      case 'emergency':
        consoleOutPutType = 'error';
        break;
    }
    console[consoleOutPutType](this.getDateFormat() + '%c ' + level.toUpperCase() + ' %c ' + message + ' ', ...this.format(level));
    (boom == true) ? alert(message) : '';
  },
  info(message, boom = false) {
    this.send(message, 'info', boom);
  },
  notice(message, boom = false) {
    this.send(message, 'notice', boom);
  },
  success(message, boom = false) {
    this.send(message, 'success', boom);
  },
  warning(message, boom = false) {
    this.send(message, 'warning', boom);
  },
  alert(message, boom = false) {
    this.send(message, 'alert', boom);
  },
  error(message, boom = false) {
    this.send(message, 'error', boom);
  },
  emergency(message, boom = false) {
    this.send(message, 'emergency', boom);
  },
};

const owo = {
  splitURL(url, layer) {
    let splitted = url.split('/').filter((e) => { return e; });
    if (typeof layer === 'number') {
      return splitted[layer] ?? undefined;
    }
    switch (layer.toLowerCase()) {
      case 'first':
        return splitted.shift();
      case 'end':
        return splitted.pop();
    }
  },
  path(layer = 'end') {
    return this.splitURL(location.pathname, layer);
  },
  sleep(time) {
    // 需要配合 async/await 使用;
    return new Promise((resolve) => setTimeout(resolve, time * 1000));
  },
  highlight: {
    style: 'border: 10px solid red; box-shadow: 0 0 5px 5px #a96464',
    selector: null,
    select(element) {
      let selector;
      if (typeof element === 'string') {
        selector = document.querySelector(element);
      } else if (typeof element === 'object') {
        selector = element;
      } else {
        logger.error('Element \'' + element + '\' is undefined!');
        return;
      }
      this.selector = selector;
      return this;
    },
    shoot(func) {
      if (this.selector === null) {
        logger.error('Element is empty, cannot use Highlight function.');
        return;
      }
      if (typeof func === 'function') func(this);
      this.selector.style = this.style;
      logger.info('Highlighted class \'' + this.selector.className + '\'.');
    },
    remove(func) {
      if (this.selector === null) {
        logger.error('Element is empty, cannot use Highlight function.');
        return;
      }
      if (typeof func === 'function') func(this);
      this.selector.style = '';
      logger.info('Removed highlight from \'' + this.selector.className + '\'.');
    }
  }
};




/**
 * KuaishouParser.js
 */
/**
 * ~定义基本信息模型
 */
const userId = owo.path();
const initData = {
  scriptVersion: 'v1.0.0',
  userId: userId,
  timeout: 5,
  filterList: [],
  isInitialized: false,
  isRunning: null,
};

const kuai = {
  storage: {
    errorList: [],
    succeedList: {}
  },
  platform: null,
  selector: null,
  reset() {
    if (this.platform === 0) {
      let div = document.querySelector('.photo-preview');
      if (div !== null) div.style = '';
    }
    localStorage.removeItem('successList');
    localStorage.removeItem('errorList');
    logger.info('已重置捕获状态.');
  },
  init() {
    logger.settings.useTime = true;
    logger.info('-------------------------');
    logger.info('快手作品解析脚本 ' + initData.scriptVersion + ' By HanskiJay');
    logger.info('当前作者ID: ' + initData.userId);
    logger.info('-------------------------');

    if (/^live.kuaishou.com$/.test(location.hostname)) {
      this.platform = 0;
      this.selector = document.querySelectorAll('div.work-card-thumbnail.ready');
      if (this.selector !== null) {
        initData.isInitialized = true;
      }
    }
    else if (/^www.kuaishou.com$/.test(location.hostname)) {
      this.platform = 1;
      this.selector = document.querySelectorAll('img.poster-img');
      if (this.selector !== null) {
        initData.isInitialized = true;
      }
    } else {
      this.sendErrorMessage('0x000', '不支持的解析站点.');
    }
    return initData.isInitialized;
  },
  highlightErrorList() {
    for (i of this.storage.errorList) {
      owo.highlight.select(this.selector[i]).shoot((h) => {
        kuai.setAttentionText(h.selector, 'temp' + i, 'Error-Number: ' + i);
      });
    }
  },
  query(id, articleId) {
    // ~开始解析资源地址;
    logger.info('-------------------------------------------');
    logger.info('[' + id + '] 正在尝试请求作品ID为 [' + articleId + '] 的资源地址...');
    let image = document.querySelectorAll('img.long-mode-item');
    let video = document.querySelector('video.player-video');
    let executed = false;
    this.storage.succeedList[articleId] = [];
    if (image.length > 0) {
      for (let img of image) {
        this.storage.succeedList[articleId].push(img.src);
      }
      logger.info('请求成功完成. 共计' + Object.keys(image).length + '个资源地址.');
      executed = true;
    } else if (video !== null) {
      this.storage.succeedList[articleId].push(video.src);
      logger.info('请求成功完成. 共计' + Object.keys(video).length + '个资源地址.');
      executed = true;
    } else {
      this.setErrorIn(id);
    }
    return executed;
  },
  sendData(userId) {
    post('https://main.local/kuai/', JSON.stringify({
      userId: userId,
      url: this.storage.succeedList
    }), function (success) {
      logger.success(success)
    })
  },
  async retryErrorList() {
    for (i of this.storage.errorList) {
      let selector = this.selector[i];
      selector.click();
      await owo.sleep(2);
      let articleId = owo.path();
      if (this.query(i, articleId)) {
        logger.success('[' + i + '] 作品ID ' + articleId + ' 为的资源地址已经成功保存.');
      }
    }
    logger.notice('重试成功, 正在更新请求...');
    this.sendData(initData.userId);
  },
  async start() {
    try {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
      if (!this.init()) {
        this.sendErrorMessage('0x001', '无法获取对应的元素选择器.');
        return false;
      }
      // 设置运行状态;
      initData.isRunning = true;
      // 定义上一个请求的ID;
      let lastRequestedId = null;
      // 开始日志输出;
      logger.notice('已捕获到 ' + Object.keys(this.selector).length + ' 个作品, 即将进行资源地址请求...');
      if (initData.filterList.length > 0) {
        logger.notice('检测到元素过滤器中存在值, 最终捕获的作品总数将少于当前作者的所有作品的总数.');
      }
      // ~开始循环请求;
      for (let i = 0; i < this.selector.length; i++) {
        if (initData.isRunning === false) {
          logger.warning('脚本已通过安全方法停止运行.');
          this.reset();
          return;
        }
        // ~过滤器检测;
        let prefix = '[Nr.' + i + '] ';
        if (initData.filterList.includes(i)) {
          logger.notice(prefix + '当前选择器的序号存在元素过滤器列表中, 已跳过请求.');
          continue;
        }
        let selector = this.selector[i];
        selector.click();
        this.setCurrentExecute(selector, i);
        await owo.sleep(0.5);
        // ~关闭图床预览DIV, 降低渲染时间;
        let photoPreviewDiv = document.querySelector('.photo-preview');
        if (photoPreviewDiv !== null) {
          photoPreviewDiv.style = 'display: none;';
        }
        // ~检测URL地址;
        let tmpCount = 0;
        while (owo.path() === lastRequestedId) {
          if (tmpCount >= initData.timeout) {
            this.removeCurrentExecute(selector, i);
            this.setErrorIn(i);
            this.sendErrorMessage('0x010', '当前选择器的资源请求已经超时. 目前捕获的列表已存在与当前选择器相同的ID. 已将当前选择器高亮并列队至错误列表中.');
            break;
          }
          await owo.sleep(1);
          tmpCount++;
        }
        // 设置当前作品ID;
        let articleId = owo.path();
        let check = this.storage.succeedList[articleId];
        let executed = false;
        if ((check !== undefined) && (check !== null)) {
          logger.notice(prefix + '当前选择器的序号已经被保存过一次了, 已跳过本次请求.');
          executed = true;
        } else {
          executed = this.query(i, articleId);
        }
        if (executed === true) {
          this.removeCurrentExecute(selector, i);
          let temp = document.getElementById('temp' + i);
          if (temp !== null) {
            temp.parentNode.removeChild(temp);
            owo.highlight.select(selector).remove();
          }
          lastRequestedId = articleId;
        }
      }
      logger.info('-------------------------------------------');
      this.reset();
      localStorage.setItem('errorList', JSON.stringify(this.storage.errorList));
      localStorage.setItem('succeedList', JSON.stringify(this.storage.succeedList));
      this.sendData(initData.userId);
      logger.success('已捕获' + Object.keys(this.storage.succeedList).length + '个作品.');
      let close = document.querySelector('div.close');
      if (close !== null) {
        close.click();
      }
    } catch (e) {
      kuai.stop();
      kuai.reset();
    }
  },
  setCurrentExecute(selector, number) {
    if ((selector !== null) && (selector !== undefined)) {
      this.setAttentionText(selector, 'currentNr-' + number, '[Nr.' + number + '] 正在请求数据...');
      let documentScroll = document.documentElement.scrollTop;
      let containerWidth = document.querySelector('ul.feed-list').offsetWidth;
      let containerHeight = document.querySelector('ul.feed-list').offsetHeight;
      let itemBoxWidth = document.querySelector('li.feed-list-item').offsetWidth;
      let itemBoxHeight = document.querySelector('li.feed-list-item').offsetHeight;
      let countInColumn = Math.floor(containerWidth / itemBoxWidth);
      let countInRow = Math.floor(containerHeight / itemBoxHeight) - 1;
      let totalCount = countInColumn * countInRow;
      if ((number + 1) % countInColumn === 0) {
        window.scrollTo({
          top: documentScroll + itemBoxWidth,
          behavior: 'smooth'
        });
      }
    }
  },
  removeCurrentExecute(selector, number) {
    if ((selector !== null) && (selector !== undefined)) {
      let temp = document.getElementById('currentNr-' + number);
      if (temp !== null) {
        temp.parentNode.removeChild(temp);
      }
    }
  },
  setErrorIn(number, message = '网络请求超时.') {
    this.storage.errorList.push(number);
    owo.highlight.select(this.selector[number]).shoot((h) => {
      kuai.setAttentionText(h.selector, 'temp' + number, 'Error-Number: ' + number);
    });
    this.sendErrorMessage('UnknownCode', '捕获异常: ' + message);
  },
  sendErrorMessage(code, message) {
    let codePrefix = '[' + code + '] ';
    if (/0x00/.test(code)) {
      message = codePrefix + '脚本初始化异常: ' + message;
      logger.emergency(message);
    }
    else if (/0x01/.test(code)) {
      message = codePrefix + '脚本运行过程中异常: ' + message;
      logger.emergency(message);
    } else {
      message = codePrefix + '' + message;
      logger.error(message);
    }
    return message;
  },
  setAttentionText(selector, id, text) {
    if ((selector !== null) && (selector !== undefined)) {
      let html = selector.innerHTML;
      selector.innerHTML = '<div id="' + id + '" style="position: absolute; z-index: 999; padding: 10px; font-size: 16px; background-color: rgba(255, 255, 255, 0.8); color: orangered">' + text + '</div>' + html;
    }
  },
  isRunning() {
    return initData.isRunning;
  },
  stop() {
    initData.isRunning = false;
  }
}


// ~网上找的HTTP-POST请求方法;
function post(url, data, success, contentType = 'application/json; charset=UTF-8') {
  let xmlhttp = null;
  if (window.XMLHttpRequest) {
    xmlhttp = new XMLHttpRequest();
  }
  xmlhttp.open("POST", url, true);
  xmlhttp.setRequestHeader("Content-type", contentType); //www-form-urlencoded
  xmlhttp.timeout = 4000;
  xmlhttp.onreadystatechange = function () {
    if (xmlhttp.readyState == 4) {
      if (xmlhttp.status == 504) {
        logger.info("服务器请求超时..");
        error();
        xmlhttp.abort();
      } else if (xmlhttp.status == 200) {
        success(xmlhttp.responseText);
      }
      xmlhttp = null;
    }
  };
  xmlhttp.ontimeout = function () {
    logger.info("客户端请求超时..");
    error();
  };
  xmlhttp.send(data); //JSON.stringify({name:"张三"})
  /**
   *访问超时后处理
   */
  function error() {
    let body = document.querySelector("body");
    body.innerHTML = "";
    let errorHTML = document.createElement("div");
    errorHTML.innerHTML = "连接超时";
    body.appendChild(errorHTML);
    let refreshHtml = document.createElement("div");
    refreshHtml.innerHTML = "刷新";
    refreshHtml.id = "fresh";
    body.appendChild(refreshHtml);
    refreshHtml.addEventListener(
      "click",
      function (e) {
        window.location.reload();
      },
      false
    );
  }
}
kuai.start();