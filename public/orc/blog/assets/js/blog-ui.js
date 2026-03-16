/**
 * Shared UI rendering functions for the blog (Categories, Tags, Recent Posts)
 */

const swiperInstances = {};

function renderPopularTags(tags) {
    const container = document.querySelector('.blog-sidebar__tag ul');
    if (!container) return;

    if (!tags || tags.length === 0) {
        container.closest('.blog-sidebar__wrap').style.display = 'none';
        return;
    }

    container.innerHTML = tags.map(tag => `
        <li><a href="/posts?tag=${encodeURIComponent(tag.name)}">${tag.name}</a></li>
    `).join('');
}

function renderFooterPopularTags(tags) {
    const container = document.querySelector('.footer__tags');
    if (!container) return;

    if (!tags || tags.length === 0) {
        container.closest('.col-xl-4.col-lg-4.col-md-6').style.display = 'none';
        return;
    }

    container.innerHTML = tags.map(tag => `
        <li><a href="/posts?tag=${encodeURIComponent(tag.name)}">#${tag.name}</a></li>
    `).join('');
}

function renderSidebarCategories(categories) {
    const container = document.querySelector('.blog-sidebar__categories ul');
    if (!container) return;

    container.innerHTML = categories.map(cat => `
        <li><a href="/posts?category=${cat.name}">${cat.name.charAt(0).toUpperCase() + cat.name.slice(1)} <span>${cat.post_count}</span></a></li>
    `).join('');
}

function renderFooterCategories(categories) {
    const container = document.querySelector('.footer__categories');
    if (!container) return;

    const sortedCategories = [...categories].sort((a, b) => {
        if (b.post_count !== a.post_count) {
            return b.post_count - a.post_count;
        }
        return b.id - a.id;
    });

    const topCategories = sortedCategories.slice(0, 3);

    container.innerHTML = topCategories.map(cat => `
        <li><a href="/posts?category=${cat.name}">${cat.name.charAt(0).toUpperCase() + cat.name.slice(1)} <span>${cat.post_count}</span></a></li>
    `).join('');
}

function renderSidebarRecentPosts(posts) {
    const container = document.querySelector('.blog-sidebar__post');
    if (!container) return;

    const title = `<h3 class="blog-sidebar__title">Most Recent</h3>`;
    const postsHtml = posts.map((post, index) => `
        <div class="blog-sidebar__post-item">
            <div class="blog-sidebar__post-thumb">
                <span class="blog-sidebar__post-number">${(index + 1).toString().padStart(2, '0')}</span>
                <a href="/post?slug=${post.slug}">
                    <img src="${post.featured_image || '/orc/blog/assets/img/blog/blog-sidebar-01.png'}" alt="${post.title}">
                </a>
            </div>
            <div class="blog-sidebar__post-content">
                <h3 class="blog-sidebar__post-title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 45)}</a></h3>
                <ul class="blog-sidebar__post-meta">
                    <li><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></li>
                    <li>${formatDate(post.publish_date || post.created_at)}</li>
                    <li>${post.reading_time || 5} min read</li>
                </ul>
            </div>
        </div>
    `).join('');

    container.innerHTML = title + postsHtml;
}

function renderFooterRecentPosts(posts) {
    const container = document.querySelector('.footer__post');
    if (!container) return;

    container.innerHTML = posts.map(post => {
        let imageUrl = post.featured_image || '/orc/blog/assets/img/footer/footer-01.jpg';
        if (imageUrl && !imageUrl.startsWith('/') && !imageUrl.startsWith('http')) {
            imageUrl = '/' + imageUrl;
        }

        return `
            <div class="footer__post-item">
                <div class="thumb">
                    <img src="${imageUrl}" alt="${post.title}">
                </div>
                <div class="text">
                    <p class="general"><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                    <h2 class="title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 35)}</a></h2>
                    <span class="meta"><i class="fa-solid fa-calendar-days"></i> ${formatDateLong(post.publish_date || post.created_at)}</span>
                </div>
            </div>
        `;
    }).join('');
}

