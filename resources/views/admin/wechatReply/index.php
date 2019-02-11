<?php $view->layout() ?>

<!-- /.page-header -->
<div class="page-header">
  <div class="pull-right">

    <div class="btn-group">
      <button data-toggle="dropdown" class="btn btn-success dropdown-toggle">
        系统回复
        <i class="fa fa-angle-down icon-on-right"></i>
      </button>
      <ul class="dropdown-menu pull-right">
        <li><a href="<?= $url('admin/wechat-reply/edit?id=default&scene=默认回复', ['accountId' => $req['accountId']]) ?>">默认回复</a></li>
        <li><a href="<?= $url('admin/wechat-reply/edit?id=subscribe&scene=关注时回复', ['accountId' => $req['accountId']]) ?>">关注时回复</a></li>
        <li><a href="<?= $url('admin/wechat-reply/edit?id=phone&scene=输入手机号码', ['accountId' => $req['accountId']]) ?>">输入手机号码</a></li>
        <li><a href="<?= $url('admin/wechat-reply/edit?id=scan&scene=扫码', ['accountId' => $req['accountId']]) ?>">扫码</a></li>
      </ul>
    </div>

    <a href="<?= $url('admin/wechat-reply/new?type=text') ?>" class="btn btn-success">添加回复</a>
  </div>
  <h1>
    回复管理
  </h1>
</div>

<div class="row">
  <div class="col-12">
    <!-- PAGE CONTENT BEGINS -->
    <div class="table-responsive">
      <table class="record-table table table-bordered table-hover table-center">
        <thead>
        <tr>
          <th>关键词</th>
          <th>回复</th>
          <th style="width: 120px">回复类型</th>
          <th style="width: 120px">匹配模式</th>
          <th style="width: 120px">操作</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>
    <!-- /.table-responsive -->
    <!-- PAGE CONTENT ENDS -->
  </div>
  <!-- /col -->
</div>
<!-- /row -->

<script id="table-actions" type="text/html">
  <div class="action-buttons">
    <a href="<%= $.url('admin/wechat-reply/edit', {id: id}) %>" title="编辑">
      <i class="fa fa-edit bigger-130"></i>
    </a>
    <a class="text-danger delete-record" href="javascript:;" data-id="<%= id %>" title="删除">
      <i class="fa fa-trash-o bigger-130"></i>
    </a>
  </div>
</script>

<script id="replies-tpl" type="text/html">
  <a href="<%= replies.image.url %>" target="_blank">
    <img style="max-height: 60px" src="<%= replies.image.url %>">
  </a>
</script>

<?= $block->js() ?>
<script>
  require(['dataTable', 'form', 'jquery-deparam'], function () {
    $('#search-form').loadParams().update(function () {
      recordTable.reload($(this).serialize());
    });

    var recordTable = $('.record-table').dataTable({
      ajax: {
        url: $.queryUrl('admin/wechat-reply/index?_format=json')
      },
      columns: [
        {
          data: 'keywords'
        },
        {
          data: 'content',
          render: function (data, type, full) {
            if (full.type === 'image') {
              return template.render('replies-tpl', full);
            } else if (full.type === 'article') {
              var title = [];
              for (var i in full.articles) {
                title.push(full.articles[i].title);
              }
              return title.join(', ');
            } else {
              return data;
            }
          }
        },
        {
          data: 'type',
          render: function (data) {
            if (data === 'text') {
              return '文本';
            } else if (data === 'article') {
              return '图文';
            } else {
              return '图片';
            }
          }
        },
        {
          data: 'matchTypeName',
          render: function (data) {
            return data.substr(0, 2);
          }
        },
        {
          data: 'id',
          render: function (data, type, full) {
            return template.render('table-actions', full);
          }
        }
      ]
    });

    recordTable.on('click', '.delete-record', function () {
      var $this = $(this);
      $.confirm('删除后将无法还原,确认删除?', function () {
        $.post($.url('admin/wechat-reply/destroy', {id: $this.data('id')}), function (result) {
          $.msg(result);
          recordTable.reload();
        }, 'json');
      });
    });

    recordTable.tooltip({
      selector: '.action-buttons a'
    });
  });
</script>
<?= $block->end() ?>

