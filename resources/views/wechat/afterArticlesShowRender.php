<?= $block('js') ?>
<script>
  require(['plugins/wechat/js/wx'], function (wx) {
    wx.load(function () {
      wx.hideOptionMenu();
    });
  });
</script>
<?= $block->end() ?>
