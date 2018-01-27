<?= $block->js() ?>
<script>
  (function () {
    var $checkAll = $('.js-table-check-all');
    $checkAll.click(function () {
      $('.js-table-checkbox').prop('checked', $(this).prop('checked'));
    });

    $('.js-batch-download').click(function () {
      var $checked = $('.js-table-checkbox:checked');
      if ($checked.length === 0) {
        return $.err('请至少选择一项');
      }
      if ($checked.length > 30) {
        return $.err('最多可选择30项');
      }
      var sceneIds = $.map($checked, function (checkbox) {
        return $(checkbox).val();
      });
      window.location = $.url('admin/wechat-qrcode/batchDownload', {sceneIds: sceneIds});
    });

    $('.js-record-table').on('draw', function () {
      $checkAll.prop('checked', false);
    });
  }());
</script>
<?= $block->end() ?>
