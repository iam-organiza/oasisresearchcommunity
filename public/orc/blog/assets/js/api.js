/**
 * Shared API fetching functions for the blog.
 */

async function fetchCategories() {
    try {
        const response = await fetch('/api/blog-categories');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching categories:', error);
        return { success: false, data: { categories: [] } };
    }
}

async function fetchPosts(params = {}) {
    try {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(`/api/blog-posts?${queryString}`);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching posts:', error);
        return { success: false, data: { result: [] } };
    }
}

async function fetchPost(slug) {
    try {
        const response = await fetch(`/api/blog-posts/${slug}`);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching post:', error);
        return { success: false, data: null };
    }
}

async function fetchPopularTags() {
    try {
        const response = await fetch('/api/blog-tags');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching tags:', error);
        return { success: false, data: { tags: [] } };
    }
}

async function submitComment(postRef, commentData) {
    try {
        const response = await fetch(`/api/blog-posts/${postRef}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(commentData)
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error submitting comment:', error);
        return { success: false, message: 'Failed to submit. Please try again later.' };
    }
}

async function fetchComments(postRef) {
    try {
        const response = await fetch(`/api/blog-posts/${postRef}/comments`);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching comments:', error);
        return { success: false, data: { comments: [], total: 0 } };
    }
}