function renderFeaturedNews(posts) {
    const container = document.querySelector('.featured-news__active .swiper-wrapper');
    if (!container) return;

    if (!posts || posts.length === 0) {
        container.closest('.featured-news__area').style.display = 'none';
        return;
    }

    container.innerHTML = posts.map(post => {
        let imageUrl = post.featured_image || '/orc/blog/assets/img/blog/blog-29.jpg';
        if (imageUrl && !imageUrl.startsWith('/') && !imageUrl.startsWith('http')) {
            imageUrl = '/' + imageUrl;
        }

        return `
            <div class="swiper-slide">
                <div class="featured-news__item">
                    <div class="featured-news__thumb">
                        <a href="/post?slug=${post.slug}">
                            <img src="${imageUrl}" alt="${post.title}">
                        </a>
                    </div>
                    <div class="featured-news__content">
                        <div class="featured-news__meta">
                            <span>${timeAgo(post.publish_date || post.created_at)}</span>
                        </div>
                        <h3 class="featured-news__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 25)}</a></h3>
                        <div class="featured-news__date">
                            <span><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></span>
                            <span>${post.reading_time || 8} min read</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    // Re-initialize Swiper for Featured News
    const selector = '.featured-news__active';
    const el = document.querySelector(selector);
    
    if (typeof Swiper !== 'undefined' && el && el.querySelectorAll('.swiper-slide').length > 0) {
        if (swiperInstances.featuredNews) swiperInstances.featuredNews.destroy();
        
        swiperInstances.featuredNews = new Swiper(selector, {
            slidesPerView: 3,
            spaceBetween: 24,
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            speed: 400,
            navigation: {
                nextEl: ".featured-news__arrow-next",
                prevEl: ".featured-news__arrow-prev",
            },
            breakpoints: {
                320: {slidesPerView: 1},
                767: {slidesPerView: 1.8},
                992: {slidesPerView: 2.3},
                1200: {slidesPerView: 3},
            },
        });
    }
}

function renderMainSlider(posts) {
    const container = document.querySelector('.blog-post__active .swiper-wrapper');
    if (!container) return;

    if (!posts || posts.length === 0) {
        container.closest('.blog-post__wrap').style.display = 'none';
        return;
    }

    container.innerHTML = posts.map(post => {
        let imageUrl = post.featured_image || '/orc/blog/assets/img/blog/blog-28.jpg';
        if (imageUrl && !imageUrl.startsWith('/') && !imageUrl.startsWith('http')) {
            imageUrl = '/' + imageUrl;
        }

        return `
            <div class="swiper-slide">
                <div class="blog-post__slide">
                    <div class="new-posts__thumb">
                        <a href="/post?slug=${post.slug}">
                            <img src="${imageUrl}" alt="${post.title}" style="width: 100%; height: 837.812px">
                        </a>
                        <div class="new-posts__info">
                            <div class="featured-stories__meta">
                                <span>${timeAgo(post.publish_date || post.created_at)}</span>
                            </div>
                            <h3 class="featured-stories__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 50)}</a></h3>
                            <p class="blog-post__dec">${truncateString(post.excerpt || post.content.replace(/<[^>]*>/g, ''), 120)}</p>
                            <div class="featured-stories__date">
                                <p><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                                <span>${post.reading_time || 8} min read</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    // Re-initialize Swiper for Main Slider
    const selector = '.blog-post__active';
    const el = document.querySelector(selector);

    if (typeof Swiper !== 'undefined' && el && el.querySelectorAll('.swiper-slide').length > 0) {
        if (swiperInstances.mainSlider) swiperInstances.mainSlider.destroy();

        swiperInstances.mainSlider = new Swiper(selector, {
            slidesPerView: 'auto',
            spaceBetween: 24,
            centeredSlides: true,
            speed: 500,
            loop: true,
            autoplay: {
                delay: 2500,
                disableOnInteraction: false,
            },
            navigation: {
                nextEl: ".blog-post__arrow-next",
                prevEl: ".blog-post__arrow-prev",
            },
        });
    }
}

function renderSideFeaturedStories(posts) {
    const container = document.querySelector('.blog-post__area .col-xl-4 .row');
    if (!container) return;

    if (!posts || posts.length === 0) {
        container.closest('.col-xl-4').style.display = 'none';
        return;
    }

    container.innerHTML = posts.map(post => {
        let imageUrl = post.featured_image || '/orc/blog/assets/img/blog/blog-26.jpg';
        if (imageUrl && !imageUrl.startsWith('/') && !imageUrl.startsWith('http')) {
            imageUrl = '/' + imageUrl;
        }

        return `
            <div class="col-lg-12 col-sm-6">
                <div class="featured-stories__item mb-20">
                    <div class="featured-stories__thumb">
                        <a href="/post?slug=${post.slug}">
                            <img src="${imageUrl}" alt="${post.title}" style="width: 100%; height: 229px;">
                        </a>
                    </div>
                    <div class="featured-stories__content">
                        <div class="featured-stories__meta">
                            <span>${timeAgo(post.publish_date || post.created_at)}</span>
                        </div>
                        <h3 class="featured-stories__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 45)}</a></h3>
                        <div class="featured-stories__date">
                            <p><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                            <span>${post.reading_time || 8} min read</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderSideLatestUpdates(posts) {
    const container = document.querySelector('.blog-post__item');
    if (!container) return;

    if (!posts || posts.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.innerHTML = posts.map(post => {
        const date = new Date(post.publish_date || post.created_at);
        const day = date.getDate();
        const month = date.toLocaleString('default', {month: 'short'});

        return `
            <div class="latest-updates-2__info">
                <div class="latest-updates-2__text">
                    <h3 class="latest-updates-2__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 45)}</a></h3>
                    <div class="latest-updates-2__date">
                        <p><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                        <span>${day} ${month}</span>
                        <span>${post.reading_time || 5} min read</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}
// 6. Global Search Listener
document.addEventListener('submit', (e) => {
    const searchForm = e.target.closest('.search__form form');
    if (searchForm) {
        e.preventDefault();
        const input = searchForm.querySelector('.search-input-field');
        const query = input ? input.value.trim() : '';
        if (query) {
            window.location.href = `/posts?search=${encodeURIComponent(query)}`;
        }
    }
});

function renderLandingPageCategories(categories) {
    const container = document.querySelector('.post-category__wrap');
    if (!container) return;

    if (!categories || categories.length === 0) {
        container.closest('.post-category__area').style.display = 'none';
        return;
    }

    container.innerHTML = categories.map((cat, index) => {
        const number = (index + 1).toString().padStart(2, '0');
        return `
            <div class="post-category__item">
                <a href="/posts?category=${encodeURIComponent(cat.name)}">
                    <h3 class="post-category__title">${ucfirst(cat.name)}</h3>
                    <div class="post-category__thumb">
                        <div class="post-category__number">
                            <span>${number}</span>
                        </div>
                        <div class="image">
                            <img src="/orc/blog/assets/img/blog/blog-category-01.png" alt="${cat.name}">
                        </div>
                    </div>
                    <!-- <div class="post-category__count" style="text-align: center; margin-top: 10px;">
                        <span style="font-size: 14px; color: #666;">${cat.post_count} Posts</span>
                    </div> -->
                </a>
            </div>
        `;
    }).join('');
}

function renderEditorsChoice(posts) {
    const mainContainer = document.querySelector('.editors-choice__area .col-lg-6:first-child');
    const sideContainer = document.querySelector('.editors-choice__wrap');

    if (!mainContainer || !sideContainer) return;

    if (!posts || posts.length === 0) {
        document.querySelector('.editors-choice__area').style.display = 'none';
        return;
    }

    // First post is the large one
    const mainPost = posts[0];
    let mainImageUrl = mainPost.featured_image || '/orc/blog/assets/img/blog/blog-25.jpg';
    if (mainImageUrl && !mainImageUrl.startsWith('/') && !mainImageUrl.startsWith('http')) {
        mainImageUrl = '/' + mainImageUrl;
    }

    mainContainer.innerHTML = `
        <div class="editors-choice__item mb-24 wow fadeInUp" data-wow-duration="0.7s" data-wow-delay="0.2s">
            <div class="new-posts__thumb">
                <a href="/post?slug=${mainPost.slug}">
                    <img src="${mainImageUrl}" alt="${mainPost.title}" style="width: 100%; height: 508px;">
                </a>
                <div class="new-posts__info">
                    <div class="featured-stories__meta">
                        <span>${timeAgo(mainPost.publish_date || mainPost.created_at)}</span>
                    </div>
                    <h3 class="featured-stories__title"><a href="/post?slug=${mainPost.slug}">${truncateString(mainPost.title, 60)}</a></h3>
                    <div class="featured-stories__date">
                        <p><a href="/posts?category=${mainPost.category}">${ucfirst(mainPost.category)}</a></p>
                        <span>${mainPost.reading_time || 8} min read</span>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remaining posts are side items
    const sidePosts = posts.slice(1, 4);
    sideContainer.innerHTML = sidePosts.map(post => {
        let imageUrl = post.featured_image || '/orc/blog/assets/img/blog/blog-13.jpg';
        if (imageUrl && !imageUrl.startsWith('/') && !imageUrl.startsWith('http')) {
            imageUrl = '/' + imageUrl;
        }

        return `
            <div class="latest-updates-2__item grid-item">
                <div class="featured-stories__thumb">
                    <a href="/post?slug=${post.slug}">
                        <img src="${imageUrl}" alt="${post.title}" style="width: 204px; height: 132px;">
                    </a>
                </div>
                <div class="featured-stories__content">
                    <div class="featured-stories__meta">
                        <span>${timeAgo(post.publish_date || post.created_at)}</span>
                    </div>
                    <h3 class="featured-stories__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 45)}</a></h3>
                    <div class="featured-stories__date">
                        <p><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                        <span>${post.reading_time || 8} min read</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderPopularPosts(posts) {
    const gridContainer = document.querySelector('.popular-posts__area .new-posts__wrap .row');
    if (!gridContainer) return;

    if (!posts || posts.length === 0) {
        const section = document.querySelector('.popular-posts__area');
        if (section) section.style.display = 'none';
        return;
    }

    const largePosts = posts.slice(0, 2);
    const smallPosts = posts.slice(2, 5);
    const delays = ['0.1s', '0.2s', '0.3s', '0.4s', '0.5s'];

    const largeHtml = largePosts.map((post, i) => {
        let imageUrl = post.featured_image || '/orc/blog/assets/img/blog/blog-16.jpg';
        if (imageUrl && !imageUrl.startsWith('/') && !imageUrl.startsWith('http')) imageUrl = '/' + imageUrl;
        return `
            <div class="col-sm-6">
                <div class="popular-posts__wrap mb-24 wow fadeInUp" data-wow-duration="0.7s" data-wow-delay="${delays[i]}">
                    <div class="popular-posts__thumb">
                        <a href="/post?slug=${post.slug}">
                            <img src="${imageUrl}" alt="${post.title}">
                        </a>
                        <div class="popular-posts__info">
                            <div class="latest-updates__meta">
                                <span>${timeAgo(post.publish_date || post.updated_at || post.created_at)}</span>
                            </div>
                            <h3 class="latest-updates__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 55)}</a></h3>
                            <div class="latest-updates__date">
                                <p><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                                <span>${post.reading_time || 8} min read</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
    }).join('');

    const smallHtml = smallPosts.map((post, i) => {
        let imageUrl = post.featured_image || '/orc/blog/assets/img/blog/blog-18.jpg';
        if (imageUrl && !imageUrl.startsWith('/') && !imageUrl.startsWith('http')) imageUrl = '/' + imageUrl;
        return `
            <div class="col-lg-4 col-sm-6">
                <div class="latest-updates__wrap mb-24 wow fadeInUp" data-wow-duration="0.7s" data-wow-delay="${delays[i + 2]}">
                    <div class="latest-updates__thumb">
                        <a href="/post?slug=${post.slug}">
                            <img src="${imageUrl}" alt="${post.title}">
                        </a>
                    </div>
                    <div class="latest-updates__content">
                        <div class="latest-updates__meta">
                            <span>${timeAgo(post.publish_date || post.updated_at || post.created_at)}</span>
                        </div>
                        <h3 class="latest-updates__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 45)}</a></h3>
                        <div class="latest-updates__date">
                            <p><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                            <span>${post.reading_time || 8} min read</span>
                        </div>
                    </div>
                </div>
            </div>`;
    }).join('');

    gridContainer.innerHTML = largeHtml + smallHtml;
}

function renderLatestUpdates2(posts) {
    const listContainer = document.querySelector('.latest-updates-2__wrapper');
    const cardsContainer = document.querySelector('.latest-updates-2__area .col-lg-7');
    if (!listContainer || !cardsContainer) return;

    if (!posts || posts.length === 0) {
        const section = document.querySelector('.latest-updates-2__area');
        if (section) section.style.display = 'none';
        return;
    }

    const listPosts = posts.slice(0, 4);
    const cardPosts = posts.slice(4, 7);
    const listDelays = ['0.1s', '0.2s', '0.3s', '0.4s'];
    const cardDelays = ['0.2s', '0.3s', '0.4s'];

    listContainer.innerHTML = listPosts.map((post, i) => {
        const date = new Date(post.updated_at || post.publish_date || post.created_at);
        const day = date.getDate();
        const month = date.toLocaleString('default', {month: 'short'});
        return `
            <div class="latest-updates-2__info wow fadeInUp" data-wow-duration="0.7s" data-wow-delay="${listDelays[i]}">
                <div class="latest-updates-2__number"><span>${i + 1}</span></div>
                <div class="latest-updates-2__text">
                    <h3 class="latest-updates-2__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 50)}</a></h3>
                    <div class="latest-updates-2__date">
                        <p><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                        <span>${day} ${month}</span>
                        <span>${post.reading_time || 5} min read</span>
                    </div>
                </div>
            </div>`;
    }).join('');

    cardsContainer.innerHTML = cardPosts.map((post, i) => {
        let imageUrl = post.featured_image || '/orc/blog/assets/img/blog/blog-13.jpg';
        if (imageUrl && !imageUrl.startsWith('/') && !imageUrl.startsWith('http')) imageUrl = '/' + imageUrl;
        return `
            <div class="latest-updates-2__item wow fadeInUp" data-wow-duration="0.7s" data-wow-delay="${cardDelays[i]}">
                <div class="featured-stories__thumb">
                    <a href="/post?slug=${post.slug}"><img src="${imageUrl}" alt="${post.title}"></a>
                </div>
                <div class="featured-stories__content">
                    <div class="featured-stories__meta">
                        <span>${timeAgo(post.updated_at || post.publish_date || post.created_at)}</span>
                    </div>
                    <h3 class="featured-stories__title"><a href="/post?slug=${post.slug}">${truncateString(post.title, 45)}</a></h3>
                    <div class="featured-stories__date">
                        <p><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></p>
                        <span>${post.reading_time || 8} min read</span>
                    </div>
                </div>
            </div>`;
    }).join('');
}
