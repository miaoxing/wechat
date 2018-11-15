<script>
  var wxInitUrl = <?= json_encode(wei()->request->getUrl()) ?>;
  var wxShare = <?= wei()->share->toWechatJson() ?>;
  require(['plugins/wechat/js/wx'], function (wx) {
    wx.load(function () {
      wx.onMenuShareTimeline(wxShare);
      wx.onMenuShareAppMessage(wxShare);
    });
  });
</script>
