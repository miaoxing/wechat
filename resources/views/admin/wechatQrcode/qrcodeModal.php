<!-- Modal -->
<div class="js-qrcode-modal modal fade" tabindex="-1" role="dialog" aria-labelledby="qrcodeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qrcodeModalLabel">查看二维码</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <img class="js-qrcode-img" src="">
        <div class="js-qrcode-name text-lg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
      </div>
    </div>
  </div>
</div>

<?= $block->js() ?>
<script>
  $('body').on('click', '.js-qrcode-show', function () {
    $('.js-qrcode-name').html($(this).data('name'));
    $.ajax({
      url: $.url('admin/wechat-qrcode/show', {sceneId: $(this).data('scene-id')}),
      dataType: 'json',
      success: function (ret) {
        if (ret.code !== 1) {
          $.msg(ret);
        } else {
          $('.js-qrcode-img').attr('src', ret.data.image);
          $('.js-qrcode-modal').modal('show');
        }
      }
    });
  });
</script>
<?= $block->end() ?>

