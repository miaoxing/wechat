<?= $block('css') ?>
<link rel="stylesheet" href="<?= $asset('assets/wechat/media.css') ?>"/>
<?= $block->end() ?>

<script id="media-news" type="text/html">
  <li class="news-item">
    <div class="thumbnail" style="margin-bottom:0">
      <h4><a target="_blank" href="<%= Articles.item[0].Url %>"><%= Articles.item[0].Title %></a></h4>

      <p class="news-meta"><span><%= time %></span></p>
      <img class="lazy" src="" data-original="<%= Articles.item[0].PicUrl %>" style="width: 320px; height: 160px;">

      <div class="caption">
        <%== Articles.item[0].Description %>
      </div>
      <div class="text mask">
        <div class="inner">
          <a class="send" href="javascript:;" data-id="<%= appId %>" title="直接发送">
            <i class="fa fa-check bigger-300"></i>
          </a>
          <a href="<%= Articles.item[0].Url %>" title="新窗口预览" target="_blank">
            <i class="fa fa-search-plus bigger-300"></i>
          </a>
        </div>
      </div>
    </div>
  </li>
</script>

<!-- 图文右上角的操作按钮 -->
<script id="media-article-actions-tpl" type="text/html">
  <div class="article-actions">
    <a class="text-muted" target="_blank" href="<%= $.url('admin/article/edit', {id: id}) %>" title="编辑"><i class="fa fa-edit"></i></a>
    <a class="js-article-picker-remote remove-article text-muted" href="javascript:;" title="移除" data-id="<%= id %>"><i class="fa  fa-times-circle-o"></i></a>
  </div>
</script>

<!-- 图文 -->
<script id="media-article-tpl" type="text/html">
  <%
  if (data.length == 1) {
  var article = data[0];
  %>
  <div class="appmsg">
    <div class="appmsg_content appmsg-sigle-content">
      <%== template.render('media-article-actions-tpl', article) %>
      <h4 class="appmsg_title">
        <a href="<%= $.url('article/show', {id: article.id}) %>" target="_blank"><%= article.title %></a>
      </h4>

      <div class="appmsg_thumb_wrp">
        <img src="<%= article.thumb %>" alt="" class="appmsg_thumb">
      </div>
      <p class="appmsg_desc"><%= article.intro %></p>
    </div>
  </div>
  <% } else { %>
  <div class="appmsg multi">
    <div class="appmsg_content">
      <%
      for (var i in data) {
      var article = data[i];
      if (i == 0) {
      %>
      <div class="cover_appmsg_item">
        <%== template.render('media-article-actions-tpl', article) %>
        <h4 class="appmsg_title">
          <a href="<%= $.url('article/show', {id: article.id}) %>" target="_blank"><%= article.title %></a>
        </h4>

        <div class="appmsg_thumb_wrp">
          <img src="<%= article.thumb %>" alt="" class="appmsg_thumb"/>
        </div>
      </div>
      <% } else { %>
      <div class="appmsg_item">
        <%== template.render('media-article-actions-tpl', article) %>
        <img src="<%= article.thumb %>" alt="" class="appmsg_thumb"/>
        <h4 class="appmsg_title">
          <a href="<%= $.url('article/show', {id: article.id}) %>" target="_blank"><%= article.title %></a>
        </h4>
      </div>
      <%
      }
      }
      %>
    </div>
  </div>
  <% } %>
</script>
