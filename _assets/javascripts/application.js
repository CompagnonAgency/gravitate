//= require_self

$(function() {
  $('a[href*="#"]:not([href="#"])').click(function() {
    if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname) {
      var target = $(this.hash);
      target = target.length ? target : $('[name=' + this.hash.slice(1) +']');
      if (target.length) {
        $('html, body').animate({
          scrollTop: target.offset().top
        }, 1000);
        return false;
      }
    }
  });

  var startId = -1;
  setInterval(function() {
    ++startId;
    if (startId <= 10) {
      $listItem = $('[data-id='+startId+']');
      $listItem.addClass('is-added');
    }
  }, 500);
});
