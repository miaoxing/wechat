define(['plugins/app/libs/jquery.populate/jquery.populate'], function(){
    var self = {};

    // 选项

    self.data = {};
    self.articles = [];
    self.returnUrl = $.url('admin/wechat-reply/index');
    self.articleTable = $('.article-table');
    self.articleList = $('.article-list');
    self.articleTableModal = $('#article-table-modal');
    self.form = $('.reply-form');

    // 方法

    self.initForm = function(options) {
        $.extend(self, options);

        self.articles = self.data.articles;

        self.renderArticleList(self.articles);

        self.form
            .populate(self.data)
            .loadParams()
            .ajaxForm({
                dataType: 'json',
                beforeSubmit: function(arr, $form, options) {
                    var articleIds = [];
                    for (var i in self.articles) {
                        arr[arr.length] = {name: 'articleIds[]', value: self.articles[i]['id']};
                    }

                    if (!self.articles.length && $('input[name=type][value=article]').prop('checked')) {
                      $.err('请至少选择一篇图文');
                      return false;
                    }

                    return $form.valid();
                },
                success: function (result) {
                    $.msg(result, function(){
                        if (result.code > 0) {
                            window.location = self.returnUrl;
                        }
                    });
                }
            })
            .validate({
                rules: {
                    keywords: 'required'
                },
                errorClass: 'error text-danger',
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent());
                }
            });

        $('input[name=type]')
            .change(function(){
                $('.type-form-group').hide();
                $('.' + $(this).val() + '-form-group').show();
            });

        $('input[name=type]:checked').trigger('change');

        // 点击按钮,移除单项图文
        self.form.on('click', '.remove-article', function() {
            self.removeArticleById($(this).data('id'));
            self.renderArticleList(self.articles);
        });

        self.initArticleTable();

        // 删除记录
        $('.delete-record').click(function(){
            var $this = $(this);
            $.confirm('删除后将无法还原,确认删除?', function(result) {
              if (!result) {
                return;
              }

                $.post($this.data('href'), function(result) {
                    $.msg(result,function(){
                        if (result.code > 0) {
                            window.location = self.returnUrl;
                        }
                    });
                }, 'json');
            });
        });

        $('.js-replies-image-url').imageUpload({
          url: $.url('admin/wechat-medias/upload-image.json')
        });
    };

    self.initArticleTable = function() {
        self.articleTableModal.on('show.bs.modal', function(){
            if ($.fn.dataTable.fnIsDataTable(self.articleTable[0])) {
                self.articleTable.reload();
            } else {
                self.articleTable = self.articleTable.dataTable({
                    dom: "t<'row'<'col-sm-6'ir><'col-sm-6'p>>",
                    ajax: {
                        url: $.url('admin/article.json')
                    },
                    columns: [
                        {
                            data: 'id',
                            sClass: 'text-center',
                            render: function(data) {
                                var checked = '';
                                for (var i in self.articles) {
                                    if (self.articles[i]['id'] == data) {
                                        checked = ' checked';
                                        break;
                                    }
                                }
                                return '<label><input type="checkbox" class="ace" value="' + data + '"' + checked + '><span class="lbl"></span></label>'
                            }
                        },
                        {
                            data: 'title'
                        }
                    ]
                });

              $('#search-form').update(function () {
                self.articleTable.reload($(this).serialize(), false);
              });
            }
        });

        // 选择文章,更新自动回复的图文
        self.articleTable.on('change', 'input:checkbox', function(){
            // 将图文加入或移除
            var data = self.articleTable.fnGetData($(this).parents('tr')[0]);
            if ($(this).is(':checked')) {
                self.articles.push(data);
            } else {
                self.articles.splice(self.articles.indexOf(data), 1);
            }

            // 更新表单中的图文卡片
            self.renderArticleList(self.articles);
        });

        // 刷新表格
        $('.refresh-articles').click(function(e){
            self.articleTable.reload();
            e.preventDefault();
        });
    };

    /**
     * 根据ID删除文章
     */
    self.removeArticleById = function(id) {
        for (var i in self.articles) {
            if (self.articles[i]['id'] == id) {
                self.articles.splice(i,  1);
            }
        }
    };

    /**
     * 更新图文卡片
     */
    self.renderArticleList = function(data) {
        var html = '';
        if (data.length) {
            html = template.render('media-article-tpl', {data: data, template: template});
        }
        self.articleList.html(html);
    };

    return self;
});
