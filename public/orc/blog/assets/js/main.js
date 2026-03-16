/***************************************************
==================== JS INDEX ======================
01. Data Background Set

****************************************************/

(function ($) {
  "use strict";



  // grid
  if ($(".grid").length != 0) {
    if ($(window).width() > 0) {
      var $grid = $(".grid").imagesLoaded(function () {
        $grid.isotope({
          itemSelector: ".grid-item",
          percentPosition: true,
          masonry: {
            columnWidth: ".grid-item"
          }
        });

        $(".filter-tab").on("click", "button", function () {
          var filterValue = $(this).attr("data-filter");
          $grid.isotope({ filter: filterValue });
        });

        $(".filter-tab button").on("click", function (event) {
          $(this).siblings(".active").removeClass("active");
          $(this).addClass("active");
          event.preventDefault();
        });
      });
    }
  }

  // banner - section
  if (document.querySelector(".banner-section-5__active")) {
    var swiperhero5 = new Swiper(".banner-section-5__active", {
      slidesPerView: 1,
      spaceBetween: 10,
      loop: true,
      centeredSlides: false,
      autoplay: true,
      centerMode: true,
      speed: 500,
      navigation: {
        nextEl: ".banner-section-5__arrow-next",
        prevEl: ".banner-section-5__arrow-prev",
      },
    });
  }


  // brand - section
  if (document.querySelector(".blog-post__active")) {
    document.addEventListener("DOMContentLoaded", function () {
      const swiper = new Swiper(".blog-post__active", {
        slidesPerView: 'auto',
        spaceBetween: 24,
        centeredSlides: true,
        speed: 500,
        loop: true,
        freeMode: false,
        allowTouchMove: false,
        autoplay: {
          delay: 2500,
        },
        navigation: {
          nextEl: ".blog-post__arrow-next",
          prevEl: ".blog-post__arrow-prev",
        },
      });
    });
  }

  // featured - news
  if (document.querySelector(".featured-news__active")) {
    var swiper = new Swiper(".featured-news__active", {
      slidesPerView: 3,
      spaceBetween: 24,
      loop: true,
      centeredSlides: false,
      autoplay: true,
      centerMode: true,
      speed: 400,
      navigation: {
        nextEl: ".featured-news__arrow-next",
        prevEl: ".featured-news__arrow-prev",
      },
      breakpoints: {
        320: {
          slidesPerView: 1,
        },
        767: {
          slidesPerView: 1.8,
        },
        992: {
          slidesPerView: 2.3,
        },
        1200: {
          slidesPerView: 3,
        },
      },
    });
  }

  // blog - slider
  if (document.querySelector(".blog-slider__active")) {
    document.addEventListener("DOMContentLoaded", function () {
      const swiper = new Swiper(".blog-slider__active", {
        slidesPerView: 'auto',
        spaceBetween: 24,
        centeredSlides: false,
        loop: true,
        autoplay: true,
        speed: 500,
        freeMode: false,
        allowTouchMove: true,
      });
    });
  }


  // brand - section
  if (document.querySelector(".featured-news-2__active")) {
    document.addEventListener("DOMContentLoaded", function () {
      const swiper = new Swiper(".featured-news-2__active", {
        slidesPerView: 'auto',
        spaceBetween: 28,
        centeredSlides: true,
        speed: 4000,
        loop: true,
        freeMode: false,
        allowTouchMove: false,
        autoplay: {
          delay: 0.4,
        },
      });
    });
  }

  // featured - news
  if (document.querySelector(".featured-news-3__active")) {
    var swiper = new Swiper(".featured-news-3__active", {
      slidesPerView: 3,
      spaceBetween: 23,
      loop: true,
      centeredSlides: false,
      autoplay: true,
      centerMode: true,
      speed: 400,
      navigation: {
        nextEl: ".featured-news-3__arrow-next",
        prevEl: ".featured-news-3__arrow-prev",
      },
      breakpoints: {
        320: {
          slidesPerView: 1,
        },
        767: {
          slidesPerView: 1.5,
        },
        992: {
          slidesPerView: 2,
        },
        1200: {
          slidesPerView: 3,
        },
      },
    });
  }


  // featured - news
  if (document.querySelector(".news-alert__active")) {
    var swiper = new Swiper(".news-alert__active", {
      slidesPerView: 1,
      spaceBetween: 0,
      loop: true,
      centeredSlides: false,
      autoplay: true,
      centerMode: true,
      speed: 2000,
      navigation: {
        nextEl: ".news-alert__arrow-next",
        prevEl: ".news-alert__arrow-prev",
      },
    });
  }




  // Update----------------------------


  // banner - section 6
  if (document.querySelector(".banner-section-6__active")) {
    var swiperhero5 = new Swiper(".banner-section-6__active", {
      slidesPerView: 1,
      spaceBetween: 0,
      loop: true,
      centeredSlides: false,
      autoplay: true,
      centerMode: true,
      speed: 500,
      navigation: {
        nextEl: ".banner-section-6__arrow-next",
        prevEl: ".banner-section-6__arrow-prev",
      },
    });
  }

  // latest - blog - 6
  if (document.querySelector(".latest-blog-6__active")) {
    var swiper = new Swiper(".latest-blog-6__active", {
      slidesPerView: 3,
      spaceBetween: 23,
      loop: true,
      centeredSlides: false,
      autoplay: true,
      centerMode: true,
      speed: 400,
      navigation: {
        nextEl: ".latest-blog-6__arrow-next",
        prevEl: ".latest-blog-6__arrow-prev",
      },
      pagination: {
        el: ".latest-blog-6__pagination",
      },
      breakpoints: {
        320: {
          slidesPerView: 1,
        },
        576: {
          slidesPerView: 2,
        },
        767: {
          slidesPerView: 2,
        },
        992: {
          slidesPerView: 3,
        },
        1200: {
          slidesPerView: 3,
        },
      },
    });
  }

  // personal-blog 7
  if (document.querySelector(".personal-blog-7__active")) {
    var swiperhero5 = new Swiper(".personal-blog-7__active", {
      slidesPerView: 1,
      spaceBetween: 0,
      loop: true,
      centeredSlides: false,
      autoplay: true,
      centerMode: true,
      speed: 500,
      navigation: {
        nextEl: ".personal-blog-7__arrow-next",
        prevEl: ".personal-blog-7__arrow-prev",
      },
    });
  }

  // my-experience 7
  if (document.querySelector(".my-experience-7__active")) {
    var swiper = new Swiper(".my-experience-7__active", {
      slidesPerView: 3,
      spaceBetween: 24,
      loop: true,
      centeredSlides: false,
      autoplay: true,
      centerMode: true,
      speed: 400,
      navigation: {
        nextEl: ".my-experience-7__arrow-next",
        prevEl: ".my-experience-7__arrow-prev",
      },
      breakpoints: {
        320: {
          slidesPerView: 1,
        },
        576: {
          slidesPerView: 2,
        },
        767: {
          slidesPerView: 2,
        },
        992: {
          slidesPerView: 3,
        },
        1200: {
          slidesPerView: 3,
        },
      },
    });
  }


  // blog - slider 8
  if (document.querySelector(".blog-slider-8__active")) {
    document.addEventListener("DOMContentLoaded", function () {
      const swiper = new Swiper(".blog-slider-8__active", {
        slidesPerView: "auto",
        spaceBetween: 24,
        centeredSlides: false,
        loop: true,
        speed: 500,
        autoplay: {
          delay: 3000,
          disableOnInteraction: false,
        },
      });
    });
  }




})(jQuery);


