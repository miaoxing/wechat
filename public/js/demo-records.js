define(function () {
  var WechatRecords = function () {
    // do nothing.
  };

  WechatRecords.prototype.indexAction = function (options) {
    $.extend(this, options);
  };

  return new WechatRecords();
});
