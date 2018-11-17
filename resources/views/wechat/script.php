<script>
  var wxInitUrl = <?= json_encode(wei()->request->getUrl()) ?>;
  <?php if (!wei()->app->isAdmin() && !wei()->plugin->isInstalled('wxa')) { ?>
  var wxShare = <?= wei()->share->toWechatJson() ?>;
  require(['plugins/wechat/js/wx'], function (wx) {
    wx.load(function () {
      wx.onMenuShareTimeline(wxShare);
      wx.onMenuShareAppMessage(wxShare);
    });
  });
  <?php } ?>
</script>
