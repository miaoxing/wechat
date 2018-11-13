import React from "react";

export default class Share extends React.Component {
  componentDidMount() {
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
