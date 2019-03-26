/* global wxInitUrl, wxConfigUrl */
import app from 'app';
import $ from 'jquery';
import wx from 'weixin-js-sdk';

const jsApiList = [
  'checkJsApi',
  'onMenuShareTimeline',
  'onMenuShareAppMessage',
  'onMenuShareQQ',
  'onMenuShareWeibo',
  'onMenuShareQZone',
  'hideMenuItems',
  'showMenuItems',
  'hideAllNonBaseMenuItem',
  'showAllNonBaseMenuItem',
  'translateVoice',
  'startRecord',
  'stopRecord',
  'onVoiceRecordEnd',
  'playVoice',
  'onVoicePlayEnd',
  'pauseVoice',
  'stopVoice',
  'uploadVoice',
  'downloadVoice',
  'chooseImage',
  'previewImage',
  'uploadImage',
  'downloadImage',
  'getNetworkType',
  'openLocation',
  'getLocation',
  'hideOptionMenu',
  'showOptionMenu',
  'closeWindow',
  'scanQRCode',
  'chooseWXPay',
  'openProductSpecificView',
  'addCard',
  'chooseCard',
  'openCard',
  'openWXDeviceLib',
  'closeWXDeviceLib',
  'getWXDeviceInfos',
  'sendDataToWXDevice',
  'startScanWXDevice',
  'stopScanWXDevice',
  'connectWXDevice',
  'disconnectWXDevice',
  'getWXDeviceTicket',
  'onWXDeviceBindStateChange',
  'onWXDeviceStateChange',
  'onReceiveDataFromWXDevice',
  'onScanWXDeviceResult',
  'onWXDeviceBluetoothStateChange'
];

// 是否已经向后台发送请求加载配置
let loaded = false;

// 接口是否已准备好
let ready = initPromise();

function initPromise() {
  let callback;
  const promise = new Promise(resolve => {
    callback = resolve
  });
  promise.resolve = callback;
  return promise;
}

function getInitUrl() {
  if (window.__wxjs_environment !== 'miniprogram' && /(android)/i.test(navigator.userAgent)) {
    // 单页应用+安卓+公众号下,使用当前地址,留空后台自动生成
    return '';
  } else {
    return typeof wxInitUrl === 'undefined' ? '' : wxInitUrl;
  }
}

function getConfigUrl() {
  return typeof wxConfigUrl === 'undefined' ? app.url('wechat/js-config') : wxConfigUrl;
}

function load() {
  if (loaded) {
    return;
  }
  loaded = true;

  $.ajax({
    url: getConfigUrl(),
    type: 'post',
    dataType: 'json',
    data: {url: getInitUrl()}
  }).then(ret => {
    if (ret.code !== 1) {
      $.log('获取微信配置失败', ret);
      return;
    }

    ret.config.jsApiList = jsApiList;
    wx.config(ret.config);

    wx.error(res => {
      // 只上报不提示错误,因为微信可能误报签名错误
      $.log('配置微信接口失败', {res: res, ret: ret});
    });

    wx.ready(() => {
      ready.resolve();
    });
  });
}

wx.load = (fn) => {
  load();
  ready.then(fn);
};

wx.reset = () => {
  loaded = false;
  ready = initPromise();
};

export default wx;
