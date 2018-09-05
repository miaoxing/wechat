define(['//res.wx.qq.com/open/js/jweixin-1.3.2.js'], function (wx) {
  var jsApiList = [
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

  var dfd = $.Deferred();
  var called = false;
  var ajax = function () {
    if (called) {
      return;
    }
    called = true;

    $.ajax({
      url: typeof wxConfigUrl !== 'undefined' ? wxConfigUrl : $.url('wechat/js-config'),
      type: 'post',
      dataType: 'json',
      success: function (ret) {
        if (ret.code !== 1) {
          $.log('获取微信配置失败' + JSON.stringify(ret));
          return;
        }

        ret.config.jsApiList = jsApiList;
        wx.config(ret.config);

        wx.error(function (res) {
          // 只上报不提示错误,因为微信可能误报签名错误
          $.log('配置微信接口失败' + JSON.stringify({res: res, ret: ret}));
        });

        wx.ready(function () {
          dfd.resolve();
        });
      }
    });
  };

  wx.load = function (fn) {
    ajax();
    dfd.done(fn);
  };

  wx.reset = function () {
    dfd = $.Deferred();
    called = false;
  };

  return wx;
});
