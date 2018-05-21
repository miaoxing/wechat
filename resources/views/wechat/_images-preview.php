<?= $block->js() ?>
<script>
  require(['plugins/wechat/js/wx'], function (wx) {
    wx.load(function () {
      $('body').on('click', '.js-images-preview img', function () {
        var urls = $(this).closest('.js-images-preview').find('img').map(function () {
          return this.src;
        }).get();
        wx.previewImage({
          current: $(this).attr('src'),
          urls: urls
        });
      });
    });
  });
</script>
<?= $block->end() ?>
