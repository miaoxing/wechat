import React from "react";

export default class Share extends React.Component {
  componentWillMount() {
    // 进入页面要刷新接口
    requirejs(['plugins/wechat/js/wx'], function (wx) {
      wx.reset();
    });
  }

  componentDidMount() {
    if (typeof wxShare === 'undefined') {
      window.wxShare = {};
    }

    if (!window.wxDefaultTtile) {
      window.wxDefaultTtile = wxShare.title;
    }

    requirejs(['plugins/wechat/js/wx'], (wx) => {
      wx.load(() => {
        if (!document.title.includes(wxDefaultTtile)) {
          wxShare.title = wxDefaultTtile + document.title;
        }
        wx.onMenuShareTimeline(wxShare);
        wx.onMenuShareAppMessage(wxShare);
      });
    });
  }

  render() {
    return '';
  }
}
