/**
 * UI logic for post.html (Details Page)
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Fetch and render categories (Sidebar & Footer)
    const categoryData = await fetchCategories();
    if (categoryData.success) {
        const categories = categoryData.data.categories;
        renderSidebarCategories(categories);
        renderFooterCategories(categories);
    }

    // 2. Fetch and render popular tags
    const tagsData = await fetchPopularTags();
    if (tagsData.success) {
        renderPopularTags(tagsData.data.tags);
        renderFooterPopularTags(tagsData.data.tags);
    }

    // 3. Fetch and render "Most Recent" in sidebar
    const recentPostsData = await fetchPosts({size: 3});
    if (recentPostsData.success) {
        renderSidebarRecentPosts(recentPostsData.data.result);
        renderFooterRecentPosts(recentPostsData.data.result.slice(0, 2));
    }

    // 4. Fetch and render the single post detail
    const urlParams = new URLSearchParams(window.location.search);
    const slug = urlParams.get('slug');

    if (!slug) {
        window.location.href = '/knowledge-base';
        return;
    }

    const postData = await fetchPost(slug);
    if (postData.success) {
        renderPostDetail(postData.data);
    } else {
        document.querySelector('.blog-details__wrapper').innerHTML = `<div class="blog-details__card"><p>Post not found.</p></div>`;
    }
});

function renderPostDetail(post) {
    // 1. Update SEO Meta Tags
    if (post.meta_title) {
        document.title = `${post.meta_title} - OASIS Research Community`;
    } else {
        document.title = `${post.title} - OASIS Research Community`;
    }

    const updateMeta = (name, content, property = false) => {
        if (!content) return;
        const selector = property ? `meta[property="${name}"]` : `meta[name="${name}"]`;
        let element = document.querySelector(selector);
        if (element) {
            element.setAttribute('content', content);
        } else {
            // Create if doesn't exist (optional, but good practice)
            element = document.createElement('meta');
            if (property) element.setAttribute('property', name);
            else element.setAttribute('name', name);
            element.setAttribute('content', content);
            document.head.appendChild(element);
        }
    };

    updateMeta('description', post.meta_description);
    updateMeta('og:title', post.og_title || post.title, true);
    updateMeta('og:description', post.og_description || post.meta_description, true);

    // OG Image hierarchy: post.og_image || post.featured_image || default logo
    const sharingImage = post.og_image || post.featured_image || '/orc/assets/media/logos/default-logo.png';
    updateMeta('og:image', sharingImage, true);

    updateMeta('og:url', window.location.href, true);

    if (post.canonical_url) {
        let canonical = document.querySelector('link[rel="canonical"]');
        if (canonical) {
            canonical.setAttribute('href', post.canonical_url);
        } else {
            canonical = document.createElement('link');
            canonical.setAttribute('rel', 'canonical');
            canonical.setAttribute('href', post.canonical_url);
            document.head.appendChild(canonical);
        }
    }

    // Update Breadcrumb & Title
    document.querySelector('.breadcrumb-section__title').textContent = post.title;

    // Update Meta (Date & Author)
    const metaContainer = document.querySelector('.blog-details__meta');
    if (metaContainer) {
        metaContainer.innerHTML = `
            <span class="date"><i class="fa-regular fa-calendar"></i> ${formatDateLong(post.publish_date || post.created_at)}</span>
            <span class="author"><i class="fa-regular fa-user"></i> by ${post.author || 'admin'}</span>
        `;
    }

    // Update Featured Image
    // const thumb = document.querySelector('.blog-details__thumb img');
    // if (thumb) {
    //     thumb.src = post.featured_image || '/orc/blog/assets/img/inner-page/inner-01.jpg';
    //     thumb.alt = post.title;
    // }

    const thumbContainer = document.querySelector('.blog-details__thumb');

    if (thumbContainer) {
        const img = document.createElement('img');
        let imageSrc = post.featured_image || '/orc/blog/assets/img/inner-page/inner-01.jpg';
        if (post.featured_image && !imageSrc.startsWith('/') && !imageSrc.startsWith('http')) {
            imageSrc = '/' + imageSrc;
        }
        img.src = imageSrc;
        img.alt = post.title;

        thumbContainer.appendChild(img);
    }


    // Update Post Title & Content
    const title = document.querySelector('.blog-details__title');
    if (title) title.textContent = post.title;

    const contentContainer = document.querySelector('.blog-details__content');
    if (contentContainer) {
        // Keep the meta and title, only replace the content paragraphs
        const paragraphs = contentContainer.querySelectorAll('.blog-details__dec');
        paragraphs.forEach(p => p.remove());

        const contentHtml = `
            <div class="blog-details__main-content">
                ${post.content}
            </div>
        `;
        contentContainer.insertAdjacentHTML('beforeend', contentHtml);
    }

    // Update Post Tags (bottom of article)
    const tagsList = document.querySelector('.blog-details__tag ul');
    if (tagsList) {
        if (post.tags && post.tags.length > 0) {
            tagsList.innerHTML = post.tags.map((tag, index) => {
                const comma = index < post.tags.length - 1 ? ',' : '';
                return `<li><a href="/posts?tag=${encodeURIComponent(tag)}">${tag}${comma}</a></li> `;
            }).join('');
        } else {
            tagsList.closest('.blog-details__tag').style.display = 'none';
        }
    }

    // Update Author Info Box
    const authorTitle = document.querySelector('.blog-details__author-title');
    if (authorTitle) authorTitle.textContent = post.author || 'OASIS Editor';

    // Update Quotes/Gallery if they exist (optional, mostly static in template but we can clear them)
    const quote = document.querySelector('.blog-details__quote');
    if (quote) quote.style.display = 'none'; // Template specific, we handle actual content in main-content

    const gallery = document.querySelector('.blog-details__gallery');
    if (gallery) gallery.style.display = 'none'; // Same as above

    // Update Social Share Links (using AddToAny library)
    const shareList = document.querySelector('.blog-details__share');
    if (shareList) {
        const url = window.location.href;
        const title = post.title;

        shareList.innerHTML = `
            <span class="blog-details__share-title">Share this post</span>
            <div class="a2a_kit a2a_kit_size_32 a2a_default_style mt-10" data-a2a-url="${url}" data-a2a-title="${title}">
                <a class="a2a_button_facebook"></a>
                <a class="a2a_button_linkedin"></a>
                <a class="a2a_button_whatsapp"></a>
                <a class="a2a_button_x"></a>
                <a class="a2a_dd" href="https://www.addtoany.com/share"></a>
            </div>
        `;

        // Re-initialize AddToAny if it's already loaded
        if (window.a2a) {
            window.a2a.init_all();
        }
    }

    // 4. Handle Comments Visibility
    const commentWrap = document.querySelector('.blog-details__comment-wrap');
    const commentForm = document.querySelector('.blog-details__comment-form');

    // allow_comments is usually 1 (true) or 0 (false) from the API
    if (parseInt(post.allow_comments) === 0) {
        if (commentWrap) commentWrap.style.display = 'none';
        if (commentForm) commentForm.style.display = 'none';
    } else {
        if (commentWrap) commentWrap.style.display = 'block';
        if (commentForm) commentForm.style.display = 'block';
    }

    // Hide previous/next for now as they are not dynamic
    const pagination = document.querySelector('.blog-details__page');
    if (pagination) pagination.style.display = 'none';

    // 5. Setup Comment Form Handler
    setupCommentForm(post.postRef);

    // 6. Fetch and render comments
    loadComments(post.postRef);
}

async function loadComments(postRef) {
    const commentWrap = document.querySelector('.blog-details__comment-wrap');
    if (!commentWrap || commentWrap.style.display === 'none') return;

    const data = await fetchComments(postRef);
    if (data.success) {
        renderComments(data.data.comments);
    }
}

function renderComments(comments) {
    const countTitle = document.getElementById('comment-count-title');
    const commentList = document.getElementById('comment-list');

    if (!countTitle || !commentList) return;

    // Update count (calculate total interactions recursively)
    let totalCount = 0;
    const countRecursive = (list) => {
        list.forEach(c => {
            totalCount++;
            if (c.replies) countRecursive(c.replies);
        });
    };
    countRecursive(comments);
    countTitle.textContent = `${totalCount} Comment${totalCount !== 1 ? 's' : ''}`;

    if (totalCount === 0) {
        commentList.innerHTML = '<li><p>No comments yet. Be the first to share your thoughts!</p></li>';
        return;
    }

    // Render the tree
    commentList.innerHTML = comments.map(comment => renderSingleComment(comment, 1)).join('');
}

function renderSingleComment(comment, level) {
    const isReply = level > 1;
    const hasReplies = comment.replies && comment.replies.length > 0;

    // Add extra indentation for levels beyond 2
    // const extraIndent = level > 2 ? (level - 2) * 55 : 0;
    const extraIndent = level > 2 ? 55 : 0;

    console.log('extraIndent', extraIndent);


    return `
        <li style="margin-left: ${extraIndent}px">
            <div class="${isReply ? 'comment_reply' : ''}">
                <div class="blog-details__comment__author">
                    <div class="author_thumb">
                        <img src="/orc/assets/media/avatars/blank.png" alt="author">
                    </div>
                    <div class="author_info_wrap">
                        <div class="author_info">
                            <div>
                                <span class="date">${timeAgo(comment.created_at)}</span>
                                <h3 class="name">${comment.author_name}</h3>
                            </div>
                            <div class="reply">
                                <a href="#reply-indicator" onclick="setReplyTo(${comment.id}, '${comment.author_name}')">Reply <i class="fa-solid fa-reply"></i></a>
                            </div>
                        </div>
                        <p class="comment">${comment.content}</p>
                    </div>
                </div>
            </div>
            ${hasReplies ? `<ul class="nested-comments">${comment.replies.map(r => renderSingleComment(r, level + 1)).join('')}</ul>` : ''}
        </li>
    `;
}

// State for handling replies
let currentReplyTo = null;

// Global helper for reply
window.setReplyTo = (commentId, authorName) => {
    currentReplyTo = commentId;
    const indicator = document.getElementById('reply-indicator');
    const nameSpan = document.getElementById('reply-to-name');
    const messageInput = document.getElementById('comment-message');
    const formTitle = document.querySelector('.blog-details__form-title');

    if (indicator && nameSpan) {
        nameSpan.textContent = authorName;
        indicator.style.display = 'block';
    }

    if (formTitle) {
        formTitle.textContent = 'Reply To Comment';
    }

    if (messageInput) {
        messageInput.focus();
        // Scroll to form if not in view
        indicator.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
};

window.cancelReply = () => {
    currentReplyTo = null;
    const indicator = document.getElementById('reply-indicator');
    const formTitle = document.querySelector('.blog-details__form-title');

    if (indicator) {
        indicator.style.display = 'none';
    }

    if (formTitle) {
        formTitle.textContent = 'Leave A Reply';
    }
};

function setupCommentForm(postRef) {
    const form = document.getElementById('comment-form');
    if (!form) return;

    // Remove any existing listeners to avoid duplicates if re-rendered
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);

    newForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = newForm.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.text-one');
        const originalText = btnText.innerHTML;

        const nameInput = document.getElementById('comment-name');
        const emailInput = document.getElementById('comment-email');
        const messageInput = document.getElementById('comment-message');

        const submissionData = {
            author_name: nameInput.value,
            author_email: emailInput.value,
            content: messageInput.value,
            parent_id: currentReplyTo
        };

        try {
            // Loading state
            submitBtn.disabled = true;
            btnText.innerHTML = 'Submitting... <i class="fa-solid fa-spinner fa-spin"></i>';

            const result = await submitComment(postRef, submissionData);

            if (result.success) {
                // Show success message
                const successMsg = currentReplyTo
                    ? 'Reply submitted successfully! It will appear after moderation.'
                    : 'Comment submitted successfully! It will appear after moderation.';

                showMessage(newForm, 'success', result.message || successMsg);
                newForm.reset();
                cancelReply(); // Reset reply state
            } else {
                // Show error message
                showMessage(newForm, 'error', result.message || 'Failed to submit. Please try again.');
            }
        } catch (error) {
            console.error('Submission error:', error);
            showMessage(newForm, 'error', 'An unexpected error occurred. Please try again later.');
        } finally {
            submitBtn.disabled = false;
            btnText.innerHTML = originalText;
        }
    });
}

function showMessage(container, type, text) {
    // Remove existing messages
    const existing = container.querySelector('.alert');
    if (existing) existing.remove();

    const alert = document.createElement('div');
    alert.className = `alert alert-${type === 'success' ? 'success' : 'danger'} mt-20`;
    alert.style.padding = '15px';
    alert.style.borderRadius = '5px';
    alert.style.marginBottom = '20px';
    alert.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
    alert.style.color = type === 'success' ? '#155724' : '#721c24';
    alert.style.border = `1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'}`;
    alert.innerHTML = text;

    // Prepend to form
    container.insertBefore(alert, container.firstChild);

    // Auto-hide after 5 seconds
    setTimeout(() => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }, 5000);
}
