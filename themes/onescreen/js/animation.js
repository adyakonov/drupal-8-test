(function ($) {
  'use strict';

  $(document).ready(function () {

    $('#landing-page-region').viewportChecker({
      classToAdd: 'ta-visible animated fadeIn',
      offset: 150
    });

    $('#main-content').viewportChecker({
      classToAdd: 'ta-visible animated fadeIn',
      offset: 150
    });

    $('#bottom').viewportChecker({
      classToAdd: 'ta-visible animated fadeIn',
      offset: 100
    });

    $('#footer').viewportChecker({
      classToAdd: 'ta-visible animated fadeIn',
      offset: 50
    });

    $('#navigation-bar').addClass('ta-visible animated fadeInDown');
    $('#slidebox .flex-caption').addClass('ta-visible animated fadeIn');
    $('#slidebox .flex-control-nav').addClass('ta-visible animated fadeIn');

  });

})(jQuery);
