<div id="article-table-modal" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">请选择图文</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        </div>
      </div>

      <div class="modal-body p-0">
        <div class="well">
          <form class="form-inline" id="search-form" role="form">
            <div class="form-group">
              <select class="form-control" name="categoryId" id="categoryId">
                <option value="">全部栏目</option>
              </select>
            </div>
            <div class="form-group">
              <input type="text" class="form-control" name="search" placeholder="请输入标题搜索">
            </div>

            <div class="form-group float-right">
              <a class="btn btn-secondary refresh-articles" title="刷新" href="javascript:;">
                <i class="fa fa-refresh"></i>
              </a>
              <a class="btn btn-secondary add-article" title="增加" href="<?= $url('admin/article/new') ?>"
                target="_blank">
                <i class="fa fa-plus"></i>
              </a>
            </div>
            <!--<button type="submit" class="btn btn-sm">搜索</button>-->
          </form>
        </div>
        <table class="article-table table table-bordered table-hover">
          <thead>
          <tr>
            <th style="width:50px;"></th>
            <th>标题</th>
          </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
      </div>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div>
