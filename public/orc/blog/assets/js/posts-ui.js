/**
 * UI logic for posts.html
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Fetch and render categories (Sidebar & Footer)
    const categoryData = await fetchCategories();
    if (categoryData.success) {
        const categories = categoryData.data.categories;
        renderSidebarCategories(categories);
        renderFooterCategories(categories);
    }

    // 2. Fetch and render posts based on category, tag, or page filter
    const urlParams = new URLSearchParams(window.location.search);
    const categoryFilter = urlParams.get('category');
    const tagFilter = urlParams.get('tag');
    const searchFilter = urlParams.get('search');
    const pageFilter = urlParams.get('page') || 0;

    // Preserve search query in input field
    if (searchFilter) {
        const searchInput = document.querySelector('.search-input-field');
        if (searchInput) searchInput.value = searchFilter;
    }

    const postsParams = {
        size: 6, // Items per page
        page: pageFilter
    };
    if (categoryFilter) {
        postsParams.category = categoryFilter;
    }
    if (tagFilter) {
        postsParams.tag = tagFilter;
    }
    if (searchFilter) {
        postsParams.search = searchFilter;
    }

    const postsData = await fetchPosts(postsParams);
    if (postsData.success) {
        renderMainPosts(postsData.data);
    }

    // 3. Fetch and render "Most Recent" in sidebar
    const recentPostsData = await fetchPosts({size: 3});
    if (recentPostsData.success) {
        renderSidebarRecentPosts(recentPostsData.data.result);
        renderFooterRecentPosts(recentPostsData.data.result.slice(0, 2));
    }

    // 4. Fetch and render "Popular Tags" in sidebar
    const tagsData = await fetchPopularTags();
    if (tagsData.success) {
        renderPopularTags(tagsData.data.tags);
        renderFooterPopularTags(tagsData.data.tags);
    }
});

function renderMainPosts(data) {
    const posts = data.result;
    const container = document.querySelector('.news-page__wrapper');
    if (!container) return;

    if (!posts || posts.length === 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const searchTerm = urlParams.get('search');
        const message = searchTerm ? `No posts found for "${searchTerm}".` : "No posts found matching your criteria.";
        container.innerHTML = `<div class="news-page__item mb-24"><p>${message}</p></div>`;
        return;
    }

    // Save pagination container for re-appending
    const pagination = container.querySelector('.news-page__list');

    const postsHtml = posts.map(post => `
        <div class="news-page__item mb-24 wow fadeInUp" data-wow-duration="0.7s" data-wow-delay="0.2s">
            <div class="news-page__thumb">
                <a href="/post?slug=${post.slug}">
                    <img src="${post.featured_image || '/orc/blog/assets/img/blog/blog-183.jpg'}" alt="${post.title}">
                </a>
            </div>
            <div class="news-page__contact">
                <div class="new-post-3__info">
                    <div class="new-post-3__admin">
                        <span><i class="fa-solid fa-user"></i></span>
                        <ul>
                            <li>by ${post.author || 'Brooks'}</li>
                            <li>${timeAgo(post.publish_date || post.created_at)}</li>
                        </ul>
                    </div>
                    <h3 class="new-post-3__title"><a href="/post?slug=${post.slug}">${post.title}</a></h3>
                    <p class="new-post-3__dec">${post.excerpt || (post.content ? post.content.substring(0, 200) + '...' : '')}</p>
                    <div class="new-post-3__meta">
                        <ul>
                            <li><a href="/posts?category=${post.category}">${ucfirst(post.category)}</a></li>
                            <li>${post.reading_time || 8} min read</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = postsHtml;
    if (pagination) {
        container.appendChild(pagination);
        renderPagination(data);
    }
}

function renderPagination(data) {
    const paginationContainer = document.querySelector('.news-page__list ul');
    if (!paginationContainer) return;

    const {totalPages, currentPage} = data;
    if (totalPages <= 1) {
        paginationContainer.parentElement.style.display = 'none';
        return;
    }

    paginationContainer.parentElement.style.display = 'block';

    const urlParams = new URLSearchParams(window.location.search);

    let html = '';

    // Previous Arrow
    if (currentPage > 0) {
        urlParams.set('page', parseInt(currentPage) - 1);
        html += `<li><a href="?${urlParams.toString()}"><i class="fa-solid fa-arrow-left"></i></a></li>`;
    }

    for (let i = 0; i < totalPages; i++) {
        urlParams.set('page', i);
        const isActive = parseInt(i) === parseInt(currentPage) ? 'active' : '';
        html += `<li class="${isActive}"><a href="?${urlParams.toString()}">${i + 1}</a></li>`;
    }

    // Next Arrow
    if (currentPage < totalPages - 1) {
        urlParams.set('page', parseInt(currentPage) + 1);
        html += `<li><a href="?${urlParams.toString()}"><i class="fa-solid fa-arrow-right"></i></a></li>`;
    }

    paginationContainer.innerHTML = html;
}
