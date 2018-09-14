import React from 'react';
import {findDOMNode} from "react-dom";

class PreviewImage extends React.Component {
  componentDidMount() {
    const $this = $(findDOMNode(this));

    requirejs(['plugins/wechat/js/wx'], function (wx) {
      wx.load(function () {
        $this.on('click', 'img', function () {
          const urls = $this.find('img').map(function () {
            return this.src;
          }).get();
          wx.previewImage({
            current: $(this).attr('src'),
            urls: urls
          });
        });
      });
    });
  }

  render() {
    return <div>{this.props.children}</div>;
  }
}

export default PreviewImage;
