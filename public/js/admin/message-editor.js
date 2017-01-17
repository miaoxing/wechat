define(['plugins/article/js/admin/picker'], function (picker) {
  var Editor = function (options) {
    options && $.extend(this, options);
    this.initialize.apply(this, arguments);
  };

  Editor.TYPE_ARTICLE = 10;

  Editor.TYPE_TEXT = 1;

  Editor.prototype.$el = $('.js-message-editor');

  Editor.prototype.initialize = function (options) {

  };

  Editor.prototype.$ = function (selector) {
    return this.$el.find(selector);
  };

  Editor.prototype.init = function (options) {
    options && $.extend(this, options);

    picker.init();
    this.initNav();
    this.initArticle();
    this.initText();
  };

  Editor.prototype.initNav = function () {
    var self = this;
    this.$('.js-message-editor-nav a').on('shown.bs.tab', function (e) {
      var type = $(e.target).data('type');
      var typeKey = $(e.target).data('type-key');

      self.$('.js-message-editor-type').val(type);
      if (typeKey == 'text') {
        // 切换到文字编辑框,自动聚焦
        self.$('.js-message-editor-text').focus();
      }
    });
  };

  Editor.prototype.initArticle = function () {
    this.$('.js-message-editor-article-delete').click(function () {
      picker.articles = [];
      picker.renderArticleList([]);
    });
  };

  // 初始化文本输入框
  Editor.prototype.initText = function () {
    var self = this;
    require(['comps/jquery-inputlimiter/jquery.inputlimiter.1.3.1.min'], function () {
      self.$('.js-message-editor-text').inputlimiter({
        boxAttach: false,
        remText: '%n',
        limitText: '/ %n',
        remTextHideOnBlur: false,
        allowExceed: true
      });
    });
  };

  // 检查编辑器内容是否未填写
  Editor.prototype.checkData = function () {
    var data = this.getData();
    if (data.type == Editor.TYPE_ARTICLE && data.articleIds == '') {
      return {code: -1, message: '请选择图文'};
    }
    if (data.type == Editor.TYPE_TEXT && data.content == []) {
      return {code: -1, message: '请输入文字内容'};
    }
    return {code: 1, message: '检查通过'};
  };

  // 获取编辑器的内容
  Editor.prototype.getData = function () {
    var data = {};
    data.type = this.$('.js-message-editor-type').val();
    data.content = this.$('.js-message-editor-text').val();
    data.articleIds = $.map(picker.articles, function (article) {
      return article.id;
    });
    return data;
  };

  return new Editor();
});
