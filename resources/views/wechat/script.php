<script>
  var wxInitUrl = <?= json_encode(wei()->request->getUrl()) ?>;
  require(['plugins/wechat/js/wx'], function (wx) {
    var share = <?= wei()->share->toWechatJson() ?>;
    wx.onMenuShareTimeline(share);
    wx.onMenuShareAppMessage(share);
  });
</script>
