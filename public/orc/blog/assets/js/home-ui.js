/**
 * UI logic for index.html (Blog Home)
 */

document.addEventListener('DOMContentLoaded', async () => {
    // Fire all API requests in parallel so we're not waiting on each one sequentially
    const [
        categoryData,
        recentPostsData,
        tagsData,
        featuredData,
        sliderData,
        sideFeaturedData,
        latestUpdatesData,
        editorsChoiceData,
        sidebarRecentPostsData,
        popularPostsData,
        latestUpdates2Data,
    ] = await Promise.all([
        fetchCategories(),
        fetchPosts({size: 2}),
        fetchPopularTags(),
        fetchPosts({is_featured: 1, size: 6}),
        fetchPosts({size: 3}),
        fetchPosts({is_editors_choice: 1, size: 2}),
        fetchPosts({size: 6}),
        fetchPosts({is_editors_choice: 1, size: 4}),
        fetchPosts({size: 3}),
        fetchPosts({is_popular: 1, size: 5}),
        fetchPosts({size: 7, sort_by: 'updated_at'}),
    ]);

    // 1. Categories
    if (categoryData.success) {
        renderFooterCategories(categoryData.data.categories);
        renderLandingPageCategories(categoryData.data.categories);
    }

    // 2. Footer recent posts
    if (recentPostsData.success) {
        renderFooterRecentPosts(recentPostsData.data.result);
    }

    // 3. Popular Tags (Footer)
    if (tagsData.success) {
        renderFooterPopularTags(tagsData.data.tags);
    }

    // 4. Featured News slider
    if (featuredData.success) {
        renderFeaturedNews(featuredData.data.result);
    }

    // 5. Main Slider
    if (sliderData.success) {
        renderMainSlider(sliderData.data.result);
    }

    // 6. Side Featured Stories
    if (sideFeaturedData.success) {
        renderSideFeaturedStories(sideFeaturedData.data.result);
    }

    // 7. Latest Updates (sidebar)
    if (latestUpdatesData.success) {
        renderSideLatestUpdates(latestUpdatesData.data.result);
    }

    // 8. Editor's Choice
    if (editorsChoiceData.success) {
        renderEditorsChoice(editorsChoiceData.data.result);
    }

    // 9. Sidebar Recent Posts
    if (sidebarRecentPostsData.success) {
        renderSidebarRecentPosts(sidebarRecentPostsData.data.result);
    }

    // 10. Popular Posts section
    if (popularPostsData.success) {
        renderPopularPosts(popularPostsData.data.result);
    }

    // 11. Latest Updates 2 section (sorted by updated_at)
    if (latestUpdates2Data.success) {
        renderLatestUpdates2(latestUpdates2Data.data.result);
    }
});
