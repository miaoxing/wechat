import { Component } from 'react';
import {findDOMNode} from "react-dom";

class PreviewImage extends Component {
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
    return <span>{this.props.children}</span>;
  }
}

export default PreviewImage;
