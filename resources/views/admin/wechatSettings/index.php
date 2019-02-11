<?php $view->layout() ?>

<div class="page-header">
  <h1>
    功能设置
  </h1>
</div>

<div class="row">
  <div class="col-12">
    <form action="<?= $url('admin/wechat-settings/update') ?>" class="js-setting-form form-horizontal"
      method="post" role="form">

      <div class="form-group">
        <label class="col-lg-2 control-label" for="share-image">
          默认分享图
        </label>

        <div class="col-lg-4">
          <input class="js-share-image form-control" id="share-image" name="settings[wechat.shareImage]" type="text">
        </div>

        <label class="col-lg-6 help-text" for="share-image">
          尺寸300*300以上,长宽1:1
        </label>
      </div>

      <div class="clearfix form-actions form-group">
        <div class="offset-lg-2">
          <button class="btn btn-primary" type="submit">
            <i class="fa fa-check bigger-110"></i>
            提交
          </button>
        </div>
      </div>
    </form>
  </div>
  <!-- PAGE CONTENT ENDS -->
</div><!-- /.col -->
<!-- /.row -->

<?= $block->js() ?>
<script>
  require(['plugins/admin/js/image-upload', 'form', 'validator'], function () {
    $('.js-setting-form')
      .loadJSON(<?= json_encode([
      'js-share-image' => $shareImage,
]) ?>)
      .ajaxForm({
        dataType: 'json',
        beforeSubmit: function(arr, $form, options) {
          return $form.valid();
        },
        success: function (ret) {
          $.msg(ret);
        }
      })
      .validate();

    $('.js-share-image').imageUpload();
  });
</script>
<?= $block->end() ?>

