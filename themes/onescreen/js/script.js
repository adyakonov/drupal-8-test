/*

Scripts

*/


(function ($) {
  'use strict';

  $(document).ready(function () {

    $('.site-navigation').affix({
      offset: {
        top: 40
      }
    });

    $('.mobile-nav').click(function () {
      $('.navigation').removeClass('close');
      $('.navigation').addClass('open');
    });

    $('.mobile-nav-close').click(function () {
      $('.navigation').removeClass('open');
      $('.navigation').addClass('close');
    });

  });

})(jQuery);
